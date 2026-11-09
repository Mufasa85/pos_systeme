-- ============================================================================
-- ALTER TABLE : Ajout du champ "acces_extras" à la table shops
--
-- OBJECTIF
--   Permettre au super_admin d'accorder (ou retirer) à une boutique précise
--   l'accès à la fonctionnalité « Extras » de la caisse (imputation d'une
--   vente sur un séjour hôtelier) ET aux routes API associées :
--     - GET  /api/hotel/sejours/en-cours
--     - POST /api/hotel/sejours/{id}/extras-from-cart
--
-- CONTEXTE
--   Avant cette migration, l'accès aux Extras dépendait du feature flag API
--   « hotel » (RechargeAccessService::canAccessHotel). Le flag est global et
--   ne permettait pas un réglage par boutique. La configuration vit désormais
--   dans `shops.acces_extras`, cochée par le super-admin dans /shops.
--
-- VALEUR PAR DÉFAUT
--   0 = accès refusé (fail-closed). Le super_admin doit cocher explicitement
--   la case sur la boutique concernée pour ouvrir l'accès aux Extras.
--
-- RAPPEL
--   Cette migration ne touche pas au module Hôtel lui-même (réservations,
--   facturation, réception) qui reste conditionné par le flag API « hotel ».
--   Seule la fonctionnalité « Extras » bascule sur cette configuration.
--
-- À exécuter si la table shops existe déjà en production.
-- Idempotent : peut être rejoué sans erreur.
-- ============================================================================

SET FOREIGN_KEY_CHECKS = 0;

DELIMITER $$

DROP PROCEDURE IF EXISTS add_shops_acces_extras_column_if_missing$$
CREATE PROCEDURE add_shops_acces_extras_column_if_missing()
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_schema = DATABASE()
          AND table_name = 'shops'
          AND column_name = 'acces_extras'
    ) THEN
        ALTER TABLE `shops`
            ADD COLUMN `acces_extras` TINYINT(1) NOT NULL DEFAULT 0
            AFTER `actif`;
    END IF;
END$$

DELIMITER ;

CALL add_shops_acces_extras_column_if_missing();

DROP PROCEDURE IF EXISTS add_shops_acces_extras_column_if_missing;

SET FOREIGN_KEY_CHECKS = 1;
