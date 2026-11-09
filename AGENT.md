# AGENT.md — Guide pour les agents IA travaillant sur ce projet

> Document à lire en premier par toute IA (Cursor, Claude, Hermes, etc.) avant de
> proposer du code, des migrations, ou des modifications. Il est maintenu par les
> humains ET les IA qui passent sur le projet.

---

## 1. C'est quoi ce projet

Système de **caisse enregistreuse (POS)** multi-boutique en PHP MVC + MySQL, plus
depuis octobre 2026 un **module de gestion hôtelière (PMS)** greffé dessus.

Le but métier : faire tourner un hôtel de 20 chambres (réception, housekeeping,
facturation) en réutilisant la caisse existante pour la vente des extras (bar,
restaurant, room service).

Utilisateur principal : un seul hôtel réel, ~20 chambres, simples et doubles.
Le propriétaire est aussi le développeur — il lit et valide tout le code.

---

## 2. Stack et conventions

| Aspect | Choix | Notes |
|---|---|---|
| Backend | PHP 8.1+, MVC maison | Pas de Laravel. Router = `AltoRouter` (voir `app/core/`) |
| BDD | MySQL 9.x (gérée séparément, pas XAMPP) | Connexion via PDO, config dans `config/config.php` |
| Front | Pages PHP server-side + JS vanilla | Pas de framework JS. CSS dans `app/views/mobile-caisse.css` et `public/assets/css/` |
| Routing web | `routes/web.php` (pages) + `routes/api.php` (API) | `Router::get(...)`, `Router::post(...)` |
| Auth | Session PHP (`$_SESSION['user_id']`, `$_SESSION['role']`) | Vérifiée dans `PageController::render()` |
| Tests | PHPUnit + Playwright | `composer test`, `npm run test:e2e` |
| Lint PHP | PHP-CS-Fixer + PHPStan | `composer cs:check`, `composer static` |
| Lint JS | ESLint + Prettier | `npm run lint`, `npm run format` |

**Conventions SQL** :
- Migrations dans `migrations/`, nommées `AAAA_MM_JD_description.sql`
- Préfixe par module : `payroll_*` (paie), `hotel_*` (PMS), `ventes` (POS)
- Toutes les tables ont `created_at` et `updated_at` quand c'est pertinent
- Foreign keys explicites + index sur les colonnes de jointure

