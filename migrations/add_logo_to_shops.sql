-- ============================================================================
-- ALTER TABLE : Ajout du champ "logo" à la table shops
-- Stocke la référence relative (ex: media/logo/xxx.jpg) vers le logo du
-- magasin, utilisé sur la facture imprimée (ticket + facture classique).
-- À exécuter si la table shops existe déjà en production.
-- ============================================================================

SET FOREIGN_KEY_CHECKS = 0;

DELIMITER $$

DROP PROCEDURE IF EXISTS add_shops_logo_column_if_missing$$
CREATE PROCEDURE add_shops_logo_column_if_missing()
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_schema = DATABASE()
          AND table_name = 'shops'
          AND column_name = 'logo'
    ) THEN
        ALTER TABLE `shops` ADD COLUMN `logo` VARCHAR(255) NULL DEFAULT NULL AFTER `nid`;
    END IF;
END$$

DELIMITER ;

CALL add_shops_logo_column_if_missing();

DROP PROCEDURE IF EXISTS add_shops_logo_column_if_missing;

SET FOREIGN_KEY_CHECKS = 1;
