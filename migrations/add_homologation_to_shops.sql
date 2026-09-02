-- ============================================================================
-- ALTER TABLE : Ajout du champ "homologation" à la table shops
-- Permet au super_admin de marquer un magasin comme homologué DGI
-- (licence/RCCM en règle). Cette information est envoyée à la DGI dans la
-- clé "store_homologation" lors de la validation de facture.
-- À exécuter si la table shops existe déjà en production.
-- ============================================================================

SET FOREIGN_KEY_CHECKS = 0;

DELIMITER $$

DROP PROCEDURE IF EXISTS add_shops_homologation_column_if_missing$$
CREATE PROCEDURE add_shops_homologation_column_if_missing()
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_schema = DATABASE()
          AND table_name = 'shops'
          AND column_name = 'homologation'
    ) THEN
        ALTER TABLE `shops` ADD COLUMN `homologation` TINYINT(1) NOT NULL DEFAULT 0 AFTER `isf`;
    END IF;
END$$

DELIMITER ;

CALL add_shops_homologation_column_if_missing();

DROP PROCEDURE IF EXISTS add_shops_homologation_column_if_missing;

SET FOREIGN_KEY_CHECKS = 1;
