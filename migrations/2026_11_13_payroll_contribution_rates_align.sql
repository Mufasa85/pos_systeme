-- =============================================================================
-- Module Paie — alignement des taux de cotisation
-- Date : 2026-11-13
--
-- CONTEXTE
-- --------
-- Deux sources décrivent les taux de paie :
--   1. `Recap paie.xlsx` (classeur du comptable) :
--        CNSS salarié 5 % · CNSS patronale 6,5 % (× 2 lignes) ·
--        INPP 3 % · ONEM 0,2 % · IER 25 %
--   2. la base `paie_db` (ancien projet paie autonome, 4 agents avec contrats
--      et taux cohérents entre eux) :
--        CNSS_PAT 6,5 % · CNSS_PAT2 6,5 % · INPP 2,2 % · ONEM 0,15 %
--
-- Divergences : INPP (3 % vs 2,2 %) et ONEM (0,2 % vs 0,15 %).
-- Le CNSS patronale concorde (6,5 %, sur DEUX lignes dans les deux sources).
--
-- ARBITRAGE
-- ---------
-- `paie_db` est le système de paie réellement utilisé (contrats, articles,
-- historique) : ses taux patronaux sont retenus. Le classeur reste la seule
-- source pour le CNSS SALARIÉ (5 %), que `paie_db` n'a pas (0 %).
--
--   CNSS salarié     5 %      (classeur)
--   CNSS patronale   6,5 % ×2 (classeur ET paie_db — concordants)
--   INPP             2,2 %    (paie_db)
--   ONEM             0,15 %   (paie_db)
--
-- ⚠️ `INSS` est DÉSACTIVÉE : « Institut national de sécurité sociale » et
-- « Caisse nationale de sécurité sociale » désignent la MÊME institution en
-- RDC. La laisser active revient à compter deux fois la même cotisation
-- (elle vient du seed `migrations/seed_payroll_business_data.sql`, et ni le
-- classeur ni `paie_db` ne l'ont).
--
-- Ces taux restent MODIFIABLES depuis /payroll/settings (onglet Cotisations).
-- La présente migration n'est qu'un point de départ cohérent.
--
-- Idempotent : peut être rejoué sans effet de bord.
-- =============================================================================

SET NAMES utf8mb4;

-- ----------------------------------------------------------------------------
-- 1. `payroll_contribution_rates` (mode « standard »)
-- ----------------------------------------------------------------------------
UPDATE `payroll_contribution_rates`
   SET `employee_rate` = 5.0000,
       `employer_rate` = 6.5000
 WHERE `code` = 'CNSS';

UPDATE `payroll_contribution_rates`
   SET `employee_rate` = 0.0000,
       `employer_rate` = 2.2000
 WHERE `code` = 'INPP';

UPDATE `payroll_contribution_rates`
   SET `employee_rate` = 0.0000,
       `employer_rate` = 0.1500
 WHERE `code` = 'ONEM';

-- Doublon institutionnel avec la CNSS : on le désactive (conservé pour l'historique).
UPDATE `payroll_contribution_rates`
   SET `is_active` = 0
 WHERE `code` = 'INSS';

-- ----------------------------------------------------------------------------
-- 2. `payroll_settings` (mode « Excel / RDC ») — mêmes taux
-- ----------------------------------------------------------------------------
UPDATE `payroll_settings` SET `setting_value` = '5'    WHERE `setting_key` = 'cnss_employee_rate';
UPDATE `payroll_settings` SET `setting_value` = '6.5'  WHERE `setting_key` = 'cnss_employer_rate';
UPDATE `payroll_settings` SET `setting_value` = '2.2'  WHERE `setting_key` = 'inpp_employer_rate';
UPDATE `payroll_settings` SET `setting_value` = '0.15' WHERE `setting_key` = 'onem_employer_rate';
UPDATE `payroll_settings` SET `setting_value` = '25'   WHERE `setting_key` = 'ier_employer_rate';

-- Devise : la paie est en francs congolais (`Fc`), le barème IRT est libellé
-- en USD et converti via `exchange_rate`. Le taux est celui de l'API OSAT
-- (`/dgi/currency/`) par défaut ; le taux figé du classeur reste le repli.
UPDATE `payroll_settings` SET `setting_value` = 'Fc' WHERE `setting_key` = 'currency';
