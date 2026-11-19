# Politique d'accès aux pages par boutique — Plan d'implémentation

> **Pour les agents d'implémentation :** SOUS-COMPÉTENCE REQUISE : utiliser `superpowers:subagent-driven-development` (recommandé) ou `superpowers:executing-plans` pour implémenter ce plan tâche par tâche. Les étapes utilisent la syntaxe à cases (`- [ ]`) pour le suivi.

**Objectif :** Permettre au `super_admin` de masquer une page, par boutique et par audience (`admin` / `shop_user`), avec masquage effectif de la sidebar, blocage des URL et des APIs, configuration générale depuis `/shops` et configuration Hôtel depuis `/hotel/acces-pages`.

**Spécification :** `docs/superpowers/specs/2026-11-18-shop-page-access-policy-design.md`

**Stack :** PHP 8.1+ (MVC maison), MySQL 9.x, JS vanilla, PHPUnit 11, Playwright.

**Règles du dépôt :**
- Aucun commit automatique : l'utilisateur commit manuellement (`AGENT.md` §7-§8).
- Validation **sprint par sprint** : chaque tâche ci-dessous est un point de revue.
- Migrations SQL **idempotentes** dans `migrations/`, nommées `AAAA_MM_JD_description.sql`.
- Comments et documentation en français.
- Ne pas modifier `utilisateurs.acces_*`, les rôles, les feature flags ni la logique métier existante.

---

## Conventions du plan

- Les blocs `**Interfaces :**` définissent les signatures exactes. Une tâche ultérieure
  doit les réutiliser telles quelles, sans les redéfinir.
- Les étapes sont ordonnées en **TDD** : test rouge → implémentation minimale → test vert.
- Les commandes sont données pour **Windows / PowerShell**, depuis la racine du dépôt.
- La base de test est `pos_systeme_test` (`phpunit.xml`). Aucune migration ne doit être
  appliquée sur `pos_system` pendant le développement.

### Commandes de validation globales

```powershell
# Tests ciblés d'une tâche (adapter le chemin)
vendor\bin\phpunit tests\Unit\PagePolicyServiceTest.php

# Suites complètes
composer test:unit
composer test:integration
composer test:all

# Qualité
composer static
composer cs:check
npm run lint
npx prettier --check docs\superpowers\plans\2026-11-18-shop-page-access-policy.md
```

---

## 0. Cartographie des fichiers

### Fichiers à créer

| Fichier | Responsabilité unique |
|---|---|
| `migrations/2026_11_18_shop_page_restrictions.sql` | Table `shop_page_restrictions` (restrictions uniquement) |
| `app/models/ShopPageRestriction.php` | CRUD des restrictions par boutique / audience / page |
| `app/Services/PagePolicyService.php` | Catalogue des pages, audience, visibilité, sauvegarde, audit |
| `app/controllers/PagePolicyController.php` | Endpoints de lecture et de sauvegarde des deux familles |
| `app/views/shops-pages.php` | Matrice de configuration des pages générales |
| `app/views/hotel-acces-pages.php` | Matrice de configuration des pages Hôtel |
| `public/assets/js/page-policy.js` | Sauvegarde de la matrice, états de loading/erreur/succès |
| `tests/Unit/PagePolicyServiceTest.php` | Règles de lecture, fail-closed, idempotence |
| `tests/Integration/PagePolicyPersistenceTest.php` | Persistance, contraintes SQL, audit, scoping |
| `tests/Integration/PagePolicyRoutesTest.php` | Gardes de routes, 403 page / 403 JSON, accès config |
| `e2e/page-policy.spec.js` | Scénario bout-en-bout super_admin → vendeur |

### Fichiers à modifier

| Fichier | Modification |
|---|---|
| `app/core/Router.php` | Enregistrement des routes + garde de politique centralisée |
| `app/controllers/Controller.php` | Helpers de garde de politique réutilisables |
| `app/controllers/PageController.php` | `pageVisibility` dans `render()`, pages de config, redirections Hôtel |
| `app/controllers/SaleController.php` | Contexte métier `caisse` / `recharges` pour la mutation partagée |
| `app/views/layout/header.php` | Masquage des entrées + lien `/hotel/acces-pages` |
| `app/views/shops.php` | Bouton « Gérer les pages » par boutique |
| `app/views/layout/footer.php` | Inclusion de `page-policy.js` |
| `routes/web.php` | Routes pages + APIs de configuration |
| `routes/api.php` | APIs de configuration + classification des routes métier |
| `ROUTES.md` | Documentation des routes et gardes |
| `AGENT.md` | Nouvelle couche d'accès dans §3 |

---

## Tâche 1 — Table `shop_page_restrictions`

**Livrable :** la table existe, est rejouable et respecte le schéma validé.

### Interfaces

Table SQL cible :

```sql
CREATE TABLE `shop_page_restrictions` (
  `id`          INT NOT NULL AUTO_INCREMENT,
  `shop_id`     INT NOT NULL,
  `audience`    ENUM('admin', 'shop_user') NOT NULL,
  `page_key`    VARCHAR(64) NOT NULL,
  `created_by`  INT DEFAULT NULL,
  `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_shop_page_audience` (`shop_id`, `audience`, `page_key`),
  KEY `idx_shop_page_key` (`page_key`),
  CONSTRAINT `fk_shop_page_restrictions_shop`
    FOREIGN KEY (`shop_id`) REFERENCES `shops` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_shop_page_restrictions_user`
    FOREIGN KEY (`created_by`) REFERENCES `utilisateurs` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
```

### Étapes

- [x] **1.1 — Écrire la migration**

  Créer `migrations/2026_11_18_shop_page_restrictions.sql` avec :
  - un en-tête commente en français reprenant l'objectif, la sémantique
    « absence de restriction = visible » et la valeur par défaut sans impact ;
  - `CREATE TABLE IF NOT EXISTS` avec le SQL ci-dessus ;
  - les `CREATE TABLE IF NOT EXISTS` étant idempotents, aucune procédure stockée
    n'est nécessaire ;
  - un `SET NAMES utf8mb4;` en tête.

- [x] **1.2 — Appliquer la migration sur la base de test**

  ```powershell
  $env:DB_NAME='pos_systeme_test'; php scripts\apply_migration.php migrations\2026_11_18_shop_page_restrictions.sql
  ```

  Résultat attendu : `Migration appliquée sur pos_systeme_test : N statement(s) exécuté(s).`

- [x] **1.3 — Vérifier l'idempotence**

  Rejouer exactement la commande du point 1.2.

  Résultat attendu : aucun message d'erreur MySQL.

- [x] **1.4 — Vérifier le schéma réel**

  ```powershell
  $env:DB_NAME='pos_systeme_test'; php -r "require 'config/config.php'; `$pdo=new PDO('mysql:host='.DB_HOST.';dbname='.DB_NAME,'.DB_USER.','.DB_PASS); `$row=`$pdo->query('SHOW CREATE TABLE shop_page_restrictions')->fetch(PDO::FETCH_NUM); echo `$row[1],PHP_EOL;"
  ```

  Résultat attendu : les colonnes `INT` signées, les deux FK, l'index unique
  `uq_shop_page_audience` et `idx_shop_page_key` sont présents.

- [x] **1.5 — Point de revue utilisateur**

  ⚠️ Ne pas poursuivre sans validation de l'utilisateur (convention `AGENT.md` §8).

---

## Tâche 2 — Modèle `ShopPageRestriction`

**Livrable :** le modèle lit, crée et supprime des restrictions de façon fiable.

### Interfaces

```php
namespace App\Models;

class ShopPageRestriction
{
    /** @return array<int, array<string, mixed>> */
    public function allForShop(int $shopId): array;

    /** @return array<int, array<string, mixed>> */
    public function allForShopAndPage(int $shopId, string $pageKey): array;

    public function create(int $shopId, string $audience, string $pageKey, ?int $createdBy = null): int;

    public function delete(int $shopId, string $audience, string $pageKey): bool;