**Conventions PHP** :
- PSR-4 via Composer, namespace `App\` pour `app/`
- Modèles dans `app/models/`, contrôleurs dans `app/controllers/`
- Vues dans `app/views/`, layout commun dans `app/views/layout/`
- Pas de helper global magique : injection par constructeur ou `use`
- `var_dump` interdit en prod, `error_log` OK

**Conventions UI** :
- Sidebar conditionnelle par rôle dans `app/views/layout/header.php`
- SVG inline pour les icônes (pas de bibliothèque d'icônes)
- Mobile-first pour les pages gouvernante (`/hotel/etages`)

---

## 3. Rôles utilisateurs (table `utilisateurs`)

Valeurs possibles de la colonne `role` :
- `super_admin` — accès total, voit toutes les boutiques
- `admin` — manager d'une boutique (= manager d'hôtel)
- `vendeur` — caissier (POS uniquement, pas le PMS)
- `receptionniste` — accueil de l'hôtel (POS + PMS) — **ajouté en octobre 2026**
- `gouvernante` — personnel d'étage (PMS mobile uniquement) — **ajouté en octobre 2026**

Pour ajouter un nouveau rôle : modifier la colonne `role` (c'est un `VARCHAR` ou
`ENUM`, vérifier le type), ajouter une condition dans la sidebar
(`header.php`), et ajouter les vérifications de session dans les contrôleurs.

---

## 4. État du module Hôtel (PMS)

Plan de référence : `docs/PLAN_HOTEL_V1.md` (à lire avant toute modif).

### Avancement par sprint

- [x] **Sprint 1 — Fondations** (en cours) : tables, seed 20 chambres, bouton sidebar, route `/hotel`
- [ ] Sprint 2 — Le réceptionniste (grille chambres, check-in/out, extras)
- [ ] Sprint 3 — La gouvernante (vue mobile)
- [ ] Sprint 4 — Le manager (tableau de bord, CRUD chambres, saisons)
- [ ] Sprint 5 — Liaison caisse POS + polish

### Tables du module

Toutes préfixées `hotel_` :
- `hotel_chambres` — les chambres physiques
- `hotel_guests` — clients de passage
- `hotel_sejours` — séjour = guest + chambre + période
- `hotel_extras` — extras consommés (bar, etc.) liés à un séjour ET à une `vente` POS
- `hotel_paiements` — paiements partiels sur un séjour
- `hotel_housekeeping` — tâches de ménage
- `hotel_tarifs_saison` — multiplicateurs saisonniers

### Lien avec le POS existant

Le seul pont entre POS et PMS = la table `ventes`. Quand un client de chambre
consomme au bar, la vente est faite normalement dans la caisse, mais elle est
**rattachée** à un séjour via `hotel_extras.vente_id`. Au check-out, le total
des extras rattachés est ajouté à la note.

**Ne jamais** dupliquer la logique de facturation POS dans le module hôtel :
toujours réutiliser la table `ventes` et les modèles `Sale`, `SaleDetail`.

### Accès à la fonctionnalité « Extras » (bouton de la caisse)

⚠️ **Différence avec le reste du module Hôtel** : le bouton « Extras » de
`/caisse` et les routes API associées ne dépendent **PAS** du feature flag API
`hotel`, mais d'une configuration **par boutique** :

| Élément | Valeur |
|---|---|
| Colonne | `shops.acces_extras` (TINYINT, défaut `0` = refusé) |
| Migration | `migrations/2026_11_02_shop_extras_access.sql` |
| Réglage | Super-admin → `/shops` → checkbox « Accès aux Extras » |
| Garde backend | `Controller::requireShopExtrasAccess()` |
| Helper modèle | `Shop::hasExtrasAccess($shopId)` |
| Flag JS | `FEATURE_FLAGS.extras` (header.php) |

L'authentification reste obligatoire : « accessible » signifie accessible aux
utilisateurs **authentifiés** de la boutique cochée, pas une route anonyme.
Ne pas réintroduire `requireFeatureApiAccess('hotel')` sur ces routes sans
demander à l'utilisateur.

### Règle métier : un séjour déjà facturé n'est plus imputable aux extras

Une facture hôtel peut être émise **sans check-out** : le séjour reste alors
`statut='en_cours'` mais sa **note est close**. Aucun extra ne peut donc plus
lui être imputé, sinon le total de la facture déjà émise serait faux.

| Élément | Valeur |
|---|---|
| Facture active | `hotel_factures.statut != 'annulee'` |
| Helper modèle | `HotelReservation::factureActive($sejourId)` |
| Liste du picker | `HotelReservation::sejoursImputablesAuxExtras()` |
| Garde d'écriture | `HotelExtrasController::extrasFromCart()` + `SaleController::create()` |
| Code erreur | `422` / `sejour_deja_facture` |

Une facture **annulée** ne bloque pas l'imputation (elle n'est plus active).

---

## 5. Module Pressing (suivi des commandes de pressing)

Intégration de l'appli autonome `pressing/` (dossier non suivi par git, à
supprimer après validation). Référence : `docs/PLAN_PRESSING.md`.

**But métier :** un client paie une facture DGI pour des services de
pressing ; le pressing suit ensuite le traitement du linge, vêtement par
vêtement, jusqu'à la remise au client.

### Les 7 étapes

| Étape | Libellé                | État du dossier |
| ----- | ---------------------- | --------------- |
| 0     | Déposé / En attente    | `encours`       |
| 1     | Lavage commencé        | `encours`       |
| 2     | Lavage terminé         | `encours`       |
| 3     | Repassage commencé     | `encours`       |
| 4     | Repassage terminé      | `encours`       |
| 5     | Prêt pour récupération | `prete`         |
| 6     | Remis au client        | `remise`        |

`PressingCommande::etatCommande()` calcule l'état du dossier ; un article non
prêt dont la `date_dispo` est dépassée compte en retard.

### Tables (3 — pas 5)

L'appli autonome avait sa base et ses tables. La fusion dans le POS a
supprimé les redondances :

| Table POS               | Remplace l'ancienne table          |
| ----------------------- | ----------------------------------- |
| `clients`               | `clients_pressing`                  |
| `audit_logs`            | `suivi_articles_pressing`           |
| `pressing_commandes`    | `commandes_pressing`                |
| `pressing_articles`     | `commande_articles_pressing`        |
| `pressing_paiements`    | `paiements_pressing`                |

⚠️ **`pressing_paiements` n'est PAS redondant** avec `ventes.payments` :
le pressing a son propre flux d'encaissement. Le total d'un dossier = prix ×
coefficient, qui diffère du montant de la vente POS et de la facture DGI
(56 000 facturés → 70 000 à payer au pressing avec un coef de 1.25). Le
paiement de la vente POS n'est donc **pas** compté dans le solde pressing.

### Multi-boutique et ISF

L'ISF n'est **jamais** codé en dur (l'appli autonome figeait
`$local_nif = 'A1615551X'`). La résolution, par ordre :

1. `shops.isf` de la boutique de l'**utilisateur connecté**
   (`$_SESSION['shop_id']`) — source normale, y compris pour un
   `super_admin` qui a une boutique rattachée.
2. `company_info.isf` — repli, super_admin *sans* boutique.
3. `ventes.store_isf` — l'ISF figé sur la vente, quand la facture est déjà
   connue et que la boutique n'a pas d'ISF.

Le périmètre des listes passe par `pressing_commandes.shop_id`. Un dossier
est rattaché à la boutique de l'utilisateur qui le crée ; sans boutique
sélectionnée, la création est refusée (422).

### Calcul du total

`prix_unitaire` envoyé à l'API est le prix de **base**. Le contrôleur
calcule `prix × coefficient` à la création et stocke le prix **majoré**. Le
total est donc `Σ (quantité × prix_unitaire stocké)`, sans réappliquer le
coefficient. L'UI affiche le prix majoré en direct (aperçu) mais envoie le
prix de base : le total facturé ne dépend jamais du client HTTP.

### Accès à la fonctionnalité

L'accès suit **exactement le même mécanisme que `/recharges` et `/hotel`** :
un flag renvoyé par l'API OSAT, pas un réglage par boutique.

| Élément                | Valeur                                            |
| ---------------------- | ------------------------------------------------- |
| Flag API               | `pressing` (`option_client/feedback.php` → `data.pressing`) |
| Lecture                | `RechargeAccessService::canAccessPressing()`      |
| Cache                  | 5 min, `$_SESSION['feature_access_cache']`        |
| Comportement           | fail-closed : flag faux / absent / API KO → 403    |
| Migration              | `migrations/2026_11_04_pressing_module.sql`       |

Le lien « Pressing » de la sidebar est masqué quand le flag est faux, comme
celui de « Hôtel » : l'utilisateur ne tombe jamais sur une 403 en cliquant
un lien visible.

⚠️ Aucune colonne `shops.acces_pressing` : l'accès ne se règle pas dans
`/shops`, c'est l'abonnement OSAT qui le détermine.
| Garde backend          | `Controller::requireShopPressingAccess()`          |
| Helper modèle          | `Shop::hasPressingAccess($shopId)`                 |
| Badge                  | `.shop-badge-pressing` (dans `/shops`)             |
| Lien sidebar           | `app/views/layout/header.php` (conditionné)        |

Comme « Extras », l'accès est **fail-closed** : boutique inconnue, sans
identifiant, ou erreur SQL → refus.

### Règles métier à ne pas casser

- **La remise au client (étape 6) est bloquée** tant que le dossier n'est pas
  soldé. C'est le cœur du workflow : on ne rend pas du linge non payé.
- **La suppression est bloquée** tant qu'un vêtement n'est pas remis.
- L'**historique des étapes** est écrit dans `audit_logs`
  (`entity = 'pressing_article'`, `action = 'pressing_step'`), pas dans une
  table dédiée. Aucun script ne purge `audit_logs` → l'historique dure.
- `client_id` est **nullable** : un client supprimé ne doit pas faire
  disparaître le dossier.

### Le lien avec le POS

- `pressing_commandes.vente_id` → `ventes(id)` `ON DELETE SET NULL` : le
  dossier est rattaché à la vente qui porte le même numéro de facture.
- `GET /api/pressing/ventes-sans-commande` liste les ventes POS sans dossier :
  c'est la vue « assistance » qui permet de démarrer un pressing en un clic.
- Le numéro de téléphone est normalisé (`clients.numero_normalise`, colonne
  GENERATED) : le pressing stocke `+243 89 548 96 08`, le POS `0895511485`.
  Sans ça, la même personne est créée en double.

### Piège CSS

Toutes les classes de la vue et du JS sont préfixées `pr-`. L'appli autonome
utilisait `.btn`, `.card`, `.modal`, `.tablebox`… qui **entrent en collision**
avec `styles.css`, `recharges.css` et `mobile-caisse.css` : importer son CSS
tel quel déformerait toute l'interface du POS.

---

## 6. Commandes utiles

```bash
# Migrations
mysql -u root -p pos_system < migrations/2026_10_15_hotel_module_v1.sql

