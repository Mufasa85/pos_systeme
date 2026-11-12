# Plan d'intégration des rapports DGI/OSAT

> **Projet :** SYS-POS  
> **Module :** Rapports fiscaux  
> **Source externe :** OSAT Énergie — `GET /dgi/rapport/index.php`  
> **Statut :** Implémentation en cours — première livraison en lecture seule

## 1. Objectif

Ajouter à la page **Rapports** une consultation des agrégats fiscaux fournis par la DGI via le provider OSAT, sans remplacer immédiatement les rapports locaux Z/X/A.

La route provider utilisée est :

```text
GET https://osat-energie.com/dgi/rapport/index.php
    ?nif={ISF}
    &debut={DD/MM/YYYY HH:MM:SS}
    &fin={DD/MM/YYYY HH:MM:SS}
```

Le navigateur ne doit **jamais** appeler cette URL directement : tous les appels passent par le backend du POS.

## 2. Réponse provider observée

La réponse est un JSON sans enveloppe `success` :

```json
{
  "data_final": {
    "ha": { "haa": 0, "hab": 0 },
    "va": { "vaa": 0, "vab": 0 },
    "ts": { "tsa": 0, "tsb": 0 },
    "HT1": 0,
    "TTC": 0,
    "TVA": 0,
    "TS_TOTAL": 0,
    "nombre_factures_traitees": 0,
    "remise": 0
  },
  "data_par_type": {
    "FA": { "ha": {}, "va": {}, "ts": {}, "HT1": 0, "TTC": 0 }
  }
}
```

Les montants sont numériques, mais ils peuvent contenir des approximations IEEE-754 (`68666.61000000003`). Ils sont conservés dans le backend et arrondis uniquement au formatage visuel.

## 3. Règles métier et points de vigilance

### 3.1 Identifiant provider

Le paramètre `nif` de la route est traité comme l'**ISF** de la boutique (`shops.isf`) dans cette première version.

Le client ne peut pas fournir librement le NIF. Le backend le résout ainsi :

1. `admin` : ISF de sa boutique ;
2. `super_admin` : ISF de la boutique explicitement sélectionnée ;
3. aucune boutique pour le super-admin : requête refusée.

### 3.2 Accès

Les rapports DGI sont réservés aux rôles `admin` et `super_admin`. Les rapporteurs locaux X existants restent accessibles selon les règles actuelles.

### 3.3 HT1, TVA, TS et TTC

**Confirmé par OSAT :** `HT1` est le total **hors taxes**, et `ha` est la
décomposition de ce HT par catégorie fiscale. La terminologie interne a donc
été alignée :

| Provider | POS normalisé |
|---|---|
| `HT1` | `totals.ht` (affiché « Total HT ») |
| `ha.haX` | `tax_groups[].amount` (colonne « HT ») |

Le backend affiche les compteurs explicites du provider (`HT1`, `TVA`,
`TS_TOTAL`, `TTC`) et n'impose aucune formule de recalcul.

### 3.4 Filtre par type de facture

Le provider ne propose pas de filtre : il renvoie toujours l'ensemble des
types dans `data_par_type`. Le filtre est donc appliqué **côté POS** :

- paramètre `types=FV,FT` (liste de codes DGI) ;
- types inconnus refusés (`400`);
- les totaux affichés sont **recalculés** à partir des seuls types retenus ;
- les catégories fiscales sont également recalculées ;
- la cohérence est en revanche toujours vérifiée sur l'**ensemble** des types
  (une somme partielle ne peut pas égaler un total global).

### 3.5 X et Z

- **X DGI** (`GET`) : consultation d'une période choisie, sans effet de clôture.
- **Z DGI — consultation** (`GET`) : agrégat de la période à clôturer, de la fin
  de la dernière clôture DGI jusqu'à maintenant.
- **Z DGI — clôture** (`POST`) : enregistre la clôture en base
  (`dgi_z_reports`), incrémente le compteur et **chaîne la période suivante**
  sur la fin de la clôture.

### 3.6 Décalage horaire : la DGI indexe en UTC

**Découverte majeure (10/11/2026, boutique SuperMarché / ISF `A1720894F`).**

La DGI horodate ses factures en **UTC**, alors que le POS enregistre en
**heure locale** (Kinshasa = UTC+1). Mesure faite sur les 6 factures du jour,
par un scan minute par minute des bornes de période demandées au provider :

