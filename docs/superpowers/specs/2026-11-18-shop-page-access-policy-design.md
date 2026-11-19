# Politique d’accès aux pages par boutique

**Statut :** conception validée — en attente de validation du document  
**Date :** 2026-11-18  
**Périmètre :** POS multi-boutique + module Hôtel (PMS)

---

## 0. Résumé

Le `super_admin` pourra masquer une page à une catégorie d’utilisateurs pour une
boutique donnée. La politique est **restrictive** : elle peut retirer un droit
déjà accordé, mais ne peut jamais en accorder un qui est refusé par le rôle,
les droits individuels `utilisateurs.acces_*` ou les feature flags OSAT.

La portée est :

```text
une boutique × une audience (admin ou shop_user) × une page
```

Deux interfaces utiliseront le même moteur :

- les pages générales seront gérées depuis `/shops` ;
- les pages Hôtel seront gérées exclusivement depuis `/hotel/acces-pages`,
  visible et accessible uniquement au `super_admin`.

Le masquage est effectif : le lien disparaît de la sidebar, l’accès direct à
l’URL renvoie `403`, et les APIs exclusives à la page renvoient `403 JSON`.
Le `super_admin` reste toujours omniscient afin de ne pas se verrouiller.

---

## 1. Contexte et problème

Le projet possède déjà plusieurs couches d’accès qui ne doivent pas être
confondues :

1. **Rôle applicatif** : `utilisateurs.role`
   (`super_admin`, `admin`, `vendeur`) ;
2. **Rôle métier Hôtel** : `utilisateurs.hotel_role`
   (`manager`, `receptionniste`, `gouvernante`) ;
3. **Droits individuels POS** : `utilisateurs.acces_*`, configurés dans
   `/hotel/roles` ;
4. **Abonnements / feature flags OSAT** : Hôtel, Paie, Pressing, Recharges ;
5. **Configuration par boutique** : `shops.acces_extras` pour les Extras.

La logique POS du module Hôtel montre déjà qu’un rôle métier peut retirer un
utilisateur d’un poste de travail. En revanche, `User::canAccessModule()`
exempte définitivement `admin` et `super_admin`. La nouvelle feature doit donc
introduire une couche supérieure, pilotée par le `super_admin`, capable de
restreindre un `admin` sans modifier son rôle.

### Besoin métier

Pour une boutique donnée, le `super_admin` doit pouvoir :

- masquer une page à tous les admins de la boutique ;
- masquer une page à tous les utilisateurs de la boutique ;
- conserver les autres pages visibles ;
- appliquer la restriction immédiatement, sans attendre une reconnexion.

---

## 2. Décisions validées

| Décision | Choix |
|---|---|
| Portée | Par boutique et par catégorie d’utilisateurs |
| Catégories | `admin`, `shop_user` |
| Effet | Masquage du menu **et** blocage URL/API |
| Valeur initiale | Toutes les pages visibles |
| Pages Hôtel | Gérées dans `/hotel/acces-pages` |
| Accès à `/hotel/acces-pages` | `super_admin` uniquement |
| Feature flag sur `/hotel/acces-pages` | Même règle que le module Hôtel |
| Pages générales | Gérées depuis `/shops` |
| `super_admin` | Jamais bloqué par cette politique |
| Rôle `hotel_role='manager'` avec `role='vendeur'` | Reste dans `shop_user` |

---

## 3. Objectifs et hors périmètre

### Objectifs

- Interdire l’accès à une page par boutique et par audience sans modifier le
  rôle d’un utilisateur.
- Faire de la sidebar et du backend les deux consommateurs d’une source de vérité
  unique.
- Appliquer les changements immédiatement.
- Préserver les comptes et les comportements existants lors du déploiement.
- Garder la configuration générale dans le module général et la configuration
  Hôtel dans le module Hôtel.
- Fournir un historique auditable des changements de politique.

### Hors périmètre V1

- Masquage global à toutes les boutiques.
- Une audience distincte « manager hôtel ».
- Modification des droits pour un utilisateur individuel : déjà couvert par
  `utilisateurs.acces_*`.
- Personnalisation du contenu d’une page ou désactivation d’une action interne.
- Remplacement des feature flags, des rôles ou des permissions Hôtel existantes.
- Modification de la bibliothèque AltoRouter dans `vendor/`.

---

## 4. Règle d’accès effective

Une page est accessible uniquement si **toutes** les conditions suivantes sont
vraies :

```text
accès effectif =
    rôle et contrôles existants
    ET droits individuels acces_*
    ET feature flag / abonnement requis
    ET absence de restriction super_admin pour l’audience et la page
```

