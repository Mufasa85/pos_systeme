-- ============================================================================
-- MIGRATION : Détails produits dans hotel_extras (Sprint 6 — 2026-10-18)
-- Référence  : docs/PLAN_HOTEL_FACTURATION.md
--
-- But : permettre à la page de facturation hôtel (/hotel/facturation) de
-- calculer le vrai total des extras (prix HT + TS produit + TVA produit) en
-- joignant simplement hotel_extras → produits → taxes, sans avoir à parser
-- la description "Ch. 105 — 2x Coca-Cola" (fragile, best-effort).
--
-- Avant : hotel_extras ne stockait que (description VARCHAR, montant DECIMAL).
--   Le parsing "Nx Nom" côté backend était incertain et l'UI ne pouvait pas
--   appliquer correctement la TS et la TVA par produit.
--
-- Après : hotel_extras stocke les mêmes champs que details_vente :
--   produit_id, quantite, prix_unitaire, remise_*, taxe_specifique_*,
--   tax_rate (snapshot du taux TVA du produit au moment de l'extra).
--
-- Toutes les colonnes ajoutées sont NULLables : rétro-compatible avec les
-- lignes existantes. Les anciens extras garderont produit_id NULL et seront
-- affichés comme aujourd'hui (agrégat par ligne, sans détail produit).
-- ============================================================================

SET NAMES utf8mb4;
SET @sql_mode = (SELECT @@SESSION.sql_mode);
SET SESSION sql_mode = '';

-- produit_id : référence au produit (FK ON DELETE SET NULL pour ne pas
-- bloquer la suppression d'un produit — l'historique des extras reste
-- consultable même si le produit est archivé).
SET @col_exists := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'hotel_extras'
      AND COLUMN_NAME = 'produit_id'
);
SET @stmt := IF(@col_exists = 0,
    'ALTER TABLE `hotel_extras`
       ADD COLUMN `produit_id` INT NULL
         COMMENT "Référence au produit consommé (NULL si extra manuel ou ligne historique)"
         AFTER `vente_id`,
       ADD KEY `hotel_extras_produit_idx` (`produit_id`),
       ADD CONSTRAINT `hotel_extras_produit_fk`
         FOREIGN KEY (`produit_id`) REFERENCES `produits`(`id`)
         ON DELETE SET NULL ON UPDATE CASCADE',
    'SELECT 1');
PREPARE stmt FROM @stmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;-- quantite : DECIMAL(10,3) comme details_vente (supporte les produits au poids)
SET @col_exists := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'hotel_extras'
      AND COLUMN_NAME = 'quantite'
);
SET @stmt := IF(@col_exists = 0,
    "ALTER TABLE `hotel_extras`
       ADD COLUMN `quantite` DECIMAL(10,3) NULL DEFAULT 1
         COMMENT 'Quantite consommee (1 par defaut, NULL pour extras manuels/legacy)'
         AFTER `produit_id`",
    "SELECT 1");
PREPARE stmt FROM @stmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- prix_unitaire : prix HT unitaire snapshot au moment de l'extra
SET @col_exists := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'hotel_extras'
      AND COLUMN_NAME = 'prix_unitaire'
);
SET @stmt := IF(@col_exists = 0,
    "ALTER TABLE `hotel_extras`
       ADD COLUMN `prix_unitaire` DECIMAL(10,2) NULL
         COMMENT 'Prix unitaire HT snapshot au moment de l extra (pour recaler le calcul TS/TVA)'
         AFTER `quantite`",
    "SELECT 1");
PREPARE stmt FROM @stmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- remise_type + remise_value : alignes sur details_vente (futur-proof)
SET @col_exists := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'hotel_extras'
      AND COLUMN_NAME = 'remise_type'
);
SET @stmt := IF(@col_exists = 0,
    "ALTER TABLE `hotel_extras`
       ADD COLUMN `remise_type` ENUM('%', 'CDF') NULL DEFAULT '%'
         COMMENT 'Type de remise (%% ou CDF) snapshot au moment de l extra'
         AFTER `prix_unitaire`",
    "SELECT 1");
PREPARE stmt FROM @stmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @col_exists := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'hotel_extras'
      AND COLUMN_NAME = 'remise_value'
);
SET @stmt := IF(@col_exists = 0,
    "ALTER TABLE `hotel_extras`
       ADD COLUMN `remise_value` DECIMAL(10,2) NULL DEFAULT 0
         COMMENT 'Valeur de remise snapshot au moment de l extra'
         AFTER `remise_type`",
    "SELECT 1");
PREPARE stmt FROM @stmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- taxe_specifique_type + taxe_specifique_value : TS unitaire du produit
SET @col_exists := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'hotel_extras'
      AND COLUMN_NAME = 'taxe_specifique_type'
);
SET @stmt := IF(@col_exists = 0,
    "ALTER TABLE `hotel_extras`
       ADD COLUMN `taxe_specifique_type` ENUM('%', 'CDF') NULL DEFAULT '%'
         COMMENT 'Type TS snapshot au moment de l extra (%% ou CDF)'
         AFTER `remise_value`",
    "SELECT 1");
PREPARE stmt FROM @stmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @col_exists := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'hotel_extras'
      AND COLUMN_NAME = 'taxe_specifique_value'
);
SET @stmt := IF(@col_exists = 0,
    "ALTER TABLE `hotel_extras`
       ADD COLUMN `taxe_specifique_value` DECIMAL(10,2) NULL DEFAULT 0
         COMMENT 'Valeur TS snapshot au moment de l extra'
         AFTER `taxe_specifique_type`",
    "SELECT 1");
PREPARE stmt FROM @stmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- tax_rate : taux TVA snapshot du produit (cf. taxes.taux via produits.taxe_id)
SET @col_exists := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'hotel_extras'
      AND COLUMN_NAME = 'tax_rate'
);
SET @stmt := IF(@col_exists = 0,
    "ALTER TABLE `hotel_extras`
       ADD COLUMN `tax_rate` DECIMAL(5,2) NULL DEFAULT 0
         COMMENT 'Taux TVA snapshot (produits.taxe_id vers taxes.taux au moment de l extra)'
         AFTER `taxe_specifique_value`",
    "SELECT 1");
PREPARE stmt FROM @stmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET SESSION sql_mode = @sql_mode;
