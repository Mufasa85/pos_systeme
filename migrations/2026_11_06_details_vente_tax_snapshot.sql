-- ============================================================================
-- MIGRATION : Snapshot du taux de TVA sur les lignes de détail
-- Date       : 2026-11-06
-- Reference  : intégration de l'hôtel dans les rapports fiscaux
--
-- PROBLÈME
--   `Report::getSalesByTaxGroup()` calcule les montants par groupe de
--   taxation en faisant :
--       LEFT JOIN produits p ON dv.produit_id = p.id
--       LEFT JOIN taxes   t ON p.taxe_id = t.id
--   Les lignes d'une facture HÔTEL (nuitées) ont `produit_id = NULL` (une
--   chambre n'est pas un produit stockable) → `t.taux` est NULL → ces lignes
--   sont totalement ABSENTES du rapport « Par groupe de taxation » : ni
--   montant, ni compteur, alors qu'elles pèsent le CA principal de l'hôtel.
--
--   De plus, la TVA hôtel se calcule sur le PRIX DE LA CHAMBRE
--   (`hotel_chambres.taxe_id` → `taxes`), pas sur un produit.
--
-- SOLUTION
--   On FIGE le taux de TVA sur la ligne de détail au moment de la
--   facturation (snapshot), sur le modèle de `hotel_extras.tax_rate` qui
--   existe déjà pour les extras. Le rapport lit alors :
--       COALESCE(dv.tax_rate, t.taux, 0)
--   → une ligne sans produit (nuitées, recharges) retrouve son groupe de
--   taxation, et le taux reste correct même si la taxe de la chambre change
--   après coup.
--
-- Idempotent : pattern INFORMATION_SCHEMA (cf. migration 2026_10_27).
-- ============================================================================

SET NAMES utf8mb4;

-- ----------------------------------------------------------------------------
-- 1. Colonnes de snapshot sur `details_vente`
-- ----------------------------------------------------------------------------
SET @add := IFNULL((
    SELECT IF(COUNT(*) = 0,
        'ALTER TABLE `details_vente` ADD COLUMN `tax_rate` DECIMAL(5,2) DEFAULT 0.00
            COMMENT ''Taux TVA snapshot au moment de la vente (nuitees hotel, recharges)''',
        'SELECT 1')
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'details_vente' AND COLUMN_NAME = 'tax_rate'
), 'SELECT 1');
PREPARE stmt FROM @add; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @add := IFNULL((
    SELECT IF(COUNT(*) = 0,
        'ALTER TABLE `details_vente` ADD COLUMN `tax_etiquette` VARCHAR(100) DEFAULT NULL
            COMMENT ''Etiquette du groupe de taxation snapshot (A, B, C...)''',
        'SELECT 1')
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'details_vente' AND COLUMN_NAME = 'tax_etiquette'
), 'SELECT 1');
PREPARE stmt FROM @add; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @add := IFNULL((
    SELECT IF(COUNT(*) = 0,
        'ALTER TABLE `details_vente` ADD COLUMN `tax_groupe` VARCHAR(50) DEFAULT NULL
            COMMENT ''Groupe de taxation snapshot (Groupe B, Groupe C...)''',
        'SELECT 1')
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'details_vente' AND COLUMN_NAME = 'tax_groupe'
), 'SELECT 1');
PREPARE stmt FROM @add; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ----------------------------------------------------------------------------
-- 2. Colonnes jumelles sur `details_vente_archive`
-- ----------------------------------------------------------------------------
-- `scripts/archive_data.php` copie une liste fixe de colonnes : sans ces
-- colonnes, une facture archivée perdrait son groupe de taxation.
SET @add := IFNULL((
    SELECT IF(COUNT(*) = 0,
        'ALTER TABLE `details_vente_archive` ADD COLUMN `tax_rate` DECIMAL(5,2) DEFAULT 0.00
            COMMENT ''Taux TVA snapshot au moment de la vente''',
        'SELECT 1')
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'details_vente_archive' AND COLUMN_NAME = 'tax_rate'
), 'SELECT 1');
PREPARE stmt FROM @add; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @add := IFNULL((
    SELECT IF(COUNT(*) = 0,
        'ALTER TABLE `details_vente_archive` ADD COLUMN `tax_etiquette` VARCHAR(100) DEFAULT NULL
            COMMENT ''Etiquette du groupe de taxation snapshot''',
        'SELECT 1')
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'details_vente_archive' AND COLUMN_NAME = 'tax_etiquette'
), 'SELECT 1');
PREPARE stmt FROM @add; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @add := IFNULL((
    SELECT IF(COUNT(*) = 0,
        'ALTER TABLE `details_vente_archive` ADD COLUMN `tax_groupe` VARCHAR(50) DEFAULT NULL
            COMMENT ''Groupe de taxation snapshot''',
        'SELECT 1')
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'details_vente_archive' AND COLUMN_NAME = 'tax_groupe'
), 'SELECT 1');
PREPARE stmt FROM @add; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ----------------------------------------------------------------------------
-- 3. Backfill : lignes de nuitées déjà facturées
-- ----------------------------------------------------------------------------
-- On rattache le taux de la chambre au groupe de taxation correspondant, en
-- reprenant l'information déjà portée par `ventes.invoice_type` (qui vaut
-- 'HB' pour les anciennes factures hôtel, cf. migration 2026_11_05).
--
-- On passe par `hotel_factures` → `hotel_sejours` → `hotel_chambres` →
-- `taxes` : c'est la chaîne de FK qui relie une ligne de nuit à sa taxe.
UPDATE `details_vente` d
INNER JOIN `ventes` v ON v.id = d.vente_id
INNER JOIN `hotel_factures` hf ON hf.vente_id = v.id
INNER JOIN `hotel_sejours` s ON s.id = hf.sejour_id
INNER JOIN `hotel_chambres` c ON c.id = s.chambre_id
INNER JOIN `taxes` t ON t.id = c.taxe_id
SET d.tax_rate      = t.taux,
    d.tax_etiquette = t.etiquette,
    d.tax_groupe    = t.groupe_taxe
WHERE d.tax_rate IS NULL
  AND v.invoice_type = 'HB';
