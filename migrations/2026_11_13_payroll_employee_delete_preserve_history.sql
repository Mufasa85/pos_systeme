-- ============================================================================
-- MIGRATION : Suppression d'une fiche employé Paie — conservation de l'historique
-- Date       : 2026-11-13
-- Référence  : Étape 3 — fiabilisation du module Paie (bouton « Supprimer »)
--
-- PROBLÈME
-- --------
-- La suppression d'une fiche `payroll_employees` (bouton « Supprimer » ajouté
-- à /payroll/employees) déclenchait un `ON DELETE CASCADE` en cascade :
--
--   payroll_payslips.employee_id        → ON DELETE CASCADE  ⚠️
--   payroll_payslip_lines.payslip_id     → ON DELETE CASCADE  ⚠️
--   payroll_contracts.employee_id        → ON DELETE CASCADE  ⚠️
--   payroll_attendance.employee_id       → ON DELETE CASCADE  ⚠️
--   payroll_absences.employee_id         → ON DELETE CASCADE  ⚠️
--   payroll_overtimes.employee_id        → ON DELETE CASCADE  ⚠️
--   payroll_payments.payslip_id          → ON DELETE CASCADE  ⚠️
--
-- Concrètement : supprimer UN agent effaçait TOUS ses bulletins de paie,
-- ses lignes de bulletin, son contrat, ses présences, ses absences, ses
-- heures supplémentaires et ses paiements.
--
-- C'est exactement le risque que la migration
-- `2026_10_25_user_delete_fk_set_null.sql` avait déjà supprimé pour
-- `utilisateurs` (règle : « les données comptables / fiscales / de paie ne
-- JAMAIS ne doivent disparaître à cause de la suppression d'un compte »).
-- La même règle doit s'appliquer à la suppression d'une fiche Paie.
--
-- DÉCISION
-- --------
-- On SUPPRIME la fiche `payroll_employees` (l'agent quitte la paie active)
-- mais on CONSERVE tout l'historique :
--   - `payroll_payslips.employee_id`  → SET NULL (bulletin conservé, détaché)
--   - `payroll_contracts.employee_id` → SET NULL (contrat conservé)
--   - `payroll_attendance / absences / overtimees` → SET NULL (saisie conservée)
--
-- ⚠️ Conséquence : un bulletin détaché n'est plus rattaché à un agent, donc
-- il ne peut plus être recalculé (le calcul part de la fiche employé). C'est
-- le comportement souhaité : un bulletin validé/calculé est un document
-- comptable figé.
--
-- APPLICATION
-- -----------
-- `PayrollWorkflow::canModifyEmployee()` refuse déjà la suppression d'une
-- fiche ayant un bulletin `validated` ou `paid` (document comptable validé).
-- Cette migration couvre le cas des bulletins `calculated` (non verrouillés).
--
-- EXÉCUTION
--   mysql -u root -p pos_system < migrations/2026_11_13_payroll_employee_delete_preserve_history.sql
--
-- Le script est idempotent : il peut être rejoué sans risque.
-- ============================================================================

SET NAMES utf8mb4;
SET @sql_mode = (SELECT @@SESSION.sql_mode);
SET SESSION sql_mode = '';

-- ----------------------------------------------------------------------------
-- 1. payroll_payslips.employee_id  (le bulletin doit SURVIVRE à l'agent)
-- ----------------------------------------------------------------------------
SET @fk_name := (
    SELECT CONSTRAINT_NAME FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'payroll_payslips'
      AND COLUMN_NAME = 'employee_id' AND REFERENCED_TABLE_NAME = 'payroll_employees'
    LIMIT 1
);
SET @drop := IFNULL(
    CONCAT('ALTER TABLE `payroll_payslips` DROP FOREIGN KEY `', @fk_name, '`'), 'SELECT 1');
PREPARE stmt FROM @drop; EXECUTE stmt; DEALLOCATE PREPARE stmt;

ALTER TABLE `payroll_payslips`
  MODIFY COLUMN `employee_id` INT NULL DEFAULT NULL
    COMMENT 'FK vers payroll_employees.id. NULL = fiche supprimee (bulletin conserve).';

SET @add := IFNULL(
    CONCAT('ALTER TABLE `payroll_payslips` ADD CONSTRAINT `', @fk_name,
           '` FOREIGN KEY (`employee_id`) REFERENCES `payroll_employees` (`id`) ON DELETE SET NULL ON UPDATE CASCADE'),
    'SELECT 1');
PREPARE stmt FROM @add; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ----------------------------------------------------------------------------
-- 2. payroll_contracts.employee_id  (le contrat est l'historique de la paie)
-- ----------------------------------------------------------------------------
SET @fk_name := (
    SELECT CONSTRAINT_NAME FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'payroll_contracts'
      AND COLUMN_NAME = 'employee_id' AND REFERENCED_TABLE_NAME = 'payroll_employees'
    LIMIT 1
);
SET @drop := IFNULL(
    CONCAT('ALTER TABLE `payroll_contracts` DROP FOREIGN KEY `', @fk_name, '`'), 'SELECT 1');
PREPARE stmt FROM @drop; EXECUTE stmt; DEALLOCATE PREPARE stmt;

ALTER TABLE `payroll_contracts`
  MODIFY COLUMN `employee_id` INT NULL DEFAULT NULL
    COMMENT 'FK vers payroll_employees.id. NULL = fiche supprimee (contrat conserve).';

SET @add := IFNULL(
    CONCAT('ALTER TABLE `payroll_contracts` ADD CONSTRAINT `', @fk_name,
           '` FOREIGN KEY (`employee_id`) REFERENCES `payroll_employees` (`id`) ON DELETE SET NULL ON UPDATE CASCADE'),
    'SELECT 1');
PREPARE stmt FROM @add; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ----------------------------------------------------------------------------
-- 3. payroll_attendance / payroll_absences / payroll_overtimes
--    (saisie historique : conservée pour la traçabilité)
-- ----------------------------------------------------------------------------

-- DROP des 3 contraintes
SET @fk_name := (
    SELECT CONSTRAINT_NAME FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'payroll_attendance'
      AND COLUMN_NAME = 'employee_id' AND REFERENCED_TABLE_NAME = 'payroll_employees'
    LIMIT 1
);
SET @drop := IFNULL(
    CONCAT('ALTER TABLE `payroll_attendance` DROP FOREIGN KEY `', @fk_name, '`'), 'SELECT 1');
PREPARE stmt FROM @drop; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk_name := (
    SELECT CONSTRAINT_NAME FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'payroll_absences'
      AND COLUMN_NAME = 'employee_id' AND REFERENCED_TABLE_NAME = 'payroll_employees'
    LIMIT 1
);
SET @drop := IFNULL(
    CONCAT('ALTER TABLE `payroll_absences` DROP FOREIGN KEY `', @fk_name, '`'), 'SELECT 1');
PREPARE stmt FROM @drop; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk_name := (
    SELECT CONSTRAINT_NAME FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'payroll_overtimes'
      AND COLUMN_NAME = 'employee_id' AND REFERENCED_TABLE_NAME = 'payroll_employees'
    LIMIT 1
);
SET @drop := IFNULL(
    CONCAT('ALTER TABLE `payroll_overtimes` DROP FOREIGN KEY `', @fk_name, '`'), 'SELECT 1');
PREPARE stmt FROM @drop; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Colonnes rendues NULLABLE
ALTER TABLE `payroll_attendance`
  MODIFY COLUMN `employee_id` INT NULL DEFAULT NULL
    COMMENT 'FK vers payroll_employees.id. NULL = fiche supprimee (saisie conservee).';

ALTER TABLE `payroll_absences`
  MODIFY COLUMN `employee_id` INT NULL DEFAULT NULL
    COMMENT 'FK vers payroll_employees.id. NULL = fiche supprimee (saisie conservee).';

ALTER TABLE `payroll_overtimes`
  MODIFY COLUMN `employee_id` INT NULL DEFAULT NULL
    COMMENT 'FK vers payroll_employees.id. NULL = fiche supprimee (saisie conservee).';

-- Recréation des 3 contraintes en SET NULL
SET @fk_name := (
    SELECT CONSTRAINT_NAME FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'payroll_attendance'
      AND COLUMN_NAME = 'employee_id' AND REFERENCED_TABLE_NAME = 'payroll_employees'
    LIMIT 1
);
SET @add := IFNULL(
    CONCAT('ALTER TABLE `payroll_attendance` ADD CONSTRAINT `', @fk_name,
           '` FOREIGN KEY (`employee_id`) REFERENCES `payroll_employees` (`id`) ON DELETE SET NULL ON UPDATE CASCADE'),
    'SELECT 1');
PREPARE stmt FROM @add; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk_name := (
    SELECT CONSTRAINT_NAME FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'payroll_absences'
      AND COLUMN_NAME = 'employee_id' AND REFERENCED_TABLE_NAME = 'payroll_employees'
    LIMIT 1
);
SET @add := IFNULL(
    CONCAT('ALTER TABLE `payroll_absences` ADD CONSTRAINT `', @fk_name,
           '` FOREIGN KEY (`employee_id`) REFERENCES `payroll_employees` (`id`) ON DELETE SET NULL ON UPDATE CASCADE'),
    'SELECT 1');
PREPARE stmt FROM @add; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk_name := (
    SELECT CONSTRAINT_NAME FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'payroll_overtimes'
      AND COLUMN_NAME = 'employee_id' AND REFERENCED_TABLE_NAME = 'payroll_employees'
    LIMIT 1
);
SET @add := IFNULL(
    CONCAT('ALTER TABLE `payroll_overtimes` ADD CONSTRAINT `', @fk_name,
           '` FOREIGN KEY (`employee_id`) REFERENCES `payroll_employees` (`id`) ON DELETE SET NULL ON UPDATE CASCADE'),
    'SELECT 1');
PREPARE stmt FROM @add; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ----------------------------------------------------------------------------
-- 4. payroll_time_clock_events  (pointage : conservé pour la traçabilité)
-- ----------------------------------------------------------------------------
SET @fk_name := (
    SELECT CONSTRAINT_NAME FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'payroll_time_clock_events'
      AND COLUMN_NAME = 'employee_id' AND REFERENCED_TABLE_NAME = 'payroll_employees'
    LIMIT 1
);
SET @drop := IFNULL(
    CONCAT('ALTER TABLE `payroll_time_clock_events` DROP FOREIGN KEY `', @fk_name, '`'), 'SELECT 1');
PREPARE stmt FROM @drop; EXECUTE stmt; DEALLOCATE PREPARE stmt;

ALTER TABLE `payroll_time_clock_events`
  MODIFY COLUMN `employee_id` INT NULL DEFAULT NULL
    COMMENT 'FK vers payroll_employees.id. NULL = fiche supprimee (pointage conserve).';

SET @add := IFNULL(
    CONCAT('ALTER TABLE `payroll_time_clock_events` ADD CONSTRAINT `', @fk_name,
           '` FOREIGN KEY (`employee_id`) REFERENCES `payroll_employees` (`id`) ON DELETE SET NULL ON UPDATE CASCADE'),
    'SELECT 1');
PREPARE stmt FROM @add; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET SESSION sql_mode = @sql_mode;

-- ============================================================================
-- FIN DE LA MIGRATION
-- ============================================================================