| Facture POS | Classification DGI | Écart |
|---|---|---:|
| 16:53:24 | 15:54 | −59 min |
| 17:09:29 | 16:10 | −59 min |
| 17:11:59 | 16:12 | −60 min |
| 17:14:06 | 16:15 | −59 min |
| 17:29:08 | 16:30 | −59 min |
| 17:30:50 | 16:31 | −60 min |

Le décalage est constant et le provider classe les factures **à la seconde
exacte** (scan au pas de 10 s : bascule à 16:29:10 pour une facture de
16:29:08).

**Conséquence sans correctif :** une période demandée en heure locale ne
contient jamais les factures attendues. Une clôture peut donc figer une
période vide, et les factures émises juste après une clôture restent
invisibles.

**Correctif :** toutes les périodes sont converties de l'heure de la boutique
vers l'UTC avant l'appel provider (`DgiReportService::toProviderUtc()`). Le
fuseau est explicite (`DGI_REPORT_TIMEZONE`, défaut `Africa/Kinshasa`) et il est
passé **au parseur** de date : `createFromFormat()` interpréterait sinon
l'heure dans le fuseau du serveur (`Europe/Berlin`) et cumulerait deux
décalages.

Le fuseau est indépendant du `date.timezone` de `php.ini` : le calcul du
décalage est reproductible quelle que soit la configuration du serveur.

### 3.7 Sémantique des montants

Relations **stables** sur toutes les périodes observées :

```text
somme(ha) = TTC
somme(ts) = TS_TOTAL
```

⚠️ La somme des **TVA par catégorie** ne correspond PAS au total TVA (écart
mesuré de 6 800 sur le payload de référence). Cette relation n'est donc pas
contrôlée : elle produirait un faux positif systématique.

⚠️ **La définition de `HT1` côté provider a changé.** Comparaison de la
même période (07/10 → 07/11) entre le payload d'origine et la réponse
actuelle du provider :

| | Payload d'origine | Provider aujourd'hui |
|---|---|---|
| `HT1` | 57 090 793,26 | 129 278 440,74 |
| `TTC − TVA` | 119 255 132,26 | **129 271 640,74** ✅ |
| `somme(ha) − catégorie A` | **57 090 793,26** ✅ | 63 203 416,24 |

Selon la période, `HT1` vaut donc `TTC − TVA` **ou** `somme(ha) − catégorie A`.
Le POS **n'invente rien** : il affiche `HT1` tel que le provider le fournit,
et n'impose aucune formule de recalcul. Seules les relations stables
(§ ci-dessus) font l'objet d'un contrôle de cohérence.

> Conséquence pour l'affichage : les indicateurs HT, TVA, TS et TTC sont
> **indépendants** et ne doivent pas être présentés comme une addition.
> L'écart apparent entre eux est normal et reflète la convention du provider.

### 3.8 Authentification provider

Aucun token n'est requis pour l'instant (`DGI_REPORT_API_TOKEN` reste vide et
inerte). La configuration est conservée pour un usage ultérieur.

## 4. Architecture

```text
Navigateur
   │ /api/reports/dgi/x ou /api/reports/dgi/z
   ▼
ReportController
   │ résolution boutique + ISF + dates
   ▼
DgiReportService
   │ construction URL + cURL + validation + normalisation
   ▼
Provider OSAT
```

Le service de rapport DGI est indépendant de `ReportService` (rapports locaux). Il peut recevoir un transport HTTP injectable afin que les tests n'utilisent jamais le réseau.

## 5. Contrat API interne

### 5.1 Rapport X DGI

```http
GET /api/reports/dgi/x?from=YYYY-MM-DD&to=YYYY-MM-DD&shop_id={id}
```

- `shop_id` n'est accepté que pour le super-admin ;
- dates par défaut : jour courant ;
- aucune écriture en base.

### 5.2 Rapport Z DGI — consultation

```http
GET /api/reports/dgi/z?shop_id={id}
```

Période = fin de la dernière clôture DGI → maintenant. Sans clôture
antérieure, le repli est « un an jusqu'à maintenant ».

### 5.3 Rapport Z DGI — clôture

```http
POST /api/reports/dgi/z?shop_id={id}
```

- appelle le provider sur la période à clôturer ;
- **refuse la clôture si la période ne contient aucune facture** (`409`
  `dgi_closure_empty_period`) : une clôture vide figerait une période sans
  aucune vente et la période resterait bloquée ;
