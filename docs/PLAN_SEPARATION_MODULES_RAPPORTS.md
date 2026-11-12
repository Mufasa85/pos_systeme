# Plan d'implémentation — Séparation des modules POS / Hôtel / Recharges + Rapports

**Date de rédaction** : 2026-11-04
**Statut** : ⏳ **EN ATTENTE DE VALIDATION — NE PAS IMPLÉMENTER AVANT ACCORD**
**Auteur** : Cline (IA), pour César
**Références** : `AGENT.md`, `docs/PLAN_HOTEL_FACTURATION.md`, `docs/PLAN_IMPLEMENTATION_RAPPORTS_FISCAUX.md`

---

## 1. Contexte & problème à résoudre

Aujourd'hui le module Hôtel **écrit dans les tables du POS** (`ventes`, `details_vente`) et
**contamine** les rapports fiscaux du POS :

| Symptôme | Cause | Fichier |
|---|---|---|
| Le rapport « Par type de facture » affiche une ligne `HB` | `invoice_type='HB'` codé en dur (type **non DGI**) | `HotelFactureController.php:922` et `:992` |
| `ventes.tva` ≈ 0 alors que le TTC inclut la TVA | Sémantique « régime B » (TVA sur prix non collectée) différente de `app.js` | `HotelFactureController.php:750-769`, `:814` |
| Les extras hôtel entrent dans les totaux du rapport POS | `invoice_type='EX'` non filtré | `Report.php:96-107` |
| Les recharges Élec/Eau sont invisibles dans l'A-rapport | `if (!$isRecharge)` → **aucune** ligne `details_vente` | `SaleController.php:224-227` |
| Les nuitées sont invisibles dans l'A-rapport | `produit_id = NULL` + `FROM produits p LEFT JOIN details_vente` | `Report.php:182-222` |
| Le type choisi dans le modal « Informations Facture » est ignoré | `buildDgiPayload(invoiceNum, 'FV')` en dur | `hotel-facturation.js:301`, appel ligne ~1590 |

**Objectif** : séparer **structurellement** l'hôtel du POS (calculs, filtres, rapports),
tout en gardant la **vision consolidée** pour la direction, et en produisant **un rapport
dédié à l'hôtel**.

---

## 2. Décisions d'architecture (verrouillées)

| # | Décision | Choix | Justification |
|---|---|---|---|
| D1 | Support de stockage | **Table partagée** `ventes` + axe `module` | `AGENT.md` interdit `hotel_ventes` séparée ; la facture hôtel a besoin du même appareil DGI (QR, compteur, ISF, synchro différée) |
| D2 | Type de facture | `invoice_type` = **type DGI réel** (`FV/EV/FT/FA/EA/ET`) | `HB` n'existe pas à la DGI → il pollue le rapport fiscal. `HB` devient un **module**, pas un type |
| D3 | Axe de séparation | **1 colonne** `ventes.module` (`VARCHAR(20)`) | Un seul discriminant, lisible, extensible sans `ALTER ENUM` (table rewrite) |
| D4 | `ventes.type_vente` (ENUM) | **Déprécié** → aligné sur `module`, plus utilisé pour la logique métier | Déjà incohérent : les recharges y écrivent `'product'` alors que ce sont des paiements de factures |
| D5 | Nature d'une ligne de détail | `details_vente.ligne_type` (`nuit`/`extra`/`recharge`/`produit`) | Indispensable pour séparer nuitées / extras / recharges **sans** jointure ni parsing de libellé |
| D6 | Libellé figé | `details_vente.libelle` | Permet d'afficher « Chambre 101 — Simple » dans les rapports **et** les tickets, sans table de correspondance |
| D7 | Service au niveau ligne | `details_vente.service` | `ventes.service` existe déjà mais n'est jamais lu ; une recharge = 1 service/vente, une facture hôtel peut avoir des natures multiples |
| D8 | Rapports | **2 sorties** : rapport consolidé (modules cochables) **+** H-rapport dédié | Répond à la demande : « considérer l'hôtel dans les calculs » **et** « un cas de rapport juste pour l'hôtel » |
| D9 | Archivage | `scripts/archive_data.php` corrigé **dans la même phase** que le schéma | Sinon les rapports d'archive perdent module/type/libellé (déjà le cas aujourd'hui) |

