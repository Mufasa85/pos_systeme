-- ============================================================================
-- MIGRATION : POS System Multi-Boutique Evolution
-- Date       : Juillet 2026
-- Backup     : pos_system-2026-07-16_212007-dump.sql
-- Description: Ajoute le support multi-boutique, 2FA/OTP, notifications,
--              audit logs, archivage, rate limiting
-- ============================================================================
-- IMPORTANT : Exécuter sur une copie de la BDD avant la production !
-- ============================================================================

SET FOREIGN_KEY_CHECKS = 0;

-- ============================================================================
-- 1. NOUVELLES TABLES
-- ============================================================================

-- ----------------------------------------------------------------------------
-- 1.1 Table `service_types` (types de service)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `service_types` (
  `id`          INT NOT NULL AUTO_INCREMENT,
  `name`        VARCHAR(50) NOT NULL,
  `created_at`  DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_service_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Insert default service types
INSERT INTO `service_types` (`name`) VALUES
  ('Caisse'),
  ('Quincaillerie'),
  ('Restaurant'),
  ('Reparation'),
  ('Livraison'),
  ('Bijouterie'),
  ('Coiffure');

-- ----------------------------------------------------------------------------
-- 1.2 Table `company_info` (informations entreprise)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `company_info` (
  `id`          INT NOT NULL AUTO_INCREMENT,
  `name`        VARCHAR(100) DEFAULT NULL,
  `address`     VARCHAR(255) DEFAULT NULL,
  `email`       VARCHAR(100) DEFAULT NULL,
  `pdv`         VARCHAR(50) DEFAULT NULL,
  `phone`       VARCHAR(30) DEFAULT NULL,
  `ice`         VARCHAR(50) DEFAULT NULL,
  `rccm`        VARCHAR(50) DEFAULT NULL,
  `isf`         VARCHAR(50) DEFAULT NULL,
  `nid`         VARCHAR(100) DEFAULT NULL,
  `token`       VARCHAR(255) DEFAULT NULL,
  `port`        VARCHAR(10) DEFAULT NULL,
  `created_at`  DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Insert default company info row
INSERT INTO `company_info` (name, address, email, pdv, phone, ice, rccm, isf, nid, token, port)
VALUES (NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL);

-- ----------------------------------------------------------------------------
-- 1.3 Table `shops` (boutiques)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `shops` (
  `id`          INT NOT NULL AUTO_INCREMENT,
  `nom`         VARCHAR(100) NOT NULL,
  `code`        VARCHAR(20) NOT NULL,
  `adresse`     VARCHAR(255) DEFAULT NULL,
  `telephone`   VARCHAR(30) DEFAULT NULL,
  `email`       VARCHAR(100) DEFAULT NULL,
  `ice`         VARCHAR(50) DEFAULT NULL,
  `rccm`        VARCHAR(50) DEFAULT NULL,
  `isf`         VARCHAR(50) DEFAULT NULL,
  `pdv`         VARCHAR(100) DEFAULT NULL,
  `nid`         VARCHAR(100) DEFAULT NULL,
  `token`       VARCHAR(255) DEFAULT NULL,
  `port`        VARCHAR(20) DEFAULT NULL,
  `service_type_id` INT DEFAULT NULL,
  `actif`       TINYINT(1) NOT NULL DEFAULT 1,
  `created_at`  DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_shop_code` (`code`),
  KEY `idx_shop_service_type` (`service_type_id`),
  CONSTRAINT `fk_shop_service_type` FOREIGN KEY (`service_type_id`) REFERENCES `service_types`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ----------------------------------------------------------------------------
-- 1.4 Table `audit_logs` (journal d'activité)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `audit_logs` (
  `id`          INT NOT NULL AUTO_INCREMENT,
  `user_id`     INT DEFAULT NULL,
  `shop_id`     INT DEFAULT NULL,
  `action`      VARCHAR(50) NOT NULL,
  `entity`      VARCHAR(50) NOT NULL,
  `entity_id`   INT DEFAULT NULL,
  `details`     JSON DEFAULT NULL,
  `ip_address`  VARCHAR(45) DEFAULT NULL,
  `created_at`  DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_audit_user` (`user_id`),
  KEY `idx_audit_shop` (`shop_id`),
  KEY `idx_audit_action` (`action`),
  KEY `idx_audit_date` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ----------------------------------------------------------------------------
-- 1.5 Table `login_attempts` (rate limiting)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `login_attempts` (
  `id`           INT NOT NULL AUTO_INCREMENT,
  `username`     VARCHAR(50) NOT NULL,
  `ip_address`   VARCHAR(45) NOT NULL,
  `attempted_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_login_user` (`username`),
  KEY `idx_login_ip` (`ip_address`),
  KEY `idx_login_date` (`attempted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ----------------------------------------------------------------------------
-- 1.6 Table `otp_codes` (double authentification OTP)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `otp_codes` (
  `id`          INT NOT NULL AUTO_INCREMENT,
  `user_id`     INT NOT NULL,
  `code`        VARCHAR(6) NOT NULL,
  `type`        ENUM('login','password_reset') NOT NULL DEFAULT 'login',
  `channel`     ENUM('email','sms') NOT NULL DEFAULT 'email',
  `expires_at`  DATETIME NOT NULL,
  `used`        TINYINT(1) NOT NULL DEFAULT 0,
  `created_at`  DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_otp_user` (`user_id`),
  KEY `idx_otp_code` (`code`),
  KEY `idx_otp_expires` (`expires_at`),
  CONSTRAINT `fk_otp_user` FOREIGN KEY (`user_id`) REFERENCES `utilisateurs`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ----------------------------------------------------------------------------
-- 1.7 Table `password_resets` (récupération mot de passe)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `password_resets` (
  `id`          INT NOT NULL AUTO_INCREMENT,
  `user_id`     INT NOT NULL,
  `token`       VARCHAR(64) NOT NULL,
  `channel`     ENUM('email','sms') NOT NULL,
  `expires_at`  DATETIME NOT NULL,
  `used`        TINYINT(1) NOT NULL DEFAULT 0,
  `created_at`  DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_reset_token` (`token`),
  KEY `idx_reset_user` (`user_id`),
  KEY `idx_reset_expires` (`expires_at`),
  CONSTRAINT `fk_reset_user` FOREIGN KEY (`user_id`) REFERENCES `utilisateurs`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ----------------------------------------------------------------------------
-- 1.8 Table `notifications` (alertes internes)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `notifications` (
  `id`          INT NOT NULL AUTO_INCREMENT,
  `user_id`     INT DEFAULT NULL,
  `shop_id`     INT DEFAULT NULL,
  `type`        ENUM('stock_low','sale_target','suspicious_action','system') NOT NULL,
  `title`       VARCHAR(150) NOT NULL,
  `message`     TEXT NOT NULL,
  `link`        VARCHAR(255) DEFAULT NULL,
  `is_read`     TINYINT(1) NOT NULL DEFAULT 0,
  `sent_email`  TINYINT(1) NOT NULL DEFAULT 0,
  `sent_sms`    TINYINT(1) NOT NULL DEFAULT 0,
  `created_at`  DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_notif_user` (`user_id`),
  KEY `idx_notif_shop` (`shop_id`),
  KEY `idx_notif_type` (`type`),
  KEY `idx_notif_read` (`is_read`),
  KEY `idx_notif_date` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ============================================================================
-- 2. MODIFICATIONS DES TABLES EXISTANTES
-- ============================================================================

-- ----------------------------------------------------------------------------
-- 2.1 `utilisateurs` : ajout rôle super_admin + shop_id + email + telephone + 2FA
-- ----------------------------------------------------------------------------
ALTER TABLE `utilisateurs`
  MODIFY COLUMN `role` ENUM('super_admin','admin','vendeur') NOT NULL DEFAULT 'vendeur';

ALTER TABLE `utilisateurs`
  ADD COLUMN `shop_id` INT DEFAULT NULL AFTER `role`,
  ADD COLUMN `email` VARCHAR(100) DEFAULT NULL AFTER `agent_code`,
  ADD COLUMN `telephone` VARCHAR(30) DEFAULT NULL AFTER `email`,
  ADD COLUMN `two_factor_enabled` TINYINT(1) NOT NULL DEFAULT 1 AFTER `telephone`,
  ADD COLUMN `profile_image` VARCHAR(255) DEFAULT NULL AFTER `two_factor_enabled`;

ALTER TABLE `utilisateurs`
  ADD KEY `idx_user_shop` (`shop_id`);

-- ----------------------------------------------------------------------------
-- 2.2 `categories` : ajout shop_id
-- ----------------------------------------------------------------------------
ALTER TABLE `categories`
  ADD COLUMN `shop_id` INT DEFAULT NULL AFTER `category`;

ALTER TABLE `categories`
  ADD KEY `idx_cat_shop` (`shop_id`);

-- ----------------------------------------------------------------------------
-- 2.3 `produits` : ajout shop_id
-- ----------------------------------------------------------------------------
ALTER TABLE `produits`
  ADD COLUMN `shop_id` INT DEFAULT NULL AFTER `category_id`;

ALTER TABLE `produits`
  ADD KEY `idx_prod_shop` (`shop_id`);

-- ----------------------------------------------------------------------------
-- 2.4 `ventes` : ajout shop_id
-- ----------------------------------------------------------------------------
ALTER TABLE `ventes`
  ADD COLUMN `shop_id` INT DEFAULT NULL AFTER `vendeur_id`;

ALTER TABLE `ventes`
  ADD KEY `idx_vente_shop` (`shop_id`);

-- ----------------------------------------------------------------------------
-- 2.5 `clients` : ajout shop_id
-- ----------------------------------------------------------------------------
ALTER TABLE `clients`
  ADD COLUMN `shop_id` INT DEFAULT NULL AFTER `adresse`;

ALTER TABLE `clients`
  ADD KEY `idx_client_shop` (`shop_id`);

-- ----------------------------------------------------------------------------
-- 2.6 `settings` : ajout shop_id + modification contrainte UNIQUE
-- ----------------------------------------------------------------------------
ALTER TABLE `settings`
  ADD COLUMN `shop_id` INT DEFAULT NULL AFTER `setting_key`;

ALTER TABLE `settings`
  ADD KEY `idx_setting_shop` (`shop_id`);

ALTER TABLE `settings`
  DROP INDEX `setting_key`;

ALTER TABLE `settings`
  ADD UNIQUE KEY `uq_setting_shop` (`setting_key`, `shop_id`);

-- ============================================================================
-- 3. BOUTIQUE PAR DÉFAUT + MIGRATION DES DONNÉES EXISTANTES
-- ============================================================================

-- Créer une boutique par défaut avec les infos du settings actuel
INSERT INTO `shops` (`id`, `nom`, `code`, `adresse`, `telephone`, `email`, `ice`, `rccm`, `isf`, `actif`)
SELECT
  1,
  COALESCE((SELECT `value` FROM `settings` WHERE `setting_key` = 'store_name' LIMIT 1), 'Boutique Principale'),
  'MAIN',
  COALESCE((SELECT `value` FROM `settings` WHERE `setting_key` = 'store_address' LIMIT 1), NULL),
  COALESCE((SELECT `value` FROM `settings` WHERE `setting_key` = 'store_phone' LIMIT 1), NULL),
  COALESCE((SELECT `value` FROM `settings` WHERE `setting_key` = 'store_email' LIMIT 1), NULL),
  COALESCE((SELECT `value` FROM `settings` WHERE `setting_key` = 'store_ice' LIMIT 1), NULL),
  COALESCE((SELECT `value` FROM `settings` WHERE `setting_key` = 'store_rccm' LIMIT 1), NULL),
  COALESCE((SELECT `value` FROM `settings` WHERE `setting_key` = 'store_isf' LIMIT 1), NULL),
  1
ON DUPLICATE KEY UPDATE `nom` = VALUES(`nom`);

-- Rattacher toutes les données existantes à la boutique par défaut (id=1)
UPDATE `utilisateurs` SET `shop_id` = 1 WHERE `shop_id` IS NULL;
UPDATE `categories` SET `shop_id` = 1 WHERE `shop_id` IS NULL;
UPDATE `produits` SET `shop_id` = 1 WHERE `shop_id` IS NULL;
UPDATE `ventes` SET `shop_id` = 1 WHERE `shop_id` IS NULL;
UPDATE `clients` SET `shop_id` = 1 WHERE `shop_id` IS NULL;
UPDATE `settings` SET `shop_id` = 1 WHERE `shop_id` IS NULL;

-- ============================================================================
-- 4. CLÉS ÉTRANGÈRES (après insertion des données)
-- ============================================================================

ALTER TABLE `utilisateurs`
  ADD CONSTRAINT `fk_user_shop` FOREIGN KEY (`shop_id`) REFERENCES `shops`(`id`) ON DELETE SET NULL;

ALTER TABLE `categories`
  ADD CONSTRAINT `fk_cat_shop` FOREIGN KEY (`shop_id`) REFERENCES `shops`(`id`) ON DELETE CASCADE;

ALTER TABLE `produits`
  ADD CONSTRAINT `fk_prod_shop` FOREIGN KEY (`shop_id`) REFERENCES `shops`(`id`) ON DELETE CASCADE;

ALTER TABLE `ventes`
  ADD CONSTRAINT `fk_vente_shop` FOREIGN KEY (`shop_id`) REFERENCES `shops`(`id`) ON DELETE SET NULL;

ALTER TABLE `clients`
  ADD CONSTRAINT `fk_client_shop` FOREIGN KEY (`shop_id`) REFERENCES `shops`(`id`) ON DELETE SET NULL;

ALTER TABLE `settings`
  ADD CONSTRAINT `fk_setting_shop` FOREIGN KEY (`shop_id`) REFERENCES `shops`(`id`) ON DELETE CASCADE;

ALTER TABLE `notifications`
  ADD CONSTRAINT `fk_notif_user` FOREIGN KEY (`user_id`) REFERENCES `utilisateurs`(`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_notif_shop` FOREIGN KEY (`shop_id`) REFERENCES `shops`(`id`) ON DELETE CASCADE;

-- ============================================================================
-- 5. PROMOUVOIR UN ADMIN EN SUPER_ADMIN
-- ============================================================================

-- Le premier admin trouvé devient super_admin avec shop_id = NULL (voit tout)
UPDATE `utilisateurs`
  SET `role` = 'super_admin', `shop_id` = NULL
  WHERE `role` = 'admin'
  ORDER BY `id` ASC
  LIMIT 1;

-- ============================================================================
-- 6. TABLES D'ARCHIVE
-- ============================================================================

-- Table d'archive des ventes (même structure + shop_id déjà ajouté)
CREATE TABLE IF NOT EXISTS `ventes_archive` LIKE `ventes`;

-- Supprimer les contraintes FK de la table archive (les archives n'ont pas besoin de FK)
-- On recrée la table sans FK pour être sûr
DROP TABLE IF EXISTS `ventes_archive`;
CREATE TABLE `ventes_archive` (
  `id` INT NOT NULL,
  `numero_facture` VARCHAR(50) NOT NULL,
  `sous_total_ht` DECIMAL(10,2) NOT NULL,
  `tva` DECIMAL(10,2) NOT NULL,
  `total` DECIMAL(10,2) NOT NULL,
  `payments` JSON DEFAULT NULL,
  `vendeur_id` INT NOT NULL,
  `shop_id` INT DEFAULT NULL,
  `date` DATETIME DEFAULT NULL,
  `dateDGI` VARCHAR(100) DEFAULT NULL,
  `qrCode` TEXT,
  `codeDEFDGI` VARCHAR(100) DEFAULT NULL,
  `counters` VARCHAR(100) DEFAULT NULL,
  `nim` VARCHAR(100) DEFAULT NULL,
  `comment` TEXT,
  `client_id` INT DEFAULT NULL,
  `type_vente` ENUM('product','bill_payment') DEFAULT 'product',
  `provider_id` INT DEFAULT NULL,
  `numero_compteur` VARCHAR(50) DEFAULT NULL,
  `client_reference` VARCHAR(100) DEFAULT NULL,
  `api_response` TEXT,
  `service` VARCHAR(50) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_archive_date` (`date`),
  KEY `idx_archive_shop` (`shop_id`),
  KEY `idx_archive_vendeur` (`vendeur_id`),
  KEY `idx_archive_facture` (`numero_facture`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table d'archive des détails de vente
CREATE TABLE IF NOT EXISTS `details_vente_archive` (
  `id` INT NOT NULL,
  `vente_id` INT NOT NULL,
  `produit_id` INT NOT NULL,
  `quantite` INT NOT NULL,
  `prix` DECIMAL(10,2) NOT NULL,
  `remise_type` VARCHAR(10) DEFAULT 'percent',
  `remise_value` DECIMAL(10,2) DEFAULT '0.00',
  `taxe_specifique_type` VARCHAR(10) DEFAULT '%',
  `taxe_specifique_value` DECIMAL(10,2) DEFAULT '0.00',
  PRIMARY KEY (`id`),
  KEY `idx_detail_archive_vente` (`vente_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ============================================================================
-- 7. RÉACTIVER LES CLÉS ÉTRANGÈRES
-- ============================================================================

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================================
-- MIGRATION TERMINÉE
-- ============================================================================
-- Vérifications post-migration :
-- 1. SELECT COUNT(*) FROM shops;                     -- Doit retourner >= 1
-- 2. SELECT * FROM utilisateurs WHERE role = 'super_admin';  -- Doit retourner 1 user
-- 3. SELECT COUNT(*) FROM produits WHERE shop_id IS NULL;    -- Doit retourner 0
-- 4. SELECT COUNT(*) FROM ventes WHERE shop_id IS NULL;      -- Doit retourner 0
-- 5. SELECT COUNT(*) FROM categories WHERE shop_id IS NULL;  -- Doit retourner 0
-- ============================================================================
