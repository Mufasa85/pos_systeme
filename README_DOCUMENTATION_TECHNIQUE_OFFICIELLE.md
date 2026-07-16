# Documentation Technique Officielle - POS System

> **Projet** : pos_systeme  
> **Type** : Système de caisse (Point of Sale / POS)  
> **Langage** : PHP 8+ (architecture MVC maison)  
> **Base de données** : MySQL 9.1 (InnoDB, utf8mb4)  
> **Frontend** : HTML5, CSS3, JavaScript vanilla  
> **Serveur** : Apache avec mod_rewrite  
> **Date de génération** : Juillet 2026

---

## Table des matières

1. [Vue d'ensemble](#vue-densemble)
2. [Stack technique](#stack-technique)
3. [Architecture logicielle](#architecture-logicielle)
4. [Structure des dossiers](#structure-des-dossiers)
5. [Configuration et dépendances](#configuration-et-dépendances)
6. [Point d'entrée et routage](#point-dentrée-et-routage)
7. [Couche base de données](#couche-base-de-données)
8. [Schéma de la base de données](#schéma-de-la-base-de-données)
9. [Contrôleurs](#contrôleurs)
10. [Modèles](#modèles)
11. [Vues et frontend](#vues-et-frontend)
12. [Routes API détaillées](#routes-api-détaillées)
13. [Sécurité](#sécurité)
14. [Impression et facturation](#impression-et-facturation)
15. [Intégrations externes](#intégrations-externes)
16. [Migrations](#migrations)
17. [Déploiement](#déploiement)
18. [Fichiers de documentation complémentaires](#fichiers-de-documentation-complémentaires)
19. [Notes et bonnes pratiques](#notes-et-bonnes-pratiques)

---

## Vue d'ensemble

Le POS System est une application web de gestion de caisse conçue pour les supermarchés, boutiques et petits commerces. Elle permet de gérer les produits, les ventes, les clients, les taxes, les utilisateurs et les paiements de factures (SNEL / REGIDESO). L'application est développée en PHP pur, sans framework lourd, avec une architecture MVC légère, une API REST interne et une base de données MySQL.

Les principales fonctionnalités sont :

- Authentification par session avec rôles `admin` et `vendeur`
- Gestion des produits (CRUD, code-barres, stock, image, catégories, taxes)
- Caisse interactive avec scan, recherche, grille de produits et panier
- Enregistrement des ventes avec calcul automatique de TVA et totaux
- Historique des ventes, factures et reçus imprimables
- Gestion des clients et des types de clients
- Gestion des taxes (groupes de taxe DGI)
- Paiement de factures via API OSAT-Energie (SNEL / REGIDESO)
- Intégration DGI (Direction Générale des Impôts) avec proxy local
- Tableau de bord et analytics (ventes par jour, semaine, mois, vendeur, produit)
- Paramètres du magasin, thème et format d'impression

---

## Stack technique

| Couche | Technologie | Fichier(s) / Dossier(s) |
|--------|-------------|------------------------|
| Backend | PHP 8+ | `app/`, `public/index.php`, `routes/`, `config/` |
| Router | AltoRouter 2.0 (via Composer) | `app/core/Router.php`, `app/App.php` |
| Base de données | MySQL 9.1, PDO | `app/core/Database.php` |
| Mail (prévu) | PHPMailer 7.0 | `composer.json` |
| Frontend | HTML5, CSS3, JS vanilla | `public/assets/js/`, `public/assets/css/`, `app/views/` |
| Polices | Inter, JetBrains Mono (Google Fonts) | `app/views/layout/header.php` |
| Serveur web | Apache + mod_rewrite | `public/.htaccess` |
| Gestion des dépendances | Composer | `composer.json`, `composer.lock` |

---

## Architecture logicielle

L'application suit une architecture **MVC maison** :

- **Model** : accès et manipulation des données (`app/models/`)
- **View** : rendu HTML/PHP (`app/views/`)
- **Controller** : logique métier et orchestration (`app/controllers/`)
- **Core** : classes fondamentales (Router, Database, Security)
- **Routes** : déclaration des routes web et API (`routes/web.php`, `routes/api.php`)
- **Config** : constantes de configuration (`config/config.php`)
- **Public** : point d'entrée unique et assets (`public/`)

Le flux de requête est le suivant :

```
Requête HTTP
    ↓
public/.htaccess (rewrite vers index.php)
    ↓
public/index.php (session_start + autoload + routes)
    ↓
routes/web.php + routes/api.php (déclaration des routes)
    ↓
App\Core\Router::matcher() (AltoRouter)
    ↓
Contrôleur + Méthode
    ↓
Modèle(s) ←→ PDO/MySQL
    ↓
Vue (render) ou réponse JSON
```

---

## Structure des dossiers

```
pos_systeme/
├── app/
│   ├── App.php                 # Singleton AltoRouter
│   ├── controllers/            # Logique métier
│   │   ├── AuthController.php
│   │   ├── BillPaymentController.php
│   │   ├── CategoryController.php
│   │   ├── ClientController.php
│   │   ├── Controller.php      # Classe de base (JSON, status, sanitize)
│   │   ├── InvoiceController.php
│   │   ├── PageController.php
│   │   ├── ProductController.php
│   │   ├── SaleController.php
│   │   ├── SettingsController.php
│   │   ├── TaxController.php
│   │   └── UserController.php
│   ├── core/                   # Classes fondamentales
│   │   ├── Database.php        # Singleton PDO
│   │   ├── Router.php          # Wrapper AltoRouter
│   │   └── Security.php        # CSRF token
│   ├── models/                 # Accès BDD
│   │   ├── Category.php
│   │   ├── Client.php
│   │   ├── InvoiceType.php
│   │   ├── Product.php
│   │   ├── Sale.php
│   │   ├── SaleDetail.php
│   │   ├── Settings.php
│   │   ├── Tax.php
│   │   ├── TypeClient.php
│   │   └── User.php
│   └── views/                  # Templates PHP
│       ├── layout/
│       │   ├── header.php
│       │   ├── footer.php
│       │   ├── header-minimal.php
│       │   ├── footer-minimal.php
│       │   └── print-format-modal.php
│       ├── analytics.php
│       ├── caisse.php
│       ├── categories.php
│       ├── dashboard.php
│       ├── facture-client.php
│       ├── facture-ticket.php
│       ├── facture.php
│       ├── historique.php
│       ├── login.php
│       ├── new-scanner.php
│       ├── parametres.php
│       ├── produits.php
│       ├── recharges.php
│       ├── scanner.php
│       ├── taxes.php
│       ├── test.php
│       └── utilisateurs.php
├── config/
│   └── config.php              # Constantes DB + APP_URL
├── docs/                       # Documentation complémentaire
│   ├── API_OSAT_ENERGIE.md
│   ├── API_REGIDESO_RESPONSE.md
│   ├── OPTION_A_FINALE.md
│   └── STRUCTURE_SIMPLE.md
├── migrations/                 # Scripts SQL d'évolution
│   ├── add_paper_type_setting.sql
│   ├── add_product_type_column.sql
│   ├── add_remise_column.sql
│   ├── add_service_column.sql
│   ├── change_stock_to_float.sql
│   ├── option_a_bill_payment.sql
│   └── remove_api_token_column.sql
├── public/                     # DocumentRoot
│   ├── index.php               # Point d'entrée unique
│   ├── .htaccess               # Réécriture Apache
│   ├── assets/
│   │   ├── css/
│   │   │   ├── mobile-caisse.css
│   │   │   ├── recharges.css
│   │   │   ├── scanner.css
│   │   │   ├── styles.css
│   │   │   └── styles.css.append
│   │   ├── img/                # Favicon, icônes, produits
│   │   └── js/
│   │       ├── app.js          # Logique principale caisse
│   │       ├── paper-type.js
│   │       ├── print-format-override.js
│   │       ├── recharges.js    # Paiement factures SNEL/REGIDESO
│   │       ├── scanner.js
│   │       ├── service-bill-fetcher.js
│   │       ├── test.md
│   │       └── theme.js
│   ├── debug.php
│   ├── simple.php
│   └── test.php
├── routes/
│   ├── api.php                 # Routes API REST
│   └── web.php                 # Routes web (pages)
├── composer.json
├── composer.lock
├── pos_system-2026-07-06_160842-dump.sql   # Dump complet de la BDD
├── pos_system-2026-06-15_210708-dump.sql   # Dump antérieur
├── run_migration.php
├── inject_paper_type.ps1
├── README*.md                  # Documentation existante
├── ROUTES.md
├── readme.md
└── readmedeploie.md
```

---

## Configuration et dépendances

### Composer (`composer.json`)

```json
{
    "require": {
        "phpmailer/phpmailer": "^7.0",
        "altorouter/altorouter": "^2.0"
    },
    "autoload": {
        "psr-4": {
            "App\\": "app/",
            "App\\Models\\": "app/models/",
            "App\\Controllers\\": "app/controllers/",
            "App\\Core\\": "app/core/"
        }
    }
}
```

### Configuration BDD (`config/config.php`)

```php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'pos_system');

define('APP_URL', 'http://localhost/pos_systeme/');
define('BASE_PATH', dirname(__DIR__) . '/');
```

> **Important** : les identifiants sont également hardcodés dans `app/core/Database.php` en plus de `config/config.php`. En production, il faut synchroniser les deux.

---

## Point d'entrée et routage

### `public/index.php`

```php
<?php
session_start();
require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';

use App\Core\Router;

require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'routes/web.php';
require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'routes/api.php';

Router::matcher();
```

### `public/.htaccess`

```apache
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*)$ index.php?url=$1 [L,QSA]
```

Toutes les requêtes non statiques sont redirigées vers `index.php`. Le paramètre `url` est ignoré par AltoRouter qui utilise `$_SERVER['REQUEST_URI']`.

### `app/core/Router.php`

Wrapper statique autour de `AltoRouter`. Fournit les méthodes :

- `Router::get($route, $target)`
- `Router::post($route, $target)`
- `Router::put($route, $target)`
- `Router::delete($route, $target)`
- `Router::name($name)`
- `Router::matcher()`

Les `$target` peuvent être :

- un callable (`function ($params) { ... }`) ;
- un tableau `[NomController::class, 'methodName']`.

### `app/App.php`

Singleton qui instancie et conserve l'objet `AltoRouter`. Aucun `basePath` n'est configuré : les routes sont relatives à la racine du domaine.

---

## Couche base de données

### `app/core/Database.php`

Singleton PDO configuré avec :

- Hôte : `localhost:3306`
- Base : `pos_system`
- Charset : `utf8mb4`
- Mode d'erreur : exceptions
- Fetch mode : `FETCH_ASSOC`
- Emulated prepares : désactivé (requêtes préparées natives)

Méthodes exposées :

| Méthode | Description |
|---------|-------------|
| `getInstance()` | Retourne l'instance unique |
| `getConnection()` | Retourne l'objet PDO |
| `query($sql, $params)` | Exécute et retourne `fetchAll()` |
| `fetchAll($sql, $params)` | Alias de `query()` |
| `fetch($sql, $params)` | Retourne une seule ligne |
| `execute($sql, $params)` | Exécute sans retour, loggue dans `error_log` |
| `beginTransaction()` / `commit()` / `rollBack()` | Gestion des transactions |
| `lastInsertId()` | Dernier ID inséré |

---

## Schéma de la base de données

Le dump officiel est : `pos_system-2026-07-06_160842-dump.sql`. Le schéma contient 10 tables principales.

### 1. `categories`

| Colonne | Type | Description |
|---------|------|-------------|
| `id` | INT PK AUTO_INCREMENT | Identifiant |
| `category` | VARCHAR(120) | Nom de la catégorie |
| `created_at` | DATETIME | Date de création |
| `updated_at` | DATETIME | Date de mise à jour (auto) |

### 2. `type_client`

| Colonne | Type | Description |
|---------|------|-------------|
| `id` | INT PK AUTO_INCREMENT | Identifiant |
| `code` | VARCHAR(50) | Code du type (PP, PM, PC, PL, AO) |
| `description` | TEXT | Description |

Données par défaut : PP, PM, PC, PL, AO.

### 3. `clients`

| Colonne | Type | Description |
|---------|------|-------------|
| `id` | INT PK AUTO_INCREMENT | Identifiant |
| `numero` | VARCHAR(30) | Numéro de téléphone |
| `nom_client` | VARCHAR(50) | Nom du client |
| `code_client` | VARCHAR(20) UNIQUE | Code client auto (CLI-XXX) |
| `type_client_id` | INT FK | Type de client |
| `nif` | TEXT | Numéro d'identification fiscale |
| `adresse` | VARCHAR(255) | Adresse |

FK : `fk_type_client` → `type_client(id)`.

### 4. `utilisateurs`

| Colonne | Type | Description |
|---------|------|-------------|
| `id` | INT PK AUTO_INCREMENT | Identifiant |
| `nom_utilisateur` | VARCHAR(50) UNIQUE | Login |
| `mot_de_passe` | VARCHAR(255) | Hash bcrypt |
| `nom_complet` | VARCHAR(100) | Nom complet |
| `role` | ENUM('admin','vendeur') | Rôle |
| `actif` | TINYINT(1) | 1 = actif, 0 = inactif |
| `agent_code` | VARCHAR(50) UNIQUE | Code agent (optionnel) |

### 5. `taxes`

| Colonne | Type | Description |
|---------|------|-------------|
| `id` | INT PK AUTO_INCREMENT | Identifiant |
| `groupe_taxe` | VARCHAR(50) | Code groupe (Groupe A, B, C...) |
| `etiquette` | VARCHAR(100) | Étiquette visible |
| `description` | TEXT | Description |
| `taux` | DECIMAL(5,2) | Taux en % |
| `couleur` | VARCHAR(7) | Couleur associée |

Les 16 premières taxes sont considérées comme "système" et ne peuvent pas être modifiées/supprimées (`id <= 16`).

### 6. `produits`

| Colonne | Type | Description |
|---------|------|-------------|
| `id` | INT PK AUTO_INCREMENT | Identifiant |
| `code_barres` | VARCHAR(50) UNIQUE | Code-barres |
| `nom` | VARCHAR(100) | Nom du produit |
| `category_id` | INT FK | Catégorie |
| `prix` | DECIMAL(10,2) | Prix de vente HT |
| `stock` | FLOAT | Stock actuel |
| `stock_minimum` | FLOAT | Seuil d'alerte |
| `image` | VARCHAR(255) | Chemin relatif de l'image |
| `taxe_id` | INT FK | Groupe de taxe (défaut 1) |
| `product_type` | VARCHAR(20) | `unite` ou `poids` |
| `prod_service` | ENUM('BIE','SER','TAX') | Classification DGI optionnelle |
| `remise_type` | VARCHAR(10) | `%` ou `CDF` |
| `remise_value` | DECIMAL(10,2) | Valeur de remise |
| `taxe_specifique_type` | VARCHAR(10) | `%` ou `CDF` |
| `taxe_specifique_value` | DECIMAL(10,2) | Valeur taxe spécifique |

FK : `produits_ibfk_1` → `categories(id)`.

### 7. `ventes`

| Colonne | Type | Description |
|---------|------|-------------|
| `id` | INT PK AUTO_INCREMENT | Identifiant |
| `numero_facture` | VARCHAR(50) UNIQUE | Numéro de facture (format `YYYY/XXXXXX`) |
| `sous_total_ht` | DECIMAL(10,2) | Total HT |
| `tva` | DECIMAL(10,2) | Montant TVA |
| `total` | DECIMAL(10,2) | Total TTC |
| `payments` | JSON | Modes de paiement utilisés |
| `vendeur_id` | INT FK | Vendeur |
| `date` | DATETIME | Date de la vente |
| `dateDGI` | VARCHAR(100) | Horodatage DGI |
| `qrCode` | TEXT | QR Code DGI |
| `codeDEFDGI` | VARCHAR(100) | Code DGI |
| `counters` | VARCHAR(100) | Compteurs DGI |
| `nim` | VARCHAR(100) | Numéro d'identification machine |
| `comment` | TEXT | Commentaire |
| `client_id` | INT FK | Client (optionnel) |
| `type_vente` | ENUM('product','bill_payment') | Type de vente |
| `provider_id` | INT | Fournisseur SNEL/REGIDESO |
| `numero_compteur` | VARCHAR(50) | N° compteur pour factures |
| `client_reference` | VARCHAR(100) | Référence client fournisseur |
| `api_response` | TEXT | Réponse brute API JSON |
| `service` | VARCHAR(50) | Service (Eau, Electricite) pour recharges |

FK : `ventes_ibfk_1` → `utilisateurs(id)`, `fk_ventes_client` → `clients(id)`.

### 8. `details_vente`

| Colonne | Type | Description |
|---------|------|-------------|
| `id` | INT PK AUTO_INCREMENT | Identifiant |
| `vente_id` | INT FK | Vente parente |
| `produit_id` | INT FK | Produit vendu |
| `quantite` | INT | Quantité (peut être négative pour avoir) |
| `prix` | DECIMAL(10,2) | Prix unitaire HT |
| `remise_type` | VARCHAR(10) | `%` ou `CDF` |
| `remise_value` | DECIMAL(10,2) | Valeur remise |
| `taxe_specifique_type` | VARCHAR(10) | `%` ou `CDF` |
| `taxe_specifique_value` | DECIMAL(10,2) | Valeur taxe spécifique |

FK : `details_vente_ibfk_1` → `ventes(id)`, `details_vente_ibfk_2` → `produits(id)`.

### 9. `service_providers`

| Colonne | Type | Description |
|---------|------|-------------|
| `id` | INT PK AUTO_INCREMENT | Identifiant |
| `code` | VARCHAR(20) UNIQUE | Code SNEL, REGIDESO |
| `nom` | VARCHAR(100) | Nom complet |
| `type_service` | ENUM('electricity','water') | Type de service |
| `api_endpoint` | VARCHAR(255) | URL API (optionnel) |
| `api_key` | VARCHAR(255) | Clé API (optionnel) |
| `actif` | TINYINT(1) | Actif |
| `created_at` | DATETIME | Date de création |

Données par défaut : SNEL et REGIDESO.

### 10. `settings`

| Colonne | Type | Description |
|---------|------|-------------|
| `id` | INT PK AUTO_INCREMENT | Identifiant |
| `setting_key` | VARCHAR(100) UNIQUE | Clé du paramètre |
| `value` | TEXT | Valeur |
| `created_at` | TIMESTAMP | Création |
| `updated_at` | TIMESTAMP | Mise à jour (auto) |

Clés utilisées : `store_name`, `store_address`, `store_phone`, `store_email`, `store_ice`, `store_rccm`, `store_isf`, `tax_rate`, `theme`, `paper_type`, `token`, `pdv`, `nid`, `port`.

---

## Contrôleurs

| Contrôleur | Rôle | Namespace |
|------------|------|-----------|
| `AuthController` | Login, logout, session | `App\Controllers` |
| `PageController` | Rendu des pages web (dashboard, caisse, produits, historique, analytics, etc.) | `App\Controllers` |
| `ProductController` | CRUD produits + recherche par code-barres | `App\Controllers` |
| `CategoryController` | CRUD catégories | `App\Controllers` |
| `UserController` | CRUD utilisateurs | `App\Controllers` |
| `SaleController` | Création, détails, suppression, numéro de facture | `App\Controllers` |
| `ClientController` | CRUD clients, recherche, types | `App\Controllers` |
| `TaxController` | CRUD taxes | `App\Controllers` |
| `SettingsController` | Paramètres magasin, TVA, thème, format papier | `App\Controllers` |
| `InvoiceController` | Affichage facture, PDF, envoi WhatsApp/SMS, facture publique | `App\Controllers` |
| `BillPaymentController` | Paiement factures SNEL/REGIDESO (legacy + API) | (sans namespace, require manuel) |

La classe de base `Controller` fournit :

- `sanitaze($input)` : `strip_tags` + `htmlspecialchars`
- `status($status)` : définit le code HTTP et retourne l'instance
- `json($array)` : envoie une réponse JSON
- `inputs()` : lit `php://input`

---

## Modèles

| Modèle | Table | Rôle |
|--------|-------|------|
| `Category` | `categories` | CRUD catégories |
| `Client` | `clients` | CRUD clients + jointure type_client |
| `InvoiceType` | - | Types de factures (définitions) |
| `Product` | `produits` | CRUD produits, stock, recherche code-barres |
| `Sale` | `ventes` | Création, liste, détails, génération numéro facture |
| `SaleDetail` | `details_vente` | Lignes de vente |
| `Settings` | `settings` | Clé/valeur paramètres |
| `Tax` | `taxes` | CRUD taxes |
| `TypeClient` | `type_client` | Types de clients |
| `User` | `utilisateurs` | Authentification, CRUD utilisateurs |

Tous les modèles utilisent `App\Core\Database::getInstance()` pour accéder à PDO.

---

## Vues et frontend

### Layout

- `header.php` : balise `<head>`, inclusion CSS/JS, sidebar, variables globales `APP_URL` et `CURRENT_USER`
- `footer.php` : modaux (reçu, preview, formulaire produit), inclusion scripts
- `header-minimal.php` / `footer-minimal.php` : layout minimal pour factures publiques
- `print-format-modal.php` : modal de configuration du format d'impression

### Pages principales

| Vue | Route | Description |
|-----|-------|-------------|
| `login.php` | `/` | Connexion |
| `dashboard.php` | `/dashboard` | Tableau de bord avec stats |
| `caisse.php` | `/caisse` | Interface de caisse |
| `recharges.php` | `/recharges` | Paiement factures SNEL/REGIDESO |
| `produits.php` | `/produits` | Gestion des produits |
| `categories.php` | `/categories` | Gestion des catégories (admin) |
| `utilisateurs.php` | `/utilisateurs` | Gestion utilisateurs (admin) |
| `historique.php` | `/historique` | Historique des ventes |
| `taxes.php` | `/taxes` | Gestion taxes (admin) |
| `parametres.php` | `/parametres` | Paramètres système (admin) |
| `analytics.php` | `/analytics` | Statistiques avancées (admin) |
| `scanner.php` | `/scanner` | Scanner de code-barres (caméra) |
| `new-scanner.php` | `/new-scanner` | Nouveau scanner |
| `facture.php` | `/facture/[id]` | Facture authentifiée |
| `facture-ticket.php` | `/facture?ref=...` | Ticket public |
| `facture-client.php` | `/facture-client/[id]` | Facture client publique |

### JavaScript

- `app.js` : logique principale de la caisse (panier, produits, DGI, facturation, impression)
- `recharges.js` : paiement factures SNEL/REGIDESO via API OSAT-Energie
- `scanner.js` : scanner de code-barres
- `theme.js` : gestion du thème (couleur)
- `paper-type.js` : format d'impression
- `print-format-override.js` : overrides d'impression
- `service-bill-fetcher.js` : helper fetch factures services

### CSS

- `styles.css` : styles principaux
- `mobile-caisse.css` : adaptations mobile
- `recharges.css` : styles page recharges
- `scanner.css` : styles scanner

---

## Routes API détaillées

### Authentification

| Méthode | Route | Contrôleur | Description |
|---------|-------|------------|-------------|
| GET | `/` | `AuthController::showLogin` | Page de login |
| POST | `/login` | `AuthController::login` | Connexion (JSON ou form) |
| GET | `/logout` | `AuthController::logout` | Déconnexion |

### Pages web

| Méthode | Route | Contrôleur | Accès |
|---------|-------|------------|-------|
| GET | `/dashboard` | `PageController::dashboard` | Tous |
| GET | `/caisse` | `PageController::caisse` | Tous |
| GET | `/recharges` | `PageController::recharges` | Tous |
| GET | `/produits` | `PageController::produits` | Tous |
| GET | `/historique` | `PageController::historique` | Tous |
| GET | `/utilisateurs` | `PageController::utilisateurs` | Admin |
| GET | `/categories` | `PageController::categories` | Admin |
| GET | `/taxes` | `PageController::taxes` | Admin |
| GET | `/parametres` | `PageController::parametres` | Admin |
| GET | `/analytics` | `PageController::analytics` | Admin |
| GET | `/scanner` | `PageController::scanner` | Tous |
| GET | `/new-scanner` | `PageController::newScanner` | Tous |
| GET | `/facture/[i:id]` | `InvoiceController::show` | Tous (auth) |
| GET | `/facture` | `InvoiceController::showByRef` | Public |
| GET | `/facture-client/[i:id]` | `InvoiceController::publicInvoice` | Public |
| POST | `/api/facture/[i:id]/send` | `InvoiceController::sendInvoice` | Tous (auth) |
| GET | `/api/facture/[i:id]/pdf` | `InvoiceController::downloadPdf` | Tous (auth) |

### Produits

| Méthode | Route | Contrôleur | Auth |
|---------|-------|------------|------|
| GET | `/api/produits` | `ProductController::index` | Non |
| GET | `/api/produit?code_barres=...` | `ProductController::find` | Non |
| POST | `/api/produit` | `ProductController::create` | Admin |
| POST | `/api/produit/update` | `ProductController::update` | Admin |
| POST | `/api/produit/delete` | `ProductController::delete` | Admin |

### Catégories

| Méthode | Route | Contrôleur | Auth |
|---------|-------|------------|------|
| GET | `/api/categories` | `CategoryController::index` | Non |
| POST | `/api/categories` | `CategoryController::create` | Admin |
| POST | `/api/categories/update` | `CategoryController::update` | Admin |
| POST | `/api/delete/category` | `CategoryController::delete` | Non (⚠️) |

### Utilisateurs

| Méthode | Route | Contrôleur | Auth |
|---------|-------|------------|------|
| GET | `/api/users` | `UserController::all` | Non (⚠️) |
| POST | `/api/create/user` | `UserController::create` | Admin |
| POST | `/api/update/user` | `UserController::update` | Admin |
| POST | `/api/delete/user` | `UserController::delete` | Non (⚠️) |

### Ventes

| Méthode | Route | Contrôleur | Auth |
|---------|-------|------------|------|
| POST | `/api/vente` | `SaleController::create` | Tous (auth) |
| POST | `/api/delete/vente` | `SaleController::delete` | Admin |
| GET | `/api/vente/[i:id]/details` | `SaleController::details` | Non |
| GET | `/api/vente/next-invoice` | `SaleController::nextInvoice` | Non |

### Clients

| Méthode | Route | Contrôleur |
|---------|-------|------------|
| GET | `/api/clients` | `ClientController::index` |
| GET | `/api/client/lookup?numero=...` | `ClientController::lookup` |
| GET | `/api/client/search?numero=...` | `ClientController::searchByNumero` |
| GET | `/api/client/types` | `ClientController::getTypes` |
| POST | `/api/client` | `ClientController::create` |
| PUT | `/api/client/[i:id]` | `ClientController::update` |
| POST | `/api/client/update/[i:id]` | `ClientController::update` |

### Taxes

| Méthode | Route | Contrôleur | Auth |
|---------|-------|------------|------|
| GET | `/api/taxes` | `TaxController::index` | Tous (auth) |
| POST | `/api/taxes` | `TaxController::create` | Admin |
| POST | `/api/taxes/update` | `TaxController::update` | Admin |
| POST | `/api/taxes/delete` | `TaxController::delete` | Admin |

### Paramètres

| Méthode | Route | Contrôleur | Auth |
|---------|-------|------------|------|
| GET | `/api/settings` | `SettingsController::index` | Non |
| POST | `/api/settings` | `SettingsController::update` | Admin |
| POST | `/api/settings/store` | `SettingsController::updateStore` | Admin |
| POST | `/api/settings/tax` | `SettingsController::updateTax` | Admin |
| POST | `/api/settings/theme` | `SettingsController::saveTheme` | Admin |
| GET | `/api/settings/theme` | `SettingsController::getTheme` | Non |
| POST | `/api/settings/paper-type` | `SettingsController::updatePaperType` | Admin |
| GET | `/api/settings/paper-type` | `SettingsController::getPaperType` | Non |

### Proxy / Intégrations externes

| Méthode | Route | Description |
|---------|-------|-------------|
| GET | `/api/dgi` | Proxy GET vers `https://osat-energie.com/dgi/` |
| POST | `/api/dgi` | Proxy POST vers DGI avec token depuis `settings.token` |
| GET/POST | `/api/dgi/sms` | Proxy SMS DGI |
| GET/POST | `/api/service-bill` | Proxy facture enregistrée DGI |
| GET | `/api/currency` | Proxy taux de change `https://osat-energie.com/dgi/currency/` |
| POST | `/api/bill-payment` | Proxy OSAT-Energie SNEL/REGIDESO (évite CORS) |
| GET | `/api/client/search` | Recherche client par numéro (web) |
| GET | `/api/client/types` | Liste des types de clients (web) |

---

## Sécurité

### Authentification

- Authentification par **sessions PHP** (`$_SESSION`)
- Mots de passe hashés avec **`password_hash()` / `password_verify()`** (bcrypt)
- Vérification du rôle `admin` sur les routes sensibles
- Vérification `actif = 1` lors du login

### Requêtes SQL

- Toutes les requêtes utilisent des **requêtes préparées PDO** (`prepare` + `execute` avec paramètres)
- `PDO::ATTR_EMULATE_PREPARES` est désactivé

### Entrées utilisateur

- Sanitisation de base via `Controller::sanitaze()` (`strip_tags` + `htmlspecialchars`)
- Les fichiers uploadés sont vérifiés (extension, type MIME implicite)

### CSRF

- Classe `Security` fournissant un token CSRF en session (`$_SESSION['csrf']`)
- Généré avec `random_bytes(32)`

### Points de vigilance identifiés

- Certaines routes de suppression (`/api/delete/category`, `/api/delete/user`) ne vérifient pas le rôle admin
- La route `/api/users` est publique
- Les identifiants BDD sont hardcodés dans `Database.php` et `config.php`
- Les tokens/codes d'agent ne sont pas toujours masqués dans les logs

---

## Impression et facturation

### Formats supportés

Paramètre `paper_type` dans `settings` : `80mm`, `57mm`, `A4`, `A5`, `Letter`, `Legal`. Défaut : `80mm`.

### Types de facture (frontend)

- `FV` : Facture de Vente
- `EV` : Facture de Vente à l'exportation
- `FT` : Facture d'acompte
- `FA` : Facture d'avoir
- `EA` : Facture d'avoir à l'exportation
- `ET` : Facture d'acompte à l'exportation

Pour les types `FA` et `EA`, les quantités envoyées au backend sont négatives, ce qui **augmente le stock** (retour/avoir). Pour les autres types, les quantités positives **décrémentent le stock**.

### Types de client (frontend)

- `PP` : Personne Physique
- `PM` : Personne Morale
- `PC` : Personne Physique Commerçante
- `PL` : Profession Libérale
- `AO` : Ambassades et Organisations Internationales

### Numérotation des factures

Format généré par `Sale::generateInvoiceNumber()` : `AAAA/XXXXXX` (ex: `2026/000001`). Compteur global annuel basé sur la dernière facture de l'année en cours.

---

## Intégrations externes

### API DGI / OSAT-Energie

L'application communique avec les services OSAT-Energie via des **proxies PHP locaux** pour éviter les problèmes CORS côté navigateur.

| Service | URL distante | Route proxy locale |
|---------|--------------|-------------------|
| DGI GET | `https://osat-energie.com/dgi/` | `GET /api/dgi` |
| DGI POST | `https://osat-energie.com/dgi/` | `POST /api/dgi` |
| SMS DGI | `https://osat-energie.com/dgi/sms/` | `/api/dgi/sms` |
| Facture enregistrée | `https://osat-energie.com/dgi/facture/` | `/api/service-bill` |
| Taux de change | `https://osat-energie.com/dgi/currency/` | `GET /api/currency` |
| Bill Payment OSAT | `https://osat-energie.com/snel_regideso/index.php` | `POST /api/bill-payment` |

Le token DGI est lu depuis `settings` avec la clé `token`.

### Paiement de factures SNEL / REGIDESO

- Fournisseurs stockés dans `service_providers`
- Requête vers API OSAT-Energie avec `compteur` + `service`
- Résultat parsé : `results[annee][mois][{MONTANT, NUMERO_FACTURE}]`
- Les mois sélectionnés sont enregistrés dans `ventes` (`type_vente = 'bill_payment'`) et `details_vente` (`produit_id = NULL`)

---

## Migrations

Les scripts SQL dans `migrations/` documentent l'évolution du schéma :

| Fichier | Description |
|---------|-------------|
| `add_paper_type_setting.sql` | Ajout du paramètre `paper_type` (80mm par défaut) |
| `add_product_type_column.sql` | Colonne `product_type` (unite/poids) + index + `agent_code` sur utilisateurs |
| `add_remise_column.sql` | Colonne `remise` sur produits (legacy) |
| `add_service_column.sql` | Colonne `service` sur ventes pour recharges |
| `change_stock_to_float.sql` | Stock et stock_minimum en FLOAT |
| `option_a_bill_payment.sql` | Table `service_providers` + colonnes bill_payment dans `ventes` |
| `remove_api_token_column.sql` | Suppression d'une colonne token (legacy) |

> **Important** : il n'existe pas de runner de migration automatisé. Les scripts doivent être appliqués manuellement dans l'ordre chronologique ou via `run_migration.php` (si configuré).

---

## Déploiement

### Environnement local (Laragon / XAMPP / WAMP)

1. Cloner le projet dans `c:\laragon\www\Php_Pure\pos_systeme`
2. Créer la base `pos_system` dans MySQL
3. Importer `pos_system-2026-07-06_160842-dump.sql`
4. Vérifier `config/config.php` et `app/core/Database.php`
5. Lancer `http://localhost/pos_systeme/`
6. Se connecter avec un utilisateur existant du dump

### Déploiement en production (Hostinger / sous-domaine)

Un guide détaillé est fourni dans `readmedeploie.md`. Points clés :

- Le dossier `public/` doit être le **DocumentRoot**
- Sous-domaine pointant vers `public_html/caisse/public`
- PHP 8.0+ avec extensions `pdo_mysql`, `mbstring`, `curl`, `json`
- Exécuter `composer install --no-dev --optimize-autoloader` ou uploader `vendor/`
- Forcer HTTPS dans `.htaccess`
- Permissions 755 sur dossiers, 644 sur fichiers sensibles

---

## Fichiers de documentation complémentaires

| Fichier | Contenu |
|---------|---------|
| `readme.md` | Présentation générale et fonctionnalités |
| `README-BDD.md` | Schéma relationnel et détails des tables |
| `README-MODIF.md` | Historique des modifications récentes |
| `README_VENTES.md` | Flux des données de ventes |
| `ROUTES.md` | Liste détaillée des routes web et API |
| `readmedeploie.md` | Guide de déploiement Hostinger |
| `docs/API_OSAT_ENERGIE.md` | Intégration API OSAT-Energie |
| `docs/API_REGIDESO_RESPONSE.md` | Format réponse REGIDESO |
| `docs/OPTION_A_FINALE.md` | Option A pour bill payment dans `ventes` |
| `docs/STRUCTURE_SIMPLE.md` | Stratégie table `ventes` modifiée |

---

## Notes et bonnes pratiques

- **Ne jamais** exposer `app/`, `config/`, `routes/`, `vendor/` ou les dumps SQL via le web. Le `.htaccess` actuel ne bloque pas explicitement ces dossiers ; il est recommandé d'ajouter des règles de protection en production.
- Toujours appliquer les migrations avant de lancer une nouvelle version.
- Sauvegarder régulièrement la base de données (les dumps SQL sont présents à la racine).
- Vérifier que le token DGI est bien configuré dans les paramètres avant d'utiliser les fonctionnalités DGI.
- Les tests de paiement de factures doivent être réalisés avec des compteurs de test fournis par OSAT-Energie.

---

*Fin de la documentation technique officielle.*