- enregistre la clôture (`dgi_z_reports`) : compteur, bornes, totaux, snapshot ;
- archive la clôture DGI précédente ;
- journalise l'opération (`audit_logs`) ;
- la période suivante démarre à la fin de cette clôture.

> Le chaînage ignore les clôtures **vides** (`invoice_count = 0`) : une
> clôture vide ne doit pas servir de point de départ à la période suivante.
> La fin de période est arrondie à la minute pour éviter les chaînes de
> clôtures à la seconde.

### 5.4 Historique des clôtures

```http
GET /api/reports/dgi/z-history?shop_id={id}
```

### 5.3 Réponse

```json
{
  "success": true,
  "data": {
    "source": "dgi",
    "header": {
      "type": "X_DGI",
      "denomination": "Ma boutique",
      "nif": "A1720894F",
      "isf": "A1720894F",
      "period_start": "2026-10-07 00:00:00",
      "period_end": "2026-11-07 23:59:59",
      "generated_at": "2026-11-09 12:00:00",
      "official_closure": false
    },
    "totals": {
      "ht1": 57090793.26,
      "tva": 9252760.95,
      "ts_total": 5264859,
      "ttc": 128507893.21,
      "invoice_count": 163,
      "discount_total": 72810
    },
    "tax_groups": [],
    "by_invoice_type": [],
    "warnings": []
  }
}
```

### 5.4 Erreurs

```json
{
  "success": false,
  "code": "dgi_provider_unavailable",
  "message": "Le service DGI est momentanément indisponible."
}
```

| Situation | HTTP |
|---|---:|
| Non authentifié / rôle interdit | 401/403 |
| Dates invalides | 400 |
| Boutique absente | 404 |
| ISF non configuré | 422 |
| Fonctionnalité désactivée | 503 |
| Timeout provider | 504 |
| Réponse provider invalide ou 5xx | 502 |

Le détail technique du provider n'est jamais renvoyé tel quel au navigateur.

## 6. Mapping

| Provider | POS normalisé |
|---|---|
| `data_final.HT1` | `totals.ht` |
| `data_final.TVA` | `totals.tva` |
| `data_final.TS_TOTAL` | `totals.ts_total` |
| `data_final.TTC` | `totals.ttc` |
| `nombre_factures_traitees` | `totals.invoice_count` |
| `remise` | `totals.discount_total` |
| `ha.haX` | `tax_groups[].amount` (HT de la catégorie) |
| `va.vaX` | `tax_groups[].tva` |
| `ts.tsX` | `tax_groups[].ts` |
| `data_final.paiements` | `payments[]` (modes de paiement) |
| `data_par_type.paiements` | `by_invoice_type[].payments[]` |
| `data_par_type.FA` | `by_invoice_type[]` |

### 6.1 Modes de paiement

La clé `paiements` est un objet `{ MODE: montant }`, présent au niveau
global **et** par type de facture. Elle est normalisée en liste triée par
montant décroissant, avec un libellé lisible :

| Code provider | Libellé affiché |
|---|---|
| `ESPECES` | Espèces |
| `CARTEBANCAIRE` | Carte bancaire |
| `MOBILEMONEY` | Mobile Money |
| `CREDIT` | Crédit |
| `CHEQUES` | Chèques |
| `VIREMENT` | Virement |

Un code inconnu est affiché tel quel (pas de perte de donnée).

Vérifié sur le provider réel : la somme des `paiements` par type est
**identique** au total global (écart 0,00 sur les 6 modes). Un écart
déclenche un avertissement de cohérence, comme les autres compteurs.

Le champ `data_final.va.ts` est ignoré : il duplique `TS_TOTAL` et ne représente pas une catégorie.

## 7. Contrôles de cohérence

La structure est toujours validée. Les incohérences de montants sont signalées dans `warnings` sans invalider la réponse :

- somme des HT1 par type contre `data_final.HT1` ;
- somme des TVA par type contre `data_final.TVA` ;
- somme des TS par type contre `data_final.TS_TOTAL` ;
- somme des TTC par type contre `data_final.TTC` ;
- somme des factures par type contre le compteur global ;
- somme des remises par type contre la remise globale ;
- somme des catégories TS contre `TS_TOTAL`.

Les écarts sont tolérés à 0,01 près pour les montants.

## 8. Configuration

| Constante | Défaut | Rôle |
|---|---|---|
| `DGI_REPORT_ENABLED` | `true` | Active la fonctionnalité |
| `DGI_REPORT_API_URL` | URL OSAT communiquée | Endpoint provider |
| `DGI_REPORT_API_TOKEN` | vide | Token **global** de repli, envoyé dans `X-DGI-Token` |
| `DGI_REPORT_CONNECT_TIMEOUT` | `5` | Timeout de connexion, secondes |

