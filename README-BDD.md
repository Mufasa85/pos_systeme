# Structure de la Base de Données - pos_systeme

*Document généré le 01/05/2026*
*Mises à jour le 01/05/2026 (ajout clients et taxes)*

---

## Schéma des Relations (Version 3)

```
┌─────────────────┐     ┌─────────────────┐     ┌─────────────────┐
│  type_client    │     │   utilisateurs  │     │    categories   │
├─────────────────┤     ├─────────────────┤     ├─────────────────┤
│ id (PK)         │     │ id (PK)         │     │ id (PK)         │
│ nom             │     │ nom_utilisateur │     │ category        │
│ description     │     │ mot_de_passe    │     │ created_at      │
│ actif           │     │ nom_complet     │     │ updated_at      │
└────────┬────────┘     │ role            │     └────────┬────────┘
         │ 1:N          │ actif           │              │
         ▼              └────────┬────────┘              │
┌─────────────────┐              │                       │
│    clients      │              │ 1:N                   │ 1:N
├─────────────────┤              ▼                       ▼
│ id (PK)         │     ┌─────────────────┐     ┌─────────────────┐
│ nom             │◄────│     ventes      │     │    produits     │
│ numero          │     ├─────────────────┤     ├─────────────────┤
│ code_client     │     │ id (PK)         │◄────│ category_id(FK) │
│ type_client_id  │     │ numero_facture  │     │ id (PK)         │
│ actif           │     │ client_id (FK)  │     │ code_barres     │
└────────┬────────┘     │ vendeur_id(FK)  │     │ nom             │
         │              │ sous_total_ht   │     │ prix_ht         │
         │              │ tva             │     │ groupe_taxe(FK) │
         │              │ total           │     │ stock           │
         │              │ date            │     │ stock_minimum   │
         │              └────────┬────────┘     │ image           │
         │                       │              └─────────────────┘
         │                       │ 1:N
         │                       ▼
         │               ┌─────────────────┐
         │               │  details_vente  │
         │               ├─────────────────┤
         │               │ id (PK)         │
         │               │ vente_id (FK)   │◄────┐
         │               │ produit_id (FK) │◄────┤
         │               │ quantite        │     │
         │               │ prix            │     │
         │               └─────────────────┘     │
         │                                       │
         │                                       ▼
         │                               ┌─────────────────┐
         │                               │     produits    │
         │                               └─────────────────┘

┌─────────────────┐
│     taxes       │
├─────────────────┤
│ id (PK)         │
│ groupe_taxe     │
│ etiquette       │
│ description     │
│ taux            │
│ actif           │
└────────┬────────┘
         │ 1:N
         ▼
┌─────────────────┐
│    produits     │
└─────────────────┘
```

---

## Tables Existantes

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
- `N:1` ← `taxes` (appartient à un groupe de taxe)
- `1:N` → `details_vente` (peut être vendu plusieurs fois)

---

### 4. `ventes`

| Colonne | Type | Contraintes | Description |
|---------|------|-------------|-------------|
| `id` | INT | PK, AUTO_INCREMENT | Identifiant unique |
| `numero_facture` | VARCHAR(50) | NOT NULL, UNIQUE | Numéro de facture |
| `client_id` | INT | FK → clients(id), NULL | Client (optionnel) |
| `vendeur_id` | INT | FK → utilisateurs(id) | Vendeur ayant fait la vente |
| `sous_total_ht` | DECIMAL(10,2) | NOT NULL | Montant HT |
| `tva` | DECIMAL(10,2) | NOT NULL | Montant TVA (16%) |
| `total` | DECIMAL(10,2) | NOT NULL | Montant TTC |
| `date` | DATETIME | DEFAULT CURRENT_TIMESTAMP | Date/heure de la vente |

**Relations:**
- `N:1` ← `clients` (vente pour un client, optionnel)
- `N:1` ← `utilisateurs` (réalisée par un vendeur)
- `1:N` → `details_vente` (contient plusieurs articles)

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

## ✨ NOUVELLES TABLES (Proposition)

### 6. `type_client`

| Colonne | Type | Contraintes | Description |
|---------|------|-------------|-------------|
| `id` | INT | PK, AUTO_INCREMENT | Identifiant unique |
| `nom` | VARCHAR(50) | NOT NULL | Nom du type (ex: Particulier, Entreprise) |
| `description` | TEXT | NULL | Description du type |
| `actif` | TINYINT(1) | NOT NULL, DEFAULT 1 | 1=actif, 0=inactif |
| `created_at` | DATETIME | DEFAULT CURRENT_TIMESTAMP | Date de création |

**Relations:**
- `1:N` → `clients` (un type peut être appliqué à plusieurs clients)

**Exemples de données:**
| id | nom | description |
|----|-----|-------------|
| 1 | Particulier | Client personne physique |
| 2 | Entreprise | Client personne morale |

---

### 7. `clients`

| Colonne | Type | Contraintes | Description |
|---------|------|-------------|-------------|
| `id` | INT | PK, AUTO_INCREMENT | Identifiant unique |
| `nom` | VARCHAR(100) | NOT NULL | Nom du client |
| `numero` | VARCHAR(30) | NOT NULL | Numéro de téléphone |
| `code_client` | VARCHAR(20) | NOT NULL, UNIQUE | Code client (ex: CLI-001) |
| `type_client_id` | INT | FK → type_client(id) | Type de client |
| `adresse` | VARCHAR(255) | NULL | Adresse |
| `email` | VARCHAR(100) | NULL | Email |
| `actif` | TINYINT(1) | NOT NULL, DEFAULT 1 | 1=actif, 0=inactif |
| `created_at` | DATETIME | DEFAULT CURRENT_TIMESTAMP | Date de création |