    /** @param array<int, array{audience: string, page_key: string}> $restrictions */
    public function replaceForShopAndPages(
        int $shopId,
        array $restrictions,
        ?int $createdBy = null,
        array $familyPageKeys = []
    ): bool;
}
```

> **Note (implémentée en tâche 2) :** le paramètre `$familyPageKeys` a été
> ajouté par rapport au plan initial. Sans lui, la méthode ne peut pas
> distinguer « remplacer les restrictions de cette famille » de « remplacer
> toutes les restrictions de la boutique » : une restriction sur une page
> Hôtel aurait été supprimée lors d'une sauvegarde de la famille générale.
> Le modèle ne connaissant pas le catalogue des pages (il vit dans le service),
> l'appelant lui transmet les clés de la famille concernée.

`audience` est **toujours** `admin` ou `shop_user` ; `page_key` **toujours** une clé
connue du catalogue (Task 3). Le modèle ne valide pas le catalogue métier : il
stocke fidèlement ; c'est le service qui valide.

### Étapes

- [x] **2.1 — Écrire le test d'intégration rouge**

  Créer `tests/Integration/PagePolicyPersistenceTest.php` en suivant le pattern de
  `tests/Integration/ShopExtrasAccessConfigTest.php` :
  - `setUpBeforeClass()` : `Database::getInstance()->getConnection()` ;
  - `setUp()` : créer une boutique (`Shop::create()`) et deux utilisateurs
    (`admin` + `vendeur`) ;
  - `tearDown()` : supprimer utilisateurs puis boutique (la FK `ON DELETE CASCADE`
    supprime les restrictions de la boutique).

  Tests à écrire :

  | Nom de test | Assertion |
  |---|---|
  | `testUneBoutiqueSansRestrictionExposeToutesLesPages` | `allForShop()` renvoie `[]` |
  | `testUneRestrictionEstPersistee` | `create()` puis `allForShop()` contient la ligne |
  | `testLaContrainteUniqueEmpecheLesDoublons` | 2e `create()` lève une exception PDO |
  | `testDeleteRetireLaRestriction` | après `delete()`, `allForShop()` est vide |
  | `testUneContrainteEstSupprimeeAvecLaBoutique` | supprimer la boutique supprime les lignes |
  | `testUneContrainteSurvitALaSuppressionDuCreateur` | `created_by` devient `NULL`, la ligne reste |


- [x] **2.2 — Lancer le test et confirmer l'échec**

  ```powershell
  vendor\bin\phpunit tests\Integration\PagePolicyPersistenceTest.php
  ```

  Résultat attendu : erreur « Class not found » ou « table not found ».

- [x] **2.3 — Implémenter le modèle**

  Créer `app/models/ShopPageRestriction.php` en suivant le style de
  `app/models/Shop.php` (PDO via `Database::getInstance()`, `fetchAll()`,
  `fetch()`, `execute()`).

  - `allForShop()` : `SELECT * FROM shop_page_restrictions WHERE shop_id = ? ORDER BY audience, page_key`.
  - `allForShopAndPage()` : même requête filtrée sur `page_key`.
  - `create()` : `INSERT INTO shop_page_restrictions (shop_id, audience, page_key, created_by) VALUES (?, ?, ?, ?)`.
  - `delete()` : `DELETE FROM shop_page_restrictions WHERE shop_id = ? AND audience = ? AND page_key = ?`.
  - `replaceForShopAndPages()` : supprimer les restrictions existantes pour la
    boutique **dans la famille de pages concernée**, puis insérer les nouvelles,
    le tout dans une transaction `beginTransaction()` / `commit()` / `rollBack()`
    (méthodes déjà disponibles sur `App\Core\Database`).

- [x] **2.4 — Lancer le test et confirmer le succès**

  ```powershell
  vendor\bin\phpunit tests\Integration\PagePolicyPersistenceTest.php
  ```

  Résultat attendu : tous les tests verts.

- [x] **2.5 — Vérifier la qualité**

  ```powershell
  composer static
  composer cs:check
  ```

  Résultat attendu : aucune nouvelle erreur.

- [x] **2.6 — Point de revue utilisateur**

---

## Tâche 3 — Service `PagePolicyService` (catalogue + lecture)

**Livrable :** une source unique connaît les pages, l'audience d'un utilisateur et
la visibilité d'une page, avec un comportement fail-closed.

### Interfaces

```php
namespace App\Services;

final class PagePolicyService
{
    public const AUDIENCE_ADMIN     = 'admin';
    public const AUDIENCE_SHOP_USER = 'shop_user';

    public const FAMILY_GENERAL = 'general';
    public const FAMILY_HOTEL   = 'hotel';

    /** @return array<string, array{label: string, family: string}> */
    public static function getCatalog(): array;

    /** @return array<int, string> */
    public static function getPageKeys(string $family): array;

    public static function isKnownPage(string $pageKey): bool;

    public static function isKnownFamily(string $family): bool;

    /** @return array<int, string> */
    public static function getAudiences(): array;

    /** null = super_admin (jamais bloqué) ; false = fail-closed. */
    public static function resolveAudience(int $userId): string|false|null;

    /** @return array{role: string, shop_id: int|null}|false */
    public static function resolveUserContext(int $userId): array|false;

    /** @return array<string, bool> pageKey => visible */
    public function getVisibilityMap(int $shopId, string $audience): array;

    public function isPageAllowed(string $pageKey, int $userId, int $shopId): bool;

    /** Résout le contexte utilisateur EN BASE (boutique + rôle) puis vérifie. */
    public function isPageAllowedForUser(string $pageKey, int $userId): bool;
}
```

**Catalogue des pages** (source de vérité, clés stables) :

| Famille | Clés |
|---|---|
| `general` | `caisse`, `recharges`, `produits`, `historique`, `pressing`, `analytics`, `paie`, `rapports`, `utilisateurs`, `categories`, `taxes`, `parametres` |
| `hotel` | `hotel_tableau_bord`, `hotel_calendrier`, `hotel_reception`, `hotel_etages`, `hotel_chambres`, `hotel_reservations`, `hotel_guests`, `hotel_facturation`, `hotel_roles` |

### Étapes

- [x] **3.1 — Écrire le test unitaire rouge**

  Créer `tests/Unit/PagePolicyServiceTest.php`. Le test doit créer ses données
  réelles en base (même approach que `tests/Unit/CaisseAccessTest.php`, qui
  utilise `Database::getInstance()`), car le service lit la table créée en Task 2.

  Tests à écrire :

  | Nom de test | Assertion |
  |---|---|
  | `testLeCatalogueContientLesDeuxFamilles` | `getCatalog()` contient une clé de chaque famille |
  | `testUnePageInconnueEstRefusee` | `isKnownPage('inexistant')` est `false` |
  | `testUneFamilleInconnueEstRefusee` | `isKnownFamily('inconnue')` est `false` |
  | `testUneBoutiqueSansRestrictionExposeToutesLesPages` | la carte de visibilité contient toutes les clés à `true` |
  | `testUneRestrictionAdminBloqueLEAdmin` | `isPageAllowed()` est `false` pour l'admin bloqué |
  | `testUneRestrictionAdminNeBloquePasLeVendeur` | `isPageAllowed()` est `true` pour le vendeur |
  | `testUneRestrictionShopUserBloqueLeVendeur` | `isPageAllowed()` est `false` pour le vendeur bloqué |
  | `testUneRestrictionShopUserNeBloquePasLEAdmin` | `isPageAllowed()` est `true` pour l'admin |
  | `testLeSuperAdminNEstJamaisBloque` | `isPageAllowed()` est `true` même avec une restriction |
  | `testUnUtilisateurInexistantEstRefuse` | `resolveAudience()` renvoie `false` (fail-closed) |
  | `testUnUtilisateurSansBoutiqueEstRefuse` | `resolveAudience()` renvoie `false` |
  | `testUnePageInconnueEstRefuseeALaLecture` | `isPageAllowed('inconnu', ...)` est `false` |
  | `testLesRestrictionsDUneAutreBoutiqueSontIgnorees` | restriction sur boutique B n'affecte pas A |


- [x] **3.2 — Lancer le test et confirmer l'échec**

  ```powershell
  vendor\bin\phpunit tests\Unit\PagePolicyServiceTest.php
  ```

  Résultat attendu : erreur « Class not found ».

- [x] **3.3 — Implémenter le service**

  Créer `app/Services/PagePolicyService.php`.

  - `getCatalog()` renvoie un tableau `pageKey => ['label' => ..., 'family' => ...]`
    contenant **exactement** les 21 clés listées ci-dessus.
  - `resolveUserContext()` : `SELECT role, shop_id FROM utilisateurs WHERE id = ?`.
    Renvoie `['role' => ..., 'shop_id' => ...]` ou `false` (introuvable / erreur SQL).
  - `resolveAudience()` s'appuie sur `resolveUserContext()` :
    - `super_admin` → `null` ;
    - `admin` → `AUDIENCE_ADMIN` ;
    - autre rôle **et** `shop_id` non nul → `AUDIENCE_SHOP_USER` ;
    - utilisateur introuvable, sans boutique ou exception SQL → `false`.
  - `getVisibilityMap($shopId, $audience)` : lit les restrictions de la boutique
    via `ShopPageRestriction::allForShop()`, puis renvoie pour **chaque** clé du
    catalogue `false` si une restriction existe pour `(audience, pageKey)` et
    `true` sinon. Une exception SQL → toutes les clés à `false` (fail-closed).
  - `isPageAllowed($pageKey, $userId, $shopId)` :
    1. page inconnue → `false` ;
    2. `resolveAudience()` → `null` → `true` (super_admin) ;
    3. `false` → `false` (fail-closed) ;
    4. audience obtenue → `getVisibilityMap()`[$pageKey].
  - `isPageAllowedForUser($pageKey, $userId)` : variante qui utilise
    `resolveUserContext()` pour prendre le `shop_id` **en base** (et non le
    `shop_id` de session), puis délègue à `isPageAllowed()`. C'est cette méthode
    que la garde du routeur utilise.

- [x] **3.4 — Lancer le test et confirmer le succès**

  ```powershell
  vendor\bin\phpunit tests\Unit\PagePolicyServiceTest.php
  ```

  Résultat attendu : tous les tests verts.

- [x] **3.5 — Vérifier la qualité**

  ```powershell
  composer static
  composer cs:check
  ```

- [x] **3.6 — Point de revue utilisateur**

---

## Tâche 4 — Écriture de la politique + audit

**Livrable :** le `super_admin` peut enregistrer une matrice de restrictions, de
façon atomique et auditee.

### Interfaces

Ajout au service de la Task 3 :

```php
/** @param array<string, array<string, bool>> $changes audience => pageKey => allowed */
public function updatePolicy(int $shopId, string $family, array $changes, int $createdBy): bool;