Les valeurs sensibles peuvent être fournies par les variables d'environnement correspondantes ou par `config/config.local.php`.

### 8.0 Token d'accès de la boutique

Le token transmis au provider est celui de la **boutique interrogée**, pas un
token global. Il est résolu à partir de l'ISF (`DgiReportService::resolveToken()`),
avec la même cascade que la facturation OSAT (`DgiSyncService::resolveToken()`) :

| Ordre | Source | Condition |
|---|---|---|
| 1 | `company_info.token` | si `company_info.isf` == ISF interrogé |
| 2 | `shops.token` | boutique portant cet ISF |
| 3 | `company_info.token` | repli |

Il est transmis en paramètre d'URL (`&token=...`), comme pour la facturation
OSAT (`GET /dgi/facture/?store_isf=...&token=...`).

> ⚠️ **Le token ne quitte jamais le backend** : il n'apparaît ni dans les
> réponses JSON, ni dans l'interface. Vérifié par test automatisé.
>
> Résoudre le token à partir de l'ISF (et non de la session) est indispensable :
> un `super_admin` n'a pas de boutique et doit pouvoir interroger toutes les
> boutiques, chacune avec son propre token.

### 8.1 Pas de limite de temps sur la requête DGI

La DGI peut mettre du temps à répondre sur une période large : **aucun
timeout n'est appliqué au traitement de la réponse**.

- pas de `CURLOPT_TIMEOUT` (seul le délai de connexion est borné) ;
- `set_time_limit(0)` sur les routes DGI, sinon PHP interromprait le script à
  30 s pendant l'attente du provider.

La durée réelle de l'appel est mesurée et renvoyée dans `meta.duration_ms` :
elle est affichée dans le panneau « Rapport DGI / OSAT ».

Mesures relevées sur la boutique `SuperMarché` (ISF `A1720894F`) :

| Période | Durée provider | Factures | Total HT |
|---|---:|---:|---:|
| 07/10/2026 → 07/11/2026 | 1 481 ms | 176 | 63 203 416,24 |
| 1 an | 1 177 ms | 257 | 63 474 738,56 |
| 3 mois | 1 129 ms | 198 | 63 322 923,64 |

## 9. Interface

La page Rapport contiendra une section « Rapport DGI / OSAT » distincte :

- période pour le X DGI ;
- bouton X DGI ;
- bouton « Consulter la période Z » ;
- bouton « Clôturer la période Z » ;
- six indicateurs globaux (HT, TVA, TS, TTC, factures, remises) ;
- tableau par mode de paiement (global) ;
- tableau par type de facture (HT, TVA, TS, TTC, nb de factures, remise, paiements) ;
- détail des catégories fiscales ;
- avertissements de cohérence ;
- durée de la requête DGI ;
- impression A4.

### 9.1 Historiques

La page comporte **deux** historiques côte à côte :

| Colonne | Source | Route |
|---|---|---|
| **Historique des Z-rapports** | `z_reports` (POS, **archive**) **+ `dgi_z_reports` (DGI, **référence**)** | `GET /api/reports/history` |
| Historique des A-rapports | `a_reports` (POS local) | `GET /api/reports/a-history` |

L'historique des Z-rapports est **fusionné** : les anciennes clôtures POS et
les clôtures DGI apparaissent dans la même liste, triés par date de
clôture/émission (antéchronologique). Chaque ligne porte un badge
d'origine :

- **DGI** — clôture fiscale DGI (montants fournis par la DGI) ;
- **POS** — ancienne clôture POS **désactivée** (`legacy`), conservée pour
  l'historique uniquement.

Le badge est indispensable : les deux sources ne sont pas équivalentes et
ne doivent pas être confondues lors d'un contrôle fiscal.

> ⚠️ **Le Z local est désactivé** (décision métier) : la clôture de
> référence est désormais **uniquement** la clôture DGI. Le bouton «
> Z-rapport » local, la route `GET /api/reports/z-report` et les méthodes
> d'écriture (`Report::createZReport`, `Report::getLastZReport`,
> `ReportService::generateZReport`, `ReportController::generateZReport`)
> ont été retirés.
>
> Conserver les deux aurait permis de **clôturer deux fois la même période**
> (une fois côté POS, une fois côté DGI), donc de compter les mêmes ventes en
> double.
>
> Conséquence : le **X local a également été retiré** (bouton, barre
> « X-rapport périodique », routes `/api/reports/x-report/daily` et
> `/api/reports/x-report/periodic`, méthodes contrôleur et service). Le
> **X DGI** (`GET /api/reports/dgi/x`) fait désormais foi. La période ouverte
> de référence reste la dernière clôture **DGI**
> (`ReportService::resolveOpenPeriodStart`).