La politique du `super_admin` est donc une règle restrictive :

- elle peut bloquer un `admin` malgré son exemption actuelle ;
- elle ne peut pas autoriser une page refusée par `acces_*` ;
- elle ne peut pas contourner un feature flag OSAT ;
- elle ne remplace aucun contrôle existant.

### Audience

| Audience | Utilisateurs concernés |
|---|---|
| `admin` | Utilisateurs dont `utilisateurs.role = 'admin'` |
| `shop_user` | Tous les autres utilisateurs rattachés à la boutique : vendeurs, réceptionnistes, gouvernantes, etc. |

Un `super_admin` n’appartient à aucune audience de restriction et n’est jamais
bloqué. Un utilisateur sans boutique ne peut pas être autorisé sur une page
protégée par cette politique : résultat fail-closed.

## 5. Catalogue des pages

Les clés sont des identifiants stables, indépendants des URLs. Elles ne doivent
pas être renommées après mise en production.

### 5.1 Pages générales

| Clé | Libellé | Page principale |
|---|---|---|
| `caisse` | Caisse / POS | `/caisse` |
| `recharges` | Recharges | `/recharges` |
| `produits` | Produits | `/produits` |
| `historique` | Historique | `/historique` |
| `pressing` | Pressing | `/pressing` |
| `analytics` | Analytics | `/analytics` |
| `paie` | Paie | `/payroll` |
| `rapports` | Rapports | `/rapports` |
| `utilisateurs` | Utilisateurs | `/utilisateurs` |
| `categories` | Catégories | `/categories` |
| `taxes` | Taxes | `/taxes` |
| `parametres` | Paramètres | `/parametres` |

### 5.2 Pages Hôtel

| Clé | Libellé | Page principale |
|---|---|---|
| `hotel_tableau_bord` | Tableau de bord Hôtel | `/hotel/tableau-bord` |
| `hotel_calendrier` | Calendrier | `/hotel/calendrier` |
| `hotel_reception` | Réception | `/hotel/reception` |
| `hotel_etages` | Étages / Housekeeping | `/hotel/etages` |
| `hotel_chambres` | Chambres | `/hotel/chambres` |
| `hotel_reservations` | Réservations | `/hotel/reservations` |
| `hotel_guests` | Clients / Guests | `/hotel/guests` |
| `hotel_facturation` | Facturation Hôtel | `/hotel/facturation` |
| `hotel_roles` | Gestion des rôles Hôtel | `/hotel/roles` |

### 5.3 Pages hors politique

Ces pages restent accessibles selon leurs contrôles actuels et ne peuvent pas
être masquées par la matrice :

- `/dashboard` (page d’atterrissage) ;
- `/mon-profil` ;
- pages de connexion, mot de passe et déconnexion ;
- factures publiques ;
- `/shops` et la configuration générale des pages ;
- `/hotel/acces-pages` ;
- pages techniques réservées au `super_admin` (`/otp-codes`).

`/hotel` est une route de redirection : elle choisit une page Hôtel autorisée
selon le rôle effectif. Si aucune page n’est autorisée, elle renvoie `403`.

---

## 6. Modèle de données

### 6.1 Table de restrictions

Une table dédiée stocke uniquement les blocages. L’absence de restriction
signifie que la page reste visible.

```sql
CREATE TABLE `shop_page_restrictions` (
  `id`          INT NOT NULL AUTO_INCREMENT,
  `shop_id`     INT NOT NULL,
  `audience`    ENUM('admin', 'shop_user') NOT NULL,
  `page_key`    VARCHAR(64) NOT NULL,
  `created_by`  INT DEFAULT NULL,
  `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
                 ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_shop_page_audience` (`shop_id`, `audience`, `page_key`),
  KEY `idx_shop_page_key` (`page_key`),
  CONSTRAINT `fk_shop_page_restrictions_shop`
    FOREIGN KEY (`shop_id`) REFERENCES `shops` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_shop_page_restrictions_user`
    FOREIGN KEY (`created_by`) REFERENCES `utilisateurs` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
