-- ============================================================================
-- MIGRATION : Facturation hôtel (PMS) V1
-- Date       : 2026-10-17
-- Référence  : docs/PLAN_HOTEL_FACTURATION.md
--
-- ⚠ Cette migration est idempotente-friendly : sauter la 1ère partie si elle
--   a déjà été appliquée.
--
-- 1. Ajoute une colonne `sejour_id` (nullable, FK) sur `ventes`.
-- 2. Crée la table `hotel_factures` (1 facture officielle par séjour).
-- ============================================================================

SET NAMES utf8mb4;

-- ── 1. ventes.sejour_id ───────────────────────────────────────────────────
-- On tente l'ALTER en mode permissif. Si tu l'as déjà appliqué, MySQL renverra
-- une erreur "Duplicate column" — ignore-la (la suite de la migration n'est
-- pas affectée).

-- Sécuriser le mode SQL pour éviter que MySQL ne convertisse le charset
SET @sql_mode = (SELECT @@SESSION.sql_mode);
SET SESSION sql_mode = '';

-- Tentative d'ajout de la colonne (idempotent via INFORMATION_SCHEMA check)
SET @col_exists := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'ventes'
      AND COLUMN_NAME = 'sejour_id'
);
SET @stmt := IF(@col_exists = 0,
    'ALTER TABLE `ventes`
       ADD COLUMN `sejour_id` INT NULL
         COMMENT ''Si non-null, vente imputée à un séjour hôtel (extra consommé par le guest).''
         AFTER `client_id`,
       ADD KEY `ventes_sejour_idx` (`sejour_id`),
       ADD CONSTRAINT `ventes_sejour_fk`
         FOREIGN KEY (`sejour_id`) REFERENCES `hotel_sejours`(`id`)
         ON DELETE SET NULL ON UPDATE CASCADE',
    'SELECT 1');
PREPARE stmt FROM @stmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET SESSION sql_mode = @sql_mode;

-- ── 2. Table hotel_factures ───────────────────────────────────────────────
DROP TABLE IF EXISTS `hotel_factures`;
CREATE TABLE `hotel_factures` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `sejour_id` INT NOT NULL,
  `vente_id`  INT NOT NULL COMMENT 'Facture officielle generee dans ventes (invoice_type=HB)',
  `numero_facture` VARCHAR(50) NOT NULL,
  `invoice_type`   VARCHAR(5)  NOT NULL DEFAULT 'HB'
                   COMMENT 'HB = Hotel Bill, regime TVA type B',
  `total_nuits`       DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `total_extras`      DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `remise_pct`        DECIMAL(5,2)  NOT NULL DEFAULT 0.00,
  `remise_montant`    DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `tva_type`          ENUM('A','B','C') NOT NULL DEFAULT 'B'
                      COMMENT 'B = regime hotelier (TVA non collectee)',
  `tva_montant`       DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `total_final`       DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `acompte_precedent` DECIMAL(10,2) NOT NULL DEFAULT 0.00
                      COMMENT 'hotel_paiements du sejour',
  `solde_du`          DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `paiements_json`    JSON NULL
                      COMMENT 'Modes de paiement encaisses a la facturation',
  `statut`    ENUM('brouillon','emise','annulee') NOT NULL DEFAULT 'brouillon',
  `user_id`   INT NOT NULL,
  `shop_id`   INT NULL,
  `notes`     TEXT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `hotel_factures_sejour_unique` (`sejour_id`),
  KEY `hotel_factures_vente_idx` (`vente_id`),
  KEY `hotel_factures_statut_idx` (`statut`),
  KEY `hotel_factures_user_idx` (`user_id`),
  CONSTRAINT `hotel_factures_sejour_fk` FOREIGN KEY (`sejour_id`) REFERENCES `hotel_sejours`(`id`),
  CONSTRAINT `hotel_factures_vente_fk`  FOREIGN KEY (`vente_id`)  REFERENCES `ventes`(`id`),
  CONSTRAINT `hotel_factures_user_fk`   FOREIGN KEY (`user_id`)   REFERENCES `utilisateurs`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
COMMENT='Une facture officielle par sejour (regime TVA type B hotellerie)';