La route `GET /api/reports/dgi/z-history` reste disponible pour obtenir les
clôtures DGI seules.

L'historique est rechargé automatiquement après chaque clôture DGI.

> Le filtre par type de facture n'est **pas exposé dans l'interface** : les
> types sont toujours tous affichés. Le paramètre `types` reste disponible
> côté API (`GET /api/reports/dgi/x?types=FV,FT`) pour un usage programmatique.

## 10. Tableau de bord : agrégats DGI (jour / semaine / mois)

Les trois cartes « Ventes du jour / Cette semaine / Ce mois » du tableau de
bord n'utilisent **plus** les agrégats SQL locaux : elles affichent les
montants officiels fournis par la DGI.

| Élément | Choix |
|---|---|
| Route | `GET /api/dashboard/dgi-stats` (authentifié, pas admin) |
| Service | `App\Services\DgiDashboardService` |
| Contrôleur | `App\Controllers\DashboardController` |
| Frontend | `public/assets/js/dashboard.js` |

### 10.1 Fuseau des périodes : horloge du serveur (+1)

Les périodes du tableau de bord sont ancrées sur l'**horloge du serveur**
(`Europe/Berlin`, soit UTC+1 — le fuseau PHP par défaut), et non sur l'heure
de Kinshasa.

| Fuseau | Rôle |
|---|---|
| `DGI_REPORT_REQUEST_TIMEZONE` | bornes des périodes du tableau de bord (serveur, +1) |
| `DGI_REPORT_TIMEZONE` | heure de référence des factures POS (`Africa/Kinshasa`) |

> ⚠️ **Le provider reçoit TOUJOURS de l'UTC.** Mesuré : il indexe ses factures
> en UTC et ne comprend **pas** les offsets explicites (`+01:00` → réponse
> illisible, `TTC` absent).
>
> - `brut 16:11–16:12` (UTC) → 1 facture ✅
> - `brut 17:11–17:12` (lu comme UTC) → 0 facture ❌
> - `17:11–17:12+01:00` → réponse illisible ❌
>
> Envoyer l'heure serveur **brute** ferait donc perdre la première heure de
> chaque période. La conversion en UTC est donc obligatoire, et c'est elle
> qui est appliquée après avoir calculé les bornes sur l'horloge serveur.

### 10.2 Périmètre du super_admin

Le super_admin interroge **son ISF d'entreprise** (`company_info.isf`), comme
il le fait pour les shops — une seule req HTTP, pas d'agrégation
multi-boutiques (qui coûterait 3 × N appels provider).

| Contexte | ISF interrogé | Requêtes |
|---|---|---|
| Admin / vendeur | `shops.isf` de sa boutique | 1 |
| Super_admin | `company_info.isf` | 1 |
| Super_admin + `?shop_id=` | `shops.isf` de la boutique choisie | 1 |

> ⚠️ Les montants de vente ne sont affichés que pour la boutique connectée
> (ou l'ISF d'entreprise). Le tableau « Performance par boutique » ne montre
> plus de montants non plus : uniquement l'inventaire (nombre de produits).

> ⚠️ Le provider ne renvoie **aucun détail par jour** (mesuré : les seules
> clés sont `data_final` et `data_par_type`). Les 3 périodes (jour /
> semaine / mois) nécessitent donc 3 requêtes provider, lancées en
> parallèle depuis le backend — le navigateur n'en fait qu'une seule.

### 10.3 Performance : appels parallèles et lots

Un appel provider prend ~1,7 s. En séquentiel, 9 appels = ~15 s : inacceptable
pour un tableau de bord.

| Levier | Mise en œuvre | Effet mesuré |
|---|---|---|
| Parallélisme | cURL multi (`DgiReportService::getReportsBatch`) | 9 appels → 1,7 s |
| Lots de 4 | `DgiDashboardService::fetchInBatches` | évite la saturation du provider |
| Timeout 20 s | borné (la clôture Z reste sans timeout) | borne l'attente |
| Retry ×2 | sur erreur transitoire | fiabilise sous charge |
| Cache 60 s | session | évite de rejouer à chaque rechargement |