### Principe cardinal (le plus important du plan)

> **Une somme n'est comptée qu'une seule fois.**
> Les extras imputés à un séjour (`hotel_extra`) sont des **consommations** : ils sont
> déjà inclus dans le total de la facture hôtel. Ils ne doivent **jamais** être
> additionnés au CA, ni dans le rapport POS, ni dans le H-rapport.

---

## 3. Modèle de données cible

### 3.1 `ventes` — 1 nouvelle colonne

```sql
ALTER TABLE `ventes`
  ADD COLUMN `module` VARCHAR(20) NOT NULL DEFAULT 'pos'
    COMMENT 'Module emetteur : pos | hotel | hotel_extra | recharge';
-- + index (shop_id, module, date) pour les rapports
```

| `module` | Contenu | `invoice_type` | Facturé à la DGI ? | Dans le rapport POS ? |
|---|---|---|---|---|
| `pos` | Ventes caisse (supermarché) | `FV/FA/EV/FT/EA/ET` | ✅ | ✅ |
| `recharge` | Paiement Électricité / Eau | `FV/FA/…` | ✅ | ✅ (c'est du CA) |
| `hotel` | **Facture hôtel** (nuitées + extras consolidés) | `FV/FA/EV/FT/EA/ET` | ✅ | ❌ **isolé** (option coalescence) |
| `hotel_extra` | **Extra imputé** à un séjour (consommation interne) | — (interne) | ❌ | ❌ **jamais compté** |

> `module='hotel_extra'` matérialise le statut **interne** d'un extra : il ne doit
> jamais apparaître dans un total de CA. C'est la clé de la règle « pas de double compte ».

### 3.2 `details_vente` — 3 nouvelles colonnes + 1 correction de type

```sql
ALTER TABLE `details_vente`
  ADD COLUMN `ligne_type` VARCHAR(20) NULL
    COMMENT 'Nature : produit | nuit | extra | recharge',
  ADD COLUMN `libelle` VARCHAR(255) NULL
    COMMENT 'Libelle fige au moment de la vente (nuitee, service, etc.)',
  ADD COLUMN `service` VARCHAR(50) NULL
    COMMENT 'Service fournisseur (ELECTRICITE, EAU)';

-- ⚠️ CORRECTION DE TYPE NÉCESSAIRE (bug latent)
ALTER TABLE `details_vente`
  MODIFY COLUMN `quantite` DECIMAL(10,3) NOT NULL DEFAULT 0
    COMMENT 'Quantite vendue (3 decimales pour les produits au poids)';
```

**Pourquoi le `MODIFY quantite` est indispensable** : `details_vente.quantite` est
aujourd'hui `int` → une vente de `0.5 kg` est **arrondie à 0 ou 1**, et l'A-rapport
(`SUM(dv.quantite)`) affiche un **faux** chiffre. `hotel_extras.quantite` est déjà en
`DECIMAL(10,3)` (migration `2026_10_18`) → on aligne. Sans cela, le rapport « articles »
reste faux **indépendamment** de l'hôtel.

### 3.3 Tables d'archive — alignement obligatoire

```sql
ALTER TABLE `ventes_archive`
  ADD COLUMN `module` VARCHAR(20) NOT NULL DEFAULT 'pos',
  ADD COLUMN `sejour_id` INT NULL;

ALTER TABLE `details_vente_archive`
  ADD COLUMN `ligne_type` VARCHAR(20) NULL,
  ADD COLUMN `libelle` VARCHAR(255) NULL,
  ADD COLUMN `service` VARCHAR(50) NULL;

-- ⚠️ BUG BLOQUANT : produit_id est NOT NULL dans l'archive alors qu'il est
--    nullable dans details_vente (nuitée, recharge) → l'archivage CRASHE.
ALTER TABLE `details_vente_archive`
  MODIFY COLUMN `produit_id` INT NULL DEFAULT NULL,
  MODIFY COLUMN `quantite` DECIMAL(10,3) NOT NULL DEFAULT 0;
```

### 3.4 `produits` — inchangé

Pas de faux produit « chambre ». Une chambre n'est pas un produit stockable : elle a un
prix variable (par nuit, avec remise de séjour), une TS propre et une TVA propre. La
séparation se fait par `ligne_type='nuit'` + `libelle`, pas par un produit fictif
qui polluerait le stock, le catalogue et l'A-rapport articles.

---

## 4. Règle de non-double-compte (le cœur métier)

C'est le point qui a fait dérailler ma première proposition. Voici le flux réel :

```
/caisse : scan produit → « Imputer au séjour #42 »
   └─ POST /api/vente  →  ventes(module='hotel_extra', invoice_type='EX', sejour_id=42)
   └─ POST .../extras-from-cart → hotel_extras (ligne de consommation)

/hotel/facturation : « Émettre la facture »
   └─ agrège nuits + hotel_extras → total_final
   └─ POST /api/hotel/factures → ventes(module='hotel', invoice_type='FV', total=total_final)
   └─ INSERT details_vente (nuits + extras)
```

**Conséquence** : les extras existent **deux fois** en base (1× comme vente `EX`,
1× comme ligne de la facture hôtel). Toute somme qui additionne les deux **double-compte**.

### Règles à appliquer partout (SQL + PHP)

| Contexte | Lignes incluses |
|---|---|
| CA / TVA d'une période (tous modules) | `module IN ('pos','recharge','hotel')` — **jamais** `hotel_extra` |
| Rapport fiscal POS | `module IN ('pos','recharge')` (+ `hotel` si option « inclure l'hôtel ») |
| H-rapport — nuitées | `module='hotel'` AND `ligne_type='nuit'` |
| H-rapport — extras | `module='hotel'` AND `ligne_type='extra'` |
| H-rapport — **CA total hôtel** | `module='hotel'` (la facture consolidée) **PAS** `hotel_extra` |
| KPI « extras du jour » (dashboard hôtel) | `hotel_extras.montant` (déjà correct : `HotelDashboardController.php:116-121`) |
| Récettes hôtel du jour | `hotel_factures.total_final` (déjà correct : `HotelDashboardController.php:102-109`) |

> Le dashboard hôtel est **déjà** correct sur ce point (il lit `hotel_factures` et
> `hotel_extras`, pas `ventes`). Les rapports, eux, lisaient `ventes` sans filtre →
> d'où la pollution constatée.

---

## 5. Corrections des bugs fonctionnels

### B1 — Type de facture hôtel (au lieu de `HB`)

**`app/controllers/HotelFactureController.php`**
- `store()` : lire `$input['invoice_type']`, valider par whitelist
  `['FV','EV','FT','FA','EA','ET']` → `invoice_type = $invoiceType` dans `ventes`
  **et** `hotel_factures` (lignes 922 et 992).
- Rejeter `HB` / `EX` côté API (400 + message explicite).

**`public/assets/js/hotel-facturation.js`**
- `buildDgiPayload(invoiceNum, 'FV')` → `buildDgiPayload(invoiceNum, window.hotelInvoiceType)`.
  Le type choisi par l'utilisateur dans le modal est déjà capturé
  (`hotel-facturation.js:1352` → `window.hotelInvoiceType`) mais **n'est jamais utilisé**.
- `DGI_INVOICE_TYPE` (ligne 301) : constante de repli uniquement.

**Vues**
- `app/views/facture.php:108` et `app/views/facture-ticket.php:129` utilisent
  `$sale['type_facture']` → **colonne inexistante** (`ventes` a `invoice_type`) → fallback
  'FV' trompeur. Corriger l'alias dans `InvoiceController` et/ou utiliser `invoice_type`.
- `app/views/facture.php:142` : le fallback `produit_id IS NULL → 'Nuitée chambre'`
  devient `dv.libelle` (plus précis : « Chambre 101 — Simple »).

### B2 — TVA hôtel à 0

Le POS (`app.js:842-864`) est la **source de référence** :
```
TS_unit   = (type=='CDF') ? valeur : prix × valeur/100
HT_ligne  = prix × qté + TS_unit × qté
TVA_ligne = HT_ligne × taux/100
TTC_ligne = HT_ligne + TVA_ligne
```
Persistance attendue :
- `ventes.sous_total_ht` = Σ HT_ligne
- `ventes.tva` = Σ TVA_ligne (**≠ 0**)
- `ventes.total` = Σ TTC_ligne
- invariant vérifié : `total == sous_total_ht + tva` (± 0.01)

**`app/controllers/HotelFactureController.php::store()`** : remplacer l'override
« front = source de vérité » (lignes 800-821, qui recopie `input['total_final']` et
`input['tva_montant']`) par un **recalcul serveur** à partir des lignes (nuitées +
extras), aligné sur la formule ci-dessus. Le front reste la source de l'**affichage**,
le serveur devient la source de la **persistance** (sinon l'incohérence est reconduite).

> ⚠️ **Décision métier à confirmer** : le régime B hôtelier collecte-t-il la TVA sur le
> prix ? Le code actuel dit « non », `app.js` dit « oui ». Le plan aligne sur `app.js`
> (cohérence POS + rapport correct). Si le métier impose le « non », alors c'est
> `app.js` qu'il faut corriger, et l'ensemble du POS est impacté.

### B3 — Recharges sans lignes de détail

**`app/controllers/SaleController.php:224-227`** : supprimer le `if (!$isRecharge)`.
Toujours insérer les `details_vente` :
- `produit_id = NULL`, `quantite = 1`, `prix = montant du mois`
- `ligne_type = 'recharge'`, `service = 'ELECTRICITE' | 'EAU'`
- `ventes.module = 'recharge'`, `ventes.service = <idem>`

---

## 6. Rapports

### 6.1 Isolation — une seule porte d'entrée

`app/models/Report.php` : **toutes** les requêtes de rapport reçoivent un filtre
`module`. Pas de filtre oublié possible si on passe par un helper unique :

```php
private function moduleFilter(array $modules, string $alias = 'v'): string
{
    // → " AND v.module IN ('pos','recharge') "
}
```

Méthodes concernées : `getSalesByInvoiceType`, `getSalesByTaxGroup`, `getPaymentsParsed`,
`getTotalDiscounts`, `getCreditNotes`, `getIncompleteSalesCount`, `getArticlesReport`.

### 6.2 Rapport consolidé (Z / X) — avec option « inclure l'hôtel »

`app/Services/ReportService.php` + `app/controllers/ReportController.php` :
- Paramètre `?modules=pos,recharge` (défaut) ou `?modules=pos,recharge,hotel`.
- Le tableau « Par type de facture » ne contient alors **que** des types DGI réels.
- Nouveau bloc « Par module » : CA / TVA / nb factures par module (POS / Recharge / Hôtel).

### 6.3 H-rapport dédié (nouveau)

**Route** : `GET /api/reports/hotel-report?from=YYYY-MM-DD&to=YYYY-MM-DD`
**Contrôleur** : `ReportController::generateHotelReport()`
**Modèle** : `app/models/HotelReport.php` (nouveau, isolé du `Report` POS)

| Bloc | Source | Contenu |
|---|---|---|
| KPIs | `ventes(module='hotel')` | Nb factures, CA TTC, CA HT, TVA, taux d'occupation |
| Nuitées | `details_vente` `ligne_type='nuit'` | Nb nuitées, CA, répartition par chambre (`libelle`) |
| Extras | `details_vente` `ligne_type='extra'` | Par produit : quantité, CA HT, TVA |
| Par type de facture | `ventes.module='hotel'` GROUP BY `invoice_type` | FV / FA / … |
| Par mode de paiement | `ventes.payments` (JSON) | Espèces / Mobile Money / … |
| Par séjour | `ventes.sejour_id` → `hotel_sejours` | Chambre, dates, total |

> Le H-rapport lit **exclusivement** `module='hotel'` → il ne peut structurellement pas
> double-compter les extras.

### 6.4 UI

- `app/views/rapports.php` : nouvelle carte d'action **« H-rapport (Hôtel) »**, visible
  seulement si le module hôtel est actif (feature flag côté serveur, comme les autres
  cartes).
- `public/assets/js/rapports.js` : rendu du H-rapport (KPI + 6 tableaux).
- `app/views/rapport-ticket.php` : nouvelle branche `h.type === 'H'` pour l'impression A4.

---

## 7. Phases d'implémentation

| # | Phase | Contenu | Dépendance |
|---|---|---|---|
| **P0** | Schéma | Migration idempotente : `ventes.module`, `details_vente.{ligne_type,libelle,service}`, `quantite` → DECIMAL(10,3), colonnes jumelles sur les 2 tables `_archive`, `details_vente_archive.produit_id` → NULLABLE | — |
| **P1** | Backfill | Classement des lignes existantes (HB→hotel, EX→hotel_extra, service→recharge, lignes produits→`ligne_type='produit'`) | P0 |
| **P2** | Écritures | `Sale::create()` (+`module`), `SaleDetail::create()` (+3 col.), `HotelFactureController::store()` (type, TVA recalculée, module, `ligne_type`, libellés, extras en détail), `SaleController::create()` (recharges en détail, module) | P1 |
| **P3** | Lecture | `SaleDetail::getBySaleId()`, `Sale::getDetailsBySaleId()` : exposer `libelle`/`service`/`ligne_type` ; `InvoiceController` : alias `type_facture` + `module` | P2 |
| **P4** | Rapports | Filtre `module` dans `Report.php` ; `ReportService` : blocs « par module » + option coalescence ; nouveau `HotelReport` + route + contrôleur | P2, P3 |
| **P5** | UI | `rapports.php` (carte H), `rapports.js` (rendu H), `rapport-ticket.php` (branche H) ; `facture.php` / `facture-ticket.php` (libellés + type) | P4 |
| **P6** | Cohérence | `scripts/archive_data.php` (copier les nouvelles colonnes) ; `DgiSyncService::resolveInitialStatus()` (retirer le cas `HB`) ; `type_vente` aligné sur `module` | P0 |
| **P7** | Tests | Tests unitaires + intégration + non-régression (`composer test`, `composer static`, `composer cs:check`) | P6 |

**Ordre non négociable** : P0 → P1 → P2 → … → P7. Toute phase dépend de la précédente.

---

## 8. Tests à écrire

| Test | Type | Vérifie |
|---|---|---|
| `ReportModuleFilterTest` | Unit | Toutes les requêtes de `Report` filtrent par `module` (réflexion sur les sources SQL) |
| `HotelInvoiceTypeTest` | Integration | `ventes.invoice_type` = type du modal (`FV`), **jamais** `HB` ; idem `hotel_factures` |
| `HotelTvaTest` | Integration | `ventes.tva > 0` avec une chambre taxée ; invariant `total == ht + tva` |
| `HotelModuleIsolationTest` | Integration | Une facture hôtel est `module='hotel'` et **exclue** du rapport fiscal par défaut |
| `HotelNoDoubleCountTest` | Integration | Le CA d'une période contenant un extra + sa facture hôtel ne compte l'extra **qu'une fois** |
| `RechargeDetailsTest` | Integration | Une recharge crée N lignes `details_vente` (`ligne_type='recharge'`, `service='ELECTRICITE'`) |
| `HotelReportTest` | Integration | Le H-rapport renvoie nuitées + extras ; n'inclut pas les `hotel_extra` |
| `ArchiveConsistencyTest` | Integration | L'archivage conserve `module`, `invoice_type`, `libelle`, `ligne_type` (et ne crash pas sur `produit_id` NULL) |

---

## 9. Risques identifiés & parades

| # | Risque | Impact | Parade |
|---|---|---|---|
| R1 | **Double compte extras** | 🔴 CA faussé | `module='hotel_extra'` exclu de toutes les sommes (§4) + test dédié |
| R2 | **`details_vente_archive.produit_id NOT NULL`** | 🔴 Archivage crash | Migration P0 (`MODIFY ... NULL`) |
| R3 | **`quantite int`** | 🟠 A-rapport faux sur produits au poids | `MODIFY ... DECIMAL(10,3)` en P0 |
| R4 | **`type_vente` ENUM incohérent** | 🟠 Confusion à terme | `module` devient l'axe unique ; `type_vente` aligné/déprécié en P6 |
| R5 | **Drift `ventes` ↔ `ventes_archive`** | 🟡 Rapports d'archive faux | Colonnes jumelles en P0 + copie dans `archive_data.php` en P6 + test |
| R6 | **`ventes.sejour_id` surchargé** (extra *et* facture) | 🟡 Confusion de lecture | `module` + `invoice_type` suffisent à distinguer (`hotel` vs `hotel_extra`) ; `hotel_factures` reste la source de vérité du lien facture↔séjour |
| R7 | **Régime TVA B vs `app.js`** | 🔴 Décision métier | Point bloquant à trancher (§5 B2) — l'alignement sur `app.js` est proposé par défaut |
| R8 | **Requêtes de rapport oubliées** | 🟠 Fuite de module | Helper unique `moduleFilter()` + test par réflexion |
| R9 | **Migration non idempotente** | 🟠 Rejouer = erreur | Pattern `INFORMATION_SCHEMA` (cf. `2026_10_27`) sur **toutes** les colonnes |
| R10 | **`HB`/`EX` dans les données** | 🟠 Historique mal classé | Backfill P1 + le filtre `module` rend les anciennes lignes non comptables par défaut |

---

## 10. Points à valider avant d'implémenter

| # | Question | Recommandation |
|---|---|---|
| **Q1** | Les extras hôtel doivent-ils être **isolés** du rapport fiscal POS ? | **Oui** → `module='hotel_extra'`, jamais comptés. C'est la seule option sans double compte |
| **Q2** | Le rapport fiscal doit-il pouvoir **inclure** l'hôtel ? | **Oui**, via option (`?modules=...,hotel`) → vision consolidée pour la direction |
| **Q3** | Le régime B hôtelier collecte-t-il la TVA sur le prix ? | Aligner sur `app.js` (TVA collectée) par défaut → rapport fiscal cohérent. À confirmer |
| **Q4** | `details_vente.quantite` → `DECIMAL(10,3)` : correction hors périmètre hôtel, mais nécessaire | **Oui**, à faire (sinon l'A-rapport reste faux sur les produits au poids) |
| **Q5** | Le H-rapport remplace-t-il l'A-rapport pour l'hôtel, ou le complète ? | **Complète** : l'A-rapport garde les articles POS + services ; le H-rapport gère l'hôtel |

---

## 11. Ce que le plan ne change pas (volontairement)

- ❌ Aucune nouvelle table métier (pas `hotel_ventes`, pas `hotel_details`) → `AGENT.md`
- ❌ Aucun produit fictif « chambre » → le stock et le catalogue restent propres
- ❌ Aucun changement de la numérotation des factures (compteur annuel partagé `AAAA/xxxxxx`)
- ❌ Aucun changement du protocole DGI / OSAT / callback
- ❌ Pas de nouvelle bibliothèque, pas de framework JS

---

## 12. Checklist de livraison

- [ ] Migration P0 appliquée **et** rejouable sans erreur
- [ ] Backfill P1 exécuté (0 ligne `module` NULL)
- [ ] `composer test` vert (unit + integration + functional)
- [ ] `composer static` (PHPStan) sans nouvelle erreur
- [ ] `composer cs:check` (PHP-CS-Fixer) vert
- [ ] `npm run lint` (ESLint) vert
- [ ] Vérification manuelle : facture hôtel → rapport fiscal (isolée) + H-rapport (complet) + ticket imprimé (type + libellés corrects)
- [ ] Vérification manuelle : recharge → visible dans l'A-rapport « Services »
- [ ] Vérification manuelle : archivage d'une période contenant une facture hôtel → sans crash, données conservées
- [ ] `docs/schema_tables.md` mis à jour (nouvelles colonnes)
- [ ] `ROUTES.md` mis à jour (nouvelle route H-rapport)
- [ ] `AGENT.md` mis à jour (nouvel axe `module` + règle de non-double-compte)

---

*Fin du plan — en attente de validation avant implémentation.*
