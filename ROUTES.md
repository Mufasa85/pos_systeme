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
