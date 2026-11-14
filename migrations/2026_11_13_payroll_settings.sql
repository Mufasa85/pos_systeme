-- =============================================================================
-- Module Paie — paramètres de calcul (mode « modèle Excel / RDC »)
-- Date : 2026-11-13
-- Description :
--   Le module Paie calculait les bulletins avec un modèle générique
--   (base + sursalaire + HS + ancienneté + primes − retenues − cotisations),
--   qui ne correspondait pas au modèle métier réel documenté dans
--   `Recap paie.xlsx` (classeur du comptable) et dans la base `paie_db`
--   (ancien projet paie autonome).
--
--   Le modèle réel comporte des éléments ABSENTS du calculateur :
--     - le barème de l'IRT (impôt sur le revenu) à 5 tranches ;
--     - le taux de change USD → devise locale (2 658,7148) ;
--     - la réduction d'IRT de 2 % par personne à charge ;
--     - le logement à 30 % de la masse salariale (LOG) ;
--     - le transport à 21,15 % du LOG ;
--     - l'ancienneté à 3 % PAR ANNÉE (et non par tranche) ;
--     - le plafond CNSS (R + S + T) distinct de la masse salariale.
--
--   Cette migration crée une table de paramètres clé/valeur pour rendre
--   ces règles configurables, sans casser le modèle actuel (rétrocompatible).
--
--   `calculation_mode` :
--     - 'standard' (défaut) → le modèle générique actuel (inchangé) ;
--     - 'excel_rdc'         → le modèle du classeur Excel / `paie_db`.
--
-- Idempotent : peut être rejoué sans effet de bord.
-- =============================================================================

SET FOREIGN_KEY_CHECKS = 0;

DELIMITER $$

DROP PROCEDURE IF EXISTS add_payroll_settings_table_if_missing$$

CREATE PROCEDURE add_payroll_settings_table_if_missing()
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'payroll_settings'
    ) THEN
        CREATE TABLE `payroll_settings` (
            `id`            INT NOT NULL AUTO_INCREMENT,
            `shop_id`       INT DEFAULT NULL COMMENT 'NULL = paramètre global',
            `setting_key`   VARCHAR(100) NOT NULL,
            `setting_value` TEXT DEFAULT NULL,
            `label`         VARCHAR(150) DEFAULT NULL COMMENT 'Libellé affiché dans l\'UI',
            `input_type`    VARCHAR(20) NOT NULL DEFAULT 'text'
                            COMMENT 'text | number | percent | select | bool',
            `options`       VARCHAR(255) DEFAULT NULL
                            COMMENT 'Valeurs possibles pour input_type = select (séparées par |)',
            `updated_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
                            ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            -- ⚠️ Un index UNIQUE sur (shop_id, setting_key) n'empêche PAS les
            -- doublons quand `shop_id` est NULL : en SQL, NULL n'est jamais
            -- égal à NULL, donc MySQL autorise plusieurs lignes globales pour
            -- une même clé. On utilise donc une colonne générée qui remplace
            -- NULL par 0 (le "shop_id technique" des paramètres globaux).
            `shop_key` int GENERATED ALWAYS AS (IFNULL(`shop_id`, 0)) STORED,
            UNIQUE KEY `payroll_settings_shop_key_idx` (`shop_key`, `setting_key`),
            KEY `payroll_settings_shop_idx` (`shop_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
    END IF;
END$$

CALL add_payroll_settings_table_if_missing()$$

DROP PROCEDURE IF EXISTS add_payroll_settings_table_if_missing$$

SET FOREIGN_KEY_CHECKS = 1;

-- -----------------------------------------------------------------------------
-- Paramètres par défaut (modele « standard » → aucun changement de comportement)
-- -----------------------------------------------------------------------------
INSERT INTO payroll_settings (shop_id, setting_key, setting_value, label, input_type, options)
VALUES
    (NULL, 'calculation_mode', 'standard',
     'Mode de calcul', 'select', 'standard|excel_rdc'),

    (NULL, 'country', 'RDC',
     'Pays / zone', 'text', NULL),

    (NULL, 'currency', 'Fc',
     'Devise de la paie', 'text', NULL),

    (NULL, 'default_working_days', '26',
     'Jours ouvrables par defaut', 'number', NULL),

    (NULL, 'exchange_rate', '2658.7148',
     'Taux de change (devise forte -> devise locale)', 'number', NULL),

    (NULL, 'exchange_rate_source', 'api',
     'Origine du taux de change', 'select', 'api|fixed'),

    (NULL, 'seniority_rate_per_year', '3',
     'Anciennete : % du salaire de base par ANNEE', 'number', NULL),

    (NULL, 'seniority_max_years', '0',
     'Plafond d\'anciennete en annees (0 = illimite)', 'number', NULL),

    (NULL, 'housing_rate', '30',
     'LOG : % de la masse salariale', 'number', NULL),

    (NULL, 'transport_rate', '21.15',
     'TRANS : % du LOG', 'number', NULL),

    (NULL, 'tax_dependents_allowance', '2',
     'Reduction d\'IRT : % par personne a charge', 'number', NULL),

    (NULL, 'cnss_employee_rate', '5',
     'CNSS : % preleve au salarie', 'number', NULL),

    (NULL, 'cnss_employer_rate', '6.5',
     'CNSS : % charge patronale', 'number', NULL),

    (NULL, 'inpp_employer_rate', '3',
     'INPP : % charge patronale', 'number', NULL),

    (NULL, 'onem_employer_rate', '0.2',
     'ONEM : % charge patronale', 'number', NULL),

    (NULL, 'ier_employer_rate', '25',
     'IER : % charge patronale sur le plafond CNSS', 'number', NULL),

    (NULL, 'irt_enabled', '1',
     'Appliquer le bareme IRT (impot sur le revenu)', 'bool', NULL),

    (NULL, 'prorate_base', '0',
     'Proratiser le salaire de base sur les jours payes (0 = non, comme le classeur)', 'bool', NULL)

ON DUPLICATE KEY UPDATE
    label      = VALUES(label),
    input_type = VALUES(input_type),
    options    = VALUES(options);
