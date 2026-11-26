# API Mobile (app Android) — Authentification par token Bearer

> **Objet** : contrat d'authentification de l'application mobile Android qui rend
> `/caisse`. L'app s'authentifie avec un **token Bearer** (pas de cookie de
> session), puis appelle **toutes les routes API existantes** sans modification.
>
> **Implémentation** :
> - Contrôleur : `app/controllers/MobileAuthController.php`
> - Service : `app/Services/MobileAuthService.php`
> - Modèle : `app/models/MobileToken.php`
> - Migration : `migrations/2026_11_22_mobile_auth.sql`
> - Routes : `routes/api.php` (bloc « Auth MOBILE »)
>
> **Test** : `tests/Integration/MobileAuthServiceTest.php` (11 tests, 32 assertions)

---

## 1. Principe : token Bearer + hydratation de session

L'app mobile ne gère pas de cookie `PHPSESSID`. Elle envoie un en-tête :

```
Authorization: Bearer <token>
```

Le token est résolu **avant le routeur** (`public/index.php`), et la session
PHP est **hydratée** comme après un login web normal :

```
$_SESSION['user_id']         $_SESSION['role']
$_SESSION['nom_utilisateur']  $_SESSION['shop_id']
$_SESSION['nom_complet']      $_SESSION['agent_code']
$_SESSION['email']           $_SESSION['telephone']
$_SESSION['hotel_role']       $_SESSION['last_activity']
```

**Conséquence** : l'app mobile peut appeler **sans rien modifier** :

| Route | Usage |
|---|---|
| `GET /api/produits` | catalogue (avec `tax_rate`, `tax_etiquette`, TS, remise) |
| `GET /api/categories` | catégories |
| `GET /api/currency` | taux de change USD |
| `GET /api/vente/next-invoice` | numéro de facture (attribué par le serveur) |
| `POST /api/dgi` | émission de la facture normalisée DGI |
| `POST /api/vente` | enregistrement de la vente |
| `GET /api/client/search` | recherche client par téléphone |
| `GET /api/shops/check-active` | statut de la boutique (réhydratation) |

Tous les guards existants s'appliquent **automatiquement** :
`requireAuth()`, `requireCaisseAccess()`, `requireActiveShop()` (boutique
désactivée → 403), et la **politique d'accès aux pages** du super_admin
(`Router::policyAllows`).

> ⚠️ Un token invalide, expiré, révoqué, ou un compte/boutique désactivé ⇒ la
> session **n'est pas** hydratée ⇒ la requête est traitée comme anonyme (401/403).

---

## 2. Authentification

### 2.1 `POST /api/mobile/auth/login`

```json
{
  "username": "caissier",
  "password": "motdepasse",
  "device": {
    "device_name": "Samsung A12",
    "device_id": "a3f9-…-stable",
    "platform": "android",
    "app_version": "1.0.0"
  }
}
```

**Réponse 200 (succès, pas de 2FA)** :

```json
{
  "success": true,
  "token": "3f9a…(64 caractères hex)",
  "token_type": "Bearer",
  "expires_at": "2026-12-22 14:30:00",
  "user": {
    "id": 12,
    "nom_utilisateur": "caissier",
    "nom_complet": "César",
    "role": "vendeur",
    "hotel_role": null,
    "shop_id": 3,
    "agent_code": "AF0042",
    "email": "…",
    "telephone": "…",
    "two_factor_enabled": false,
    "actif": true
  },
  "shop": {
    "id": 3,
    "nom": "SuperMarché Express",
    "adresse": "…",
    "telephone": "…",
    "email": "…",
    "ice": "001234567890123",
    "rccm": "…",
    "isf": "A900001T",
    "nid": "…",
    "pdv": "PDV-01",
    "logo": "media/logo/shop_3.jpg",
    "homologation": true,
    "actif": true
  },
  "context": {
    "user": { … },
    "shop": { … },
    "company": { … },
    "nif": "A900001T",
    "isf": "A900001T",
    "permissions": { "caisse": true, "produits": true, "historique": true },
    "receipt": {
      "paper_type": "80mm",
      "receipt_padding_80mm": { "h": 0, "v": 1 },
      "receipt_padding_57mm": { "h": 0, "v": 1 }
    }
  }
}
```

> **`shop` est exactement ce dont l'app a besoin pour l'en-tête de la facture**
> (nom, adresse, téléphone, ICE, RCCM, ISF, NID, PDV, logo) — cf. les champs
> utilisés par `app.js` (`STORE_INFO`).
>
> **`permissions.caisse`** reflète `utilisateurs.acces_caisse` : le manager peut
> retirer un caissier du poste de vente, l'app doit le refléter.

**Réponse 200 (2FA activée)** — aucun token d'accès n'est émis :