**Relations:**
- `N:1` ← `type_client` (appartient à un type)
- `1:N` → `ventes` (un client peut avoir plusieurs achats)

**Exemples de données:**
| code_client | nom | type_client_id |
|-------------|-----|----------------|
| CLI-001 | Jean Dupont | 1 (Particulier) |
| CLI-002 | SARL Muamba | 2 (Entreprise) |
| CLI-003 | Marie Kabongo | 1 (Particulier) |

---

### 8. `taxes`

| Colonne | Type | Contraintes | Description |
|---------|------|-------------|-------------|
| `id` | INT | PK, AUTO_INCREMENT | Identifiant unique |
| `groupe_taxe` | VARCHAR(50) | NOT NULL | Groupe de taxe (ex: TVACONF, TVARED) |
| `etiquette` | VARCHAR(100) | NOT NULL | Étiquette visible (ex: "TVA 16%") |
| `description` | TEXT | NULL | Description de la taxe |
| `taux` | DECIMAL(5,2) | NOT NULL, DEFAULT 0 | Taux en pourcentage (ex: 16.00) |
| `actif` | TINYINT(1) | NOT NULL, DEFAULT 1 | 1=actif, 0=inactif |
| `created_at` | DATETIME | DEFAULT CURRENT_TIMESTAMP | Date de création |

**Relations:**
- `1:N` → `produits` (un groupe de taxe peut être appliqué à plusieurs produits)

**Exemples de données:**
| groupe_taxe | etiquette | taux | description |
|-------------|-----------|------|-------------|
| EXONERE | Exonéré | 0.00 | Produit exonéré de TVA |
| TVACONF | TVA Confédérale | 16.00 | TVA standard RDC |
| TVARED | TVA Réduite | 8.00 | TVA réduite pour certains produits |
| TVAZERO | TVA 0% | 0.00 | Produits à taux zéro |

---

## Modifications nécessaires sur `produits`

La table `produits` devra être modifiée pour inclure la clé étrangère vers `taxes`:

```sql
-- Nouvelle colonne à ajouter
ALTER TABLE produits ADD COLUMN taxe_id INT DEFAULT 1;

-- Nouvelle contrainte
ALTER TABLE produits ADD FOREIGN KEY (taxe_id) REFERENCES taxes(id)
    ON DELETE SET NULL ON UPDATE CASCADE;
```

---

## Modifications nécessaires sur `ventes`

La table `ventes` devra être modifiée pour inclure la clé étrangère vers `clients`:

```sql
-- Nouvelle colonne à ajouter
ALTER TABLE ventes ADD COLUMN client_id INT NULL;

-- Nouvelle contrainte
ALTER TABLE ventes ADD FOREIGN KEY (client_id) REFERENCES clients(id)
    ON DELETE SET NULL ON UPDATE CASCADE;
```

---

## Requêtes SQL pour créer les nouvelles tables

```sql
-- ===================================
-- TABLE : type_client
-- ===================================
CREATE TABLE IF NOT EXISTS `type_client` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `code` VARCHAR(50) NOT NULL,
  `description` TEXT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insertion des types par défaut
INSERT INTO `type_client` (`nom`, `description`) VALUES
('Particulier', 'Client personne physique'),
('Entreprise', 'Client personne morale');

-- ===================================
-- TABLE : clients
-- ===================================
CREATE TABLE IF NOT EXISTS `clients` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `nom` VARCHAR(100) NOT NULL,
  `numero` VARCHAR(30) NOT NULL,
  `code_client` VARCHAR(20) NOT NULL UNIQUE,
  `type_client_id` INT NOT NULL,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`type_client_id`) REFERENCES `type_client`(`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ===================================
-- TABLE : taxes
-- ===================================
CREATE TABLE IF NOT EXISTS `taxes` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `groupe_taxe` VARCHAR(50) NOT NULL,
  `etiquette` VARCHAR(100) NOT NULL,
  `description` TEXT NULL,
  `taux` DECIMAL(5,2) NOT NULL DEFAULT 0,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insertion des taxes par défaut
INSERT INTO `taxes` (`groupe_taxe`, `etiquette`, `description`, `taux`) VALUES
('EXONERE', 'Exonéré', 'Produit exonéré de toute taxe', 0.00),
('TVACONF', 'TVA Confédérale', 'TVA standard RDC', 16.00),
('TVARED', 'TVA Réduite', 'TVA réduite pour produits spécifiques', 8.00),
('TVAZERO', 'TVA 0%', 'Produit au taux zéro', 0.00);
```

---

## Schéma simplifié des nouvelles relations

```
TYPE_CLIENT
   │ 1:N
   ▼
CLIENTS
   │ 1:N
   ▼
VENTES ──── 1:N ──── DETAILS_VENTE
   │                         │
   │                        1:N
   │                         │
   │                         ▼
   │                      PRODUITS
   │                         │
   │                        1:N
   │                         │
   │                         ▼
   │                      TAXES
   │
   1:N
   │
   ▼
UTILISATEURS
```

---

## Résumé des changements

| Action | Table | Description |
|--------|-------|-------------|
| AJOUT | `type_client` | Types de clients (Particulier, Entreprise) |
| AJOUT | `clients` | Clients avec référence au type |
| AJOUT | `taxes` | Groupes de taxes pour les produits |
| MODIF | `produits` | Ajouter `taxe_id` FK |
| MODIF | `ventes` | Ajouter `client_id` FK |

---

*Fin du document*