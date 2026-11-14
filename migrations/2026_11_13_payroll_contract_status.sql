-- =============================================================================
-- Module Paie — statut de rémunération du contrat
-- Date : 2026-11-13
-- Description :
--   Le module Paie ne disposait d'aucun moyen de savoir QUELS agents sont
--   effectivement payés, ni COMBIEN ils gagnent :
--     - le salaire de base vit dans `payroll_contracts.base_salary`, mais cette
--       table n'avait AUCUN CRUD (ni route API, ni vue, ni contrôleur) ;
--     - `PayrollCalculator::calculateForPeriod()` ignoreait silencieusement
--       tout employé sans contrat actif (donc sans bulletin).
--
--   Cette migration ajoute au contrat :
--     - `is_paid`      : 1 = l'agent est payé (bulletin calculé), 0 = non payé
--                         (ex. intérimaire, saisonnier, agent en试用期, etc.)
--     - `paid_from` /
--       `paid_until`   : période d'affectation au calcul de la paie (nullable).
--                         Vides = l'agent est payé tous les mois.
--
--   Le flag `is_paid` est la réponse à la question "qui doit être payé ?".
--   Un contrat `is_paid = 0` est conservé (historique) mais n'est pas retenu
--   par `PayrollCalculator::findActiveByEmployee()` pour le calcul.
--
-- Rappel structurel de `payroll_contracts` (inchangé) :
--   employee_id, type, start_date, end_date, base_salary, sursalary,
--   pay_type (monthly|daily|hourly), currency, is_active
--
-- Idempotent : peut être rejoué sans effet de bord.
-- =============================================================================

SET FOREIGN_KEY_CHECKS = 0;

DELIMITER $$

DROP PROCEDURE IF EXISTS add_payroll_contracts_status_columns_if_missing$$

CREATE PROCEDURE add_payroll_contracts_status_columns_if_missing()
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'payroll_contracts'
          AND COLUMN_NAME = 'is_paid'
    ) THEN
        ALTER TABLE `payroll_contracts`
            ADD COLUMN `is_paid` TINYINT(1) NOT NULL DEFAULT 1
            COMMENT '1 = agent payé (entré au calcul), 0 = non payé';
    END IF;

    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'payroll_contracts'
          AND COLUMN_NAME = 'paid_from'
    ) THEN
        ALTER TABLE `payroll_contracts`
            ADD COLUMN `paid_from` DATE DEFAULT NULL
            COMMENT 'Début de la période de rémunération (NULL = illimité)';
    END IF;

    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'payroll_contracts'
          AND COLUMN_NAME = 'paid_until'
    ) THEN
        ALTER TABLE `payroll_contracts`
            ADD COLUMN `paid_until` DATE DEFAULT NULL
            COMMENT 'Fin de la période de rémunération (NULL = illimité)';
    END IF;
END$$

CALL add_payroll_contracts_status_columns_if_missing()$$

DROP PROCEDURE IF EXISTS add_payroll_contracts_status_columns_if_missing$$

SET FOREIGN_KEY_CHECKS = 1;

-- Index de pilotage : lister les contrats payés d'une boutique.
--
-- ⚠️ `payroll_contracts` n'a PAS de colonne `shop_id` : la boutique se déduit
-- via `payroll_employees.shop_id` (cf. `PayrollContract::all()` qui fait
-- `JOIN payroll_employees pe ON pc.employee_id = pe.id`). L'index porte donc
-- sur la table des employés.
--
-- Idempotent : on ignore l'erreur si l'index existe déjà.
SET @idx_exists := (
    SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'payroll_employees'
      AND INDEX_NAME = 'payroll_employees_shop_paid_idx'
);

SET @idx_sql := IF(
    @idx_exists > 0,
    'SELECT 1',
    'CREATE INDEX `payroll_employees_shop_paid_idx` ON `payroll_employees` (`shop_id`, `status`)'
);

PREPARE stmt FROM @idx_sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
