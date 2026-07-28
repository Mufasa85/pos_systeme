-- ============================================================================
-- ALTER TABLE : Ajout des champs RCCM/PDV/NID/TOKEN/PORT à la table shops
-- À exécuter si la table shops existe déjà en production
-- ============================================================================

SET FOREIGN_KEY_CHECKS = 0;

-- Méthode compatible MySQL 5.7+/MariaDB 10.2+
-- Vérifie l'existence de chaque colonne avant de l'ajouter

DELIMITER $$

DROP PROCEDURE IF EXISTS add_shops_columns_if_missing$$
CREATE PROCEDURE add_shops_columns_if_missing()
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns 
        WHERE table_schema = DATABASE() 
          AND table_name = 'shops' 
          AND column_name = 'pdv'
    ) THEN
        ALTER TABLE `shops` ADD COLUMN `pdv` VARCHAR(100) DEFAULT NULL AFTER `isf`;
    END IF;

    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns 
        WHERE table_schema = DATABASE() 
          AND table_name = 'shops' 
          AND column_name = 'nid'
    ) THEN
        ALTER TABLE `shops` ADD COLUMN `nid` VARCHAR(100) DEFAULT NULL AFTER `pdv`;
    END IF;

    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns 
        WHERE table_schema = DATABASE() 
          AND table_name = 'shops' 
          AND column_name = 'token'
    ) THEN
        ALTER TABLE `shops` ADD COLUMN `token` VARCHAR(255) DEFAULT NULL AFTER `nid`;
    END IF;

    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns 
        WHERE table_schema = DATABASE() 
          AND table_name = 'shops' 
          AND column_name = 'port'
    ) THEN
        ALTER TABLE `shops` ADD COLUMN `port` VARCHAR(20) DEFAULT NULL AFTER `token`;
    END IF;
END$$

DELIMITER ;

CALL add_shops_columns_if_missing();

DROP PROCEDURE IF EXISTS add_shops_columns_if_missing;

SET FOREIGN_KEY_CHECKS = 1;