```

La migration devra être idempotente, conformément aux migrations récentes.
Les clés étrangères utilisent des colonnes `INT` signées, identiques au type
réel de `shops.id` et `utilisateurs.id` dans le schéma actuel.

### 6.2 Pourquoi uniquement des restrictions ?

- Aucun impact au déploiement : les comptes existants conservent leurs accès.
- Les nouvelles pages restent visibles par défaut.
- Pas d’initialisation massive de lignes autorisées.
- Une case décochée correspond directement à une ligne présente.
- Le reset d’une case revient à supprimer la restriction.

### 6.3 Absence de restriction

Pour une boutique, une audience et une page sans ligne correspondante :

```text
visible = true
```

Cette sémantique est partout identique dans le service, l’API et les vues.

### 6.4 Audit

Les changements seront journalisés dans `audit_logs` via `logAudit()` :

- `action` : `page_policy_update` ;
- `entity` : `shop_page_policy` ;
- `entity_id` : identifiant de la boutique ;
- `details` : `{ audience, page_key, allowed, previous_allowed }`.

Chaque restriction créée, supprimée ou modifiée dans une même requête peut
être journalisée dans un seul événement contenant toutes les différences.

Le `shop_id` de l’entrée d’audit doit être **la boutique ciblée par la
configuration**, et non le `shop_id` de session : ce dernier est `NULL` pour le
`super_admin`. Si `Controller::logAudit()` ne permet pas de choisir la boutique,
le contrôleur de politique utilisera `AuditLog::log()` directement avec la
boutique ciblée.

## 7. Architecture applicative

### 7.1 Catalogue central

Un service central définira les pages configurables, leur libellé et leur
famille (`general` ou `hotel`). Le même catalogue alimentera :

- la matrice dans `/shops` ;
- la matrice dans `/hotel/acces-pages` ;
- la validation côté API ;
- la sidebar ;
- la garde des routes.

Exemple de contrat :

```php
final class PagePolicyService
{
    public const GENERAL_PAGES = [...];
    public const HOTEL_PAGES = [...];

    /** null = super_admin (jamais bloqué) ; false = fail-closed. */
    public function resolveAudience(int $userId): string|false|null;

    /** @return array{role: string, shop_id: int|null}|false */
    public function resolveUserContext(int $userId): array|false;

    public function isPageAllowed(string $pageKey, int $userId, int $shopId): bool;

    /** Résout le contexte utilisateur EN BASE puis vérifie la politique. */
    public function isPageAllowedForUser(string $pageKey, int $userId): bool;

