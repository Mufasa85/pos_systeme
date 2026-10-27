-- ============================================================================
-- MIGRATION : Suppression d'un utilisateur — détachement de l'auteur (SET NULL)
-- Date       : 2026-10-25
-- Référence  : bug "Cannot delete or update a parent row ... hotel_extras_user_fk"
--
-- PROBLÈME
-- --------
-- La table `utilisateurs` est référencée par 12 clés étrangères. Tenter de
-- supprimer un utilisateur lié au module Hôtel levait :
--   SQLSTATE[23000]: Integrity constraint violation: 1451
--   Cannot delete or update a parent row: a foreign key constraint fails
--   (`pos_system`.`hotel_extras`, CONSTRAINT `hotel_extras_user_fk` ...)
--
-- Les 4 tables du module Hôtel (`hotel_sejours`, `hotel_extras`,
-- `hotel_paiements`, `hotel_factures`) déclaraient `user_id INT NOT NULL`
-- avec `ON DELETE NO ACTION` (= RESTRICT) : MySQL refuse tout DELETE.
--
-- Deux autres tables posaient un risque de perte de données SILENCIEUSE :
--   - `ventes.vendeur_id`         ON DELETE CASCADE → supprimait les FACTURES
--                                 FISCALES du vendeur (document fiscal !)
--   - `payroll_employees.user_id` ON DELETE CASCADE → supprimait TOUT
--                                 l'historique de paie de l'employé
--                                 (bulletins, absences, pointages, contrats)
--
-- DÉCISION
-- --------
-- Suppression réelle de l'utilisateur, mais l'historique est CONSERVÉ :
-- toutes les clés étrangères passent en `ON DELETE SET NULL` et les colonnes
-- deviennent NULLABLE. L'auteur est simplement "détaché" de la ligne.
-- Le NULL est interprété côté applicatif comme « Utilisateur supprimé ».
--
-- RÈGLE : les données comptables / fiscales / de paie ne JAMAIS ne doivent
-- disparaître à cause de la suppression d'un compte. D'où SET NULL partout
-- plutôt que CASCADE.
--
-- RÉTRO-COMPATIBILITÉ
--   - Aucune ligne existante n'est modifiée (les colonnes restent renseignées).
--   - Les `LEFT JOIN utilisateurs` existants continuent de fonctionner.
--   - Les `JOIN utilisateurs` (INNER) de la paie sont convertis en `LEFT JOIN`
--     dans le code applicatif, sinon les bulletins d'un employé supprimé
--     disparaîtraient des listes (le JOIN INNER exclut les user_id = NULL).
--
-- EXÉCUTION
--   mysql -u root -p pos_system < migrations/2026_10_25_user_delete_fk_set_null.sql
--
-- Le script est idempotent : il peut être rejoué sans risque.
-- ============================================================================

SET NAMES utf8mb4;
SET @sql_mode = (SELECT @@SESSION.sql_mode);
SET SESSION sql_mode = '';