/** @return array{success: bool, message: string, errors?: array<int, string>} */
public function validateChanges(string $family, array $changes): array;

/** @return array<string, array<string, bool>> audience => pageKey => visible */
public function getPolicyMap(int $shopId, string $family): array;
```

**Contrat de validation** (`validateChanges`) :
- audience inconnue → erreur ;
- page inconnue → erreur ;
- page d'une autre famille → erreur ;
- valeur non booléenne → erreur ;
- `shop_id` ≤ 0 → erreur.

**Contrat d'audit** : un appel à `AuditLog::log()` avec
`action = 'page_policy_update'`, `entity = 'shop_page_policy'`,
`entity_id = $shopId` (la **boutique ciblée**, pas le `shop_id` de session),
`details = ['audience' => ..., 'page_key' => ..., 'allowed' => bool,
'previous_allowed' => bool]`.

### Étapes

- [x] **4.1 — Écrire les tests rouges**

  Ajouter à `tests/Unit/PagePolicyServiceTest.php` (service) et
  `tests/Integration/PagePolicyPersistenceTest.php` (persistance + audit) :

  | Nom de test | Assertion |
  |---|---|
  | `testLaValidationRejetteUneAudienceInconnue` | `validateChanges()` renvoie `success = false` |
  | `testLaValidationRejetteUnePageInconnue` | idem |
  | `testLaValidationRejetteUnePageDUneAutreFamille` | clé `hotel_reception` refusée pour `general` |
  | `testLaValidationRejetteUneValeurNonBooleenne` | `'oui'` refusée |
  | `testUneSauvegardeCreeLaRestriction` | ligne créée en base |
  | `testUneSauvegardeSupprimeLaRestrictionLevee` | ligne supprimée quand `allowed = true` |
  | `testUneSauvegardeEstIdempotente` | 2e appel identique ne crée pas de doublon |
  | `testLaPolitiqueEstIsoleeParBoutique` | boutique B inchangée |
  | `testLeChangementEstAudite` | une ligne `audit_logs` avec `entity = 'shop_page_policy'` |
  | `testLAuditEstRattacheALaBoutiqueCiblee` | `audit_logs.shop_id` = boutique ciblée |


- [x] **4.2 — Lancer les tests et confirmer l'échec**

  ```powershell
  vendor\bin\phpunit tests\Unit\PagePolicyServiceTest.php tests\Integration\PagePolicyPersistenceTest.php
  ```

  Résultat attendu : erreurs « undefined method ».

- [x] **4.3 — Implémenter la validation**

  Implémenter `validateChanges(string $family, array $changes)` dans
  `PagePolicyService` :

  - itérer sur `$changes` ; chaque clé d'audience doit être dans
    `getAudiences()` ;
  - chaque clé de page doit être connue **et** appartenir à `$family` ;
  - chaque valeur doit être un booléen strict (`is_bool()`) ;
  - retourner `['success' => false, 'message' => ..., 'errors' => [...]]` dès
    qu'une erreur est trouvée, sinon `['success' => true, 'message' => 'OK']`.

- [x] **4.4 — Implémenter `getPolicyMap()`**

  Pour chaque audience et chaque page de la famille, renvoyer `true` si aucune
  restriction n'existe, `false` sinon. Une exception SQL → toutes les valeurs
  à `false` (fail-closed).

- [x] **4.5 — Implémenter `updatePolicy()`**

  1. `validateChanges()` ; si échec → `false` **sans aucune écriture** ;
  2. lire l'état courant via `getPolicyMap()` pour calculer les différences ;
  3. appeler `ShopPageRestriction::replaceForShopAndPages()` avec uniquement
     les restrictions **à garder** (c.-à-d. les pages dont `allowed = false`) ;
  4. pour chaque différence, appeler `AuditLog::log()` avec la boutique ciblée ;
  5. retourner `true`.

  ⚠️ Ne journaliser que les **différences** (une case déjà décochée n'est pas
  ré-auditée à chaque sauvegarde).

- [x] **4.6 — Lancer les tests et confirmer le succès**

  ```powershell
  vendor\bin\phpunit tests\Unit\PagePolicyServiceTest.php tests\Integration\PagePolicyPersistenceTest.php
  ```

  Résultat attendu : tous les tests verts.

- [x] **4.7 — Vérifier la qualité**

  ```powershell
  composer static
  composer cs:check
  ```

- [x] **4.8 — Point de revue utilisateur**

---

## Tâche 5 — Garde centralisée dans le routeur

**Livrable :** une page/API protégée est bloquée **avant** l'exécution du
contrôleur, avec une réponse 403 uniforme.

### Interfaces

`app/core/Router.php` — nouvelle API :

```php
public static function get(string $route, $target, string $policyKey = '', string $policyMode = 'exclusive');
public static function post(string $route, $target, string $policyKey = '', string $policyMode = 'exclusive');
public static function put(string $route, $target, string $policyKey = '', string $policyMode = 'exclusive');
public static function delete(string $route, $target, string $name = '', string $policyKey = '', string $policyMode = 'exclusive');
```

- `policyKey` vide → route **hors politique** (comportement actuel inchangé).
- `policyMode` ∈ `exclusive` | `shared_read` | `shared_write`.
- `shared_read` : `policyKey` contient une liste de pages séparées par `|`.
- `shared_write` : le contexte est résolu à l'exécution (Task 9).

`app/controllers/Controller.php` — nouveaux helpers :

```php
protected function requirePagePolicy(string $pageKey, bool $json = true): bool;
protected function requireSharedReadPolicy(string $pageKeysCsv, bool $json = true): bool;
protected function resolveSharedWritePageKey(string $pageKey, array $context): ?string;
```

**Réponse 403 page** : code `403` + `app/views/403.php`.
**Réponse 403 API** : code `403` + JSON
`['success' => false, 'error' => 'page_not_allowed', 'message' => "Cette page n'est pas accessible pour votre rôle."]`.

### Étapes

- [x] **5.1 — Écrire le test d'intégration rouge**

  Créer `tests/Integration/PagePolicyRoutesTest.php` en suivant le pattern de
  `tests/Integration/HotelRolesScopeTest.php` (sauvegarde de `$_SESSION`,
  `$_POST`, `$_GET`, `$_SERVER` ; restauration en `tearDown()`).

  Tests à écrire (via le routeur réel, pas en appelant le contrôleur directement) :

  | Nom de test | Assertion |
  |---|---|
  | `testUneRouteHorsPolitiqueFonctionneNormalement` | pas de 403 |
  | `testUnePageBloqueeRenvoie403` | code HTTP 403 |
  | `testUneApiBloqueeRenvoie403Json` | 403 + `error = page_not_allowed` |
  | `testLeSuperAdminJamaisBloque` | 200 pour le super_admin |
  | `testUnUtilisateurInconnuEstRefuse` | fail-closed |
  | `testUneErreurSqlEstFailClosed` | table absente → 403 |


- [x] **5.2 — Lancer le test et confirmer l'échec**

  ```powershell
  vendor\bin\phpunit tests\Integration\PagePolicyRoutesTest.php
  ```

  Résultat attendu : échec car aucune route protégée n'existe encore.

- [x] **5.3 — Étendre `app/core/Router.php`**

  Ajouter une propriété statique :

  ```php
  private static array $routePolicies = [];
  private static int $routeCounter = 0;
  ```

  Dans `get()` / `post()` / `put()` / `delete()` :

  1. déterminer le **nom AltoRouter** de la route :
     - si `self::$route_name !== ''` (nom public posé par `Router::name()`),
       l'utiliser ;
     - sinon générer un identifiant technique unique
       `'__route_' . (++self::$routeCounter)` ;
  2. si `$policyKey !== ''`, enregistrer la politique **sous ce même nom** :
     `self::$routePolicies[$routeName] = ['key' => $policyKey, 'mode' => $policyMode]` ;
  3. transmettre `$routeName` à `AltoRouter::map()`.

  ⚠️ La clé du registre de politique doit être **exactement** la valeur
  retournée par `AltoRouter::match()` dans `match['name']`, sinon la garde ne
  s'exécute jamais. Utiliser le nom public lorsqu'il existe, sinon
  l'identifiant technique.

  ⚠️ Le nom technique est **interne** : il ne doit jamais apparaître dans une URL
  ni dans un lien utilisateur.

  ⚠️ `Router::delete()` possède déjà un paramètre `$name` qui n'est pas utilisé
  par le code actuel (le nom provient de `self::$route_name`). Ne pas corriger
  ce comportement dans cette feature : conserver la signature existante et
  ajouter les nouveaux paramètres **après** `$name`, afin de ne pas casser les
  appels existants.

- [x] **5.4 — Ajouter la garde dans `Router::matcher()`**

  Après `$match = App::getInstanceRouter()->match();` et **avant** toute
  instanciation de contrôleur :

  ```php
  if (is_array($match) && isset($match['name'])) {
      $policy = self::$routePolicies[$match['name']] ?? null;
      if ($policy !== null && !self::policyAllows($policy)) {
          self::respondPolicyDenied($policy);
          return;
      }
  }
  ```

  - `policyAllows()` : construit un `PagePolicyService`, lit
    `$_SESSION['user_id']`, puis :
    - `exclusive` → `isPageAllowedForUser($key, $userId)` ;
    - `shared_read` → au moins une clé de la liste est autorisée ;
    - `shared_write` → le contexte est résolu par le contrôleur (Task 9) : dans
      ce cas la garde routeur **ne bloque pas** et le contrôleur applique
      `requirePagePolicy()` avec la clé résolue.
  - `respondPolicyDenied()` : `403` + `app/views/403.php` si l'URL ne commence
    pas par `/api/`, sinon `403` + JSON `page_not_allowed`.
  - utilisateur non authentifié → **ne pas** appliquer la politique : le
    comportement d'authentification existant du contrôleur reste prioritaire.
  - `super_admin` → jamais bloqué.

- [x] **5.5 — Ajouter les helpers dans `app/controllers/Controller.php`**

  - `requirePagePolicy(string $pageKey, bool $json = true)` : appelle
    `isPageAllowedForUser()` ; si refusé, émet 403 (JSON ou vue) et renvoie
    `false`.
  - `requireSharedReadPolicy(string $pageKeysCsv, bool $json = true)` :
   _split_ sur `|`, authorize si **au moins une** page est autorisée.
  - `resolveSharedWritePageKey(string $pageKey, array $context)` : retourne la
    clé de page correspondant au contexte métier, ou `null` si le contexte est
    absent/ambigu (fail-closed).

- [x] **5.6 — Lancer le test et confirmer le succès**

  ```powershell
  vendor\bin\phpunit tests\Integration\PagePolicyRoutesTest.php
  ```

  Résultat attendu : tous les tests verts.

- [x] **5.7 — Vérifier qu'aucune route existante n'est cassée**

  ```powershell
  composer test:integration
  composer test:functional
  ```

  Résultat attendu : toutes les suites existantes passent.

- [x] **5.8 — Vérifier la qualité**

  ```powershell
  composer static
  composer cs:check
  ```

- [x] **5.9 — Point de revue utilisateur**

> **Note (implémentée en tâche 5) :** une méthode `App::setRouterBasePath(string)`
> a été ajoutée dans `app/App.php`. En CLI, `$_SERVER['SCRIPT_NAME']` pointe sur
> le binaire de PHPUnit (`vendor/bin/phpunit`), ce qui donne à AltoRouter un base
> path non vide (`.../vendor/bin`) et empêche toute correspondance d'URL. Sans
> cette méthode, la garde du routeur n'est pas testable. Elle n'a aucun effet
> sur la production (le base path n'est forcé que par les tests).

---

## Tâche 6 — Classification des routes existantes

**Livrable :** chaque page et API configurable est classée, et le blocage est
effectif sur les pages réelles.

### Étapes

- [x] **6.1 — Classer les pages dans `routes/web.php`**

  Ajouter la clé de politique en 3ᵉ argument des appels `Router::get()` :

  | Route | Policy key |
  |---|---|
  | `/caisse` | `caisse` |
  | `/recharges` | `recharges` |
  | `/produits` | `produits` |
  | `/utilisateurs` | `utilisateurs` |
  | `/historique` | `historique` |
  | `/pressing` | `pressing` |
  | `/analytics` | `analytics` |
  | `/payroll`, `/payroll/[:view]` | `paie` |
  | `/rapports`, `/rapport-ticket` | `rapports` |
  | `/categories` | `categories` |
  | `/taxes` | `taxes` |
  | `/parametres` | `parametres` |
  | `/hotel/tableau-bord` | `hotel_tableau_bord` |
  | `/hotel/calendrier` | `hotel_calendrier` |
  | `/hotel/reception` | `hotel_reception` |
  | `/hotel/etages` | `hotel_etages` |
  | `/hotel/chambres` | `hotel_chambres` |
  | `/hotel/reservations` | `hotel_reservations` |
  | `/hotel/guests` | `hotel_guests` |
  | `/hotel/facturation` | `hotel_facturation` |
  | `/hotel/roles` | `hotel_roles` |

  ⚠️ `/dashboard`, `/mon-profil`, `/otp-codes`, `/shops`, `/scanner`,
  `/new-scanner` et les factures publiques restent **hors politique**.


- [x] **6.2 — Classer les APIs Hôtel (exclusives) dans `routes/web.php` et `routes/api.php`**

  | API | Policy key |
  |---|---|
  | `GET /api/hotel/dashboard` | `hotel_tableau_bord` |
  | `GET /api/hotel/calendrier` | `hotel_calendrier` |
  | `GET /api/hotel/reception/board` | `hotel_reception` |
  | `GET/POST/PUT /api/hotel/reservations*` | `hotel_reservations` |
  | `POST /api/hotel/reservations/[id]/check-in`, `/check-out` | `hotel_reception` |
  | `GET/POST/PUT /api/hotel/chambres*` | `hotel_chambres` |
  | `GET /api/hotel/etages/board`, `/api/hotel/housekeeping/*` | `hotel_etages` |
  | `GET/POST/PUT /api/hotel/guests*` | `hotel_guests` |
  | `GET/POST /api/hotel/factures*` | `hotel_facturation` |
  | `POST /api/hotel/role/update` | `hotel_roles` |

  ⚠️ `/api/hotel/guests/search` et `/api/hotel/clients/search` sont des
  **lectures partagées** (voir 6.3), pas des APIs exclusives.

- [x] **6.3 — Classer les APIs partagées en lecture**

  Utiliser le mode `shared_read` avec une liste de clés séparées par `|` :

  | API | `policyKey` (mode `shared_read`) |
  |---|---|
  | `GET /api/produits` | `caisse\|produits` |
  | `GET /api/produit` | `caisse\|produits` |
  | `GET /api/categories` | `caisse\|produits\|categories` |
  | `GET /api/hotel/guests/search` | `hotel_reservations\|hotel_guests` |
  | `GET /api/hotel/clients/search` | `hotel_reservations\|hotel_guests` |
  | `GET /api/hotel/sejours/en-cours` | `caisse\|hotel_facturation` |

  Dans le code PHP, écrire la liste entre apostrophes simples pour éviter
  l'échappement du `|` :

  ```php
  Router::get('/api/produits', [ProductController::class, 'index'], 'caisse|produits', 'shared_read');
  ```

- [x] **6.4 — Classer les APIs exclusives générales**

  | API | Policy key |
  |---|---|
  | `POST /api/produit`, `/api/produit/update`, `/api/produit/delete` | `produits` |
  | `POST /api/produit/import`, `GET /api/produit/export` | `produits` |
  | `GET/POST/PUT/DELETE /api/product-batches*` | `produits` |
  | `GET/POST/PUT /api/users`, `/api/create/user`, `/api/update/user` | `utilisateurs` |
  | `POST /api/categories`, `/api/categories/update`, `/api/categories/delete` | `categories` |
  | `GET/POST/PUT/DELETE /api/taxes*` | `taxes` |
  | `GET/POST/PUT /api/settings*` | `parametres` |
  | `GET /api/reports*`, `POST /api/reports/dgi/z` | `rapports` |
  | `GET/POST/PUT/DELETE /api/payroll*` | `paie` |
  | `GET/POST/PUT/DELETE /api/pressing*` | `pressing` |

- [x] **6.5 — Ajouter les tests de classification**

  Dans `tests/Integration/PagePolicyRoutesTest.php`, ajouter un test qui vérifie
  que la route `/rapports` est bloquée pour un vendeur dont la boutique a une
  restriction `rapports` sur `shop_user`, et que `/api/reports/history` renvoie
  `403` JSON pour la même audience.

- [x] **6.6 — Lancer les tests**

  ```powershell
  vendor\bin\phpunit tests\Integration\PagePolicyRoutesTest.php
  composer test:integration
  ```

  Résultat attendu : tous les tests verts.

- [x] **6.7 — Point de revue utilisateur**

---

## Tâche 7 — Sidebar : masquer les pages bloquées

**Livrable :** la sidebar n'affiche que les pages réellement accessibles.

### Interfaces

`PageController::render()` doit fournir :

```php
$data['pageVisibility'] = [
    'pageKey' => true|false,
    // ... une entrée par clé du catalogue
];
```

Pour le `super_admin` (sans boutique en session), toutes les valeurs sont
`true` : le super_admin n'est jamais bloqué.

### Étapes

- [x] **7.1 — Charger la carte dans `PageController::render()`**

  Après le bloc `$moduleAccess` (ligne ~91), ajouter :

  ```php
  $pagePolicyService = new \App\Services\PagePolicyService();
  $userId = (int)($_SESSION['user_id'] ?? 0);
  $audience = $userId > 0
      ? \App\Services\PagePolicyService::resolveAudience($userId)
      : false;

  if ($audience === null) {
      // super_admin : jamais bloqué
      $data['pageVisibility'] = array_fill_keys(
          array_keys($pagePolicyService::getCatalog()),
          true
      );
  } elseif ($audience === false) {
      // fail-closed : utilisateur inconnu ou sans boutique
      $data['pageVisibility'] = array_fill_keys(
          array_keys($pagePolicyService::getCatalog()),
          false
      );
  } else {
      $data['pageVisibility'] = $pagePolicyService->getVisibilityMap(
          (int)($_SESSION['shop_id'] ?? 0),
          $audience
      );
  }
  ```

- [x] **7.2 — Modifier `app/views/layout/header.php`**

  1. Lire `$data['pageVisibility']` avec un repli fail-closed si absent (comme le
     repli existant pour `moduleAccess`).
  2. Cumuler la politique avec les conditions existantes :
     - Caisse : `if ($canCaisse && ($pageVisibility['caisse'] ?? false))`
     - Historique : `if (($moduleAccess['historique'] ?? false) && ($pageVisibility['historique'] ?? false))`
     - Paie : ajouter `&& ($pageVisibility['paie'] ?? false)` à `$canPayroll`
     - Rapports : ajouter `&& ($pageVisibility['rapports'] ?? false)`
     - Utilisateurs : ajouter `&& ($pageVisibility['utilisateurs'] ?? false)`
     - Taxes / Paramètres / Catégories : idem
  3. Pour chaque sous-page Hôtel, ajouter la condition de politique :
     `$canDashboard && ($pageVisibility['hotel_tableau_bord'] ?? false)`, etc.
  4. Si la famille Hôtel est entièrement masquée pour l'utilisateur, ne pas
     rendre le dropdown Hôtel (`$showHotel` reste déterminé par le rôle et le
     feature flag, mais la politique s'ajoute en plus).

- [x] **7.3 — Vérifier la cohérence menu / backend**

  Ajouter à `tests/Integration/PagePolicyRoutesTest.php` un test qui compare la
  réponse HTTP de `/rapports` et la présence de son lien dans le HTML rendu de
  `/dashboard` pour un utilisateur bloqué : les deux doivent concorder.

- [x] **7.4 — Lancer les tests**

  ```powershell
  vendor\bin\phpunit tests\Integration\PagePolicyRoutesTest.php
  composer test:integration
  ```

- [x] **7.5 — Point de revue utilisateur**


---

## Tâche 8 — APIs de configuration

**Livrable :** le `super_admin` lit et écrit la politique via des endpoints dédiés,
réservés au `super_admin` et séparés par famille.

### Interfaces

`app/controllers/PagePolicyController.php` :

```php
namespace App\Controllers;

class PagePolicyController extends Controller
{
    public function readGeneral(): void;
    public function updateGeneral(): void;
    public function readHotel(): void;
    public function updateHotel(): void;

    /** @return array{shop_id: int, family: string, changes: array} */
    private function readRequest(): array;

    private function respondJson(int $status, array $payload): void;
}
```

**Routes** (`routes/api.php`) :

| Méthode | Route | Contrôleur |
|---|---|---|
| `GET` | `/api/page-policy?shop_id={id}` | `PagePolicyController::readGeneral` |
| `POST` | `/api/page-policy/update` | `PagePolicyController::updateGeneral` |
| `GET` | `/api/hotel/page-access?shop_id={id}` | `PagePolicyController::readHotel` |
| `POST` | `/api/hotel/page-access/update` | `PagePolicyController::updateHotel` |

⚠️ Ces 4 routes sont **hors politique** (`policyKey` vide) : elles sont
administrées par le rôle `super_admin`, pas par la politique qu'elles modifient.

**Contrat de réponse** :

```json
{
  "success": true,
  "shop_id": 123,
  "shop_name": "Ma Boutique",
  "family": "general",
  "pages": [
    { "key": "caisse", "label": "Caisse / POS" },
    { "key": "rapports", "label": "Rapports" }
  ],
  "visibility": {
    "admin":    { "caisse": true, "rapports": false },
    "shop_user": { "caisse": true, "rapports": true }
  }
}
```

Erreurs : `403` (rôle), `400` (`shop_id` absent/invalide), `404` (boutique
inexistante), `422` (payload invalide).

### Étapes

- [x] **8.1 — Écrire les tests rouges**

  Dans `tests/Integration/PagePolicyRoutesTest.php` :

  | Nom de test | Assertion |
  |---|---|
  | `testUnAdminNePeutPasLireLaPolitique` | 403 |
  | `testUnVendeurNePeutPasLireLaPolitique` | 403 |
  | `testLeSuperAdminPeutLireLaPolitiqueGenerale` | 200 + `family = general` |
  | `testLeSuperAdminPeutLireLaPolitiqueHotel` | 200 + `family = hotel` |
  | `testUneBoutiqueInexistanteEstRefusee` | 404 |
  | `testUnShopIdAbsentEstRefuse` | 400 |
  | `testUnePageInconnueEstRefuseeALaSauvegarde` | 422 |
  | `testUnePageDUneAutreFamilleEstRefuseeALaSauvegarde` | 422 |
  | `testLaSauvegardeMetAJourLaPolitique` | relecture par `GET` reflète les changements |
  | `testUnePolitiqueHotelNeModifiePasLaFamilleGenerale` | familles isolées |

- [x] **8.2 — Lancer le test et confirmer l'échec**

  ```powershell
  vendor\bin\phpunit tests\Integration\PagePolicyRoutesTest.php
  ```

- [x] **8.3 — Implémenter le contrôleur**

  Créer `app/controllers/PagePolicyController.php` :

  - `requireSuperAdmin()` en entrée de **chaque** action (403 sinon) ;
  - `readRequest()` : lire le JSON (`file_get_contents('php://input')`) avec repli
    sur `$_POST` ; valider `shop_id` (entier > 0) ; valider la famille ;
  - vérifier l'existence de la boutique via `Shop::findById()` (404 sinon) ;
  - `updateGeneral()` / `updateHotel()` : appeler
    `PagePolicyService::validateChanges()` (422 si invalide) puis
    `updatePolicy()` ; renvoyer la carte mise à jour.

- [x] **8.4 — Enregistrer les routes dans `routes/api.php`**

  Ajouter les 4 routes avec `use App\Controllers\PagePolicyController;` en tête de
  fichier, et **sans** clé de politique.

- [x] **8.5 — Lancer les tests**

  ```powershell
  vendor\bin\phpunit tests\Integration\PagePolicyRoutesTest.php
  ```

- [x] **8.6 — Vérifier la qualité**

  ```powershell
  composer static
  composer cs:check
  ```

- [x] **8.7 — Point de revue utilisateur**

---

## Tâche 9 — Mutation partagée : `POST /api/vente`

**Livrable :** une vente ne peut pas contourner une restriction en changeant de
contexte métier.

### Interfaces

`app/controllers/SaleController.php` — dans `create()`, remplacer le bloc
`requireCaisseAccess()` (ligne ~61) par :

```php
$isRecharge = !empty($data['providerService']);
$contextPageKey = $isRecharge ? 'recharges' : 'caisse';