> ⚠️ Le provider OSAT est **mutualisé** : au-delà de quelques appels
> simultanés, il sature (mesuré : 9 appels simultanés → erreurs et timeouts).
> D'où le découpage en lots de 4, qui a ramené le cas le plus défavorable de
> **55 s (1 période en échec)** à **8,8 s (toutes périodes OK)**.

### 10.4 Robustesse

- une période en échec n'annule pas les autres (`error` par période) ;
- le frontend affiche « — Fc / Indisponible » sans casser la page ;
- le rendu HTML n'attend **jamais** le provider (chargement asynchrone).

### 10.5 Intervalle de dates affiché

Chaque grille indique la période qu'elle couvre, pour éviter toute
ambiguïté sur ce qui est affiché :

| Grille | Affichage |
|---|---|
| Ventes du jour | `11/11/2026` |
| Cette semaine | `05/11/2026 → 11/11/2026` |
| Ce mois | `01/11/2026 → 30/11/2026` |

Les bornes proviennent de `meta.periods` (déjà renvoyées par l'API). Le
formatage se fait **directement sur la chaîne** : passer par `new Date()`
interpréterait la date dans le fuseau du navigateur et pourrait décaler
l'affichage d'un jour (les bornes sont des dates SQL locales, horloge
serveur).

## 11. Tests


### Unitaires

- parsing du payload réel ;
- totaux globaux et par type ;
- valeurs négatives `FA` conservées ;
- catégories absentes valant zéro ;
- cohérence des sommes ;
- JSON invalide / HTTP erreur / timeout ;
- format de l'URL provider.

### Intégration

- accès par rôle ;
- isolation multi-boutique ;
- ISF absent ;
- dates invalides ;
- aucune exposition du corps brut du provider ;
- persistance et chaînage de la clôture DGI ;
- refus de clôture sur période vide (`409`) ;
- **absence du Z local** (route, contrôleur, service et modèle) ;
- **X local aligné sur la dernière clôture DGI** ;
- marquage `legacy` des anciennes lignes `z_reports` dans l'historique.

### Tableau de bord (unitaires)

- les 3 périodes (jour / semaine / mois) sont bien interrogées ;
- les bornes de période sont correctes (minuit → 23:59:59, 7 jours
  glissants, 1er → dernier jour du mois) ;
- les totaux (HT / TTC / nb de factures) sont exposés par période ;
- les montants de plusieurs boutiques sont **additionnés** ;
- une erreur provider n'annule pas les autres périodes ;
- un ISF vide ou une liste de boutiques vide est refusé.

### Validation

```powershell
composer test
composer static
composer cs:check
node --check public/assets/js/rapports.js
node --check public/assets/js/dashboard.js
npm run lint
npm run test:e2e
```

## 12. Roadmap

- [x] Documenter le contrat provider et les limites.
- [x] Implémenter `DgiReportService` et sa configuration.
- [x] Ajouter les routes API et le contrôle d'accès.
- [x] Ajouter l'interface et l'impression A4.
- [x] Ajouter les tests unitaires et d'intégration (44 tests DGI : 28 unitaires + 16 d'intégration).
- [x] Vérifier le service contre le provider réel (période de référence du 07/10/2026 au 07/11/2026).
- [x] Confirmer la signification de `ha`/`HT1` avec OSAT (HT et ventilation HT).
- [x] Implémenter le filtre par type de facture (côté POS).
- [x] Implémenter la clôture Z DGI persistée et chaînée (`dgi_z_reports`).
- [x] **Désactiver le Z local** : la clôture DGI est l'unique référence de
      période (bouton, route et méthodes d'écriture retirés).
- [x] **Aligner le X local sur la dernière clôture DGI** (plus de double
      comptage entre X local et X DGI).
- [x] Ancrer la première clôture DGI sur la première vente réelle de la
      boutique (au lieu d'un an en arrière).
- [x] **Alimenter le tableau de bord par l'API DGI** (jour / semaine / mois)
      au lieu des agrégats SQL locaux, avec appels parallèles, lots, retry et
      cache court.
- [ ] Vérifier en conditions réelles que la clôture DGI est bien prise en
      compte par la DGI (et non seulement enregistrée côté POS).
- [ ] Comparer le X DGI avec le X local sur plusieurs périodes.
- [ ] Activer l'authentification provider si OSAT la met en place.
