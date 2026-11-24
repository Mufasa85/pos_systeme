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

### Droits d'accès aux modules du POS — `utilisateurs.acces_*`

Colonnes **indépendantes** du rôle applicatif (`role`) et du rôle métier hôtel
(`hotel_role`) : elles portent un droit métier explicite, réglable **par
utilisateur**.

| Module       | Colonne             | Page / route                  | Migration                              |
| ------------ | ------------------- | ----------------------------- | -------------------------------------- |
| Caisse       | `acces_caisse`      | `/caisse`                     | `2026_11_14_user_caisse_access.sql`    |
| Produits     | `acces_produits`    | `/produits`                   | `2026_11_15_user_module_access.sql`    |
| Historique   | `acces_historique`  | `/historique`                 | `2026_11_15_user_module_access.sql`    |
| Pressing     | `acces_pressing`    | `/pressing`                   | `2026_11_15_user_module_access.sql`    |
| Analytics    | `acces_analytics`   | `/analytics`                  | `2026_11_15_user_module_access.sql`    |
| Paie         | `acces_paie`        | `/payroll`, `/payroll/*`      | `2026_11_15_user_module_access.sql`    |

| Élément              | Valeur                                                        |
| -------------------- | ------------------------------------------------------------- |
| Source de vérité     | `User::canAccessModule($module)` / `User::setModuleAccess()`  |
| Table des modules    | `User::MODULES_ACCES` (module => colonne SQL)                  |
| Rôles de pilotage    | `User::ROLES_PILOTAGE` = `super_admin`, `admin`                |
| Qui peut les régler  | **Administrateurs POS uniquement** (`admin` + `super_admin`)   |
| Réglage              | Manager → `/hotel/roles` (colonne « Accès modules POS »)        |
| Garde page           | `PageController::requireModulePageAccess($module)`             |
| Garde API            | `Controller::requireModuleAccess($module)`                     |
| Masquage sidebar     | `app/views/layout/header.php` (liens conditionnels)             |
| API de réglage       | `POST /api/hotel/caisse-access/update`                         |

Règles :
- `super_admin` et `admin` : accès **permanent** à tous les modules (jamais
  verrouillé) — cohérent avec `resolveHotelRole()` et `requireActiveShop()`.
- `vendeur` / `receptionniste` / `gouvernante` : la valeur de la colonne fait
  foi. C'est l'**administrateur POS** de la boutique qui la modifie.
- Default `1` (fail-open) pour ne casser aucun compte existant ; côté
  applicatif, un utilisateur inconnu, une erreur SQL ou un module inconnu ⇒
  **fail-closed**.

### ⚠️ La colonne « Accès modules POS » est réservée aux administrateurs POS

Sur `/hotel/roles`, la colonne « Accès modules POS » (et le bloc JS associé)
n'est rendue **que** pour les utilisateurs dont le rôle **applicatif** est
`admin` (auto-manager de sa boutique) ou `super_admin`.