if (!$this->requirePagePolicy($contextPageKey)) {
    return;
}

// Le contrôle existant `acces_caisse` reste ajouté pour le contexte caisse.
if (!$isRecharge && !$this->requireCaisseAccess()) {
    return;
}
```

⚠️ L'ordre est important : la **politique** est vérifiée en premier (elle est
plus restrictive), puis le droit individuel `acces_caisse` comme aujourd'hui.

### Étapes

- [x] **9.1 — Écrire les tests rouges**

  Dans `tests/Integration/PagePolicyRoutesTest.php` :

  | Nom de test | Assertion |
  |---|---|
  | `testUneVenteCaisseEstRefuseeSiCaisseEstBloquee` | 403 `page_not_allowed` |
  | `testUneRechargeResteAutoriseeSiSeuleCaisseEstBloquee` | la recharge passe la garde de politique |
  | `testUneRechargeEstRefuseeSiRechargesEstBloquee` | 403 `page_not_allowed` |

- [x] **9.2 — Lancer le test et confirmer l'échec**

  ```powershell
  vendor\bin\phpunit tests\Integration\PagePolicyRoutesTest.php
  ```

- [x] **9.3 — Modifier `SaleController::create()`**

  Appliquer le bloc ci-dessus. Ne modifier **rien** d'autre dans la méthode
  (facturation, stock, DGI, bridge hôtel) : la feature ne doit pas impacter le
  flux de vente.

- [x] **9.4 — Lancer les tests**

  ```powershell
  vendor\bin\phpunit tests\Integration\PagePolicyRoutesTest.php
  composer test:integration
  ```

  Résultat attendu : tous les tests verts, y compris les tests DGI / Hotel
  existants.

- [x] **9.5 — Vérifier la qualité**

  ```powershell
  composer static
  composer cs:check
  ```

- [x] **9.6 — Point de revue utilisateur**


---

## Tâche 10 — Pages de configuration (matrices)

**Livrable :** le `super_admin` dispose d'une matrice de cases à cocher pour
chaque famille, dans le module correspondant.

### Interfaces

`app/controllers/PageController.php` — nouvelles actions :

```php
public function shopsPages(): void;     // GET /shops/pages?shop_id={id}
public function hotelPageAccess(): void; // GET /hotel/acces-pages?shop_id={id}
```

- `shopsPages()` : `requireSuperAdmin()` ; boutique ciblée via `?shop_id=`
  (obligatoire) ; famille `general`.
- `hotelPageAccess()` : `requireSuperAdmin()` **puis** `requireHotelApiAccess()`
  (le flag API Hôtel s'applique, sans exception) ; boutique ciblée via
  `?shop_id=` ; famille `hotel`.

Données passées à la vue (deux variantes selon la page) :

```php
// /shops/pages
[
    'policyFamily'     => PagePolicyService::FAMILY_GENERAL,
    ...
]