-- ----------------------------------------------------------------------------
-- Pattern appliqué à chaque table :
--   1. On mémorise le nom de la contrainte AVANT de la supprimer
--      (information_schema ne la retrouve plus après le DROP).
--   2. DROP FOREIGN KEY (ignoré si la contrainte n'existe pas déjà).
--   3. La colonne devient NULLABLE.
--   4. La FK est recréée avec ON DELETE SET NULL ON UPDATE CASCADE.
-- ----------------------------------------------------------------------------


-- 1. Module Hôtel : hotel_sejours.user_id
SET @fk_name := (
    SELECT CONSTRAINT_NAME FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'hotel_sejours'
      AND COLUMN_NAME = 'user_id' AND REFERENCED_TABLE_NAME = 'utilisateurs'
    LIMIT 1
);
SET @drop := IFNULL(
    CONCAT('ALTER TABLE `hotel_sejours` DROP FOREIGN KEY `', @fk_name, '`'), 'SELECT 1');
PREPARE stmt FROM @drop; EXECUTE stmt; DEALLOCATE PREPARE stmt;

ALTER TABLE `hotel_sejours`
  MODIFY COLUMN `user_id` INT NULL DEFAULT NULL
    COMMENT 'Utilisateur (réceptionniste) qui a créé le séjour. NULL = utilisateur supprimé.';

SET @add := IFNULL(
    CONCAT('ALTER TABLE `hotel_sejours` ADD CONSTRAINT `', @fk_name,
           '` FOREIGN KEY (`user_id`) REFERENCES `utilisateurs` (`id`) ON DELETE SET NULL ON UPDATE CASCADE'),
    'SELECT 1');
PREPARE stmt FROM @add; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 2. Module Hôtel : hotel_extras.user_id
SET @fk_name := (
    SELECT CONSTRAINT_NAME FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'hotel_extras'
      AND COLUMN_NAME = 'user_id' AND REFERENCED_TABLE_NAME = 'utilisateurs'
    LIMIT 1
);
SET @drop := IFNULL(
    CONCAT('ALTER TABLE `hotel_extras` DROP FOREIGN KEY `', @fk_name, '`'), 'SELECT 1');
PREPARE stmt FROM @drop; EXECUTE stmt; DEALLOCATE PREPARE stmt;

ALTER TABLE `hotel_extras`
  MODIFY COLUMN `user_id` INT NULL DEFAULT NULL
    COMMENT 'Utilisateur ayant saisi l''extra. NULL = utilisateur supprimé.';

SET @add := IFNULL(
    CONCAT('ALTER TABLE `hotel_extras` ADD CONSTRAINT `', @fk_name,
           '` FOREIGN KEY (`user_id`) REFERENCES `utilisateurs` (`id`) ON DELETE SET NULL ON UPDATE CASCADE'),
    'SELECT 1');
PREPARE stmt FROM @add; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 3. Module Hôtel : hotel_paiements.user_id
SET @fk_name := (
    SELECT CONSTRAINT_NAME FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'hotel_paiements'
      AND COLUMN_NAME = 'user_id' AND REFERENCED_TABLE_NAME = 'utilisateurs'
    LIMIT 1
);
SET @drop := IFNULL(
    CONCAT('ALTER TABLE `hotel_paiements` DROP FOREIGN KEY `', @fk_name, '`'), 'SELECT 1');
PREPARE stmt FROM @drop; EXECUTE stmt; DEALLOCATE PREPARE stmt;

ALTER TABLE `hotel_paiements`
  MODIFY COLUMN `user_id` INT NULL DEFAULT NULL
    COMMENT 'Utilisateur ayant encaissé le paiement. NULL = utilisateur supprimé.';

SET @add := IFNULL(
    CONCAT('ALTER TABLE `hotel_paiements` ADD CONSTRAINT `', @fk_name,
           '` FOREIGN KEY (`user_id`) REFERENCES `utilisateurs` (`id`) ON DELETE SET NULL ON UPDATE CASCADE'),
    'SELECT 1');
PREPARE stmt FROM @add; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 5. POS : ventes.vendeur_id  (CASCADE -> SET NULL : on NE SUPPRIME PLUS
--    les factures fiscales du vendeur)
SET @fk_name := (
    SELECT CONSTRAINT_NAME FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ventes'
      AND COLUMN_NAME = 'vendeur_id' AND REFERENCED_TABLE_NAME = 'utilisateurs'
    LIMIT 1
);
SET @drop := IFNULL(
    CONCAT('ALTER TABLE `ventes` DROP FOREIGN KEY `', @fk_name, '`'), 'SELECT 1');
PREPARE stmt FROM @drop; EXECUTE stmt; DEALLOCATE PREPARE stmt;

ALTER TABLE `ventes`
  MODIFY COLUMN `vendeur_id` INT NULL DEFAULT NULL
    COMMENT 'Vendeur de la vente. NULL = utilisateur supprimé (la facture est conservée).';

SET @add := IFNULL(
    CONCAT('ALTER TABLE `ventes` ADD CONSTRAINT `', @fk_name,
           '` FOREIGN KEY (`vendeur_id`) REFERENCES `utilisateurs` (`id`) ON DELETE SET NULL ON UPDATE CASCADE'),
    'SELECT 1');
PREPARE stmt FROM @add; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 6. Paie : payroll_employees.user_id  (CASCADE -> SET NULL : on NE SUPPRIME
--    PLUS l'historique de paie de l'employé)
SET @fk_name := (
    SELECT CONSTRAINT_NAME FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'payroll_employees'
      AND COLUMN_NAME = 'user_id' AND REFERENCED_TABLE_NAME = 'utilisateurs'
    LIMIT 1
);
SET @drop := IFNULL(
    CONCAT('ALTER TABLE `payroll_employees` DROP FOREIGN KEY `', @fk_name, '`'), 'SELECT 1');
PREPARE stmt FROM @drop; EXECUTE stmt; DEALLOCATE PREPARE stmt;

ALTER TABLE `payroll_employees`
  MODIFY COLUMN `user_id` INT NULL DEFAULT NULL
    COMMENT 'FK vers utilisateurs.id. NULL = utilisateur supprimé (la fiche paie est conservée).';

SET @add := IFNULL(
    CONCAT('ALTER TABLE `payroll_employees` ADD CONSTRAINT `', @fk_name,
           '` FOREIGN KEY (`user_id`) REFERENCES `utilisateurs` (`id`) ON DELETE SET NULL ON UPDATE CASCADE'),
    'SELECT 1');
PREPARE stmt FROM @add; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ----------------------------------------------------------------------------
-- 7. Nettoyage des lignes orphelines déjà présentes dans la base
--
-- `details_vente` n'a AUCUNE clé étrangère vers `ventes` : les anciennes
-- suppressions en CASCADE ont laissé des lignes de détail orphelines.
-- On supprime ces lignes (elles ne sont rattachables à aucune facture).
-- ----------------------------------------------------------------------------
DELETE dv FROM `details_vente` dv
  LEFT JOIN `ventes` v ON v.id = dv.vente_id
  WHERE v.id IS NULL;

DELETE dv FROM `details_vente_archive` dv
  LEFT JOIN `ventes_archive` va ON va.id = dv.vente_id
  WHERE va.id IS NULL;

-- ----------------------------------------------------------------------------
-- 8. `ventes_archive.vendeur_id` : pas de FK aujourd'hui, mais la colonne est
-- NOT NULL. On la rend NULLABLE par cohérence avec `ventes` (et parce que le
-- script d'archivage recopie `ventes.vendeur_id`, qui peut désormais être NULL).
-- ----------------------------------------------------------------------------
ALTER TABLE `ventes_archive`
  MODIFY COLUMN `vendeur_id` INT NULL DEFAULT NULL
    COMMENT 'Vendeur de la vente archivée. NULL = utilisateur supprimé.';

SET SESSION sql_mode = @sql_mode;

-- ============================================================================
-- FIN DE LA MIGRATION
-- ============================================================================

-- 4. Module Hôtel : hotel_factures.user_id
SET @fk_name := (
    SELECT CONSTRAINT_NAME FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'hotel_factures'
      AND COLUMN_NAME = 'user_id' AND REFERENCED_TABLE_NAME = 'utilisateurs'
    LIMIT 1
);
SET @drop := IFNULL(
    CONCAT('ALTER TABLE `hotel_factures` DROP FOREIGN KEY `', @fk_name, '`'), 'SELECT 1');
PREPARE stmt FROM @drop; EXECUTE stmt; DEALLOCATE PREPARE stmt;

ALTER TABLE `hotel_factures`
  MODIFY COLUMN `user_id` INT NULL DEFAULT NULL
    COMMENT 'Utilisateur ayant émis la facture. NULL = utilisateur supprimé.';

SET @add := IFNULL(
    CONCAT('ALTER TABLE `hotel_factures` ADD CONSTRAINT `', @fk_name,
           '` FOREIGN KEY (`user_id`) REFERENCES `utilisateurs` (`id`) ON DELETE SET NULL ON UPDATE CASCADE'),
    'SELECT 1');
PREPARE stmt FROM @add; EXECUTE stmt; DEALLOCATE PREPARE stmt;