```json
{
  "success": true,
  "requires_otp": true,
  "challenge_token": "9c1d…",
  "expires_at": "2026-11-22 14:12:00",
  "channels": ["sms"],
  "message": "Code de vérification envoyé"
}
```

**Réponses d'erreur** :

| HTTP | `error` | Cas |
|---|---|---|
| 400 | `missing_credentials` | champ vide |
| 401 | `invalid_credentials` | identifiants faux |
| 401 | `account_disabled` | compte désactivé |
| 401 | `shop_disabled` | boutique désactivée |
| 429 | `rate_limited` | > 5 échecs en 15 min |

### 2.2 `POST /api/mobile/auth/verify-otp`

```json
{ "challenge_token": "9c1d…", "code": "123456" }
```

→ même payload de réponse que `login` (avec `token`, `user`, `shop`, `context`).

Le défi OTP est **consommé** à la validation (usage unique) et expire au bout de
**10 minutes**.

### 2.3 `POST /api/mobile/auth/resend-otp`

```json
{ "challenge_token": "9c1d…" }
```

### 2.4 `POST /api/mobile/auth/logout`

En-tête `Authorization: Bearer …` requis. Révoque le token courant.

### 2.5 `GET /api/mobile/auth/me`

Retourne `user`, `shop`, `company`, `nif`, `isf` (utile au démarrage de l'app
pour re-hydrater l'affichage sans refaire un login).

### 2.6 `GET /api/mobile/auth/devices`

Liste les appareils connectés (nom, plateforme, IP, dernier usage, expiration).

### 2.7 `POST /api/mobile/auth/revoke-all`

Déconnecte **tous** les appareils de l'utilisateur.

---

## 3. Sécurité

| Mesure | Mise en œuvre |
|---|---|
| Token en clair | **jamais** stocké — seule l'empreinte SHA-256 (`token_hash`, UNIQUE) |
| Longueur | 64 caractères hexadécimaux (`random_bytes(32)`) |
| Expiration | 30 jours par défaut (`mobile_tokens.expires_at`) |
| Révocation | unitaire (`revoked_at`) ou globale (`revoke-all`) |
| Compte désactivé | invalide le token à chaque requête |
| Boutique désactivée | bloque la connexion **et** les requêtes suivantes |
| Brute-force | 5 échecs ⇒ blocage 15 min (table `login_attempts`, partagée avec le web) |
| 2FA | OTP obligatoire si `two_factor_enabled = 1` (SMS via l'endpoint OSAT existant) |
| Secret porteur | **HTTPS obligatoire** (le token équivaut à une session) |
| Audit | `mobile_login`, `mobile_logout`, `mobile_revoke_all` dans `audit_logs` |

> ⚠️ Le token **équivaut à une session complète** : ne jamais le logger en
> clair, ne jamais le stocker en clair sur un device non chiffré.

---

## 4. Exemple Android (Kotlin / Retrofit)

```kotlin
// 1) Login
@POST("api/mobile/auth/login")
suspend fun login(@Body body: LoginRequest): LoginResponse

// 2) Intercepteur : ajoute le token à chaque requête
class AuthInterceptor(private val prefs: Prefs) : Interceptor {
    override fun intercept(chain: Interceptor.Chain): Response {
        val token = prefs.accessToken
        val req = chain.request().newBuilder()
            .apply { if (!token.isNullOrBlank()) header("Authorization", "Bearer $token") }
            .build()
        return chain.proceed(req)
    }
}

// 3) Conserver le token (chiffré : EncryptedSharedPreferences)
val response = api.login(LoginRequest(username, password, DeviceInfo()))
if (response.requiresOtp) {
    // afficher le champ code → verifyOtp(challengeToken, code)
} else {
    prefs.accessToken = response.token
    // response.shop contient l'en-tête de la facture
}
```

---

## 5. Mise en production

```bash
# 1) Appliquer la migration
mysql -u root -p pos_system < migrations/2026_11_22_mobile_auth.sql
```

La migration est **idempotente** :
- `CREATE TABLE IF NOT EXISTS mobile_tokens`
- `ALTER TABLE otp_codes MODIFY type ENUM('login','password_reset','mobile_login')`

> ⚠️ Le `ALTER` sur `otp_codes.type` est sans risque : il ne fait qu'**ajouter**
> une valeur à l'ENUM, sans supprimer les valeurs existantes.

---

## 6. Tests

```bash
php vendor/bin/phpunit --testsuite Integration --filter MobileAuthServiceTest
```

11 tests / 32 assertions couvrant : émission du token, identifiants invalides,
non-stockage du token en clair, hydratation de session, token inconnu / révoqué /
expiré, compte désactivé, boutique désactivée, contexte magasin (en-tête
facture), droit `acces_caisse`, révocation.
