-- Migration: Partie B — Compléter le module Restaurant (on site, sans livraison ni stock)
-- Date: 2026-08-12
-- Description: Ajoute réservations, addition partagée, options de plats,
--              paiement mixte, QR tables et fidélité. Pas de livraison, ni stock, ni pourboire.

-- ============================================================================
-- 1. RÉSERVATIONS DE TABLES
-- ============================================================================
CREATE TABLE IF NOT EXISTS `restaurant_reservations` (
  `id`              INT AUTO_INCREMENT PRIMARY KEY,
  `shop_id`         INT NOT NULL,
  `table_id`        INT DEFAULT NULL,
  `client_nom`      VARCHAR(150) DEFAULT NULL,
  `client_telephone` VARCHAR(30) DEFAULT NULL,
  `client_id`       INT DEFAULT NULL,
  `date_heure`      DATETIME NOT NULL,
  `nb_personnes`    INT NOT NULL DEFAULT 1,
  `statut`          ENUM('confirmee','annulee','terminee','non_show') NOT NULL DEFAULT 'confirmee',
  `commentaire`     VARCHAR(255) DEFAULT NULL,
  `created_by`      INT NOT NULL,
  `created_at`      DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY `idx_restaurant_reservations_shop` (`shop_id`),
  KEY `idx_restaurant_reservations_table` (`table_id`),
  KEY `idx_restaurant_reservations_date` (`date_heure`),
  CONSTRAINT `fk_restaurant_reservations_shop` FOREIGN KEY (`shop_id`) REFERENCES `shops`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_restaurant_reservations_table` FOREIGN KEY (`table_id`) REFERENCES `restaurant_tables`(`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_restaurant_reservations_client` FOREIGN KEY (`client_id`) REFERENCES `clients`(`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_restaurant_reservations_user` FOREIGN KEY (`created_by`) REFERENCES `utilisateurs`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ============================================================================
-- 2. LIEN COMMANDE / CLIENT / RÉSERVATION
-- ============================================================================
ALTER TABLE `restaurant_commandes`
  ADD COLUMN `client_id` INT DEFAULT NULL AFTER `table_id`,
  ADD COLUMN `paid_amount` DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER `vente_id`,
  ADD COLUMN `parent_commande_id` INT DEFAULT NULL AFTER `paid_amount`,
  ADD COLUMN `merged_from` VARCHAR(255) DEFAULT NULL AFTER `parent_commande_id`,
  ADD COLUMN `merged_at` DATETIME DEFAULT NULL AFTER `merged_from`,
  ADD COLUMN `reservation_id` INT DEFAULT NULL AFTER `merged_at`,
  ADD KEY `idx_restaurant_commandes_client` (`client_id`),
  ADD KEY `idx_restaurant_commandes_reservation` (`reservation_id`),
  ADD CONSTRAINT `fk_restaurant_commandes_client` FOREIGN KEY (`client_id`) REFERENCES `clients`(`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_restaurant_commandes_reservation` FOREIGN KEY (`reservation_id`) REFERENCES `restaurant_reservations`(`id`) ON DELETE SET NULL;

-- ============================================================================
-- 3. ADDITION PARTAGÉE (SPLIT BILL)
-- ============================================================================
CREATE TABLE IF NOT EXISTS `restaurant_split_bills` (
  `id`              INT AUTO_INCREMENT PRIMARY KEY,
  `commande_id`     INT NOT NULL,
  `label`           VARCHAR(100) NOT NULL DEFAULT 'Part',
  `total`           DECIMAL(12,2) NOT NULL DEFAULT 0,
  `paid_amount`     DECIMAL(12,2) NOT NULL DEFAULT 0,
  `created_at`      DATETIME DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_restaurant_split_bills_commande` (`commande_id`),
  CONSTRAINT `fk_restaurant_split_bills_commande` FOREIGN KEY (`commande_id`) REFERENCES `restaurant_commandes`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

ALTER TABLE `restaurant_commande_details`
  ADD COLUMN `split_bill_id` INT DEFAULT NULL AFTER `commentaire`,
  ADD KEY `idx_restaurant_details_split` (`split_bill_id`),
  ADD CONSTRAINT `fk_restaurant_details_split` FOREIGN KEY (`split_bill_id`) REFERENCES `restaurant_split_bills`(`id`) ON DELETE SET NULL;

-- ============================================================================
-- 4. PAIEMENTS MIXTES (SANS POURBOIRE)
-- ============================================================================
CREATE TABLE IF NOT EXISTS `restaurant_paiements` (
  `id`              INT AUTO_INCREMENT PRIMARY KEY,
  `commande_id`     INT NOT NULL,
  `split_bill_id`   INT DEFAULT NULL,
  `montant`         DECIMAL(12,2) NOT NULL,
  `mode_paiement`   ENUM('cash','mobile_money','carte','virement','autre') NOT NULL DEFAULT 'cash',
  `reference`       VARCHAR(100) DEFAULT NULL,
  `created_by`      INT NOT NULL,
  `created_at`      DATETIME DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_restaurant_paiements_commande` (`commande_id`),
  KEY `idx_restaurant_paiements_split` (`split_bill_id`),
  KEY `idx_restaurant_paiements_user` (`created_by`),
  CONSTRAINT `fk_restaurant_paiements_commande` FOREIGN KEY (`commande_id`) REFERENCES `restaurant_commandes`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_restaurant_paiements_split` FOREIGN KEY (`split_bill_id`) REFERENCES `restaurant_split_bills`(`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_restaurant_paiements_user` FOREIGN KEY (`created_by`) REFERENCES `utilisateurs`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ============================================================================
-- 5. OPTIONS / MODIFICATEURS DE PLATS
-- ============================================================================
CREATE TABLE IF NOT EXISTS `restaurant_menu_item_options` (
  `id`              INT AUTO_INCREMENT PRIMARY KEY,
  `menu_item_id`    INT NOT NULL,
  `nom`             VARCHAR(100) NOT NULL,
  `prix_supp`       DECIMAL(12,2) NOT NULL DEFAULT 0,
  `obligatoire`     TINYINT(1) NOT NULL DEFAULT 0,
  `actif`           TINYINT(1) NOT NULL DEFAULT 1,
  `created_at`      DATETIME DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_restaurant_menu_options_item` (`menu_item_id`),
  CONSTRAINT `fk_restaurant_menu_options_item` FOREIGN KEY (`menu_item_id`) REFERENCES `restaurant_menu_items`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS `restaurant_commande_detail_options` (
  `id`                INT AUTO_INCREMENT PRIMARY KEY,
  `commande_detail_id` INT NOT NULL,
  `option_id`         INT NOT NULL,
  `nom`               VARCHAR(100) NOT NULL,
  `prix_supp`         DECIMAL(12,2) NOT NULL DEFAULT 0,
  `created_at`        DATETIME DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_restaurant_detail_options_detail` (`commande_detail_id`),
  KEY `idx_restaurant_detail_options_option` (`option_id`),
  CONSTRAINT `fk_restaurant_detail_options_detail` FOREIGN KEY (`commande_detail_id`) REFERENCES `restaurant_commande_details`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_restaurant_detail_options_option` FOREIGN KEY (`option_id`) REFERENCES `restaurant_menu_item_options`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ============================================================================
-- 6. MENUS / FORMULES
-- ============================================================================
CREATE TABLE IF NOT EXISTS `restaurant_menus` (
  `id`              INT AUTO_INCREMENT PRIMARY KEY,
  `shop_id`         INT NOT NULL,
  `nom`             VARCHAR(150) NOT NULL,
  `description`     VARCHAR(255) DEFAULT NULL,
  `prix`            DECIMAL(12,2) NOT NULL,
  `actif`           TINYINT(1) NOT NULL DEFAULT 1,
  `created_at`      DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY `idx_restaurant_menus_shop` (`shop_id`),
  CONSTRAINT `fk_restaurant_menus_shop` FOREIGN KEY (`shop_id`) REFERENCES `shops`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS `restaurant_menu_compositions` (
  `id`              INT AUTO_INCREMENT PRIMARY KEY,
  `menu_id`         INT NOT NULL,
  `menu_item_id`    INT NOT NULL,
  `quantite`        INT NOT NULL DEFAULT 1,
  `ordre`           INT NOT NULL DEFAULT 0,
  `created_at`      DATETIME DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_restaurant_menu_compositions_menu` (`menu_id`),
  KEY `idx_restaurant_menu_compositions_item` (`menu_item_id`),
  CONSTRAINT `fk_restaurant_menu_compositions_menu` FOREIGN KEY (`menu_id`) REFERENCES `restaurant_menus`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_restaurant_menu_compositions_item` FOREIGN KEY (`menu_item_id`) REFERENCES `restaurant_menu_items`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ============================================================================
-- 7. QR CODE PAR TABLE
-- ============================================================================
ALTER TABLE `restaurant_tables`
  ADD COLUMN `qr_code` VARCHAR(255) DEFAULT NULL AFTER `etat`,
  ADD COLUMN `qr_token` VARCHAR(64) DEFAULT NULL AFTER `qr_code`,
  ADD UNIQUE KEY `uq_restaurant_tables_qr_token` (`qr_token`);

-- ============================================================================
-- 8. FIDÉLITÉ CLIENT
-- ============================================================================
CREATE TABLE IF NOT EXISTS `restaurant_fidelite` (
  `id`              INT AUTO_INCREMENT PRIMARY KEY,
  `shop_id`         INT NOT NULL,
  `client_id`       INT NOT NULL,
  `points`          INT NOT NULL DEFAULT 0,
  `total_depense`   DECIMAL(12,2) NOT NULL DEFAULT 0,
  `created_at`      DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_restaurant_fidelite_client_shop` (`shop_id`, `client_id`),
  KEY `idx_restaurant_fidelite_client` (`client_id`),
  CONSTRAINT `fk_restaurant_fidelite_shop` FOREIGN KEY (`shop_id`) REFERENCES `shops`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_restaurant_fidelite_client` FOREIGN KEY (`client_id`) REFERENCES `clients`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS `restaurant_fidelite_regles` (
  `id`              INT AUTO_INCREMENT PRIMARY KEY,
  `shop_id`         INT NOT NULL,
  `montant_depense` DECIMAL(12,2) NOT NULL,
  `points_gagnes`   INT NOT NULL,
  `points_requis`   INT NOT NULL DEFAULT 0,
  `remise_points`   DECIMAL(12,2) NOT NULL DEFAULT 0,
  `actif`           TINYINT(1) NOT NULL DEFAULT 1,
  `created_at`      DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY `idx_restaurant_fidelite_regles_shop` (`shop_id`),
  CONSTRAINT `fk_restaurant_fidelite_regles_shop` FOREIGN KEY (`shop_id`) REFERENCES `shops`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