# Tests
composer test
composer test:unit
npm run test:e2e

# Lint
composer cs:check
composer static
npm run lint

# Démarrer en local
php -S localhost:8000 -t public/
```

---

## 7. Pièges connus (à NE PAS faire)

- ❌ Modifier le schéma de `ventes` ou `utilisateurs` sans migration dédiée
- ❌ Créer une table `hotel_ventes` séparée — utiliser `ventes` existante
- ❌ Mettre du JS jQuery / Vue / React — projet en JS vanilla uniquement
- ❌ Toucher à la sidebar pour un nouveau rôle sans vérifier `header.php` ligne ~271
- ❌ Commit / push automatique — l'utilisateur commit lui-même, en manuel
- ❌ Réinventer la facturation — toujours passer par `Sale` / `SaleController`
- ❌ Importer le CSS de l'appli `pressing/` tel quel — préfixer tout en `pr-`
- ❌ Supprimer `pressing_paiements` « parce que `ventes.payments` existe » —
  ce sont deux flux d'encaissement différents (cf. §5)

---

## 8. Workflow avec l'utilisateur

L'utilisateur (César) :
- Lit et valide **chaque** document de conception avant tout code
- Valide sprint par sprint, pas en bloc
- Commit lui-même, manuellement, après vérification
- Préfère du code minimal, lisible, commenté en français
- Répond très court (souvent un seul mot) : interpréter le contexte, pas juste le mot

Toute IA qui propose un changement structurel (nouvelle table, nouvelle route,
nouveau rôle, modification de schéma existant) doit d'abord poser la question,
même si le plan de référence semble l'autoriser.

---

## 9. Documents liés

- `docs/PLAN_HOTEL_V1.md` — plan complet du module hôtel
- `docs/PLAN_PRESSING.md` — plan d'intégration du module pressing
- `docs/STRUCTURE_SIMPLE.md` — structure du projet POS
- `docs/SPECIFICATIONS_TECHNIQUES_OFFICIELLES.md` — spec générales
- `ROUTES.md` — table des routes
- `TODO.md` — tâches en cours (POS)
- `README.md` — vue d'ensemble utilisateur
