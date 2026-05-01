# Structure de la Base de Données - pos_systeme

*Document généré le 01/05/2026*

---

## Schéma des Relations

```
┌─────────────────┐     ┌─────────────────┐
│  utilisateurs   │     │    categories    │
├─────────────────┤     ├─────────────────┤
│ id (PK)         │     │ id (PK)         │
│ nom_utilisateur │     │ category        │
│ mot_de_passe    │     │ created_at      │
│ nom_complet     │     │ updated_at      │
│ role            │     └────────┬────────┘
│ actif           │              │
└────────┬────────┘              │
         │                       │
         │ 1:N                  │ 1:N
         ▼                       ▼
┌─────────────────┐     ┌─────────────────┐
│     ventes      │     │    produits     │
├─────────────────┤     ├─────────────────┤
│ id (PK)         │◄────│ category_id(FK) │
│ numero_facture  │     │ id (PK)         │
│ sous_total_ht   │     │ code_barres     │
│ tva             │     │ nom             │
│ total           │     │ prix            │
│ vendeur_id (FK) │     │ stock           │
│ date            │     │ stock_minimum   │
└────────┬────────┘     │ image           │
         │              └─────────────────┘
         │ 1:N
         ▼
┌─────────────────┐
│  details_vente  │
├─────────────────┤
│ id (PK)         │
│ vente_id (FK)   │◄────┐
│ produit_id (FK) │◄────┤
│ quantite        │     │
│ prix            │     │
└─────────────────┘     │
                        │
                        ▼
                 ┌───────────────┐
                 │   produits    │
                 └───────────────┘
```

---

## Tables

### 1. `utilisateurs`

| Colonne | Type | Contraintes | Description |
|---------|------|-------------|-------------|
| `id` | INT | PK, AUTO_INCREMENT | Identifiant unique |
| `nom_utilisateur` | VARCHAR(50) | NOT NULL, UNIQUE | Nom de connexion |
| `mot_de_passe` | VARCHAR(255) | NOT NULL | Mot de passe (hashé bcrypt) |
| `nom_complet` | VARCHAR(100) | NOT NULL | Nom complet |
| `role` | ENUM | NOT NULL, DEFAULT 'vendeur' | 'admin' ou 'vendeur' |
| `actif` | TINYINT(1) | NOT NULL, DEFAULT 1 | 1=actif, 0=inactif |

**Relations:**
- `1:N` → `ventes` (un utilisateur peut faire plusieurs ventes)

**Rôles:**
- `admin` : Accès complet (gestion produits, utilisateurs, rapports)
- `vendeur` : Accès limité (caisse, historique de ses ventes)

---

### 2. `categories`

| Colonne | Type | Contraintes | Description |
|---------|------|-------------|-------------|
| `id` | INT | PK, AUTO_INCREMENT | Identifiant unique |
| `category` | VARCHAR(120) | NOT NULL | Nom de la catégorie |
| `created_at` | DATETIME | DEFAULT CURRENT_TIMESTAMP | Date de création |
| `updated_at` | DATETIME | ON UPDATE CURRENT_TIMESTAMP | Date de modification |

**Relations:**
- `1:N` → `produits` (une catégorie peut contenir plusieurs produits)

**Catégories par défaut:**
- Comestible
- Non Comestible
- Service

---

### 3. `produits`

| Colonne | Type | Contraintes | Description |
|---------|------|-------------|-------------|
| `id` | INT | PK, AUTO_INCREMENT | Identifiant unique |
| `code_barres` | VARCHAR(50) | NOT NULL, UNIQUE | Code-barres du produit |
| `nom` | VARCHAR(100) | NOT NULL | Nom du produit |
| `category_id` | INT | FK → categories(id) | Catégorie du produit |
| `prix` | DECIMAL(10,2) | NOT NULL | Prix de vente |
| `stock` | INT | NOT NULL, DEFAULT 0 | Quantité en stock |
| `stock_minimum` | INT | NOT NULL, DEFAULT 10 | Seuil d'alerte stock |
| `image` | VARCHAR(255) | DEFAULT NULL | URL de l'image |

**Relations:**
- `N:1` ← `categories` (appartient à une catégorie)
- `1:N` → `details_vente` (peut être vendu plusieurs fois)

---

### 4. `ventes`