    public function getVisibilityMap(int $shopId, string $audience): array;
    public function updatePolicy(int $shopId, array $changes): bool;
}
```

Le type `string|false|null` de `resolveAudience()` exprime trois états distincts :
une audience concrète, l’exemption du `super_admin` (`null`) et le refus
fail-closed (`false`). Les noms exacts des méthodes pourront être ajustés
pendant l’implémentation, mais le service restera l’unique source du catalogue
et des règles de lecture.

### 7.2 Lecture de l’audience

L’audience d’un utilisateur doit être déduite de son rôle actuel en base :

```sql
SELECT role, shop_id FROM utilisateurs WHERE id = :id
```

- `super_admin` → jamais bloqué ;
- `admin` → audience `admin` ;
- tout autre rôle avec un `shop_id` → audience `shop_user` ;
- utilisateur inconnu, sans boutique ou erreur SQL → fail-closed pour les
  routes protégées.

La lecture en base évite que le rôle copié dans la session permette de contourner
la politique après un changement de rôle.

### 7.3 Lecture des restrictions

Pour éviter une requête par page, le service chargera les restrictions de la
boutique courante en une requête et calculera une carte :

```php
[
    'admin' => [
        'rapports' => false,
        'paie' => false,
    ],
    'shop_user' => [
        'produits' => false,
    ],
]
```

Les clés absentes valent `true`.

### 7.4 Cache

La V1 ne utilisera pas de cache persistant. La lecture sera faite depuis la
base à chaque requête protégée. Le nombre de restrictions attendu est faible et
la présence d’une seule requête par requête HTTP est acceptable.

Si une optimisation est nécessaire plus tard, un cache très court avec
invalidation après sauvegarde pourra être ajouté, sans modifier le contrat.

### 7.5 Erreurs

- Erreur SQL de lecture des restrictions → page/API protégée : **fail-closed**.
- Page non reconnue → **fail-closed**.
- Audience inconnue → **fail-closed**.
- Boutique inexistante → `404` ou `400` selon le contexte de configuration ;
  elle ne doit jamais être traitée comme « tout autorisé » pour une écriture.

---

## 8. Application de la politique aux routes

### 8.1 Principe

La garde doit s’exécuter avant le contrôleur. Le routeur maison
`app/core/Router.php` conservera un registre interne de politique.

Pour chaque route enregistrée, le wrapper générera un identifiant technique
**unique**, par exemple `__route_42`, qu’il transmettra à AltoRouter comme nom
interne. Il conservera séparément l’association :

```text
identifiant technique unique → politique et contexte de la route
```

La clé de politique ne sert pas de nom AltoRouter : une même clé peut concerner
plusieurs URLs, alors que AltoRouter interdit les noms de route dupliqués.

L’API publique pourra donc être enrichie sans casser les appels existants :

```php
Router::get('/rapports', [PageController::class, 'rapports'], 'rapports');
Router::get('/api/reports/history', [ReportController::class, 'getReportHistory'], 'rapports');
```

Le troisième paramètre sera la politique de route. Les routes sans politique
resteront inchangées. Lors du `match`, le routeur retrouvera l’identifiant
technique dans `match['name']`, consultera son registre interne et appliquera la
garde avant d’instancier ou d’appeler le contrôleur.

Pour une route à politique :

- utilisateur non authentifié → le comportement d’authentification existant de
  la route est conservé ;
- utilisateur authentifié sans boutique, inconnu ou erreur SQL → fail-closed ;
- `super_admin` → jamais bloqué par cette politique ;
- boutique inactive → le contrôle existant de boutique désactivée reste
  prioritaire.

### 8.2 Réponse

Pour une route protégée dont l’utilisateur est bloqué :

- page web → code `403` + `app/views/403.php` ;
- route `/api/...` → code `403` + JSON :

```json
{
  "success": false,
  "error": "page_not_allowed",
  "message": "Cette page n'est pas accessible pour votre rôle."
}
```

La réponse doit être émise avant l’exécution du contrôleur afin qu’aucune
donnée métier ne soit chargée ou modifiée.

### 8.3 APIs exclusives et partagées

Une API est **exclusive** si elle ne sert qu’à une page configurable. Elle suit
la clé de cette page.

Une API est **partagée** si elle sert plusieurs pages configurables. Elle doit
alors distinguer deux cas :

#### Lecture partagée

Une lecture partagée est autorisée si **au moins une** page autorisée dépend
réellement de cette lecture. Exemples :

- `GET /api/produits` alimente la caisse, le scanner et le tableau de bord ;
- `GET /api/categories` alimente le catalogue et la caisse ;
- `/api/hotel/clients/search` alimente les réservations et les clients.

Elle ne doit donc pas être bloquée par la politique d’une seule page.

#### Écriture ou mutation partagée

Une mutation partagée ne doit jamais être autorisée par le simple fait qu’une
seule page autorisée utilise la route. Elle doit déterminer son **contexte
métier**, puis vérifier la politique de ce contexte.

Exemple principal :

- `POST /api/vente` est utilisé par la caisse et les recharges ;
- le discriminant métier existant `providerService` détermine le contexte
  `caisse` ou `recharges` ;
- la politique vérifiée est celle de la page réellement utilisée ;
- un utilisateur autorisé uniquement sur les recharges ne peut pas utiliser le
  contexte `caisse` pour contourner une restriction.

Si le contexte est absent ou ambigu, la mutation est refusée par défaut. Les
autres endpoints d’écriture partagés devront définir explicitement leur
résolveur de contexte.

Chaque route API configurable devra donc être classée explicitement comme :

- `exclusive` : une seule clé de politique ;
- `shared_read` : une liste de pages lectrices, avec règle « au moins une » ;
- `shared_write` : un résolveur de contexte qui choisit la page à vérifier.

Une route non classée ne doit pas être bloquée par erreur : le registre
centralisé rend cette classification visible et testable.

## 9. Interfaces utilisateur

### 9.1 Configuration générale depuis `/shops`

Chaque carte boutique de `/shops` disposera d’un bouton **« Gérer les pages »**.

Ce bouton ouvrira une page dédiée, par exemple :

```text
/shops/pages?shop_id=123
```

Elle ne sera accessible qu’au `super_admin` et affichera uniquement la famille
`general`.

### 9.2 Configuration Hôtel depuis `/hotel/acces-pages`

La nouvelle page :

```text
/hotel/acces-pages?shop_id=123
```

sera :

- visible dans le menu Hôtel uniquement pour le `super_admin` ;
- absente de la sidebar pour tous les autres rôles ;
- protégée côté serveur contre l’accès direct par un non-`super_admin` ;
- soumise au feature flag API `hotel`, exactement comme les autres pages Hôtel ;
- utilisée uniquement pour la famille `hotel`.

Si le flag API Hôtel est désactivé :

- le lien Hôtel n’est pas affiché ;
- l’accès direct à `/hotel/acces-pages` renvoie `403` ;
- aucune dérogation spéciale n’est accordée au `super_admin` pour cette page.

### 9.3 Matrice

La matrice aura deux colonnes d’audience :

| Page | Admins | Utilisateurs boutique |
|---|:---:|:---:|
| Tableau de bord Hôtel | ✓ | ✓ |
| Réception | ✓ | ☐ |

Convention :

- **case cochée** = page visible ;
- **case décochée** = page bloquée ;
- état initial = toutes cochées ;
- une seule boutique est modifiée à la fois ;
- le choix de boutique est explicite, comme dans `/hotel/roles` ;
- une confirmation avant sauvegarde est recommandée si une case vient d’être
  décochée.

La matrice doit être responsive : tableau sur desktop, cartes ou liste de
contrôles sur mobile, en suivant le pattern déjà présent dans
`app/views/hotel-roles.php`.

### 9.4 Feedback

Après sauvegarde :

- message de confirmation avec le nom de la boutique ;
- erreur claire si la boutique n’existe pas ;
- erreur claire si une clé de page est inconnue ;
- aucune confiance aveugle aux valeurs envoyées par le navigateur : le serveur
  valide audience, famille et clé de page.

---

## 10. Routes de configuration

### 10.1 Pages

| Méthode | Route | Accès |
|---|---|---|
| `GET` | `/shops/pages?shop_id={id}` | `super_admin` |
| `GET` | `/hotel/acces-pages?shop_id={id}` | `super_admin` + flag API `hotel` |

### 10.2 APIs de lecture et de sauvegarde

Les APIs seront séparées par famille afin que la gestion Hôtel reste dans le
module Hôtel :

| Méthode | Route | Usage |
|---|---|---|
| `GET` | `/api/page-policy?shop_id={id}` | Lire la famille `general` |
| `POST` | `/api/page-policy/update` | Mettre à jour la famille `general` |
| `GET` | `/api/hotel/page-access?shop_id={id}` | Lire la famille `hotel` |
| `POST` | `/api/hotel/page-access/update` | Mettre à jour la famille `hotel` |

Les deux endpoints de mise à jour utiliseront le même service et la même table.
Seule la famille acceptée différera.

### 10.3 Contrat de mise à jour

Payload conceptuel :

```json
{
  "shop_id": 123,
  "changes": {
    "admin": {
      "rapports": false,
      "paie": false
    },
    "shop_user": {
      "produits": false
    }
  }
}
```

Le serveur :

1. vérifie la session `super_admin` ;
2. valide l’existence de la boutique ;
3. valide que chaque clé appartient à la famille demandée ;
4. calcule les différences par rapport aux restrictions existantes ;
5. supprime les restrictions levées ;
6. crée les nouvelles restrictions ;
7. journalise le changement ;
8. retourne la carte de visibilité mise à jour.

Une transaction MySQL sera utilisée pour la suppression/création afin d’éviter un
état intermédiaire incohérent.

## 11. Mapping des pages et APIs

### 11.1 Pages

| Clé | Route principale |
|---|---|
| `caisse` | `/caisse` |
| `recharges` | `/recharges` |
| `produits` | `/produits` |
| `historique` | `/historique` |
| `pressing` | `/pressing` |
| `analytics` | `/analytics` |
| `paie` | `/payroll` et `/payroll/{view}` |
| `rapports` | `/rapports`, `/rapport-ticket` |
| `utilisateurs` | `/utilisateurs` |
| `categories` | `/categories` |
| `taxes` | `/taxes` |
| `parametres` | `/parametres` |
| `hotel_tableau_bord` | `/hotel/tableau-bord` |
| `hotel_calendrier` | `/hotel/calendrier` |
| `hotel_reception` | `/hotel/reception` |
| `hotel_etages` | `/hotel/etages` |
| `hotel_chambres` | `/hotel/chambres` |
| `hotel_reservations` | `/hotel/reservations` |
| `hotel_guests` | `/hotel/guests` |
| `hotel_facturation` | `/hotel/facturation` |
| `hotel_roles` | `/hotel/roles` |

### 11.2 APIs Hôtel exclusives

| API | Clé |
|---|---|
| `/api/hotel/dashboard` | `hotel_tableau_bord` |
| `/api/hotel/calendrier` | `hotel_calendrier` |
| `/api/hotel/reception/board` | `hotel_reception` |
| `/api/hotel/reservations/*`, check-in/out | `hotel_reservations` ou `hotel_reception` selon l’action |
| `/api/hotel/etages/board`, `/api/hotel/housekeeping/*` | `hotel_etages` |
| `/api/hotel/chambres/*` | `hotel_chambres` |
| `/api/hotel/guests/*` | `hotel_guests` |
| `/api/hotel/factures/*` | `hotel_facturation` |
| `/api/hotel/role/update` | `hotel_roles` |

### 11.3 APIs Hôtel partagées

- `/api/hotel/clients/search` sert les réservations et les clients ;
- `/api/hotel/sejours/en-cours` sert la facturation et les Extras de caisse ;
- `/api/hotel/sejours/{id}/extras-from-cart` sert le POS ;
- `/api/hotel/sejours/{id}/recu` est un document lié à la facturation.

Pour une API partagée, l’accès est autorisé si **au moins une** page autorisée
dépendant de cette API l’utilise. Cette règle doit être codée explicitement,
pas déduite implicitement.

### 11.4 APIs générales partagées

- `GET /api/produits` et `/api/produit` sont des lectures catalogue utilisées
  par la caisse, le scanner et le tableau de bord ;
- `POST /api/vente` est partagé entre la caisse et les recharges ;
- `GET /api/categories` est utilisé par le catalogue ;
- `GET /api/users` peut alimenter des listes partagées.

Les écritures de gestion (création, modification, suppression, import, export)
peuvent être exclusivement rattachées à leur page. Les lectures partagées
doivent rester disponibles tant qu’une page autorisée en dépend.

## 12. Intégration de la sidebar

### 12.1 Source unique

`PageController::render()` chargera une fois par page la carte de visibilité de la
boutique courante :

```php
$data['pageVisibility'] = $pagePolicyService->getVisibilityMap($shopId);
```

La sidebar consommera cette carte. Elle ne recalculera pas une règle différente
du backend.

Pour les points d’entrée qui n’appellent pas `PageController::render()`, la
sidebar conservera un repli fail-closed, comme le code actuel le fait déjà pour
les erreurs de `moduleAccess`.

### 12.2 Règles d’affichage

Une entrée est affichée seulement si :

```text
condition de rôle existante
ET feature flag existant
ET droit individuel existant
ET politique de page autorisée
```

Exemples :

- Caisse : `canCaisse` ET `pageVisibility['caisse']` ;
- Historique : `moduleAccess['historique']` ET
  `pageVisibility['historique']` ;
- Paie : flag `paie` ET `moduleAccess['paie']` ET
  `pageVisibility['paie']` ;
- Rapports : `pageVisibility['rapports']` ;
- Utilisateurs : rôle admin/super_admin ET `pageVisibility['utilisateurs']` ;
- Hôtel : feature flag + rôle Hôtel + sous-page autorisée par politique.

### 12.3 Menu Hôtel

Chaque sous-page Hôtel conservera sa permission métier actuelle et recevra une
condition de politique supplémentaire :

- Tableau de bord : `hotel_tableau_bord` ;
- Calendrier : `hotel_calendrier` ;
- Réservations : `hotel_reservations` ;
- Réception : `hotel_reception` ;
- Étages : `hotel_etages` ;
- Chambres : `hotel_chambres` ;
- Clients : `hotel_guests` ;
- Facturation : `hotel_facturation` ;
- Rôles : `hotel_roles`.

Le lien `/hotel/acces-pages` sera ajouté dans le menu Hôtel uniquement si :

- le rôle de session est `super_admin` ;
- le module Hôtel est visible ;
- le feature flag `hotel` est actif.

Il ne sera jamais soumis à une restriction de page.

### 12.4 Super admin

Le `super_admin` n’étant rattaché à aucune boutique, la sidebar ne peut pas
dépendre d’une politique de boutique pour ses propres accès. Il conserve ses
liens actuels. La page `/hotel/acces-pages` lui permet de choisir la boutique
dont il souhaite modifier la politique.

---

## 13. Règles de sécurité et cas limites

### 13.1 Invariants obligatoires

1. Une politique ne peut jamais **accorder** un droit absent ailleurs.
2. Une politique peut toujours **retirer** un droit normalement accordé.
3. Les droits individuels `utilisateurs.acces_*` restent inchangés.
4. Les rôles, feature flags et contrôles de tenant ne sont jamais remplacés.
5. Le `super_admin` n’est jamais bloqué par cette politique.
6. Les restrictions sont strictement isolées par `shop_id`.
7. La sidebar et le backend utilisent la même source de vérité.
8. Une lecture partagée est bloquée seulement si aucune page lectrice autorisée
   n’en dépend ; une mutation partagée valide toujours le contexte métier réel.
9. Une erreur de lecture de la politique est fail-closed.
10. `/hotel/acces-pages` suit le flag API Hôtel sans exception.

### 13.2 Tenant et session

- Les APIs de configuration sont strictement réservées au `super_admin` : un
  `admin` ne peut pas modifier la politique, même pour sa propre boutique.
- Le `super_admin` doit fournir un `shop_id` valide à la configuration.
- La boutique ciblée doit exister et être lisible avant toute écriture.
- Un `shop_id` absent, nul, non numérique ou inexistant est refusé.

### 13.3 Changement de rôle

Si le rôle d’un utilisateur change, sa nouvelle audience est prise en compte dès
la requête suivante :

- ancien `vendeur`, nouveau `admin` → audience `admin` ;
- ancien `admin`, nouveau `vendeur` → audience `shop_user`.

### 13.4 Page déjà ouverte

Une page déjà chargée peut rester visible dans le navigateur, mais toute action
qui déclenche une requête vers une page/API bloquée reçoit `403`. La sidebar
sera recalculée à la prochaine navigation.

### 13.5 Erreurs de base

- Table de restrictions absente avant la migration : fail-closed pour les
  routes protégées ; la migration doit donc être appliquée avant le code.
- Page inconnue dans le catalogue : refus.
- Audience inconnue : refus.
- Restriction orpheline impossible grâce aux clés étrangères.

### 13.6 Idempotence

Le service de sauvegarde doit pouvoir être appelé deux fois avec le même
payload sans créer de doublon ni lever d’erreur. La contrainte unique et le
`INSERT ... ON DUPLICATE KEY UPDATE` ou une suppression suivie d’une insertion
transactionnelle sont acceptables.

## 14. Stratégie de tests

### 14.1 Tests unitaires du service

- absence de restriction → page visible ;
- restriction `admin` → l’admin est bloqué ;
- restriction `admin` → le vendeur de la même boutique est autorisé ;
- restriction `shop_user` → le vendeur est bloqué ;
- restriction `shop_user` → l’admin de la même boutique est autorisé ;
- le `super_admin` n’est jamais bloqué ;
- utilisateur inexistant → fail-closed ;
- utilisateur sans boutique → fail-closed ;
- page inconnue → fail-closed ;
- audience inconnue → fail-closed ;
- erreur SQL → fail-closed ;
- restrictions d’une autre boutique → ignorées ;
- sauvegarde idempotente ;
- levée d’une restriction → suppression de la ligne ;
- ajout d’une restriction → création de la ligne ;
- famille `general` refusée sur l’API Hôtel et inversement.

### 14.2 Tests d’intégration du modèle et de la persistance

- la table accepte les deux audiences ;
- la contrainte unique empêche les doublons ;
- la FK supprime les restrictions avec la boutique ;
- la FK conserve la restriction avec `created_by = NULL` si le
  `super_admin` auteur est supprimé ;
- les valeurs par défaut visibles ne nécessitent aucune ligne initiale ;
- `audit_logs` reçoit les changements de politique.

### 14.3 Tests d’intégration des routes

- `/shops/pages` refuse un non-`super_admin` ;
- `/hotel/acces-pages` refuse un `admin` et un vendeur ;
- `/hotel/acces-pages` refuse l’accès si le flag API Hôtel est désactivé ;
- une page bloquée renvoie `403` en accès direct ;
- une API exclusive bloquée renvoie `403` et un JSON valide ;
- une API partagée reste disponible si une page autorisée en dépend ;
- une route sans politique continue de fonctionner normalement ;
- la réponse est émise avant le chargement métier du contrôleur ;
- une restriction est appliquée sans nouvelle connexion.

### 14.4 Tests de la sidebar

- un lien bloqué n’est pas rendu ;
- une page autorisée reste rendue ;
- les contrôles de rôle, feature flag et `acces_*` sont toujours cumulés ;
- `/hotel/acces-pages` apparaît uniquement pour le `super_admin` ;
- `/hotel/acces-pages` disparaît si le flag API Hôtel est désactivé.

### 14.5 Tests Playwright

Scénario minimal :

1. le `super_admin` ouvre `/hotel/acces-pages` ;
2. il choisit une boutique ;
3. il décoche Réception pour les utilisateurs de boutique ;
4. il sauvegarde ;
5. le vendeur se connecte et ne voit plus Réception dans le menu Hôtel ;
6. l’accès direct à `/hotel/reception` renvoie `403` ;
7. l’API correspondante renvoie `403 JSON` ;
8. l’admin n’est pas bloqué par cette restriction ;
9. le `super_admin` reste omniscient.

Un scénario complémentaire vérifiera le bloc d’une page générale depuis
`/shops/pages` et son effet sur un admin de la boutique.

### 14.6 Régression des droits existants

Les tests existants doivent continuer à passer :

- `tests/Unit/CaisseAccessTest.php` ;
- `tests/Integration/CaisseAccessConfigTest.php` ;
- `tests/Integration/HotelRolesScopeTest.php` ;
- tests Hotel, DGI, caisse et brouillons existants.

La nouvelle politique ne doit modifier ni les fixtures ni le comportement
attendu de `User::canAccessModule()`.

## 15. Critères d’acceptation

La feature est acceptée si :

1. le `super_admin` peut gérer les pages générales depuis `/shops` ;
2. le `super_admin` peut gérer les pages Hôtel depuis `/hotel/acces-pages` ;
3. `/hotel/acces-pages` est invisible et inaccessible aux autres rôles ;
4. `/hotel/acces-pages` respecte le feature flag API Hôtel ;
5. une restriction s’applique par boutique et par audience ;
6. une restriction masque le menu ;
7. une restriction bloque l’accès direct à la page ;
8. une restriction bloque les APIs exclusives ;
9. une API partagée n’est pas bloquée inutilement ;
10. les contrôles existants restent cumulés et prioritaires ;
11. le `super_admin` n’est jamais bloqué ;
12. les changements prennent effet immédiatement ;
13. les changements sont audités ;
14. le déploiement sans configuration ne modifie aucun accès existant ;
15. les tests unitaires, d’intégration et Playwright passent.

---

## 16. Déploiement et rollback

### 16.1 Ordre de déploiement

1. appliquer la migration `shop_page_restrictions` ;
2. vérifier les contraintes et index ;
3. déployer le service, les APIs et la garde ;
4. déployer les vues et la sidebar ;
5. exécuter les tests ;
6. utiliser les matrices pour créer les premières restrictions.

La migration doit précéder le code qui applique la politique, car la garde est
fail-closed.

### 16.2 Rollback

Le rollback applicatif consiste à revenir à la version précédente du code. La
table peut rester en place : son absence de lignes signifie « aucune
restriction », ce qui est compatible avec l’ancien code.

Si la politique doit être neutralisée sans rollback :

- supprimer toutes les lignes de `shop_page_restrictions` ;
- ou désactiver la garde pour la famille concernée via le code déployé.

### 16.3 Surveillance

Événements à surveiller après déploiement :

- erreurs SQL sur `shop_page_restrictions` ;
- réponses `403 page_not_allowed` inattendues ;
- erreurs d’audit de politique ;
- divergences constatées entre menu et accès direct.

---

## 17. Fichiers probablement impactés

La liste définitive sera précisée dans le plan d’implémentation.

### Backend et données

- `migrations/2026_11_18_shop_page_restrictions.sql` (nouveau) ;
- `app/models/ShopPageRestriction.php` (nouveau) ;
- `app/Services/PagePolicyService.php` (nouveau) ;
- `app/core/Router.php` ;
- `app/controllers/Controller.php` ;
- `app/controllers/PageController.php` ;
- éventuellement `app/controllers/ShopController.php` ou un contrôleur dédié ;
- `routes/web.php` ;
- `routes/api.php`.

### Frontend

- `app/views/layout/header.php` ;
- `app/views/shops.php` ;
- `app/views/shops-pages.php` (nouveau) ;
- `app/views/hotel-acces-pages.php` (nouveau) ;
- JavaScript dédié dans `public/assets/js/` si la matrice dépasse le code
  inline déjà utilisé dans les vues.

### Tests et documentation

- tests unitaires et d’intégration dédiés ;
- scénario Playwright ;
- `ROUTES.md` ;
- `AGENT.md` ;
- éventuellement `docs/PLAN_PAGE_ACCESS_POLICY.md` pour le détail
  d’implémentation.

---

## 18. Hypothèses et décisions à confirmer avant implémentation

Ces points ne remettent pas en cause l’architecture, mais devront être
tranchés dans le plan d’implémentation :

1. **Réponses existantes des pages POS** : lorsqu’un droit `acces_*` manque,
   le projet redirige vers `/dashboard` ou la page Hôtel du rôle. Pour une
   restriction `super_admin`, le design validé prévoit un `403`. Cette
   distinction devra rester explicite pour ne pas modifier le comportement
   existant des droits individuels.
2. **Rapports** : la page `/rapports` est visible pour tous les rôles, mais ses
   APIs exigent souvent `admin`. Une restriction `shop_user` sera réellement
   utile si la page elle-même est retirée de la sidebar et bloquée en direct.
3. **Répertoire et routes** : `/shops/pages` est proposé pour la famille
   générale ; un autre nom peut être choisi, mais `/hotel/acces-pages` est fixé.
4. **Réponse HTTP pour les APIs Hôtel** : les contrôleurs Hôtel utilisent
   plusieurs formats JSON légèrement différents. La garde centrale imposera un
   contrat uniforme `success`, `error`, `message` pour les refus de politique.
5. **Super admin sans boutique** : il ne peut jamais être bloqué et n’a pas
   d’audience ; les restrictions sont toujours configurées pour une boutique
   explicite.