Un **manager** (vendeur avec `hotel_role='manager'`) :
- continue de gérer les **rôles hôtel** de son équipe (c'est son métier) ;
- ne voit **PAS** la colonne « Accès modules POS » ;
- ne peut **PAS** modifier les droits d'accès aux modules — même en appelant
  directement `POST /api/hotel/caisse-access/update`, qui répond **403**.

Le contrôle est appliqué **côté serveur** (`PageController::apiHotelUpdateCaisseAccess()`),
pas seulement dans l'UI : un manager ne peut pas contourner la restriction en
appelant l'API. Un bandeau d'information le lui explique (pour qu'il ne pense
pas à un bug).

Implémentation :
- `PageController::hotelRoles()` injecte `canManageModuleAccess`
  (`$this->isAdmin() || $this->isSuperAdmin()`) ;
- `app/views/hotel-roles.php` conditionne le `<col>`, le `<th>`, les `<td>`
  (tableau + cards mobiles) et le bloc JavaScript sur cette variable ;
- `PageController::apiHotelUpdateCaisseAccess()` exige `isAdmin() ||
  isSuperAdmin()` — le rôle métier hôtel (`manager`) ne suffit **plus**.

Ces droits **s'ajoutent** aux contrôles existants, ils ne les remplacent pas :
- `/pressing` exige le flag API `pressing` **ET** `acces_pressing` ;
- `/payroll` exige le flag API `paie` **ET** `acces_paie` ;
- `/analytics` exige le rôle admin **ET** `acces_analytics`.

Pourquoi des colonnes dédiées plutôt qu'une déduction de `hotel_role` ? Parce que
la réceptionniste **garde** la caisse (cf. matrice `docs/PLAN_HOTEL_V1.md` :
« Utiliser la caisse POS : Manager ✅ / Réceptionniste ✅ / Gouvernante ❌ /
Vendeur ✅ »), et que le manager doit pouvoir retirer un poste de travail à
n'importe qui **sans changer son rôle**.

⚠️ **Piège 1** : `POST /api/vente` est **partagée** entre la caisse et les
recharges Élec/Eau. Le discriminant est `providerService` (cf.
`SaleController::create()`) : les recharges ne sont **pas** concernées par le
blocage du POS. Ne jamais mettre la garde d'accès sans ce filtre — sinon les
recharges Élec/Eau cassent.

⚠️ **Piège 2** : `GET /api/produits` est **global** (`public/assets/js/app.js`
l'appelle depuis la caisse via `loadProducts()`). Le bloquer casserait la
caisse ET le dashboard : le droit `acces_produits` porte sur la **page**
`/produits` (gestion du catalogue), pas sur l'API de lecture.

La page `/caisse` ne renvoie pas une 403 : elle **redirige** vers la page
hôtel du rôle effectif (`/hotel/etages` pour la gouvernante,
`/hotel/reception` pour la réceptionniste) — la personne atterrit sur son outil
de travail, pas sur une page d'erreur. Les autres modules redirigent vers
`/dashboard`.

### Pages bloquées par UTILISATEUR — `user_page_access`

Couche **supplémentaire** : le `super_admin` peut retirer l'accès à une page à
un utilisateur **précis**, sans toucher à son rôle ni à ses droits `acces_*`.

| Élément              | Valeur                                                        |
| -------------------- | ------------------------------------------------------------- |
| Table                | `user_page_access` (migration `2026_11_18_user_page_access.sql`) |
| Clé unique           | `(user_id, page_key)`                                         |
| Source de vérité     | `UserPageAccessService`                                      |
| Modèle               | `app/models/UserPageAccess.php`                               |
| Catalogue            | `UserPageAccessService::CATALOG` (21 pages : 12 POS + 9 Hôtel) |
| Qui peut les régler  | **`super_admin` uniquement**                                  |
| Écran                | `/pages-bloquees` (sidebar `super_admin`)                    |
| Réglage              | `/hotel/roles` → colonne « Pages bloquées »                   |
| API de réglage       | `POST /api/user/page-access/update`                           |
| Garde page           | `Router::policyAllows()` / `Controller::requirePagePolicy()`  |
| Masquage sidebar     | `app/views/layout/header.php` (liens conditionnels)            |

Règles :
- **Absence de blocage = accès conservé** : la table ne stocke que les
  blocages. Un déploiement ne modifie donc aucun accès existant, et les
  nouvelles pages restent accessibles par défaut.
- **Le `super_admin` n'est jamais bloqué** (il doit administrer tous les
  blocages, sur toutes les boutiques).
- La **boutique n'est pas stockée** : elle découle de `utilisateurs.shop_id`.
  Une restriction suit donc l'utilisateur s'il change de boutique.
- La politique est **restrictive** : elle retire un accès normalement accordé,
  elle n'en accorde jamais un (rôle, `acces_*`, feature flags OSAT).
- **Fail-closed** : page inconnue, utilisateur introuvable ou erreur SQL ⇒
  accès refusé.

⚠️ La colonne « Pages bloquées » est **réservée au `super_admin`** : un
manager (vendeur avec `hotel_role='manager'`) gère les rôles hôtel de son
équipe mais ne bloque **PAS** les pages de ses collaborateurs — l'API répond
**403** à un manager, comme pour la colonne « Accès modules POS ».

### Écran « Pages bloquées » — `/pages-bloquees`

Lien dans la **sidebar du `super_admin`** (à côté de « Boutiques »). Il
permet de bloquer n'importe quelle page pour n'importe quel utilisateur,
toutes boutiques confondues (sélecteur de boutique en haut).

| Élément          | Valeur                                                        |
| ---------------- | ------------------------------------------------------------- |
| Route            | `GET /pages-bloquees` (hors politique : c'est l'outil)         |
| Contrôleur       | `PageController::pagesBloquees()`                             |
| Vue              | `app/views/pages-bloquees.php`                                |
| Modale partagée  | `app/views/partials/pages-access-modal.php`                   |
| Boutique ciblée  | `?shop_id=` (le `super_admin` agit sur toutes les boutiques)   |
| Accès            | **`super_admin` uniquement** (403 sinon)                       |

La modale de sélection des pages est **partagée** avec `/hotel/roles` : le
partial `partials/pages-access-modal.php` porte le catalogue, le CSS et le
JS. Il ne doit donc pas être dupliqué.

**Chaque écran ne propose que SA famille de pages** :

| Écran              | Famille      | Pages proposées          |
| ------------------ | ------------ | ------------------------ |
| `/pages-bloquees`  | `general`    | 12 pages POS             |
| `/hotel/roles`     | `hotel`      | 9 pages Hôtel            |

Le catalogue est filtré côté PHP (`UserPageAccessService::getCatalogForFamily()`)
et une sécurité côté client (`PAGE_FAMILY`) filtre encore la liste affichée.
Les badges affichés sont eux aussi limités à la famille : un blocage POS
n'apparaît donc pas dans `/hotel/roles`, et inversement.

⚠️ Un blocage d'une famille **n'est pas modifiable** depuis l'écran de
l'autre famille : la modale n'en propose que sa famille.

### Floutage des pages hors périmètre (`isPageAvailableByDefault()`)

Dans la modale, les pages que l'utilisateur **n'a pas par défaut** sont
**floutées** (`opacity` + `blur` + bordure en pointillés) et portent une
pastille « hors périmètre » — mais elles restent **cochables** : le
super_admin peut vouloir retirer une page dont l'utilisateur n'a pas
l'accès de toute façon.

`UserPageAccessService::isPageAvailableByDefault($pageKey, $user)` répond à
la question « cette page fait-elle partie de son outil de travail ? ». Elle
repose sur les **mêmes règles** que la sidebar et les gardes backend :

| Situation                                    | Règle                                        |
| -------------------------------------------- | -------------------------------------------- |
| `super_admin`                                | toutes les pages                             |
| `admin`                                      | toutes les pages                             |
| `utilisateurs`, `parametres`, `categories`, `taxes` | rôle `admin` obligatoire            |
| `analytics`                                  | rôle `admin` obligatoire (redirection)        |
| `rapports`                                   | rôle `admin` obligatoire (ses APIs l'exigent) |
| `caisse`, `produits`, `historique`, `pressing`, `analytics`, `paie` | droit `acces_<module>`          |
| pages `hotel_*`                              | rôle métier hôtel (`hotel_role`)             |
| `recharges`                                  | accessible (abonnement OSAT traité ailleurs) |

⚠️ Cette méthode sert **UNIQUEMENT à l'affichage**. Elle n'accorde aucun
droit : la politique reste RESTRICTIVE et un blocage retire toujours l'accès.

Les données de l'écran sont préparées par
`PageController::buildPagesBloqueesData()` (isolée du rendu pour rester
testable : `render()` inclut le layout avec `require_once`).

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

## 5bis. Brouillons de panier (caisse)

> Référence : `docs/PLAN_BROUILLONS_CAISSE.md` — ajouté le 2026-11-17

Le caissier peut **sauvegarder son panier** en cours (bouton « Brouillon » dans
le header du panier) puis le **reprendre** plus tard, sur n'importe quel poste
de la même boutique, et **relancer la facture**.

### Règles invariantes (à ne pas casser)

- Un brouillon **ne déduit JAMAIS le stock** : la déduction se fait à la
  facturation, dans `SaleController::create`. Un panier « gelé » ne doit pas
  bloquer le stock d'un article pendant des heures.
- Un brouillon **ne consomme JAMAIS de numéro de facture** et **n'est pas une
  vente** : il vit dans `caisse_brouillons`, pas dans `ventes`.
  ❌ Ne pas ajouter de statut « brouillon » à `ventes` : `ventes` est la table
  fiscale, et tous les rapports DGI (A/X/Z), la clôture et les exports
  comptent les ventes.
- La facturation passe **exclusivement** par `SaleController::create` :
  ❌ ne pas dupliquer la logique de TVA, de DGI ou de stock dans le module
  brouillons.
- Le payload ne contient **que** des articles et des identifiants (ni NIF,
  ni adresse) : la fiche client est relue via `GET /api/client/{id}`.

### Points techniques

- Table : `caisse_brouillons` (1 seule table, snapshot en colonne `JSON`).
- `ventes.brouillon_id` = traçabilité inverse (colonne `NULL` par défaut).
- Scoping boutique **fail-closed** : un brouillon d'une autre boutique est
  *inexistant* (404), pas *interdit* (403).
- Statuts : `ouvert` (jamais facturé) / `converti` (facturé, lié à `ventes`).
- Purge (Q3) : `scripts/purge_brouillons_caisse.php` supprime les brouillons
  `ouvert` de plus de 30 jours. Un brouillon `converti` **n'est jamais purgé**.
  ⚠️ `Database::fetch()` renvoie `false` (et non `null`) quand aucune ligne
  ne correspond : le tester avec `=== null` casse le scoping.
- Front : `public/assets/js/caisse-brouillons.js` (module dédié, chargé après
  `app.js` dans `footer.php` car il utilise `posCart` et `APP_URL`).

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
- ❌ Revenir à `sanitaze(string $input)` — la signature stricte faisait
  planter toute l'API dès qu'un champ OPTIONNEL était absent
  (`sanitaze(): Argument #1 ($input) must be of type string, null given`)
- ❌ Passer un mot de passe dans `sanitaze()` — l'assainissement HTML le
  corrompt (`&` → `&amp;`, `<b` → supprimé) et le désynchronise de la
  saisie de connexion. Utiliser `sanitizePassword()`

### Assainissement des entrées (`Controller`)

| Méthode | Usage | Comportement |
| ------- | ----- | ------------ |
| `sanitaze($input)` | Texte d'affichage (noms, libellés, codes) | `strip_tags(htmlspecialchars())`. **Ne lève jamais** : `null` → `''`, tableau → `''` |
| `sanitizePassword($password)` | Mots de passe | `trim()` seul — **jamais** d'assainissement HTML |
| `requestInput()` | Source des données de requête | `$_POST` (formulaire) **ou** corps JSON |

⚠️ Règle : toute valeur d'entrée passe par `sanitaze()` **avec un défaut**
(`$_POST['x'] ?? ''`) — un champ optionnel non envoyé ne doit JAMAIS
provoquer de TypeError ni d'insert SQL invalide.

### ⚠️ Un utilisateur appartient TOUJOURS à une boutique

`utilisateurs.shop_id` est `NULL`-able en base, mais **l'application refuse
de créer un utilisateur sans boutique** :

| Contexte | Boutique retenue |
| -------- | ---------------- |
| `super_admin` | la boutique **explicitement choisie** dans le formulaire — **aucun repli automatique** |
| `admin` | toujours sa propre boutique (le `shop_id` reçu est ignoré) |

Pourquoi : une boutique porte la caisse, la paie et les ventes de ses
utilisateurs. Un compte sans boutique ne peut rien faire (ni se connecter à
la caisse, ni figurer dans un périmètre de paie) — c'est un compte mort.

Sur une **base fraîche** (dump sans données), aucune boutique n'existe :
le `super_admin` ne peut donc pas encore créer d'utilisateur. La page
`/utilisateurs` affiche un bandeau rouge qui l'invite à créer d'abord une
boutique (`/shops`), et l'API répond :

```json
{
  "success": false,
  "error": "Boutique non sélectionnée",
  "code": "shop_required",
  "message": "Sélectionnez la boutique de rattachement du compte avant de l'enregistrer. Un utilisateur appartient toujours à une boutique."
}
```

⚠️ Ne JAMAIS réintroduire un repli automatique (« 1re boutique ayant des
utilisateurs », « boutique de session »…) : cela recréerait le bug
silencieux d'un compte créé dans la mauvaise boutique.

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
