-- Migration: Création des tables Restaurant & Pressing
-- Date: 2026-08-09
-- Description: Tables dédiées aux modules Restaurant et Pressing (voir docs/README_RESTAURANT_PRESSING.md)
--              N'affecte aucune table existante.

-- ============================================================================
-- MODULE RESTAURANT
-- ============================================================================

CREATE TABLE IF NOT EXISTS `restaurant_tables` (
  `id`          INT AUTO_INCREMENT PRIMARY KEY,
  `shop_id`     INT NOT NULL,
  `numero`      VARCHAR(20) NOT NULL,
  `nom`         VARCHAR(100) DEFAULT NULL,
  `capacite`    INT NOT NULL DEFAULT 4,
  `etat`        ENUM('libre','occupee','reservee','nettoyage') NOT NULL DEFAULT 'libre',
  `created_at`  DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY `idx_restaurant_tables_shop` (`shop_id`),
  CONSTRAINT `fk_restaurant_tables_shop` FOREIGN KEY (`shop_id`) REFERENCES `shops`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS `restaurant_categories` (
  `id`          INT AUTO_INCREMENT PRIMARY KEY,
  `shop_id`     INT NOT NULL,
  `nom`         VARCHAR(100) NOT NULL,
  `description` VARCHAR(255) DEFAULT NULL,
  KEY `idx_restaurant_categories_shop` (`shop_id`),
  CONSTRAINT `fk_restaurant_categories_shop` FOREIGN KEY (`shop_id`) REFERENCES `shops`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS `restaurant_menu_items` (
  `id`                INT AUTO_INCREMENT PRIMARY KEY,
  `shop_id`           INT NOT NULL,
  `categorie_id`      INT NOT NULL,
  `nom`               VARCHAR(150) NOT NULL,
  `description`       TEXT DEFAULT NULL,
  `image`             VARCHAR(255) DEFAULT NULL,
  `prix`              DECIMAL(12,2) NOT NULL,
  `temps_preparation` INT DEFAULT 0 COMMENT 'minutes',
  `disponible`        TINYINT(1) NOT NULL DEFAULT 1,
  `created_at`        DATETIME DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_restaurant_menu_shop` (`shop_id`),
  KEY `idx_restaurant_menu_categorie` (`categorie_id`),
  CONSTRAINT `fk_restaurant_menu_shop` FOREIGN KEY (`shop_id`) REFERENCES `shops`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_restaurant_menu_categorie` FOREIGN KEY (`categorie_id`) REFERENCES `restaurant_categories`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS `restaurant_commandes` (
  `id`          INT AUTO_INCREMENT PRIMARY KEY,
  `shop_id`     INT NOT NULL,
  `table_id`    INT NOT NULL,
  `serveur_id`  INT NOT NULL COMMENT 'utilisateurs.id',
  `statut`      ENUM('ouverte','envoyee_cuisine','servie','payee','annulee') NOT NULL DEFAULT 'ouverte',
  `sous_total`  DECIMAL(12,2) NOT NULL DEFAULT 0,
  `taxes`       DECIMAL(12,2) NOT NULL DEFAULT 0,
  `remise`      DECIMAL(12,2) NOT NULL DEFAULT 0,
  `total`       DECIMAL(12,2) NOT NULL DEFAULT 0,
  `vente_id`    INT DEFAULT NULL COMMENT 'lien vers ventes.id une fois payée',
  `created_at`  DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY `idx_restaurant_commandes_shop` (`shop_id`),
  KEY `idx_restaurant_commandes_table` (`table_id`),
  KEY `idx_restaurant_commandes_serveur` (`serveur_id`),
  CONSTRAINT `fk_restaurant_commandes_shop` FOREIGN KEY (`shop_id`) REFERENCES `shops`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_restaurant_commandes_table` FOREIGN KEY (`table_id`) REFERENCES `restaurant_tables`(`id`),
  CONSTRAINT `fk_restaurant_commandes_serveur` FOREIGN KEY (`serveur_id`) REFERENCES `utilisateurs`(`id`),
  CONSTRAINT `fk_restaurant_commandes_vente` FOREIGN KEY (`vente_id`) REFERENCES `ventes`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS `restaurant_commande_details` (
  `id`             INT AUTO_INCREMENT PRIMARY KEY,
  `commande_id`    INT NOT NULL,
  `menu_item_id`   INT NOT NULL,
  `quantite`       INT NOT NULL DEFAULT 1,
  `prix_unitaire`  DECIMAL(12,2) NOT NULL,
  `statut_cuisine` ENUM('en_attente','en_preparation','pret','servi') NOT NULL DEFAULT 'en_attente',
  `started_at`     DATETIME DEFAULT NULL COMMENT 'horodatage du clic Commencer',
  `commentaire`    VARCHAR(255) DEFAULT NULL,
  `created_at`     DATETIME DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_restaurant_details_commande` (`commande_id`),
  KEY `idx_restaurant_details_menu` (`menu_item_id`),
  CONSTRAINT `fk_restaurant_details_commande` FOREIGN KEY (`commande_id`) REFERENCES `restaurant_commandes`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_restaurant_details_menu` FOREIGN KEY (`menu_item_id`) REFERENCES `restaurant_menu_items`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ============================================================================
-- MODULE PRESSING
-- ============================================================================

CREATE TABLE IF NOT EXISTS `pressing_depots` (
  `id`             INT AUTO_INCREMENT PRIMARY KEY,
  `shop_id`        INT NOT NULL,
  `numero`         VARCHAR(30) NOT NULL,
  `client_id`      INT NOT NULL,
  `statut`         ENUM('recu','en_lavage','en_sechage','en_repassage','pret','livre') NOT NULL DEFAULT 'recu',
  `sous_total`     DECIMAL(12,2) NOT NULL DEFAULT 0,
  `remise`         DECIMAL(12,2) NOT NULL DEFAULT 0,
  `total`          DECIMAL(12,2) NOT NULL DEFAULT 0,
  `vente_id`       INT DEFAULT NULL COMMENT 'lien vers ventes.id une fois encaissé',
  `qr_code`        VARCHAR(255) DEFAULT NULL,
  `code_barre`     VARCHAR(255) DEFAULT NULL,
  `date_reception` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `date_prevue`    DATETIME DEFAULT NULL,
  `date_livraison` DATETIME DEFAULT NULL,
  `created_by`     INT NOT NULL COMMENT 'utilisateurs.id',
  UNIQUE KEY `uq_pressing_numero` (`numero`),
  KEY `idx_pressing_shop` (`shop_id`),
  KEY `idx_pressing_client` (`client_id`),
  CONSTRAINT `fk_pressing_shop` FOREIGN KEY (`shop_id`) REFERENCES `shops`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_pressing_client` FOREIGN KEY (`client_id`) REFERENCES `clients`(`id`),
  CONSTRAINT `fk_pressing_vente` FOREIGN KEY (`vente_id`) REFERENCES `ventes`(`id`),
  CONSTRAINT `fk_pressing_created_by` FOREIGN KEY (`created_by`) REFERENCES `utilisateurs`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS `pressing_articles` (
  `id`            INT AUTO_INCREMENT PRIMARY KEY,
  `depot_id`      INT NOT NULL,
  `nom_article`   VARCHAR(100) NOT NULL,
  `quantite`      INT NOT NULL DEFAULT 1,
  `etat_initial`  VARCHAR(255) DEFAULT NULL,
  `commentaire`   VARCHAR(255) DEFAULT NULL,
  `service`       ENUM('lavage','repassage','nettoyage_sec','express') NOT NULL,
  `prix_unitaire` DECIMAL(12,2) NOT NULL,
  `prix_total`    DECIMAL(12,2) NOT NULL,
  KEY `idx_pressing_articles_depot` (`depot_id`),
  CONSTRAINT `fk_pressing_articles_depot` FOREIGN KEY (`depot_id`) REFERENCES `pressing_depots`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
