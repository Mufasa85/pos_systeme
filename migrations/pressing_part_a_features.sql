-- Migration: Partie A — Compléter le module Pressing
-- Date: 2026-08-11
-- Description: Ajoute tarification, photos, historique statut, paiements partiels,
--              livraison, catalogue services et consommables.

-- ============================================================================
-- 1. EXTENSION DE LA TABLE PRINCIPALE pressing_depots
-- ============================================================================
ALTER TABLE `pressing_depots`
  ADD COLUMN `adresse_livraison` VARCHAR(255) DEFAULT NULL AFTER `date_prevue`,
  ADD COLUMN `date_retour_prevue` DATETIME DEFAULT NULL AFTER `adresse_livraison`,
  ADD COLUMN `paid_amount` DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER `total`;

-- ============================================================================
-- 2. CATALOGUE DE SERVICES PRESSING
-- ============================================================================
CREATE TABLE IF NOT EXISTS `pressing_services` (
  `id`              INT AUTO_INCREMENT PRIMARY KEY,
  `shop_id`         INT NOT NULL,
  `nom`             VARCHAR(100) NOT NULL,
  `description`     VARCHAR(255) DEFAULT NULL,
  `duree_estimee`   INT DEFAULT 0 COMMENT 'minutes',
  `actif`           TINYINT(1) NOT NULL DEFAULT 1,
  `created_at`      DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY `idx_pressing_services_shop` (`shop_id`),
  CONSTRAINT `fk_pressing_services_shop` FOREIGN KEY (`shop_id`) REFERENCES `shops`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ============================================================================
-- 3. GRILLE DE TARIFICATION
-- ============================================================================
CREATE TABLE IF NOT EXISTS `pressing_tarifs` (
  `id`              INT AUTO_INCREMENT PRIMARY KEY,
  `shop_id`         INT NOT NULL,
  `service_id`      INT NOT NULL,
  `article_type`    VARCHAR(100) NOT NULL,
  `prix_unitaire`   DECIMAL(12,2) NOT NULL,
  `created_at`      DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY `idx_pressing_tarifs_shop` (`shop_id`),
  KEY `idx_pressing_tarifs_service` (`service_id`),
  CONSTRAINT `fk_pressing_tarifs_shop` FOREIGN KEY (`shop_id`) REFERENCES `shops`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_pressing_tarifs_service` FOREIGN KEY (`service_id`) REFERENCES `pressing_services`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ============================================================================
-- 4. ARTICLES — LIAISON AU SERVICE DU CATALOGUE
-- ============================================================================
ALTER TABLE `pressing_articles`
  ADD COLUMN `service_id` INT DEFAULT NULL AFTER `service`,
  ADD KEY `idx_pressing_articles_service_id` (`service_id`),
  ADD CONSTRAINT `fk_pressing_articles_service_id` FOREIGN KEY (`service_id`) REFERENCES `pressing_services`(`id`) ON DELETE SET NULL;

-- ============================================================================
-- 5. PHOTOS DES ARTICLES
-- ============================================================================
CREATE TABLE IF NOT EXISTS `pressing_photos` (
  `id`              INT AUTO_INCREMENT PRIMARY KEY,
  `depot_id`        INT NOT NULL,
  `article_id`      INT DEFAULT NULL,
  `chemin`          VARCHAR(255) NOT NULL,
  `type`            ENUM('etat_initial','final') NOT NULL DEFAULT 'etat_initial',
  `created_at`      DATETIME DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_pressing_photos_depot` (`depot_id`),
  KEY `idx_pressing_photos_article` (`article_id`),
  CONSTRAINT `fk_pressing_photos_depot` FOREIGN KEY (`depot_id`) REFERENCES `pressing_depots`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_pressing_photos_article` FOREIGN KEY (`article_id`) REFERENCES `pressing_articles`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ============================================================================
-- 6. HISTORIQUE DES STATUTS
-- ============================================================================
CREATE TABLE IF NOT EXISTS `pressing_status_history` (
  `id`              INT AUTO_INCREMENT PRIMARY KEY,
  `depot_id`        INT NOT NULL,
  `ancien_statut`   VARCHAR(50) DEFAULT NULL,
  `nouveau_statut`  VARCHAR(50) NOT NULL,
  `changed_by`      INT NOT NULL,
  `created_at`      DATETIME DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_pressing_status_depot` (`depot_id`),
  KEY `idx_pressing_status_user` (`changed_by`),
  CONSTRAINT `fk_pressing_status_depot` FOREIGN KEY (`depot_id`) REFERENCES `pressing_depots`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_pressing_status_user` FOREIGN KEY (`changed_by`) REFERENCES `utilisateurs`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ============================================================================
-- 7. PAIEMENTS PARTIELS / MIXTES
-- ============================================================================
CREATE TABLE IF NOT EXISTS `pressing_paiements` (
  `id`              INT AUTO_INCREMENT PRIMARY KEY,
  `depot_id`        INT NOT NULL,
  `montant`         DECIMAL(12,2) NOT NULL,
  `mode_paiement`   ENUM('cash','mobile_money','carte','virement','autre') NOT NULL DEFAULT 'cash',
  `reference`       VARCHAR(100) DEFAULT NULL,
  `created_by`      INT NOT NULL,
  `created_at`      DATETIME DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_pressing_paiements_depot` (`depot_id`),
  KEY `idx_pressing_paiements_user` (`created_by`),
  CONSTRAINT `fk_pressing_paiements_depot` FOREIGN KEY (`depot_id`) REFERENCES `pressing_depots`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_pressing_paiements_user` FOREIGN KEY (`created_by`) REFERENCES `utilisateurs`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ============================================================================
-- 8. CONSOMMABLES DE NETTOYAGE
-- ============================================================================
CREATE TABLE IF NOT EXISTS `pressing_consumables` (
  `id`              INT AUTO_INCREMENT PRIMARY KEY,
  `shop_id`         INT NOT NULL,
  `nom`             VARCHAR(100) NOT NULL,
  `quantite`        DECIMAL(12,3) NOT NULL DEFAULT 0,
  `unite`           VARCHAR(20) DEFAULT 'unité',
  `stock_minimum`   DECIMAL(12,3) NOT NULL DEFAULT 0,
  `created_at`      DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY `idx_pressing_consumables_shop` (`shop_id`),
  CONSTRAINT `fk_pressing_consumables_shop` FOREIGN KEY (`shop_id`) REFERENCES `shops`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS `pressing_consumable_usages` (
  `id`              INT AUTO_INCREMENT PRIMARY KEY,
  `depot_id`        INT NOT NULL,
  `consumable_id`   INT NOT NULL,
  `quantite`        DECIMAL(12,3) NOT NULL,
  `created_by`      INT NOT NULL,
  `created_at`      DATETIME DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_pressing_usage_depot` (`depot_id`),
  KEY `idx_pressing_usage_consumable` (`consumable_id`),
  CONSTRAINT `fk_pressing_usage_depot` FOREIGN KEY (`depot_id`) REFERENCES `pressing_depots`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_pressing_usage_consumable` FOREIGN KEY (`consumable_id`) REFERENCES `pressing_consumables`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_pressing_usage_user` FOREIGN KEY (`created_by`) REFERENCES `utilisateurs`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