// /hotel/acces-pages
[
    'policyFamily'     => PagePolicyService::FAMILY_HOTEL,
    ...
]
```

Clés communes aux deux :

```php
'policyPages'      => [['key' => 'caisse', 'label' => 'Caisse / POS'], ...],
'policyVisibility' => ['admin' => ['caisse' => true, ...], 'shop_user' => [...]],
'policyShops'      => [ /* boutiques actives pour le select */ ],
'policyShopId'     => 123,
'policyShopName'   => 'Ma Boutique',
```

### Étapes

- [ ] **10.1 — Écrire les tests rouges**

  Dans `tests/Integration/PagePolicyRoutesTest.php` :

  | Nom de test | Assertion |
  |---|---|
  | `testShopsPagesRefuseUnNonSuperAdmin` | 403 |
  | `testHotelAccesPagesRefuseUnNonSuperAdmin` | 403 |
  | `testHotelAccesPagesRespecteLeFeatureFlagHotel` | 403 si flag absent |
  | `testShopsPagesAfficheLaFamilleGenerale` | HTML contient « Rapports » et pas « Réception » |
  | `testHotelAccesPagesAfficheLaFamilleHotel` | HTML contient « Réception » et pas « Rapports » |
  | `testLeSelecteurDeBoutiqueEstPresent` | HTML contient un `select` avec les boutiques |

- [ ] **10.2 — Lancer le test et confirmer l'échec**

  ```powershell
  vendor\bin\phpunit tests\Integration\PagePolicyRoutesTest.php
  ```

- [ ] **10.3 — Ajouter les actions dans `PageController.php`**

  - `shopsPages()` : valider `shop_id` (400 si absent/invalide), vérifier la
    boutique (404 si inexistante), charger la visibilité via
    `PagePolicyService::getPolicyMap($shopId, FAMILY_GENERAL)` ;
  - `hotelPageAccess()` : mêmes validations, avec
    `PagePolicyService::getPolicyMap($shopId, FAMILY_HOTEL)` ;
  - les deux rendent leur vue via `$this->render(...)`.

  ⚠️ `hotelPageAccess()` doit appeler `requireHotelApiAccess()` **après**
  `requireSuperAdmin()` : un non-`super_admin` reçoit 403, et un
  `super_admin` sans abonnement Hôtel reçoit également 403.

- [ ] **10.4 — Créer `app/views/shops-pages.php`**

  Structure :

  - sélecteur de boutique (GET `?shop_id=`) ;
  - tableau : `Page | Admins | Utilisateurs de la boutique` ;
  - chaque case porte `data-family`, `data-page-key`, `data-audience`,
    `data-original` ;
  - toutes les cases sont **cochées** par défaut (page visible) ;
  - bouton « Enregistrer » avec `data-endpoint="/api/page-policy/update"` ;
  - zone de message `#page-policy-message` ;
  - lien de retour vers `/shops`.


