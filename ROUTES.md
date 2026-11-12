# Documentation des Routes - SYS-POS

## Table des matières

- [Routes Web (Frontend)](#routes-web-frontend)
- [Routes API (Backend)](#routes-api-backend)
- [Authentification](#authentification)
- [Permissions](#permissions)

---

## Routes Web (Frontend)

### Authentification

| Méthode | Route     | Contrôleur     | Action    | Description                            |
| ------- | --------- | -------------- | --------- | -------------------------------------- |
| GET     | `/`       | AuthController | showLogin | Affiche la page de connexion           |
| POST    | `/login`  | AuthController | login     | Traitement de la connexion utilisateur |
| GET     | `/logout` | AuthController | logout    | Déconnexion de l'utilisateur           |

**Redirection:** Si l'utilisateur est déjà connecté, il est redirigé vers `/dashboard`.

### Pages principales

| Méthode | Route         | Contrôleur     | Action     | Description                        | Accès |
| ------- | ------------- | -------------- | ---------- | ---------------------------------- | ----- |
| GET     | `/dashboard`  | PageController | dashboard  | Tableau de bord avec statistiques  | Tous  |
| GET     | `/caisse`     | PageController | caisse     | Interface de caisse/point de vente | Tous  |
| GET     | `/produits`   | PageController | produits   | Liste des produits                 | Tous  |
| GET     | `/historique` | PageController | historique | Historique des ventes              | Tous  |

### Pages d'administration

| Méthode | Route           | Contrôleur     | Action       | Description              | Accès |
| ------- | --------------- | -------------- | ------------ | ------------------------ | ----- |
| GET     | `/utilisateurs` | PageController | utilisateurs | Gestion des utilisateurs | Admin |
| GET     | `/categories`   | PageController | categories   | Gestion des catégories   | Admin |
| GET     | `/parametres`   | PageController | parametres   | Paramètres système       | Admin |

**Note:** Les pages d'administration sont accessibles uniquement aux utilisateurs avec le rôle `admin`. Les utilisateurs non-admin sont redirigés vers `/dashboard`.

### Module Pressing

| Méthode | Route      | Contrôleur     | Action   | Description                                | Accès            |
| ------- | ---------- | -------------- | -------- | ------------------------------------------ | ---------------- |
| GET     | `/pressing` | PageController | pressing | Suivi des commandes de pressing            | Boutique cochée |

L'accès est contrôlé par boutique (`shops.acces_pressing`) : le super-admin coche la case dans `/shops`. Sans accès, la page renvoie une 403 et le lien n'apparaît pas dans la sidebar.

---

## Routes API (Backend)

###前缀: `/api`

Base URL: `http://localhost:8000/api`

---

### Produits

| Méthode | Route             | Contrôleur        | Action | Description               | Auth  |
| ------- | ----------------- | ----------------- | ------ | ------------------------- | ----- |
| GET     | `/produits`       | ProductController | index  | Liste tous les produits   | Non   |
| GET     | `/produit`        | ProductController | find   | Recherche par code-barres | Non   |
| POST    | `/produit`        | ProductController | create | Crée un nouveau produit   | Admin |
| POST    | `/produit/update` | ProductController | update | Modifie un produit        | Admin |
| POST    | `/produit/delete` | ProductController | delete | Supprime un produit       | Admin |
| POST    | `/api/produit/import` | ProductImportController | import | Importe des produits (Excel/CSV) | Admin |
| GET     | `/api/produit/import/template` | ProductImportController | template | Modèle Excel à remplir | Admin |

#### GET `/api/produits`

**Description:** Retourne la liste de tous les produits.

**Réponse:**

```json
[
  {
    "id": 1,
    "code_barres": "1234567890123",
    "nom": "Nom du produit",
    "categorie_id": 1,
    "categorie": "Comestible",
    "prix": 10.99,
    "stock": 50,
    "stock_minimum": 10,
    "image": "assets/img/products/image.jpg"
  }
]
```

#### GET `/api/produit?code_barres={code}`

**Description:** Recherche un produit par son code-barres.

**Paramètres:**
| Param | Type | Requis | Description |
|-------|------|--------|-------------|
| code_barres | string | Oui | Code-barres du produit |

**Réponse (succès):**

```json
{
  "id": 1,
  "code_barres": "1234567890123",
  "nom": "Nom du produit",
  ...
}
```

**Réponse (erreur 404):**

```json
{
  "error": "Produit introuvable"
}
```

#### POST `/api/produit`

**Description:** Crée un nouveau produit.

**Authentification requise:** Oui (rôle admin)

**Paramètres POST:**
| Param | Type | Requis | Description |
|-------|------|--------|-------------|
| code_barres | string | Oui | Code-barres unique |
| nom | string | Oui | Nom du produit |
| category_id | int | Oui | ID de la catégorie |
| prix | float | Oui | Prix unitaire |
| stock | int | Non | Quantité en stock (défaut: 0) |
| stock_minimum | int | Non | Seuil d'alerte stock |
| image | file | Non | Image du produit |

**Réponse:**

```json
{
  "success": true,
  "id": 1
}
```

#### POST `/api/produit/update`

**Description:** Modifie un produit existant.

**Authentification requise:** Oui (rôle admin)

**Paramètres POST:**
| Param | Type | Requis | Description |
|-------|------|--------|-------------|
| id | int | Oui | ID du produit à modifier |
| code_barres | string | Oui | Nouveau code-barres |
| nom | string | Oui | Nouveau nom |
| category_id | int | Oui | Nouvelle catégorie |
| prix | float | Oui | Nouveau prix |
| stock | int | Non | Nouveau stock |
| stock_minimum | int | Non | Nouveau seuil |
| image | file | Non | Nouvelle image |

**Réponse:**

```json
{
  "success": true
}
```

#### POST `/api/produit/delete`

**Description:** Supprime un produit.

**Authentification requise:** Oui (rôle admin)

**Paramètres POST:**
| Param | Type | Requis | Description |
|-------|------|--------|-------------|
| id | int | Oui | ID du produit à supprimer |

**Réponse:**

```json
{
  "success": true
}
```

---

### Import de produits (Excel / CSV)

| Méthode | Route                              | Contrôleur               | Action   | Description                        | Auth  |
| ------- | ---------------------------------- | ------------------------ | -------- | ---------------------------------- | ----- |
| POST    | `/api/produit/import`              | ProductImportController  | import   | Importe des produits depuis un fichier | Admin |
| GET     | `/api/produit/import/template`     | ProductImportController  | template | Télécharge le modèle `.xlsx`       | Admin |

#### POST `/api/produit/import`

**Description:** Importe un catalogue de produits depuis un fichier Excel (`.xlsx`,
`.xls`) ou CSV. Chaque ligne est traitée indépendamment : une ligne invalide
n'annule pas les autres.

**Authentification requise:** Oui (rôle admin ou super_admin)

**Paramètres (multipart/form-data):**
| Param | Type | Requis | Description |
|-------|------|--------|-------------|
| file | file | Oui | Fichier `.xlsx`, `.xls` ou `.csv` (5 Mo maximum) |
| mode | string | Non | `create` (défaut) ignore les produits existants, `update` les met à jour |
| category_id | int | Non | Catégorie par défaut si la colonne est vide |
| tax_id | int | Non | Taxe par défaut si la colonne est vide |
| create_categories | bool | Non | Crée les catégories absentes du catalogue |
| shop_id | int | Non | Boutique cible (super_admin uniquement) |

**Colonnes attendues** (les en-têtes sont normalisés : insensibles à la casse et
aux accents) :

| Colonne | Obligatoire | Description |
|---------|-------------|-------------|
| `code_barres` | Non | Généré automatiquement si vide. Doit être unique. |
| `nom` | Oui | Nom du produit. |
| `categorie` | Oui | Catégorie existante (insensible à la casse). |
| `prix` | Oui | Prix de vente en Fc. |
| `stock` | Non | Stock initial (0 par défaut). |
| `stock_minimum` | Non | Seuil d'alerte de stock (0 par défaut). |
| `taxe` | Non | Étiquette (A, B, C…) ou groupe de taxe. Groupe A par défaut. |
| `product_type` | Non | `unite` ou `coupe`. |
| `prod_service` | Non | `BIE`, `SER` ou `TAX`. |
| `remise_type` | Non | `%` ou `CDF`. |
| `remise_value` | Non | Valeur de la remise. |
| `taxe_specifique_type` | Non | `%` ou `CDF`. |
| `taxe_specifique_value` | Non | Valeur de la taxe spécifique. |
| `date_expiration` | Non | Format `YYYY-MM-DD` ou `JJ/MM/AAAA`. |

**Réponse:**

```json
{
  "success": true,
  "summary": {
    "total": 2,
    "created": 2,
    "updated": 0,
    "skipped": 0,
    "errors": 0,
    "categories_created": 0
  },
  "rows": [
    { "line": 2, "status": "created", "nom": "Lait Frais 1L", "message": "Produit créé" }
  ]
}
```

#### GET `/api/produit/import/template`

**Description:** Télécharge le modèle Excel à remplir (en-têtes + ligne d'exemple
+ onglet « Aide »).

**Authentification requise:** Oui (rôle admin)

**Réponse:** un fichier `.xlsx` (`application/vnd.openxmlformats-officedocument.spreadsheetml.sheet`).

---

### Catégories

| Méthode | Route                | Contrôleur         | Action | Description                 | Auth  |
| ------- | -------------------- | ------------------ | ------ | --------------------------- | ----- |
| GET     | `/categories`        | CategoryController | index  | Liste toutes les catégories | Non   |
| POST    | `/categories`        | CategoryController | create | Crée une nouvelle catégorie | Admin |
| POST    | `/categories/update` | CategoryController | update | Modifie une catégorie       | Admin |
| POST    | `/delete/category`   | CategoryController | delete | Supprime une catégorie      | Non   |

#### GET `/api/categories`

**Description:** Retourne la liste de toutes les catégories.

**Réponse:**

```json
[
  {
    "id": 1,
    "nom": "Comestible",
    "couleur": "#0B5E88"
  },
  {
    "id": 2,
    "nom": "Non Comestible",
    "couleur": "#8B5E3C"
  }
]
```

#### POST `/api/categories`

**Description:** Crée une nouvelle catégorie.

**Authentification requise:** Oui (rôle admin)

**Paramètres POST:**
| Param | Type | Requis | Description |
|-------|------|--------|-------------|
| category | string | Oui | Nom de la catégorie |

**Réponse:**

```json
{
  "success": true,
  "id": 3
}
```

#### POST `/api/categories/update`

**Description:** Modifie une catégorie existante.

**Authentification requise:** Oui (rôle admin)

**Paramètres POST:**
| Param | Type | Requis | Description |
|-------|------|--------|-------------|
| id | int | Oui | ID de la catégorie |
| category | string | Oui | Nouveau nom |

**Réponse:**

```json
{
  "success": true
}
```

#### POST `/api/delete/category`

**Description:** Supprime une catégorie.

**Paramètres POST:**
| Param | Type | Requis | Description |
|-------|------|--------|-------------|
| id | int | Oui | ID de la catégorie |

---

### Utilisateurs

| Méthode | Route          | Contrôleur     | Action | Description                 | Auth  |
| ------- | -------------- | -------------- | ------ | --------------------------- | ----- |
| POST    | `/create/user` | UserController | create | Crée un nouvel utilisateur  | Admin |
| POST    | `/update/user` | UserController | update | Modifie un utilisateur      | Admin |
| GET     | `/users`       | UserController | all    | Liste tous les utilisateurs | Non   |
| POST    | `/delete/user` | UserController | delete | Supprime un utilisateur     | -     |

#### POST `/api/create/user`

**Description:** Crée un nouvel utilisateur.

**Authentification requise:** Oui (rôle admin)

**Paramètres POST:**
| Param | Type | Requis | Description |
|-------|------|--------|-------------|
| username | string | Oui | Nom d'utilisateur unique |
| password | string | Oui | Mot de passe |
| fullname | string | Oui | Nom complet |
| role | string | Non | Rôle (défaut: "vendeur") |
| actif | int | Non | Statut actif (défaut: 1) |

**Réponse:**

```json
{
  "success": true,
  "message": "user create !"
}
```

#### POST `/api/update/user`

**Description:** Modifie un utilisateur existant.

**Authentification requise:** Oui (rôle admin)

**Paramètres POST:**
| Param | Type | Requis | Description |
|-------|------|--------|-------------|
| id | int | Oui | ID de l'utilisateur |
| nom_utilisateur | string | Non | Nouveau nom d'utilisateur |
| mot_de_passe | string | Non | Nouveau mot de passe |
| nom_complet | string | Non | Nouveau nom complet |
| role | string | Non | Nouveau rôle |
| actif | int | Non | Nouveau statut |

**Réponse:**

```json
{
  "success": true
}
```

#### GET `/api/users`

**Description:** Retourne la liste de tous les utilisateurs.

**Réponse:**

```json
[
  {
    "id": 1,
    "nom_utilisateur": "admin",
    "nom_complet": "Administrateur",
    "role": "admin",
    "actif": 1
  }
]
```

#### POST `/api/delete/user`

**Description:** Supprime un utilisateur.

**Paramètres POST:**
| Param | Type | Requis | Description |
|-------|------|--------|-------------|
| id | int | Oui | ID de l'utilisateur |

**Réponse:**

```json
{
  "success": true,
  "message": "Utilisateur supprimé avec succès"
}
```

---

### Ventes

| Méthode | Route           | Contrôleur     | Action | Description             | Auth  |
| ------- | --------------- | -------------- | ------ | ----------------------- | ----- |
| POST    | `/vente`        | SaleController | create | Crée une nouvelle vente | Oui   |
| POST    | `/delete/vente` | SaleController | delete | Supprime une vente      | Admin |

#### POST `/api/vente`

**Description:** Enregistre une nouvelle vente.

**Authentification requise:** Oui

**Body (JSON):**

```json
{
  "articles": [
    {
      "produit_id": 1,
      "quantite": 2,
      "prix": 10.99
    }
  ],
  "sous_total_ht": 21.98,
  "tva": 2.2,
  "total": 24.18
}
```

**Réponse:**

```json
{
  "success": true,
  "numero_facture": "VEN-20260424-0001",
  "vente_id": 1
}
```

**Note:** Cette route met à jour automatiquement le stock des produits vendus.

#### POST `/api/delete/vente`

**Description:** Supprime une vente.

**Authentification requise:** Oui (rôle admin)

**Paramètres POST:**
| Param | Type | Requis | Description |
|-------|------|--------|-------------|
| id | int | Oui | ID de la vente à supprimer |

**Réponse:**

```json
{
  "success": true,
  "message": "Vente supprimée avec succès"
}
```

---

### Extras (imputation d'une vente sur un séjour hôtelier)

| Méthode | Route                                            | Contrôleur              | Action          | Description                          |
| ------- | ------------------------------------------------ | ----------------------- | --------------- | ------------------------------------ |
| GET     | `/api/hotel/sejours/en-cours`                     | HotelExtrasController   | enCours         | Liste les séjours en cours imputables (picker) |
| POST    | `/api/hotel/sejours/{id}/extras-from-cart`         | HotelExtrasController   | extrasFromCart  | Impute le panier sur un séjour       |

**Authentification requise:** Oui (session PHP + boutique active).

**Contrôle d'accès :** ces deux routes sont conditionnées par la
configuration **par boutique** `shops.acces_extras` (cochée par le super_admin
dans `/shops`), et **NON** par le feature flag API `hotel` utilisé pour le
reste du module Hôtel. Le bouton « Extras » de `/caisse` suit la même règle.

**Règle métier — séjour déjà facturé :** un séjour qui possède une facture
hôtel **active** (`hotel_factures.statut != 'annulee'`) n'est **plus imputable**
aux extras, même s'il est toujours `en_cours`. C'est le cas quand la facture a
été émise **sans check-out** : la note est close, un nouvel extra fausserait le
total de la facture déjà émise.

- `GET /api/hotel/sejours/en-cours` → ces séjours sont **retirés de la liste**
  (le picker de `/caisse` ne les affiche donc pas).
- `POST /api/hotel/sejours/{id}/extras-from-cart` → **422** `sejour_deja_facture`.
- `POST /api/vente` avec un `sejour_id` → **422** `sejour_deja_facture`
  (même garde, cf. `SaleController::create`).

Une facture **annulée** ne bloque pas l'imputation : elle n'est plus active.

**403 — accès non autorisé :**

```json
{
  "success": false,
  "error": "extras_not_allowed",
  "message": "La fonctionnalité Extras n'est pas activée pour votre boutique."
}
```

**422 — séjour déjà facturé :**

```json
{
  "success": false,
  "error": "sejour_deja_facture",
  "message": "Ce séjour est déjà facturé (2026/001004) — impossible d'ajouter un extra."
}
```

**POST `/api/hotel/sejours/{id}/extras-from-cart` — corps JSON :**

| Paramètre | Type | Requis | Description                                  |
| --------- | ---- | ------ | -------------------------------------------- |
| articles  | array | Oui   | Articles du panier (`produit_id`, `quantite`, `prix`, …) |

**Réponse :**

```json
{
  "success": true,
  "sejour_id": 47,
  "count": 1,
  "total": 100,
  "ids": [54]
}
```

---

### Proxy DGI (API Externe)

| Méthode | Route  | Contrôleur | Action | Description                  |
| ------- | ------ | ---------- | ------ | ---------------------------- |
| GET     | `/dgi` | Anonymous  | -      | Proxy vers l'API DGI externe |

#### GET `/api/dgi`

**Description:** Proxy vers l'API DGI externe (https://osat-energie.com/dgi/).

**Headers CORS:**

- `Access-Control-Allow-Origin: *`
- `Access-Control-Allow-Methods: GET, OPTIONS`
- `Access-Control-Allow-Headers: Content-Type`

**Réponse (succès):**

```json
{ ... }
```

**Réponse (erreur):**

```json
{
  "success": false,
  "message": "Erreur de connexion DGI"
}
```

---

### Rapports DGI/OSAT

Les agrégats fiscaux DGI sont consultables via un proxy backend authentifié.
Le navigateur ne contacte jamais directement le provider OSAT et ne peut pas
choisir librement un NIF : l'ISF est résolu depuis `shops.isf`.

| Méthode | Route | Contrôleur | Action | Auth | Description |
| ------- | ----- | ---------- | ------ | ---- | ----------- |
| GET | `/api/reports/dgi/x` | ReportController | generateDgiXReport | Admin | Rapport DGI pour une période personnalisée |
| GET | `/api/reports/dgi/z` | ReportController | generateDgiZReport | Admin | Agrégat DGI de la période à clôturer (lecture seule) |
| POST | `/api/reports/dgi/z` | ReportController | generateDgiZClosure | Admin | Clôture DGI : enregistrement + chaînage de la période |
| GET | `/api/reports/dgi/z-history` | ReportController | getDgiZHistory | Admin | Historique des clôtures DGI |

> ⚠️ **Le Z local est désactivé.** La route `GET /api/reports/z-report` a été
> retirée : la clôture de référence est **uniquement** la clôture DGI
> (`POST /api/reports/dgi/z`). Conserver les deux aurait permis de clôturer
> deux fois la même période (donc de compter les mêmes ventes en double).
>
> Le **X local a également été retiré** (bouton « X-rapport », barre
> « X-rapport périodique », routes `/api/reports/x-report/daily` et
> `/api/reports/x-report/periodic`) : le **X DGI**
> (`GET /api/reports/dgi/x`) fait désormais foi. La période ouverte de
> référence reste la dernière clôture DGI.

### Tableau de bord — indicateurs DGI/OSAT

| Méthode | Route | Contrôleur | Action | Auth | Description |
| ------- | ----- | ---------- | ------ | ---- | ----------- |
| GET | `/api/dashboard/dgi-stats` | DashboardController | dgiStats | Authentifié | Agrégats DGI des 3 périodes du tableau de bord (jour, semaine, mois) |

Les cartes « Ventes du jour / Cette semaine / Ce mois » du tableau de bord
n'affichent **plus** les agrégats SQL locaux : elles affichent les montants officiels
fournis par la DGI.

**Paramètres query :**

| Paramètre | Type | Requis | Description |
| --------- | ---- | ------ | ----------- |
| `shop_id` | int | Super-admin | Restreint à une boutique (sinon : ISF de l'entreprise) |

> Le super_admin interroge **son ISF d'entreprise** (`company_info.isf`) par
> défaut : une seule requête HTTP, pas d'agrégation multi-boutiques.

**Réponse :**

```json
{
  "success": true,
  "data": {
    "success": true,
    "periods": {
      "day":   { "ht": 15986210.54, "ttc": 16080004.25, "invoice_count": 19, "error": null },
      "week":  { "ht": 48396056.83, "ttc": 49735933.60, "invoice_count": 45, "error": null },
      "month": { "ht": 50681542.21, "ttc": 52034633.60, "invoice_count": 50, "error": null }
    },
    "meta": {
      "cached": false,
      "fetched_at": "2026-11-11 00:12:03",
      "shops_count": 3,
      "timezone": "Africa/Kinshasa"
    }
  }
}
```

> ⚠️ **Chargement asynchrone** : 3 périodes × N boutiques = autant d'appels
> provider (~1,7 s chacun). Les appels sont lancés **en parallèle**, par lots
> de 4, avec un timeout de 20 s et un retry sur erreur transitoire (le
> provider OSAT est mutualisé et sature au-delà de quelques appels
> simultanés). Un résultat est mis en cache 60 s en session.
>
> La page s'affiche immédiatement avec un état « Chargement… » : les valeurs
> sont injectées par `public/assets/js/dashboard.js`.
>
> Le token d'accès transmis au provider est celui de la boutique interrogée
> (`shops.token`, repli `company_info.token`), résolu à partir de l'ISF. Il
> **n'apparaît jamais** dans les réponses JSON.

#### GET `/api/reports/dgi/x`

**Paramètres query :**

| Paramètre | Type | Requis | Description |
| --------- | ---- | ------ | ----------- |
| `from` | `YYYY-MM-DD` | Non | Début de période (défaut : aujourd'hui) |
| `to` | `YYYY-MM-DD` | Non | Fin de période (défaut : aujourd'hui) |
| `types` | `FV,FT,...` | Non | Filtre par type de facture |
| `shop_id` | int | Super-admin | Boutique à interroger |

Un `admin` utilise toujours sa propre boutique et ne peut pas changer de
périmètre via `shop_id`. Un `super_admin` doit sélectionner une boutique.

Le filtre `types` est appliqué côté POS (le provider renvoie toujours tous les
types) : les totaux affichés sont recalculés sur les types retenus. Un type
inconnu est refusé (`400`).

#### GET `/api/reports/dgi/z`

Période = fin de la dernière clôture DGI → maintenant (repli : première vente
réelle de la boutique, sinon un an).
Consultation seule : cette route ne clôture rien.

#### POST `/api/reports/dgi/z`

**Clôture DGI.** Appelle le provider sur la période à clôturer, enregistre la
clôture en base (`dgi_z_reports` : compteur, bornes, totaux, snapshot),
archive la clôture précédente, journalise l'opération et **chaîne la période
suivante** sur la fin de cette clôture.

**Réponse (succès) :**

```json
{
  "success": true,
  "data": {
    "source": "dgi",
    "header": {
      "type": "X_DGI",
      "nif": "A1720894F",
      "isf": "A1720894F",
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

**Erreurs :** `400` (période/boutique), `403` (rôle), `404` (boutique),
`422` (ISF absent), `502` (provider invalide), `503` (désactivé),
`504` (timeout). Le corps brut du provider n'est jamais exposé.

---

### Synchronisation DGI différée (cas « update »)

Quand la DGI est indisponible, l'API OSAT renvoie un fallback (réponse au format
« non assujettie » + `store_homologation = 2`) et met la demande en attente de son
côté. OSAT rejoue la demande auprès de la DGI quand elle redevient disponible,
puis vient chercher la mise à jour chez nous.

| Méthode | Route | Contrôleur | Action | Auth | Description |
| ------- | ----- | ---------- | ------ | ---- | ----------- |
| POST | `/api/dgi/update` | DgiWebhookController | update | Secret partagé | **Route principale** — OSAT pousse la résolution DGI |
| POST | `/api/vente/[id]/dgi-sync` | DgiSyncController | syncSale | Session | Relance manuelle de la relecture d’une vente |
| GET | `/api/dgi/pending` | DgiSyncController | pending | Session (admin) | Liste des ventes en attente de résolution |
| POST | `/api/dgi/sync-all` | DgiSyncController | syncAll | Session (admin) | Relance la relecture par lot |

#### POST `/api/dgi/update`

**Description:** Point d’entrée appelé par OSAT lorsque la DGI redevient
disponible et que la demande en attente est résolue. Les éléments de sécurité
(`codeDEFDGI`, `counters`, `nim`, `qrCode`, `dateDGI`) sont écrits sur la ligne
`ventes` correspondante.

**Authentification:** secret partagé, **sans session** (OSAT est un serveur).

- `X-DGI-Token: <secret>` — secret en clair
- `X-DGI-Signature: <HMAC-SHA256 du corps>` — variante forte (prioritaire)

Le secret est défini par la constante `DGI_WEBHOOK_TOKEN` dans
`config/config.php` (ou la variable d’environnement du même nom).
**Si le secret n’est pas configuré, la route refuse tout appel** (fail-closed).

**Corps de la requête:**

```json
{
  "invoice_number": "2026/000123",
  "store_isf": "A900011T",
  "data": {
    "codeDEFDGI": "…",
    "counters": "…",
    "nim": "…",
    "qrCode": "…",
    "dateDGI": "…",
    "total": 12500.00,
    "vtotal": 14500.00
  },
  "comment": null
}
```

**Réponses:**

| Code | Signification |
| ---- | ------------- |
| 200 | Mise à jour effectuée (`updated: true`) |
| 202 | Vente introuvable (OSAT a résolu avant notre INSERT) — la relecture automatique rattrapera |
| 400 | Corps JSON invalide ou `invoice_number` manquant |
| 401 | Secret absent ou invalide |

**Note:** l’opération est idempotente — un rejeu du callback écrase avec les
mêmes valeurs.

---

### Pressing (suivi des commandes de pressing)

Module d'intégration de l'appli autonome `pressing/`. Référence :
`docs/PLAN_PRESSING.md`.

**Accès :** authentifié + flag API `pressing` actif
(`Controller::requireShopPressingAccess()` →
`RechargeAccessService::canAccessPressing()`), exactement comme
`/recharges` et `/hotel`. Le flag vient de l'API OSAT
(`option_client/feedback.php` → `data.pressing`), mis en cache 5 min en
session. Le lien de la sidebar est masqué quand le flag est faux.
La suppression est réservée à `admin` / `super_admin`.

| Méthode | Route                                          | Contrôleur           | Action          | Description                          |
| ------- | ---------------------------------------------- | -------------------- | --------------- | ------------------------------------ |
| GET     | `/pressing`                                    | PageController      | pressing        | Tableau de bord du pressing          |
| GET     | `/api/pressing/stats`                          | PressingController  | stats           | Compteurs (total, encours, prêtes…)  |
| GET     | `/api/pressing/commandes`                      | PressingController  | index           | Liste + recherche (`q`) + filtre (`f`)|
| GET     | `/api/pressing/commandes/[i:id]`               | PressingController  | show            | Détail d'un dossier                  |
| POST    | `/api/pressing/commandes`                      | PressingController  | store           | Crée un dossier de pressing          |
| POST    | `/api/pressing/commandes/[i:id]/advance-all`    | PressingController  | advanceAll      | Avance tous les vêtements d'un dossier|
| POST    | `/api/pressing/commandes/[i:id]/pay`            | PressingController  | pay             | Encaissement (partiel ou total)      |
| POST    | `/api/pressing/commandes/[i:id]/delete`         | PressingController  | destroy         | Suppression (dossier entièrement remis)|
| POST    | `/api/pressing/articles/[i:id]/advance`         | PressingController  | advance         | Avance un vêtement d'une étape       |
| POST    | `/api/pressing/articles/[i:id]/recede`          | PressingController  | recede          | Recule un vêtement d'une étape       |
| POST    | `/api/pressing/facture/lookup`                 | PressingController  | lookupFacture   | Relit une facture DGI auprès d'OSAT  |
| GET     | `/api/pressing/ventes-sans-commande`           | PressingController  | ventesSansCommande | Ventes POS sans dossier de pressing |

**Règles métier :**

- La remise au client (étape 6) est **refusée** tant que le dossier n'est pas
  entièrement payé : « Remise impossible : la commande n'est pas entièrement
  payée (solde X FC). »
- La suppression est **refusée** tant qu'un vêtement n'est pas remis :
  « Suppression impossible : le dossier n'est pas entièrement remis au client. »
- L'historique des étapes est écrit dans `audit_logs`
  (`entity = 'pressing_article'`), pas dans une table dédiée.

**Multi-boutique :**

- Le périmètre passe par `shop_id` (colonne de `pressing_commandes`), jamais
  par un ISF codé en dur.
- L'ISF utilisé pour le lookup facture DGI est **celui de l'utilisateur
  connecté** : `shops.isf` de sa boutique (`$_SESSION['shop_id']`). Pour un
  `super_admin` sans boutique rattachée, `company_info.isf` sert de repli.
- Un dossier est toujours rattaché à la boutique de l'utilisateur qui le
  crée. La création est refusée (422) si aucune boutique n'est sélectionnée.
- Le `total` d'un dossier = `Σ (quantité × prix_unitaire stocké)`. Le prix
  stocké est **déjà majoré** : le contrôleur calcule `prix de base ×
  coefficient` à la création. `prix_unitaire` envoyé à l'API = prix de base.

**Query de la liste :**

| Paramètre | Valeurs                                     | Défaut |
| --------- | ------------------------------------------- | ------ |
| `q`       | recherche (dossier, facture, nom, tél.)    | vide   |
| `f`       | `encours` `prete` `retard` `remise` `all`   | `all`  |

---

## Authentification

### Session utilisateur

Après une connexion réussie via `POST /login`, les variables de session suivantes sont définies:

| Variable                       | Type   | Description          |
| ------------------------------ | ------ | -------------------- |
| `$_SESSION['user_id']`         | int    | ID de l'utilisateur  |
| `$_SESSION['nom_utilisateur']` | string | Nom d'utilisateur    |
| `$_SESSION['nom_complet']`     | string | Nom complet          |
| `$_SESSION['role']`            | string | Rôle (admin/vendeur) |

### Connexion (Format JSON pour AJAX)

**Requête:**

```json
{
  "username": "admin",
  "password": "password"
}
```

**Réponse (succès):**

```json
{
  "success": true
}
```

**Réponse (erreur):**

```json
{
  "success": false,
  "message": "Identifiants incorrects"
}
```

---

## Permissions

### Rôles disponibles

| Rôle      | Description                                |
| --------- | ------------------------------------------ |
| `admin`   | Accès complet à toutes les fonctionnalités |
| `vendeur` | Accès limité à la caisse et consultation   |

### Vérifications de permission dans les contrôleurs

| Contrôleur         | Méthode                  | Vérification                    |
| ------------------ | ------------------------ | ------------------------------- |
| ProductController  | create, update, delete   | `$_SESSION['role'] === 'admin'` |
| CategoryController | create, update           | `$_SESSION['role'] === 'admin'` |
| UserController     | create, update           | `$_SESSION['role'] === 'admin'` |
| SaleController     | delete                   | `$_SESSION['role'] === 'admin'` |
| PageController     | utilisateurs, parametres | `$_SESSION['role'] === 'admin'` |

### Codes de réponse HTTP

| Code | Description                                    |
| ---- | ---------------------------------------------- |
| 200  | Succès                                         |
| 400  | Mauvaise requête (paramètres manquants)        |
| 403  | Accès refusé (non authentifié ou non autorisé) |
| 404  | Ressource non trouvée                          |
| 500  | Erreur serveur                                 |

---

## Structure du projet

```
pos_systeme/
├── app/
│   ├── controllers/     # Contrôleurs de l'application
│   │   ├── AuthController.php
│   │   ├── CategoryController.php
│   │   ├── PageController.php
│   │   ├── ProductController.php
│   │   ├── SaleController.php
│   │   └── UserController.php
│   ├── core/            # Classes core (Router, Database)
│   ├── models/          # Modèles de données
│   └── views/           # Vues PHP
├── config/              # Fichiers de configuration
├── public/              # Point d'entrée public
│   └── index.php
├── routes/              # Définition des routes
│   ├── api.php          # Routes API
│   └── web.php          # Routes web
└── vendor/              # Dépendances Composer
```

---

_Document généré le: 24/04/2026_
