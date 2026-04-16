# 🧾 POS System (Point of Sale) - Gestion de Supermarché

## 📌 Description du projet

Ce projet est un **système de gestion de caisse (POS)** conçu pour les supermarchés, boutiques et petits commerces.
Il permet de **gérer les produits, effectuer des ventes rapidement, scanner des codes-barres et générer des factures**, avec une interface simple adaptée à des utilisateurs sans compétences techniques.

L’application est développée en **PHP (architecture MVC)** avec une **API REST** et une base de données **MySQL**.

---

##  Objectifs

* Simplifier la gestion des ventes en magasin
* Permettre une utilisation rapide et intuitive
* Réduire les erreurs humaines
* Fournir un système professionnel, léger et efficace

---

## Technologies utilisées

* **Frontend** : HTML, CSS, JavaScript
* **Backend** : PHP (MVC)
* **Base de données** : MySQL
* **API** : REST (JSON)
* **Serveur** : Apache (XAMPP / WAMP)

---

## 🏗️ Architecture du projet

Le projet suit une architecture **MVC (Model - View - Controller)** :

```
/pos-system
│
├── /app
│   ├── /controllers   → Logique métier (Auth, Produits, Ventes)
│   ├── /models        → Accès base de données
│   ├── /views         → Interfaces utilisateur
│   ├── /core          → Base (Router, Database)
│
├── /public            → Point d’entrée (index.php)
├── /routes            → Définition des routes web et API
├── /config            → Configuration
```

---

## 🔐 Fonctionnalités principales

### 1. Authentification

* Connexion utilisateur (admin / vendeur)
* Gestion des sessions

---

### 2. Gestion des produits

* Ajouter un produit (nom, prix, code-barre, image)
* Modifier / supprimer un produit
* Stock de produits

---

### 3. Système de caisse (POS)

Le vendeur peut ajouter des produits à la facture de 3 façons :

*  Scanner un code-barre
*  Entrer un code-barre manuellement
*  Rechercher par nom
*  Cliquer sur l’image du produit

 Les produits sont automatiquement ajoutés au panier.

---

### 4. Facturation

* Affichage du panier en temps réel
* Calcul automatique du total
* Gestion des quantités
* Impression de facture

---

### 5. Gestion des ventes

* Enregistrement des ventes
* Historique des transactions
* Détails des ventes

---

##  API REST

###  Produits

| Méthode | Endpoint                 | Description        |
| ------- | ------------------------ | ------------------ |
| GET     | /api/products            | Liste des produits |
| GET     | /api/product?barcode=XXX | Trouver un produit |
| POST    | /api/product             | Ajouter un produit |

---

### 🧾 Ventes

| Méthode | Endpoint  | Description           |
| ------- | --------- | --------------------- |
| POST    | /api/sale | Enregistrer une vente |

---

## 🗄️ Structure de la base de données

### users

* id
* username
* password
* role

### products

* id
* name
* price
* barcode
* image
* stock

### sales

* id
* total
* user_id
* date

### sale_details

* id
* sale_id
* product_id
* quantity
* price

---

##  Fonctionnement global

1. L’utilisateur se connecte
2. Accède à la caisse
3. Ajoute des produits (scan / recherche / clic)
4. Les produits s’ajoutent au panier
5. Clique sur “Payer”
6. Le système :

   * enregistre la vente
   * sauvegarde les détails
   * génère la facture

---

##  Interface utilisateur

Le système est conçu pour être :

* Simple
* Rapide
* Intuitif
* Sans complexité

 Idéal pour des utilisateurs non techniques.

---

##  Installation

### 1. Cloner le projet

```
git clone https://github.com/ton-repo/pos-system.git
```

---

### 2. Configurer la base de données

* Créer une base MySQL : `pos_system`
* Importer le fichier SQL fourni

---

### 3. Configurer la connexion

Modifier :

```
/app/core/Database.php
```

---

### 4. Lancer le projet

* Démarrer Apache et MySQL (XAMPP/WAMP)
* Accéder via :

```
http://localhost/pos-system/public
```

---

##  Sécurité (basique)

* Sessions utilisateur
* Protection des routes
* Requêtes préparées (PDO)

---

##  Améliorations futures

* Gestion avancée du stock
* Multi-utilisateurs
* Dashboard avec statistiques
* Impression PDF avancée
* Intégration scanner physique

---

##  Auteur

Projet développé pour un système de gestion de magasin simple, rapide et efficace.

---

## Licence

Ce projet est libre d’utilisation pour des besoins éducatifs ou commerciaux.

---


/pos-system
│
├── /app
│   ├── /controllers
│   │   ├── AuthController.php
│   │   ├── ProductController.php
│   │   ├── SaleController.php
│   │
│   ├── /models
│   │   ├── User.php
│   │   ├── Product.php
│   │   ├── Sale.php
│   │   ├── SaleDetail.php
│   │
│   ├── /views
│   │   ├── login.php
│   │   ├── caisse.php
│   │   ├── produits.php
│   │   ├── historique.php
│   │
│   ├── /core
│   │   ├── Database.php
│   │   ├── Router.php
│   │
│
├── /public
│   ├── index.php
│   ├── .htaccess
│   ├── /assets (css, js)
│
├── /routes
│   ├── web.php
│   ├── api.php
│
└── /config
    └── config.php
