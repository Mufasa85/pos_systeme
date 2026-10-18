# Plan d'implémentation : Système OAuth 2.0 pour Facturation Externe (POS ↔ Apps Tierces)

> **Projet** : POS Système (PHP/MySQL/PDO, multi-boutique)
> **Auteur** : Équipe ArcaneCore
> **Date** : 2026
> **Statut** : 📋 Spécification fonctionnelle & technique — À valider
> **Version** : 1.0

---

## Table des matières

1. [Contexte & objectifs](#1-contexte--objectifs)
2. [Cas d'usage principal](#2-cas-dusage-principal)
3. [Architecture cible](#3-architecture-cible)
4. [Flow complet illustratif](#4-flow-complet-illustratif)
5. [Sécurité & standards](#5-sécurité--standards)
6. [Modèle de données](#6-modèle-de-données)
7. [API, contrats & routeurs](#7-api-contrats--routeurs)
8. [Contrôleurs & logique métier](#8-contrôleurs--logique-métier)
9. [Vues (UI/UX)](#9-vues-uiux)
10. [Roadmap en phases](#10-roadmap-en-phases)
11. [Critères d'acceptation](#11-critères-dacceptation)
12. [Annexes : exemples de payloads](#12-annexes--exemples-de-payloads)

---

## 1. Contexte & objectifs

### 1.1 Problème métier

Le POS système génère et valide des factures (DGI RDC, numéro officiel, QR code, etc.) pour un **magasin** (ou plusieurs boutiques en multi-tenant). Aujourd'hui, ces fonctionnalités ne sont accessibles que depuis l'UI web du POS.

**Cas concret** : un **hôtel** veut générer des factures pour ses clients (chambres, restaurant, room service). L'hôtel dispose déjà de son propre **système de gestion interne** (PMS, ERP, app custom). On ne veut **pas dupliquer** toute la logique du POS dans l'app hôtel. On veut que :

1. L'hôtel reste **maître de son UX** (son PMS gère les séjours, les chambres, les prix…).
2. Quand un client demande sa facture, l'app hôtel **délègue** la génération au POS (qui gère la conformité fiscale DGI, le QR, la numérotation officielle, l'archivage légal, etc.).
3. Le caissier POS (ou l'utilisateur autorisé de l'hôtel via le POS) **finalise** la facture dans le POS.
4. La facture finale, officielle, conforme DGI, revient à l'app hôtel.

### 1.2 Objectifs

- ✅ Permettre à une **app tierce enregistrée** de demander au POS de facturer un ensemble d'articles pré-définis.
- ✅ Respecter un **flow OAuth 2.0** (Authorization Code) afin que :
  - L'app tierce soit **authentifiée** (`client_id` + `client_secret` + signature HMAC des items).
  - L'utilisateur humain (caissier POS) **donne son consentement** avant toute action.
  - Le caissier ait le **contrôle final** sur la facture (modification, ajout, paiement).
- ✅ **Ne pas casser** les fonctionnalités POS existantes.
- ✅ **Réutiliser** au maximum l'existant : `SaleController`, `InvoiceController`, page `/caisse`, table `ventes`, `details_vente`, etc.
- ✅ Être **compatible multi-boutique** (chaque app tierce est liée à un `shop_id`).
- ✅ Offrir une **Developer Console** (UI admin) pour gérer les apps tierces.

### 1.3 Non-objectifs

- ❌ Ce n'est **pas** un OAuth "Sign in with Google" pour des consommateurs finaux.
- ❌ Ce n'est **pas** un système où le POS se connecte à des APIs externes (c'est l'inverse : on expose nos APIs).
- ❌ Pas de gestion de **refresh tokens tiers** : les tokens sont courts (1h) et renouvelables simplement par un nouvel appel à `/oauth/authorize`.
- ❌ Pas d'OpenID Connect / identité utilisateur (l'app hôtel agit *via* un humain, mais ne récupère pas l'identité du caissier).

---

## 2. Cas d'usage principal

### Acteurs

| Acteur | Rôle |
|---|---|
| **Caissier POS** (utilisateur authentifié dans le POS) | Autorise la facture, la finalise, encaisse le paiement. |
| **App Hôtel** (client OAuth tiers) | Déclenche la demande de facturation depuis son interface. |
| **Client final** (voyageur de l'hôtel) | Bénéficiaire de la facture, reçoit le PDF/URL. |

### Scénario nominal

1. Le client final règle son séjour à la réception de l'hôtel.
2. Le réceptionniste clique sur **"Générer la facture officielle"** dans l'app hôtel (PMS).
3. L'app hôtel prépare un payload d'articles (chambre, petit-déjeuner, room service…) et un **callback URL** (ex: `https://hotel.com/callbacks/invoice`).
4. L'app hôtel ouvre une **fenêtre** (popup) vers :
   ```
   https://pos.votre-domaine.com/oauth/authorize?
       client_id=pos_app_hotel_xyz&
       redirect_uri=https%3A%2F%2Fhotel.com%2Fcallbacks%2Finvoice&
       items=<items_signés_HMAC>
       &state=<csrf_token>
       &response_type=code
   ```
5. Si le caissier n'est pas logué → redirection vers `/login` (page POS), puis retour automatique à `/oauth/authorize`.
6. Le POS affiche un **consent screen** (style "Google OAuth") avec le total, la liste des articles et les boutons Autoriser / Refuser.
7. Caissier clique **Autoriser** :
   - Le POS génère un `authorization_code` (10 min, hashé en DB).
   - Le POS redirige la popup vers `redirect_uri?code=AUTH_xxx&state=csrf_xyz`.
8. L'app hôtel, en back-office (PHP, Python, Node, etc.) :
   - Vérifie le `state` (anti-CSRF).
   - Fait `POST /oauth/token` avec `code`, `client_id`, `client_secret` → reçoit `access_token` + `expires_in`.
9. L'app hôtel fait `POST /api/v1/external/sales/preview` avec le Bearer token → le POS ouvre une **deuxième popup** (ou redirect) vers `/caisse/external?preview_id=...` avec le panier pré-rempli.
10. Le caissier **voit le panier pré-rempli**, peut ajouter des extras, du client final, choisit le mode de paiement, et clique **Encaisser**.
11. Le POS crée la vente (insert dans `ventes` + `details_vente`), génère le numéro officiel, contacte la DGI, génère le QR code, puis redirige la popup vers :
    ```
    https://hotel.com/callbacks/invoice?sale_id=12345&status=success&signature=HMAC_xxx
    ```
12. L'app hôtel vérifie la signature HMAC du callback, affiche "✓ Facture #12345 officielle", et ferme la popup.

### Variante "1 seule fenêtre" (auto-confirm)

Pour les apps qui ne veulent pas s'embêter avec les 2 popups, on propose un mode "**auto-confirm**" :

- Le caissier clique **Autoriser** sur le consent screen → la facture se crée **automatiquement** (sans passage par `/caisse/external`).
- Le POS redirige directement vers `redirect_uri?sale_id=...&signature=...`.
- ⚠️ **Limitation** : pas d'ajout d'extras, pas de choix de mode de paiement (forcé à `external`).

L'app hôtel choisit ce mode via le paramètre `mode=auto_confirm` dans `/oauth/authorize`.

---

## 3. Architecture cible

```
┌─────────────────────┐                  ┌─────────────────────────┐
│  App Hôtel (tiers)  │                  │  Votre POS Système      │
│  (PMS / ERP / web)  │                  │  (PHP/MySQL/PDO)        │
└──────────┬──────────┘                  └──────────┬──────────────┘
           │                                       │
           │  Popup 1 (consent)                   │
           │  ──────────────────────────────────►  │
           │                          1. Vérif client_id
           │                          2. Vérif signature HMAC items
           │                          3. Login (si nécessaire)
           │                          4. Affiche consent screen
           │  ◄─────── Réponse user ────│
           │  Redirect + code          │
           │  ◄─────────────────────────│
           │                                       │
           │  Back-office : POST /oauth/token      │
           │  ──────────────────────────────────►  │
           │                          5. Vérif code (one-time)
           │                          6. Génère access_token
           │  ◄─────────────────────────│
           │                                       │
           │  Popup 2 (caisse) ou auto-confirm     │
           │  ──────────────────────────────────►  │
           │                          7. /caisse/external :
           │                              a. Vérif token
           │                              b. Panier pré-rempli
           │                              c. User ajoute extras
           │                              d. Click "Encaisser"
           │                              e. INSERT ventes + details_vente
           │                              f. DGI validation
           │                              g. Génère PDF / QR
           │  ◄─────────────────────────│
           │  Redirect vers app hôtel  │
           │  + sale_id + HMAC         │
           │                                       │
           │  GET /facture/:id/pdf     │  (optionnel : app hôtel récupère le PDF)
           │  ──────────────────────────────────►  │
           │                          9. Stream PDF
           │  ◄─────────────────────────│
```

### Composants à créer

| Composant | Type | Rôle |
|---|---|---|
| `oauth_clients` | Table MySQL | Apps tierces enregistrées |
| `oauth_authorization_codes` | Table MySQL | Codes d'autorisation temporaires (10 min) |
| `oauth_access_tokens` | Table MySQL | Tokens d'accès Bearer (1h) |
| `oauth_clients_access_log` | Table MySQL | Audit trail (qui a fait quoi, IP, timestamp) |
| `OAuthController` | Controller | `/oauth/authorize`, `/oauth/token`, etc. |
| `ExternalSaleController` | Controller | API REST pour apps tierces |
| `ExternalCaisseController` | Controller | Page `/caisse/external` avec panier pré-rempli |
| `OAuthMiddleware` | Middleware | Vérif Bearer token + injection `shop_id` |
| `ExternalSignature` | Service | Vérif signature HMAC des items et du callback |
| `OAuthClient` | Model | CRUD clients |
| `OAuthAuthorizationCode` | Model | CRUD codes |
| `OAuthAccessToken` | Model | CRUD tokens |
| `oauth/authorize.php` | Vue | **Consent screen** |
| `oauth/apps.php` | Vue | **Developer Console** |
| `caisse-external.php` | Vue | Variante `/caisse` avec panier pré-rempli |

---

## 4. Flow complet illustratif

#### Séquence complète (mode 2 fenêtres)

```
[App Hôtel]                            [POS Système]                        [Caissier]
     │                                       │                                  │
     │  1. Clic "Générer facture"            │                                  │
     │  Prépare items + HMAC signature       │                                  │
     │                                       │                                  │
     │  2. Popup → /oauth/authorize          │                                  │
     │     ?client_id=...                    │                                  │
     │     &items=<base64>                   │                                  │
     │     &items_signature=<HMAC>           │                                  │
     │     &redirect_uri=...                 │                                  │
     │     &state=<csrf>                     │                                  │
     │──────────────────────────────────────►│                                  │
     │                                       │                                  │
     │                                       │  3. Vérif client_id              │
     │                                       │  4. Vérif items_signature       │
     │                                       │  5. Check user logué ?          │
     │                                       │                                  │
     │                                       │  6. Si non logué → /login       │
     │                                       │─────────────────────────────────►│
     │                                       │                                  │
     │                                       │◄─────────────────────────────────│
     │                                       │   (login OK, retour à /oauth)   │
     │                                       │                                  │
     │                                       │  7. Affiche consent screen      │
     │                                       │─────────────────────────────────►│
     │                                       │   [Autoriser] [Refuser]         │
     │                                       │                                  │
     │                                       │◄─────────────────────────────────│
     │                                       │   decision=allow                 │
     │                                       │                                  │
     │  8. Redirect popup vers               │  9. INSERT oauth_auth_codes     │
     │     redirect_uri?code=xxx&state=...   │     (code = SHA-256(raw_code))   │
     │◄──────────────────────────────────────│                                  │
     │                                       │                                  │
     │  10. Back-office (PHP/Python/Node)    │                                  │
     │      POST /oauth/token                │                                  │
     │──────────────────────────────────────►│                                  │
     │                                       │                                  │
     │                                       │  11. Vérif code (one-time)      │
     │                                       │  12. Génère access_token        │
     │  ◄────────────────────────────────────│                                  │
     │      {access_token, expires_in:3600}  │                                  │
     │                                       │                                  │
     │  13. Popup 2 → /caisse/external       │                                  │
     │      ?preview_id=...                  │                                  │
     │──────────────────────────────────────►│                                  │
     │                                       │                                  │
     │                                       │  14. Vérif preview_id            │
     │                                       │  15. Charge items pré-remplis   │
     │                                       │─────────────────────────────────►│
     │                                       │   Affiche /caisse avec panier   │
     │                                       │                                  │
     │                                       │  16. Caissier modifie + valide  │
     │                                       │◄─────────────────────────────────│
     │                                       │   POST /caisse/external/checkout│
     │                                       │                                  │
     │                                       │  17. INSERT ventes              │
     │                                       │  18. INSERT details_vente       │
     │                                       │  19. DGI validation (si actif)  │
     │                                       │  20. Génère QR + PDF            │
     │                                       │                                  │
     │  ◄────────────────────────────────────│                                  │
     │  Redirect vers redirect_uri           │                                  │
     │  ?sale_id=12345                       │                                  │
     │  &status=success                      │                                  │
     │  &signature=HMAC                      │                                  │
     │                                       │                                  │
     │  21. Vérif signature, affiche "OK"   │                                  │
     │  Ferme popup                           │                                  │
```

---

## 5. Sécurité & standards

### 5.1 Standards OAuth 2.0 utilisés

| Mécanisme | Utilisation |
|---|---|
| **Authorization Code Grant** (RFC 6749 §4.1) | ✅ Principal flow |
| **PKCE** (RFC 7636) | ✅ Recommandé pour clients publics (JS) |
| **Client Credentials Grant** | ❌ Pas nécessaire (l'app hôtel agit *via* un humain) |
| **Refresh Token** | ❌ Pas nécessaire (token court + nouveau `code`) |
| **Token Revocation** (RFC 7009) | ✅ Endpoint `/oauth/revoke` |

### 5.2 Checklist sécurité

| Risque | Mitigation | Priorité |
|---|---|---|
| Vol de `client_secret` | Stocké **hashé bcrypt** (coût 12), affiché **une seule fois** à la création | 🔴 Critique |
| Falsification des items | **HMAC-SHA256** obligatoire sur le payload `items` avec secret distinct (`items_secret`) | 🔴 Critique |
| Open redirect | **Whitelist stricte** des `redirect_uri` enregistrée à la création, comparaison stricte | 🔴 Critique |
| CSRF | `state` parameter (aléatoire 32 chars) vérifié au retour | 🔴 Critique |
| Code rejoué | `authorization_code` **one-time**, expire 10 min, hashé en DB | 🔴 Critique |
| Token volé | **HTTPS obligatoire** (HSTS), tokens hashés en DB (`SHA-256`), jamais loggés en clair | 🔴 Critique |
| Brute-force sur `/oauth/token` | Rate-limit (20 req/min par IP), log d'échecs, alerte au-delà de 10 échecs | 🟠 Important |
| CORS abuse | Whitelist des origines par client, validation sur chaque requête | 🟠 Important |
| Clickjacking | Header `X-Frame-Options: DENY` sur `/oauth/authorize` | 🟠 Important |
| XSS sur items | Échappement HTML systématique dans le consent screen | 🟠 Important |
| Timing attack sur secret | `password_verify()` (constant-time), `hash_equals()` pour HMAC | 🟠 Important |
| Audit | Table `oauth_clients_access_log` : client_id, endpoint, IP, user_agent, timestamp | 🟢 Recommandé |

### 5.3 Signature HMAC des items

#### Format

```json
[
  {
    "name": "Chambre 101",
    "price": 80,
    "quantity": 1,
    "tax_rate": 16
  },
  {
    "name": "Petit-déjeuner",
    "price": 15,
    "quantity": 1,
    "tax_rate": 16
  }
]
```

`items_canonical` = `JSON.stringify(items)` trié par clés alphabétiques.
`items_signature` = `base64(HMAC-SHA256(items_secret, items_canonical))`.

#### Vérification côté POS

```php
$expected = base64_encode(hash_hmac('sha256', $canonicalItems, $items_secret, true));
if (!hash_equals($expected, $receivedSignature)) {
    http_response_code(400);
    echo json_encode(['error' => 'invalid_signature']);
    exit;
}
```

### 5.4 Signature HMAC du callback (POS → App Hôtel)

Quand le POS redirige vers `redirect_uri?sale_id=...`, il ajoute une signature HMAC :

```php
// POS
$callbackParams = [
    'sale_id'        => $saleId,
    'status'         => 'success',
    'invoice_number' => $numeroFacture,
    'total'          => $total,
    'timestamp'      => time(),
];
$signature = base64_encode(hash_hmac('sha256',
    $callbackParams['callback_secret'], // ≠ client_secret, secret distinct
    http_build_query($callbackParams)
));
$callbackParams['signature'] = $signature;
$redirectUrl = $client->redirect_uri . '?' . http_build_query($callbackParams);
```

Côté app hôtel (Python) :

```python
import hmac, hashlib
expected = hmac.new(callback_secret.encode(), query_string.encode(), hashlib.sha256).digest()
if not hmac.compare_digest(expected, received_signature):
    raise SecurityError("Invalid HMAC")
```

---

## 6. Modèle de données

### 6.1 Table `oauth_clients`

```sql
CREATE TABLE oauth_clients (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    client_id           VARCHAR(64)  NOT NULL UNIQUE,
    client_secret_hash  VARCHAR(255) NOT NULL,
    items_secret_hash   VARCHAR(255) NOT NULL,
    callback_secret_hash VARCHAR(255) NOT NULL,
    name                VARCHAR(120) NOT NULL,
    description         TEXT,
    logo_url            VARCHAR(255),
    contact_email       VARCHAR(160) NOT NULL,
    redirect_uris       JSON         NOT NULL,
    allowed_origins     JSON         NOT NULL,
    scopes              JSON         NOT NULL,
    shop_id             INT          NOT NULL,
    is_active           TINYINT(1)   DEFAULT 1,
    created_by          INT          NOT NULL,
    created_at          DATETIME     DEFAULT CURRENT_TIMESTAMP,
    updated_at          DATETIME     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    last_used_at        DATETIME     NULL,
    INDEX idx_client_id (client_id),
    INDEX idx_shop (shop_id),
    FOREIGN KEY (shop_id) REFERENCES shops(id),
    FOREIGN KEY (created_by) REFERENCES utilisateurs(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

**Notes** :
- Les secrets (`client_secret`, `items_secret`, `callback_secret`) sont **hashés bcrypt** (coût 12) → affichés **une seule fois** à la création, en clair. Pour les utiliser, on régénère un nouveau secret à chaque rotation (pas de vérification "récupérer le secret hashé").
- En pratique, on stocke aussi une **copie chiffrée** des secrets (`AES-256-GCM`) pour pouvoir les vérifier lors d'appels API. Sinon on est obligé de re-signer tous les payloads à la main.
- `redirect_uris` : array JSON des URLs autorisées (whitelist stricte).
- `allowed_origins` : array JSON des origines CORS autorisées (sans `/` final).

### 6.2 Table `oauth_authorization_codes`

```sql
CREATE TABLE oauth_authorization_codes (
    code_hash     CHAR(64)      NOT NULL PRIMARY KEY,
    client_id     VARCHAR(64)   NOT NULL,
    user_id       INT           NOT NULL,
    shop_id       INT           NOT NULL,
    redirect_uri  VARCHAR(255)  NOT NULL,
    scopes        JSON          NOT NULL,
    items_payload JSON          NULL,
    state         VARCHAR(64)   NOT NULL,
    expires_at    DATETIME      NOT NULL,
    used_at       DATETIME      NULL,
    created_at    DATETIME      DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_client (client_id),
    INDEX idx_expires (expires_at),
    FOREIGN KEY (client_id) REFERENCES oauth_clients(client_id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES utilisateurs(id),
    FOREIGN KEY (shop_id) REFERENCES shops(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

**Notes** :
- `code_hash` = SHA-256 du code en clair (code brut jamais stocké).
- `used_at` = NULL tant qu'il n'a pas été échangé (one-time).
- `expires_at` = `created_at + 10 minutes`.

### 6.3 Table `oauth_access_tokens`

```sql
CREATE TABLE oauth_access_tokens (
    id                    INT AUTO_INCREMENT PRIMARY KEY,
    token_hash            CHAR(64)      NOT NULL UNIQUE,
    client_id             VARCHAR(64)   NOT NULL,
    user_id               INT           NOT NULL,
    shop_id               INT           NOT NULL,
    authorization_code_id INT           NULL,
    scopes                JSON          NOT NULL,
    expires_at            DATETIME      NOT NULL,
    revoked_at            DATETIME      NULL,
    created_at            DATETIME      DEFAULT CURRENT_TIMESTAMP,
    last_used_at          DATETIME      NULL,
    ip_address            VARCHAR(45),
    user_agent            VARCHAR(255),
    INDEX idx_token (token_hash),
    INDEX idx_client (client_id),
    INDEX idx_expires (expires_at),
    FOREIGN KEY (client_id) REFERENCES oauth_clients(client_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

**Notes** :
- `token_hash` = SHA-256 du token en clair.
- `expires_at` = `created_at + 1 heure`.
- `revoked_at` = NULL tant que le token est valide.

### 6.4 Table `oauth_clients_access_log` (audit)

```sql
CREATE TABLE oauth_clients_access_log (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    client_id       VARCHAR(64)  NULL,
    user_id         INT          NULL,
    endpoint        VARCHAR(120) NOT NULL,
    method          VARCHAR(10)  NOT NULL,
    ip_address      VARCHAR(45),
    user_agent      VARCHAR(255),
    request_body    TEXT         NULL,
    response_status INT          NULL,
    error_message   VARCHAR(255) NULL,
    created_at      DATETIME     DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_client (client_id),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

---

## 7. API, contrats & routeurs

### 7.1 Endpoints publics (sans authentification)

| Méthode | URL | Description |
|---|---|---|
| `GET` | `/oauth/authorize` | Affiche le consent screen (style Google). |
| `POST` | `/oauth/authorize/decision` | Caissier clique Autoriser/Refuser. |
| `POST` | `/oauth/token` | Échange `authorization_code` contre `access_token`. |
| `POST` | `/oauth/revoke` | Révoque un `access_token`. |
| `GET` | `/.well-known/oauth-authorization-server` | Métadonnées OAuth (RFC 8414) — discovery. |

### 7.2 Endpoints protégés (Bearer token)

| Méthode | URL | Scope requis | Description |
|---|---|---|---|
| `POST` | `/api/v1/external/sales/preview` | `invoices:write` | Génère un `preview_id` pour la caisse externe. |
| `POST` | `/api/v1/external/sales/confirm` | `invoices:write` | Crée la vente finale (mode auto-confirm). |
| `GET` | `/api/v1/external/sales/:id` | `invoices:read` | Détails d'une vente externe. |
| `GET` | `/api/v1/external/sales/:id/pdf` | `invoices:read` | Télécharge le PDF officiel. |
| `GET` | `/api/v1/external/products/search?q=...` | `products:read` | Recherche produit par nom/code-barres. |

### 7.3 Routes à ajouter (`routes/api.php`)

```php
// ── OAuth 2.0 ────────────────────────────────────────────
Router::get('/oauth/authorize',                  [OAuthController::class, 'authorize']);
Router::post('/oauth/authorize/decision',        [OAuthController::class, 'authorizeDecision']);
Router::post('/oauth/token',                     [OAuthController::class, 'token']);
Router::post('/oauth/revoke',                    [OAuthController::class, 'revoke']);
Router::get('/.well-known/oauth-authorization-server', [OAuthController::class, 'metadata']);

// ── API externe (Bearer token requis) ────────────────────
Router::post('/api/v1/external/sales/preview',   [ExternalSaleController::class, 'preview']);
Router::post('/api/v1/external/sales/confirm',   [ExternalSaleController::class, 'confirm']);
Router::get('/api/v1/external/sales/[i:id]',     [ExternalSaleController::class, 'get']);
Router::get('/api/v1/external/sales/[i:id]/pdf', [ExternalSaleController::class, 'pdf']);
Router::get('/api/v1/external/products/search',  [ExternalSaleController::class, 'searchProducts']);

// ── Caisse externe (page HTML) ─────────────────────────────
Router::get('/caisse/external',                  [ExternalCaisseController::class, 'show']);
Router::post('/caisse/external/checkout',        [ExternalCaisseController::class, 'checkout']);
```

### 7.4 Headers CORS

Sur les endpoints `/oauth/*` et `/api/v1/external/*` :

```php
header('Access-Control-Allow-Origin: ' . $client->allowed_origin); // dynamique, pas ce CORS *
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Authorization, Content-Type');
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Max-Age: 86400');

// Gérer OPTIONS (preflight)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}
```

---

## 8. Contrôleurs & logique métier

### 8.1 `app/controllers/OAuthController.php`

#### Méthode `authorize()`

```php
public function authorize()
{
    $params = $this->validateAuthorizeParams();
    // - client_id existe + actif
    // - redirect_uri est dans la whitelist
    // - signature HMAC des items est valide
    // - state est un csrf token (32+ chars)

    // Si user non logué → /login?next=/oauth/authorize?...
    if (!isset($_SESSION['user_id'])) {
        $next = '/oauth/authorize?' . http_build_query($_GET);
        header('Location: ' . $next);
        exit;
    }

    // Affiche la vue oauth/authorize.php
    $client = (new OAuthClient())->findByClientId($params['client_id']);
    require __DIR__ . '/../views/oauth/authorize.php';
}
```

#### Méthode `authorizeDecision()`

```php
public function authorizeDecision()
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        return;
    }

    $decision = $_POST['decision'] ?? '';
    if ($decision !== 'allow' && $decision !== 'deny') {
        http_response_code(400);
        return;
    }

    $params = $this->validateAuthorizeParams();

    if ($decision === 'deny') {
        $redirect = $params['redirect_uri'] . '?error=access_denied&state=' . $params['state'];
        header('Location: ' . $redirect);
        exit;
    }

    // Génère authorization_code (one-time, 10 min)
    $rawCode = bin2hex(random_bytes(32));
    $codeHash = hash('sha256', $rawCode);

    (new OAuthAuthorizationCode())->create([
        'code_hash'     => $codeHash,
        'client_id'     => $params['client_id'],
        'user_id'       => $_SESSION['user_id'],
        'shop_id'       => (new OAuthClient())->findByClientId($params['client_id'])['shop_id'],
        'redirect_uri'  => $params['redirect_uri'],
        'scopes'        => json_encode(['invoices:write']),
        'items_payload' => json_encode($params['items']),
        'state'         => $params['state'],
        'expires_at'    => date('Y-m-d H:i:s', time() + 600),
    ]);

    $redirect = $params['redirect_uri'] . '?' . http_build_query([
        'code'  => $rawCode,
        'state' => $params['state'],
    ]);
    header('Location: ' . $redirect);
    exit;
}
```

#### Méthode `token()`

```php
public function token()
{
    header('Content-Type: application/json');
    header('Cache-Control: no-store');
    header('Pragma: no-cache');

    $grantType = $_POST['grant_type'] ?? '';

    if ($grantType === 'authorization_code') {
        return $this->handleAuthorizationCode();
    }

    http_response_code(400);
    echo json_encode([
        'error' => 'unsupported_grant_type',
        'error_description' => 'Seul authorization_code est supporté.',
    ]);
}

private function handleAuthorizationCode()
{
    $code         = $_POST['code'] ?? '';
    $clientId     = $_POST['client_id'] ?? '';
    $clientSecret = $_POST['client_secret'] ?? '';
    $redirectUri  = $_POST['redirect_uri'] ?? '';

    // 1. Vérif client
    $clientModel = new OAuthClient();
    $client = $clientModel->findByClientId($clientId);
    if (!$client || !$client['is_active']) {
        $this->oauthError('invalid_client', 401);
        return;
    }

    // 2. Vérif secret (bcrypt)
    if (!password_verify($clientSecret, $client['client_secret_hash'])) {
        sleep(1); // anti-brute-force
        $this->oauthError('invalid_client', 401);
        return;
    }

    // 3. Vérif code
    $codeHash  = hash('sha256', $code);
    $codeModel = new OAuthAuthorizationCode();
    $authCode  = $codeModel->findValid($codeHash);
    if (!$authCode) {
        $this->oauthError('invalid_grant', 400);
        return;
    }

    // 4. Vérif redirect_uri (doit matcher exactement)
    if (!hash_equals($authCode['redirect_uri'], $redirectUri)) {
        $this->oauthError('invalid_grant', 400);
        return;
    }

    // 5. Marquer code comme "used"
    $codeModel->markUsed($codeHash);

    // 6. Génère access_token
    $rawToken  = bin2hex(random_bytes(32));
    $tokenHash = hash('sha256', $rawToken);

    (new OAuthAccessToken())->create([
        'token_hash' => $tokenHash,
        'client_id'  => $clientId,
        'user_id'    => $authCode['user_id'],
        'shop_id'    => $authCode['shop_id'],
        'scopes'     => $authCode['scopes'],
        'expires_at' => date('Y-m-d H:i:s', time() + 3600),
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
        'user_agent' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
    ]);

    // 7. Log d'audit
    $this->logAccess('oauth/token', 'POST', 200);

    // 8. Réponse OAuth 2.0 standard
    echo json_encode([
        'access_token' => $rawToken,
        'token_type'   => 'Bearer',
        'expires_in'   => 3600,
        'scope'        => implode(' ', json_decode($authCode['scopes'], true) ?: []),
    ]);
}

private function oauthError($error, $statusCode)
{
    http_response_code($statusCode);
    echo json_encode([
        'error' => $error,
        'error_description' => "OAuth error: {$error}",
    ]);
    $this->logAccess('oauth/token', 'POST', $statusCode, $error);
}
```

### 8.2 `app/controllers/ExternalSaleController.php`

```php
class ExternalSaleController extends Controller
{
    /**
     * POST /api/v1/external/sales/preview
     * Génère un preview_id pour ouvrir /caisse/external.
     */
    public function preview()
    {
        $auth = OAuthMiddleware::authenticate(['invoices:write']);

        $input = json_decode(file_get_contents('php://input'), true);
        $items = $input['items'] ?? [];

        // Stocke le panier pré-rempli en cache (Redis ou fichier temporaire)
        $previewId = bin2hex(random_bytes(16));
        $this->storePreview($previewId, [
            'shop_id'    => $auth['shop_id'],
            'client_id'  => $auth['client_id'],
            'user_id'    => $auth['user_id'],
            'items'      => $items,
            'expires_at' => time() + 600,
        ]);

        $this->json([
            'success'    => true,
            'preview_id' => $previewId,
            'caisse_url' => APP_URL . '/caisse/external?preview_id=' . $previewId,
        ]);
    }

    /**
     * POST /api/v1/external/sales/confirm
     * Mode "1 seule fenêtre" : crée la facture directement.
     */
    public function confirm()
    {
        $auth = OAuthMiddleware::authenticate(['invoices:write']);

        $input = json_decode(file_get_contents('php://input'), true);
        $items = $input['items'] ?? [];

        $saleId = $this->createExternalSale($auth, $items, $input);

        $this->json([
            'success'     => true,
            'sale_id'     => $saleId,
            'invoice_url' => APP_URL . '/facture/' . $saleId,
            'pdf_url'     => APP_URL . '/api/v1/external/sales/' . $saleId . '/pdf',
        ]);
    }

    /**
     * GET /api/v1/external/sales/:id
     */
    public function get($params)
    {
        $auth   = OAuthMiddleware::authenticate(['invoices:read']);
        $saleId = $params['id'];

        $sale = (new Sale())->exist($saleId);
        if (!$sale || $sale['shop_id'] !== $auth['shop_id']) {
            $this->json(['error' => 'not_found'], 404);
            return;
        }

        $this->json(['success' => true, 'data' => $sale]);
    }

    private function createExternalSale($auth, $items, $input)
    {
        $total         = array_sum(array_map(fn($i) => $i['price'] * $i['quantity'], $items));
        $numeroFacture = (new Sale())->generateInvoiceNumber();

        return (new Sale())->create([
            'numero_facture' => $numeroFacture,
            'invoice_type'   => 'FV',
            'client_id'      => $input['client_id'] ?? null,
            'sous_total_ht'  => $total / 1.16,
            'tva'            => $total - ($total / 1.16),
            'total'          => $total,
            'payments'       => [['method' => 'external', 'amount' => $total, 'ref' => $auth['client_id']]],
            'vendeur_id'     => $auth['user_id'],
            'shop_id'        => $auth['shop_id'],
            'date'           => date('Y-m-d H:i:s'),
            'comment'        => 'Source: oauth:' . $auth['client_id'],
        ]);
    }
}
```

### 8.3 `app/controllers/ExternalCaisseController.php`

```php
class ExternalCaisseController extends Controller
{
    /**
     * GET /caisse/external?preview_id=xxx
     * Affiche la page /caisse avec panier pré-rempli.
     */
    public function show()
    {
        $previewId = $_GET['preview_id'] ?? '';
        $preview   = $this->loadPreview($previewId);

        if (!$preview) {
            http_response_code(404);
            echo "Preview expired or invalid.";
            return;
        }

        // Vérif session utilisateur
        if (!isset($_SESSION['user_id'])) {
            header('Location: /login?next=' . urlencode($_SERVER['REQUEST_URI']));
            exit;
        }

        // Passe les items pré-remplis à la vue caisse-external.php
        $data = [
            'preview_id' => $previewId,
            'items'      => $preview['items'],
            'client_id'  => $preview['client_id'],
            'shop_id'    => $preview['shop_id'],
        ];

        extract($data);
        require dirname(__DIR__) . '/views/layout/header.php';
        require dirname(__DIR__) . '/views/caisse-external.php';
        require dirname(__DIR__) . '/views/layout/footer.php';
    }

    /**
     * POST /caisse/external/checkout
     * Crée la facture, redirige vers redirect_uri avec HMAC.
     */
    public function checkout()
    {
        $previewId = $_POST['preview_id'] ?? '';
        $preview   = $this->loadPreview($previewId);
        if (!$preview) {
            http_response_code(404);
            return;
        }

        // Crée la vente (réutilise Sale::create)
        $saleId = $this->createExternalSaleFromCart($preview, $_POST);

        // Invalide le preview
        $this->deletePreview($previewId);

        // Redirige vers l'app hôtel avec signature HMAC
        $client = (new OAuthClient())->findByClientId($preview['client_id']);
        $callbackSecret = $this->decryptSecret($client['callback_secret_hash']);

        $params = [
            'sale_id'        => $saleId,
            'status'         => 'success',
            'invoice_number' => $this->getInvoiceNumber($saleId),
            'total'          => $_POST['total'],
            'timestamp'      => time(),
        ];
        $signature = hash_hmac('sha256', http_build_query($params), $callbackSecret);
        $params['signature'] = $signature;

        $redirectUri = json_decode($client['redirect_uris'], true)[0];
        header('Location: ' . $redirectUri . '?' . http_build_query($params));
        exit;
    }
}
```

### 8.4 `app/core/OAuthMiddleware.php`

```php
namespace App\Core;

use App\Models\OAuthAccessToken;

class OAuthMiddleware
{
    public static function authenticate(array $requiredScopes = []): array
    {
        $auth = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if (!preg_match('/^Bearer\s+([a-f0-9]{64})$/i', $auth, $m)) {
            self::error(401, 'invalid_token', 'Bearer token manquant');
        }

        $rawToken = $m[1];
        $tokenHash = hash('sha256', $rawToken);

        $token = (new OAuthAccessToken())->findValid($tokenHash);
        if (!$token) {
            self::error(401, 'invalid_token', 'Token invalide ou expiré');
        }

        // Vérif scopes
        $granted = json_decode($token['scopes'], true) ?: [];
        foreach ($requiredScopes as $s) {
            if (!in_array($s, $granted)) {
                self::error(403, 'insufficient_scope', "Scope requis: {$s}");
            }
        }

        // Touch last_used_at (fire & forget)
        (new OAuthAccessToken())->touch($tokenHash);

        return [
            'client_id' => $token['client_id'],
            'shop_id'   => $token['shop_id'],
            'user_id'   => $token['user_id'],
            'scopes'    => $granted,
        ];
    }

    private static function error($code, $error, $description)
    {
        http_response_code($code);
        header('Content-Type: application/json');
        echo json_encode([
            'error' => $error,
            'error_description' => $description,
        ]);
        exit;
    }
}
```

---

## 9. Vues (UI/UX)

### 9.1 Consent Screen — `app/views/oauth/authorize.php`

Design inspiré de Google OAuth :

- **En-tête** : Logo POS à gauche + titre "Autoriser l'application"
- **Corps** :
  - Logo de l'app tier + nom + description
  - Liste des **scopes demandés** (avec icônes)
  - **Aperçu de la facture** :
    - Liste des articles (nom, quantité, prix)
    - Total HT, TVA, TTC
  - Texte légal : *"En cliquant Autoriser, vous permettez à cette application de facturer via votre POS conformément aux [CGU](#). Vous pouvez révoquer cette autorisation à tout moment."*
- **Actions** :
  - Bouton vert **"[ Autoriser ]"**
  - Bouton gris **"[ Refuser ]"**
- **Footer** :
  - Lien "Paramètres de confidentialité"
  - Copyright POS

### 9.2 Developer Console — `app/views/oauth/apps.php`

Page admin (visible par `super_admin` / `admin`) :

- **Liste des apps tierces** :
  - Nom + logo + `client_id` (tronqué)
  - Boutique rattachée
  - Statut (toggle actif/inactif)
  - Date de création, dernière utilisation
  - Actions : `[Voir détails] [Révoquer] [Supprimer]`
- **Bouton "Créer une application"** :
  - Formulaire :
    - Nom, description, logo URL, contact email
    - Boutique (select si multi-boutique)
    - Scopes (checkboxes : `invoices:write`, `invoices:read`, `products:read`)
    - Redirect URIs (textarea, 1 par ligne)
    - Origines CORS autorisées (textarea, 1 par ligne)
  - À la soumission :
    - Génère `client_id` (`pos_app_xxxxx`)
    - Génère `client_secret` (32 chars random)
    - Génère `items_secret` (32 chars random)
    - Génère `callback_secret` (32 chars random)
    - Affiche **une seule fois** les secrets en clair + QR code de téléchargement
    - Stocke les hashs bcrypt (et une copie chiffrée AES-256-GCM)

### 9.3 Caisse Externe — `app/views/caisse-external.php`

Variante de `app/views/caisse.php` :

- **Header** identique à `/caisse` classique
- **Bannière d'avertissement** en haut :
  > 🏨 *Facture pour "Hôtel PMS - Suite Royale"*
  > *Cette facture sera créée pour le compte de cette application.*
- **Panier** pré-rempli avec les items reçus de l'app hôtel (modifiable)
- **Bouton "Encaisser & Rediriger"** au lieu de "Encaisser"
- Après encaissement : redirect automatique vers `redirect_uri?sale_id=...&signature=...`

---

## 10. Roadmap en phases

| Phase | Durée | Contenu | Livrables |
|---|---|---|---|
| **P1 — Fondations** | 2-3 j | Tables MySQL + `OAuthController` (3 méthodes) + `OAuthMiddleware` + 3 models + routes + CORS | Système OAuth fonctionnel en CLI, testable avec `curl` |
| **P2 — Consent Screen** | 1-2 j | Vue `oauth/authorize.php` style Google + intégration avec `OAuthController::authorize()` | Consent screen opérationnel avec aperçu items |
| **P3 — Panier pré-rempli** | 2-3 j | `ExternalCaisseController` + vue `caisse-external.php` + JS pour gérer le panier pré-rempli | Page `/caisse/external` fonctionnelle |
| **P4 — Finalisation & callback** | 1-2 j | `ExternalSaleController::preview/confirm/get/pdf` + endpoint `/caisse/external/checkout` + signature HMAC du callback | Flow complet end-to-end testé |
| **P5 — Developer Console** | 2-3 j | Vue `oauth/apps.php` + CRUD clients (create, list, toggle, revoke, delete) + génération sécurisée des secrets + page de détails | UI admin complète |
| **P6 — Sécurité, audit, doc** | 2 j | Rate-limiting sur `/oauth/token` + headers sécurité + table d'audit + endpoint `/oauth/revoke` + README + Postman | Système production-ready |
| **P7 — Tests & déploiement** | 2 j | Tests manuels end-to-end (app hôtel de démo Node.js) + tests unitaires PHP + déploiement staging + monitoring | v1 production-ready |

**Total** : **12-16 jours** pour un v1 production-ready.

### Stratégie de déploiement

1. **P1-P3** : déploiement en **staging**, tests internes.
2. **P4-P5** : déploiement en **staging**, tests avec une app hôtel partenaire (pilote).
3. **P6-P7** : hardening + déploiement **production** pour 1 boutique pilote.
4. **Généralisation** : ouverture aux autres boutiques après validation (1 mois).

---

## 11. Critères d'acceptation

### Fonctionnels

- [ ] Un admin peut **créer une app tierce** depuis la Developer Console, et reçoit les secrets **une seule fois**.
- [ ] Une app hôtel peut initier un flow OAuth via une popup et obtenir un `access_token` valide.
- [ ] Le caissier voit un **consent screen** clair avec les articles à facturer.
- [ ] Le caissier peut **modifier** le panier pré-rempli (quantité, ajouts, suppression).
- [ ] Le caissier peut **encaisser** la facture et l'app hôtel reçoit un `sale_id` + signature HMAC.
- [ ] La facture créée respecte **tous les champs obligatoires** (`ventes.numero_facture`, `ventes.total`, `ventes.tva`, etc.).
- [ ] La facture est **multiboutique-safe** : chaque app hôtel est liée à un `shop_id` et ne peut facturer que dans sa boutique.
- [ ] Le POS **n'altère pas** le comportement existant : tous les flows existants (`/caisse`, `/api/sales/*`, etc.) fonctionnent comme avant.

### Sécurité

- [ ] Tous les secrets (`client_secret`, `items_secret`, `callback_secret`) sont **hashés bcrypt** en DB.
- [ ] Les `authorization_code` sont **one-time** (utilisables une seule fois).
- [ ] Les `access_token` ont une **durée de vie** de 1h et sont hashés en DB.
- [ ] Les **redirect_uri** sont validés par **whitelist stricte** (pas de pattern matching, comparaison exacte).
- [ ] Les **items envoyés** par l'app hôtel sont **signés HMAC-SHA256** et vérifiés côté POS.
- [ ] Les **callbacks** (POS → app hôtel) sont **signés HMAC-SHA256**.
- [ ] Le rate-limiting bloque les **brute-forces** sur `/oauth/token` (max 20 req/min par IP).
- [ ] Les **CORS** sont dynamiques (pas de `*`).
- [ ] Toutes les requêtes API externes sont **logguées** dans `oauth_clients_access_log`.
- [ ] Les pages OAuth sont protégées contre le **clickjacking** (`X-Frame-Options: DENY`).

### Performance

- [ ] Le flow OAuth complet (authorize + token + checkout) prend **moins de 3 secondes** sur un réseau 4G.
- [ ] Le middleware Bearer ajoute **moins de 10ms** de latence par requête.
- [ ] Les index MySQL sont optimisés (`client_id`, `token_hash`, `code_hash`, `expires_at`).

### UX

- [ ] Le consent screen est **responsive** (mobile + tablette).
- [ ] Si la popup est bloquée par le navigateur, on propose un **fallback en redirect**.
- [ ] Le caissier peut **refuser** une facture et l'app hôtel reçoit un `error=access_denied`.
- [ ] Les erreurs sont **explicites** : `invalid_client`, `invalid_grant`, `invalid_scope`, etc.

---

## 12. Annexes : exemples de payloads

### 12.1 Requête : ouverture de popup OAuth

```
GET /oauth/authorize?
    response_type=code&
    client_id=pos_app_hotel_xyz&
    redirect_uri=https%3A%2F%2Fhotel.com%2Fcallbacks%2Finvoice&
    scope=invoices:write&
    state=<csrf_32_chars>&
    items=<base64_encoded_items>&
    items_signature=<base64_HMAC>
```

#### Format des `items` (avant encodage base64)

```json
[
  {
    "name": "Chambre 101",
    "description": "Suite Royale - 2 nuits",
    "price": 160.00,
    "quantity": 2,
    "tax_rate": 16,
    "category": "accommodation"
  },
  {
    "name": "Petit-déjeuner",
    "description": "Buffet continental",
    "price": 15.00,
    "quantity": 2,
    "tax_rate": 16,
    "category": "food"
  }
]
```

#### Calcul de la signature HMAC

```php
// Côté app hôtel
$itemsCanonical = [
    ['name' => 'Chambre 101', 'price' => 160, 'quantity' => 2, 'tax_rate' => 16],
    ['name' => 'Petit-déjeuner', 'price' => 15, 'quantity' => 2, 'tax_rate' => 16],
];
ksort($itemsCanonical); // tri par clés pour canonicalisation

$canonicalString = json_encode($itemsCanonical, JSON_UNESCAPED_UNICODE);
$itemsSignature = base64_encode(hash_hmac('sha256', $canonicalString, $itemsSecret, true));
```

### 12.2 Réponse : redirect avec `code`

```
HTTP/1.1 302 Found
Location: https://hotel.com/callbacks/invoice?
    code=abc123def456&
    state=<csrf_32_chars>
```

### 12.3 Requête : échange `code` contre `access_token`

```
POST /oauth/token
Content-Type: application/x-www-form-urlencoded

grant_type=authorization_code&
code=abc123def456&
client_id=pos_app_hotel_xyz&
client_secret=<secret>&
redirect_uri=https%3A%2F%2Fhotel.com%2Fcallbacks%2Finvoice
```

### 12.4 Réponse : `access_token`

```json
{
  "access_token": "8a3f9c2e1d4b5f6a7c8e9d0b1a2c3d4e5f6a7b8c9d0e1f2a3b4c5d6e7f8a9b0c",
  "token_type": "Bearer",
  "expires_in": 3600,
  "scope": "invoices:write"
}
```

### 12.5 Requête : création d'un preview

```
POST /api/v1/external/sales/preview
Authorization: Bearer 8a3f9c2e...
Content-Type: application/json

{
  "external_ref": "HOTEL-RES-12345",
  "client": {
    "name": "Jean Dupont",
    "phone": "+243812345678",
    "email": "jean@example.com"
  },
  "items": [
    {"name": "Chambre 101", "price": 160, "quantity": 2, "tax_rate": 16},
    {"name": "Petit-déjeuner", "price": 15, "quantity": 2, "tax_rate": 16}
  ]
}
```

### 12.6 Réponse : preview_id

```json
{
  "success": true,
  "preview_id": "preview_xyz123",
  "caisse_url": "https://pos.com/caisse/external?preview_id=preview_xyz123",
  "expires_in": 600
}
```

### 12.7 Callback final : POS → app hôtel

```
HTTP/1.1 302 Found
Location: https://hotel.com/callbacks/invoice?
    sale_id=12345&
    status=success&
    invoice_number=2026%2F000123&
    total=406.00&
    timestamp=1734567890&
    signature=<base64_HMAC>
```

#### Vérification côté app hôtel (Python)

```python
import hmac, hashlib

params = {
    'sale_id': '12345',
    'status': 'success',
    'invoice_number': '2026/000123',
    'total': '406.00',
    'timestamp': '1734567890'
}

# Recalculer la signature
query_string = urlencode(params)  # exactement comme PHP http_build_query
expected_signature = base64.b64encode(
    hmac.new(callback_secret.encode(), query_string.encode(), hashlib.sha256).digest()
).decode()

# Comparer (constant-time)
if not hmac.compare_digest(expected_signature, received_signature):
    raise SecurityError("Invalid HMAC signature")
```

---

## 📋 Récapitulatif

### Ce qui est **inclus** dans le scope

- ✅ Flow OAuth 2.0 Authorization Code (RFC 6749)
- ✅ Consent screen (style "Google OAuth")
- ✅ Signature HMAC des items (anti-usurpation)
- ✅ Signature HMAC du callback (anti-falsification)
- ✅ Multi-boutique (chaque app hôtel est liée à un `shop_id`)
- ✅ Developer Console UI
- ✅ Réutilisation de `Sale::create()`, `InvoiceController`, page `/caisse`
- ✅ Mode "1 fenêtre" (auto-confirm) + mode "2 fenêtres" (caisse externe)

### Ce qui est **hors scope** (v1)

- ❌ Refresh tokens (token court + nouveau `code` à chaque demande)
- ❌ PKCE (peut être ajouté en v2 si apps mobiles natives)
- ❌ Métadonnées OpenID Connect (pas d'identité utilisateur dans le scope)
- ❌ Discovery via `/.well-known/openid-configuration` (peut être ajouté en v2)
- ❌ Marketplace public d'apps tierces (la Developer Console est admin-only en v1)
- ❌ Facturation récurrente / paiements programmés (peut être ajouté en v2)

---

## 📚 Références

- [RFC 6749 — The OAuth 2.0 Authorization Framework](https://datatracker.ietf.org/doc/html/rfc6749)
- [RFC 7636 — Proof Key for Code Exchange (PKCE)](https://datatracker.ietf.org/doc/html/rfc7636)
- [RFC 7009 — OAuth 2.0 Token Revocation](https://datatracker.ietf.org/doc/html/rfc7009)
- [RFC 8414 — OAuth 2.0 Authorization Server Metadata](https://datatracker.ietf.org/doc/html/rfc8414)
- [Google OAuth 2.0 documentation](https://developers.google.com/identity/protocols/oauth2)
- [Stripe Connect — Client Credentials](https://stripe.com/docs/connect/authentication)
- [GitHub Apps — Authorization](https://docs.github.com/en/developers/apps/building-github-apps/authorizing-github-apps)

---

---

---