- [ ] **10.5 — Créer `app/views/hotel-acces-pages.php`**

  Même structure que `shops-pages.php`, avec :
  - `data-endpoint="/api/hotel/page-access/update"` ;
  - uniquement les 9 pages Hôtel ;
  - un bandeau d'information indiquant que cette page est réservée au
    `super_admin` et dépend de l'abonnement Hôtel.

- [ ] **10.6 — Créer `public/assets/js/page-policy.js`**

  Comportement :

  1. au chargement, envoyer `shop_id` et `family` via `GET` sur l'endpoint de
     lecture pour hydrater les cases (l'état initial rendu par PHP est déjà
     correct ; l'hydratation sert de resynchronisation) ;
  2. au clic sur « Enregistrer », construire l'objet
     `{ shop_id, changes: { admin: {...}, shop_user: {...} } }` où chaque page
     vaut `true` si cochée, `false` si décochée ;
  3. `fetch()` en POST avec `Content-Type: application/json` ;
  4. gérer les états : bouton désactivé pendant l'envoi, message de succès,
     message d'erreur (403 / 404 / 422) ;
  5. ne jamais exposer la clé de famille dans l'URL.

- [ ] **10.7 — Inclure le script dans `app/views/layout/footer.php`**

  Ajouter après les autres scripts :

  ```html
  <script src="/assets/js/page-policy.js?v=1.0.0"></script>
  ```

  ⚠️ Le script doit être tolérant : sur les pages qui n'ont pas de matrice, il
  ne doit produire aucune erreur console (vérifier l'existence de
  `#page-policy-form` avant toute action).

- [ ] **10.8 — Ajouter le bouton dans `app/views/shops.php`**

  Dans le `.shop-card-footer` (ligne ~171), ajouter un bouton :

  ```html
  <a class="btn btn-secondary btn-small" href="/shops/pages?shop_id=<?= (int)$shop['id'] ?>">
      Gérer les pages
  </a>
  ```

- [ ] **10.9 — Lancer les tests**

  ```powershell
  vendor\bin\phpunit tests\Integration\PagePolicyRoutesTest.php
  npm run lint
  ```

- [ ] **10.10 — Point de revue utilisateur**

---

## Tâche 11 — `/hotel/acces-pages` dans la sidebar + redirection `/hotel`

**Livrable :** le lien de configuration Hôtel n'apparaît que pour le
`super_admin`, et `/hotel` ne redirige jamais vers une page bloquée.

### Étapes

- [ ] **11.1 — Ajouter le lien dans `app/views/layout/header.php`**

  Dans le dropdown Hôtel, après l'entrée « Gestion des rôles », ajouter une
  entrée visible **uniquement** si :

  ```php
  $_SESSION['role'] === 'super_admin' && $hotelFeatureOk
  ```

  Le lien pointe vers `/hotel/acces-pages` et porte l'identifiant
  `id="hotel-page-access-link"` (pour les tests).

  ⚠️ Ce lien n'est **jamais** soumis à une restriction de page.

- [ ] **11.2 — Modifier `PageController::hotel()` pour ne pas rediriger vers une page bloquée**

  La redirection actuelle (lignes 459-465) doit choisir une page Hôtel
  **autorisée** :

  ```php
  $candidates = [
      'super_admin'    => ['hotel_tableau_bord', 'hotel_reception', 'hotel_etages'],
      'admin'          => ['hotel_tableau_bord', 'hotel_reception', 'hotel_etages'],
      'manager'        => ['hotel_tableau_bord', 'hotel_reception', 'hotel_etages'],
      'gouvernante'    => ['hotel_etages', 'hotel_reception', 'hotel_tableau_bord'],
      'receptionniste' => ['hotel_reception', 'hotel_tableau_bord', 'hotel_etages'],
  ];
  ```

  Pour chaque candidat, vérifier `isPageAllowedForUser()` ; rediriger vers la
  première page autorisée. Si aucune n'est autorisée → **403** + `403.php`
  (au lieu d'une redirection vers une page bloquée).


- [ ] **11.3 — Écrire les tests rouges**

  Dans `tests/Integration/PagePolicyRoutesTest.php` :

  | Nom de test | Assertion |
  |---|---|
  | `testLeLienHotelAccesPagesEstAbsentPourUnAdmin` | HTML sans `hotel-page-access-link` |
  | `testLeLienHotelAccesPagesEstPresentPourUnSuperAdmin` | HTML avec le lien |
  | `testHotelRedirigeVersUnePageAutorisee` | redirection vers une page non bloquée |
  | `testHotelRenvoie403SiToutesLesPagesSontBloquees` | 403 |

- [ ] **11.4 — Implémenter et tester**

  ```powershell
  vendor\bin\phpunit tests\Integration\PagePolicyRoutesTest.php
  composer test:integration
  ```

- [ ] **11.5 — Point de revue utilisateur**

---

## Tâche 12 — Test end-to-end Playwright

**Livrable :** le parcours complet super_admin → vendeur est vérifié dans un
navigateur réel.

### Interfaces

Créer `e2e/page-policy.spec.js`. Le test utilise les comptes de la base de test
et `request` / `page` de Playwright.

⚠️ L'environnement E2E actuel ne dispose pas de helper de connexion dans
`e2e/login.spec.js` (les tests existants supposent une session déjà établie ou
testent des cas anonymes). Créer un helper local dans le fichier :

```js
async function login(page, username, password) {
  await page.goto('/');
  await page.fill('#username', username);
  await page.fill('#password', password);
  await page.click('button[type="submit"]');
  await page.waitForURL('**/dashboard');
}
```

⚠️ Vérifier les identifiants réels des champs du formulaire dans
`app/views/login.php` avant d'écrire le helper ; si les sélecteurs diffèrent,
utiliser ceux du formulaire réel.

### Étapes

- [ ] **12.1 — Inspecter le formulaire de connexion**

  ```powershell
  Select-String -Path 'app\views\login.php' -Pattern 'id="|name="|type="submit"' | Select-Object -First 30
  ```

  Résultat attendu : les identifiants exacts des champs à remplir.

- [ ] **12.2 — Écrire le test E2E**

  Scénario (préparé via les APIs, afin de ne dépendre que du parcours UI pour la
  configuration) :

  1. se connecter en `super_admin` ;
  2. ouvrir `/hotel/acces-pages?shop_id={id}` ;
  3. décocher la case « Réception » de la colonne « Utilisateurs de la boutique » ;
  4. cliquer sur « Enregistrer » et attendre le message de succès ;
  5. se connecter en vendeur de cette boutique ;
  6. vérifier que le lien `/hotel/reception` est absent de la sidebar ;
  7. vérifier que `page.goto('/hotel/reception')` renvoie un 403 ;
  8. vérifier que `request.get('/api/hotel/reception/board')` renvoie 403 ;
  9. vérifier qu'un `admin` de la même boutique accède encore à la page ;
  10. vérifier que le `super_admin` accède encore à la page.

- [ ] **12.3 — Lancer le test**

  ```powershell
  npx playwright test e2e\page-policy.spec.js --project=chromium
  ```

  Résultat attendu : le test passe.

  ⚠️ Si l'environnement E2E ne dispose pas de serveur local ou de comptes de
  test, documenter le blocage dans `docs/PLAN_PAGE_ACCESS_POLICY.md` et laisser
  le test écrit pour exécution en conditions réelles.

- [ ] **12.4 — Vérifier la qualité JS**

  ```powershell
  npm run lint
  npx prettier --check e2e\page-policy.spec.js
  ```

- [ ] **12.5 — Point de revue utilisateur**

---

## Tâche 13 — Documentation

**Livrable :** la couche d'accès est documentée pour les développeurs et les
exploitants.

### Étapes

- [ ] **13.1 — Mettre à jour `ROUTES.md`**

  Ajouter une section « Politique d'accès aux pages par boutique » :
  - tableau des routes de configuration (4 routes) ;
  - tableau des pages configurables et de leur clé ;
  - rappel que la politique est restrictive et fail-closed ;
  - rappel que `/hotel/acces-pages` est réservé au `super_admin` et dépend du
    flag API Hôtel.

- [ ] **13.2 — Mettre à jour `AGENT.md` §3**

  Ajouter après la section « Droits d'accès aux modules du POS » :

  | Élément | Valeur |
  |---|---|
  | Source de vérité | `PagePolicyService` |
  | Table | `shop_page_restrictions` |
  | Audiences | `admin`, `shop_user` |
  | Qui peut les régler | `super_admin` uniquement |
  | Configuration générale | `/shops/pages` |
  | Configuration Hôtel | `/hotel/acces-pages` |
  | Garde backend | `Router` (routes avec clé de politique) |
  | Sidebar | `header.php` via `pageVisibility` |

  Et ajouter dans §7 (pièges connus) :

  > ❌ Ne pas faire confiance à `$_SESSION['role']` pour résoudre l'audience :
  > la politique lit le rôle **en base** afin de prendre en compte un changement
  > de rôle sans reconnexion.

- [ ] **13.3 — Créer `docs/PLAN_PAGE_ACCESS_POLICY.md`**

  Document de suivi court reprenant : l'état d'implémentation, les décisions
  validées, les commandes de validation et la liste des vérifications restantes
  côté utilisateur (application de la migration en production, test en conditions
  réelles).

- [ ] **13.4 — Vérifier le format**

  ```powershell
  npx prettier --check docs\superpowers\plans\2026-11-18-shop-page-access-policy.md docs\PLAN_PAGE_ACCESS_POLICY.md
  ```

- [ ] **13.5 — Point de revue utilisateur**


---

## Validation globale (après la tâche 13)

```powershell
# 1. Suites de tests
composer test:unit
composer test:integration
composer test:functional

# 2. Qualité PHP
composer static
composer cs:check

# 3. Qualité JS
npm run lint
npx prettier --check public\assets\js\page-policy.js e2e\page-policy.spec.js

# 4. E2E (si l'environnement est disponible)
npx playwright test e2e\page-policy.spec.js --project=chromium

# 5. Non-régression ciblée sur les droits existants
vendor\bin\phpunit tests\Unit\CaisseAccessTest.php
vendor\bin\phpunit tests\Integration\CaisseAccessConfigTest.php
vendor\bin\phpunit tests\Integration\HotelRolesScopeTest.php
```

**Critère de fin :** toutes les commandes ci-dessus passent, sans nouvelle
erreur, et les fichiers de la feature sont listés par `git status` sans
modification des fichiers hors périmètre.

---

## Couverture de la spécification

| Exigence de la spécification | Tâche(s) |
|---|---|
| Table `shop_page_restrictions` (restrictions uniquement) | 1, 2 |
| Absence de restriction = visible | 2, 3 |
| Audiences `admin` / `shop_user` | 3 |
| Règle restrictive (ne peut pas accorder) | 3, 5 |
| `super_admin` jamais bloqué | 3, 5, 7, 11 |
| Résolution du rôle **en base** | 3 |
| Fail-closed (page inconnue, erreur SQL, utilisateur inconnu) | 3, 5 |
| Catalogue des 21 pages | 3, 6 |
| Garde centralisée avant le contrôleur | 5 |
| 403 page / 403 JSON uniforme | 5 |
| APIs exclusives | 6 |
| Lectures partagées (« au moins une ») | 6 |
| Mutations partagées (contexte métier) | 9 |
| Sidebar = même source de vérité | 7 |
| `/shops` → configuration générale | 8, 10 |
| `/hotel/acces-pages` → `super_admin` uniquement | 8, 10, 11 |
| `/hotel/acces-pages` soumis au flag API Hôtel | 10, 11 |
| Redirection `/hotel` vers une page autorisée | 11 |
| Audit rattaché à la boutique ciblée | 4 |
| Idempotence de la sauvegarde | 2, 4 |
| Migration idempotente | 1 |
| Tests unitaires / intégration / E2E | 2, 3, 4, 5, 8, 9, 11, 12 |
| Documentation (`ROUTES.md`, `AGENT.md`) | 13 |

---

## Review Focus — points à tester en priorité

Ces cinq points sont les plus susceptibles de causer une régression silencieuse.
Chaque ligne doit avoir un test dans la tâche propriétaire.

1. **La sidebar et le backend ne peuvent pas diverger.**
   → Tâche 7, étape 7.3 : comparer le HTML de la sidebar et le code HTTP de la
   page pour un utilisateur bloqué.

2. **Une mutation partagée ne doit pas être contournée par un changement de
   contexte.**
   → Tâche 9 : `POST /api/vente` avec `providerService` (recharge) quand seule
   `caisse` est bloquée, et inversement.

3. **Un changement de rôle sans reconnexion doit être pris en compte.**
   → Tâche 3 : modifier `utilisateurs.role` en base puis vérifier l'audience
   résolue au cycle de requête suivant.

4. **Le `super_admin` ne doit jamais se verrouiller lui-même.**
   → Tâches 3, 5, 7, 11 : accès autorisé même avec toutes les restrictions
   actives sur sa boutique cible.

5. **Une erreur d'infrastructure doit fermer la porte, pas l'ouvrir.**
   → Tâche 5 : table absente ou erreur SQL ⇒ 403, jamais d'accès granted.

---

## Points d'attention pour l'implémentation

- **Ne pas modifier** `User::MODULES_ACCES`, `User::canAccessModule()`,
  `User::canAccessCaisse()` ni les colonnes `acces_*` : la nouvelle politique
  est une couche **supplémentaire**.
- **`Router::name()`** positionne un nom pour la route suivante ; l'extension
  du routeur ne doit pas casser ce contrat (utilisé par les routes nommées
  éventuelles).
- **Le routeur `AltoRouter` (vendor) ne doit pas être modifié.** Toute la
  logique de politique reste dans `app/core/Router.php`.
- **Les contrôleurs Hôtel sortent déjà avec `exit`** après `jsonOut()` : la
  garde du routeur doit s'exécuter **avant** leur instanciation, sinon le
  contrôleur gagnerait la course.
- **`requireActiveShop()`** reste prioritaire : une boutique désactivée
  continue de produire son comportement actuel.
- **Le feature flag Hôtel** est lu via `RechargeAccessService::canAccessHotel()`
  et le cache de session `feature_access_cache` : ne pas dupliquer cette
  logique dans la nouvelle feature.
- **Le `super_admin` n'a pas de boutique en session** : toute lecture de
  configuration doit exiger un `shop_id` explicite, sinon 400.
- **Le fichier `routes/api.php` est déjà modifié** dans le worktree par un
  autre travail en cours : ne pas écraser ces changements ; utiliser des
  éditions ciblées.
- **Ne pas exécuter de `git commit`** : l'utilisateur valide et commite
  manuellement.

---

## Ordre d'exécution recommandé

```text
Tâche 1  → Tâche 2  → Tâche 3  → Tâche 4
        → Tâche 5  → Tâche 6  → Tâche 7
        → Tâche 8  → Tâche 9  → Tâche 10
        → Tâche 11 → Tâche 12 → Tâche 13
```

Les tâches 1 à 4 construisent le moteur (données → service) et ne modifient
aucun comportement visible. La tâche 5 active la garde ; les tâches 6 et 7
branchent les pages réelles. Les tâches 8 à 11 ajoutent l'interface
d'administration. La tâche 12 valide le parcours réel et la tâche 13 documente.

**Ne pas commencer la tâche 5 sans la tâche 4** : la garde centrale sans service
d'écriture laisserait la sidebar incohérente avec la configuration.