| Colonne | Type | Contraintes | Description |
|---------|------|-------------|-------------|
| `id` | INT | PK, AUTO_INCREMENT | Identifiant unique |
| `numero_facture` | VARCHAR(50) | NOT NULL, UNIQUE | Numéro de facture |
| `sous_total_ht` | DECIMAL(10,2) | NOT NULL | Montant HT |
| `tva` | DECIMAL(10,2) | NOT NULL | Montant TVA (16%) |
| `total` | DECIMAL(10,2) | NOT NULL | Montant TTC |
| `vendeur_id` | INT | FK → utilisateurs(id) | Vendeur ayant fait la vente |
| `date` | DATETIME | DEFAULT CURRENT_TIMESTAMP | Date/heure de la vente |

**Relations:**
- `N:1` ← `utilisateurs` (réalisée par un vendeur)
- `1:N` → `details_vente` (contient plusieurs articles)

**Calculs:**
```
TVA = sous_total_ht × 0.16
total = sous_total_ht + tva
```

---

### 5. `details_vente`

| Colonne | Type | Contraintes | Description |
|---------|------|-------------|-------------|
| `id` | INT | PK, AUTO_INCREMENT | Identifiant unique |
| `vente_id` | INT | FK → ventes(id) | Vente parente |
| `produit_id` | INT | FK → produits(id) | Produit vendu |
| `quantite` | INT | NOT NULL | Quantité vendue |
| `prix` | DECIMAL(10,2) | NOT NULL | Prix unitaire HT |

**Relations:**
- `N:1` ← `ventes` (appartient à une vente)
- `N:1` ← `produits` (référence un produit)

---

## Contraintes d'intégrité référentielle

```sql
-- Suppression en cascade activée sur toutes les clés étrangères
ON DELETE CASCADE
ON UPDATE CASCADE
```

### Comportement:
| Action | Comportement |
|--------|--------------|
| Supprimer une catégorie | Supprime tous les produits associés |
| Supprimer un produit | Supprime les détails de vente associés |
| Supprimer une vente | Supprime les détails de vente associés |
| Supprimer un utilisateur | Supprime les ventes associées |

---

## Index

| Table | Index | Type | Colonne(s) |
|-------|-------|------|------------|
| `utilisateurs` | PRIMARY | PK | id |
| `utilisateurs` | UNIQUE | - | nom_utilisateur |
| `categories` | PRIMARY | PK | id |
| `produits` | PRIMARY | PK | id |
| `produits` | UNIQUE | - | code_barres |
| `produits` | INDEX | - | category_id |
| `ventes` | PRIMARY | PK | id |
| `ventes` | UNIQUE | - | numero_facture |
| `ventes` | INDEX | - | vendeur_id |
| `details_vente` | PRIMARY | PK | id |
| `details_vente` | INDEX | - | vente_id |
| `details_vente` | INDEX | - | produit_id |

---

## Requêtes SQL pour créer la BDD

```sql
-- Création de la base
CREATE DATABASE `pos_system`;
USE `pos_system`;

-- Table utilisateurs
CREATE TABLE `utilisateurs` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `nom_utilisateur` VARCHAR(50) NOT NULL UNIQUE,
  `mot_de_passe` VARCHAR(255) NOT NULL,
  `nom_complet` VARCHAR(100) NOT NULL,
  `role` ENUM('admin','vendeur') NOT NULL DEFAULT 'vendeur',
  `actif` TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table categories
CREATE TABLE `categories` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `category` VARCHAR(120) NOT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table produits
CREATE TABLE `produits` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `code_barres` VARCHAR(50) NOT NULL UNIQUE,
  `nom` VARCHAR(100) NOT NULL,
  `category_id` INT NOT NULL,
  `prix` DECIMAL(10,2) NOT NULL,
  `stock` INT NOT NULL DEFAULT 0,
  `stock_minimum` INT NOT NULL DEFAULT 10,
  `image` VARCHAR(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`category_id`) REFERENCES `categories`(`id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table ventes
CREATE TABLE `ventes` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `numero_facture` VARCHAR(50) NOT NULL UNIQUE,
  `sous_total_ht` DECIMAL(10,2) NOT NULL,
  `tva` DECIMAL(10,2) NOT NULL,
  `total` DECIMAL(10,2) NOT NULL,
  `vendeur_id` INT NOT NULL,
  `date` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`vendeur_id`) REFERENCES `utilisateurs`(`id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table details_vente
CREATE TABLE `details_vente` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `vente_id` INT NOT NULL,
  `produit_id` INT NOT NULL,
  `quantite` INT NOT NULL,
  `prix` DECIMAL(10,2) NOT NULL,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`vente_id`) REFERENCES `ventes`(`id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  FOREIGN KEY (`produit_id`) REFERENCES `produits`(`id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

---

*Fin du document*