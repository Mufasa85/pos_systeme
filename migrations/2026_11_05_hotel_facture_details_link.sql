-- ============================================================================
-- MIGRATION : Traçabilité hôtel des lignes de vente + type fiscal réel
-- Date       : 2026-11-05
-- Reference  : docs/PLAN_SEPARATION_MODULES_RAPPORTS.md
--
-- PROBLEME 1 — `details_vente` ne dit rien de l'hotel
--   Les lignes de detail d'une facture hotel (nuitees) sont inserees avec
--   `produit_id = NULL` : impossible de savoir a quel sejour / chambre /
--   date elles se rapportent. On ajoute donc des colonnes de tracabilite.
--
-- PROBLEME 2 — `ventes.invoice_type` vaut 'HB' pour les factures hotel
--   'HB' n'est PAS un type fiscal DGI valide (FV, EV, FT, FA, EA, ET) : il
--   pollue les regroupements par type de facture des rapports. On ajoute un
--   marqueur metier explicite `est_hotel` pour remplacer le 'HB' comme
--   signal "c'est une facture hotel", et on pourra stocker le vrai type
--   fiscal choisi par l'utilisateur dans `ventes.invoice_type`.
--
-- Idempotent : peut etre rejoue sans erreur.
-- ============================================================================

SET NAMES utf8mb4;

-- ----------------------------------------------------------------------------
-- 1. Tracabilite hotel sur `details_vente`
-- ----------------------------------------------------------------------------
-- `sejour_id`  : le sejour hotel facture
-- `chambre_id` : la chambre concernee
-- `ligne_type` : nature de la ligne (produit | nuit | extra_caisse | extra_manuel)
-- `libelle`    : libelle fige au moment de la facturation (ex: "Nuit 05/11 - Chambre 12")
--               -> evite de dependre de `produits.nom` qui peut changer ensuite.
--
-- Idempotent : une colonne par ALTER, avec un controle INFORMATION_SCHEMA
-- (pattern du projet, cf. migration 2026_10_27) → la migration peut etre
-- rejouee sans erreur meme apres une application partielle.
SET @add := IFNULL((
    SELECT IF(COUNT(*) = 0,
        'ALTER TABLE `details_vente` ADD COLUMN `sejour_id` INT DEFAULT NULL
            COMMENT ''Sejour hotel facture (nuitees de la facture hotel)'' AFTER `vente_id`',
        'SELECT 1')
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'details_vente' AND COLUMN_NAME = 'sejour_id'
), 'SELECT 1');
PREPARE stmt FROM @add; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @add := IFNULL((
    SELECT IF(COUNT(*) = 0,
        'ALTER TABLE `details_vente` ADD COLUMN `chambre_id` INT DEFAULT NULL
            COMMENT ''Chambre concernee par la ligne'' AFTER `sejour_id`',
        'SELECT 1')
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'details_vente' AND COLUMN_NAME = 'chambre_id'
), 'SELECT 1');
PREPARE stmt FROM @add; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @add := IFNULL((
    SELECT IF(COUNT(*) = 0,
        'ALTER TABLE `details_vente` ADD COLUMN `ligne_type` VARCHAR(20) NOT NULL DEFAULT ''produit''
            COMMENT ''produit | nuit | extra_caisse | extra_manuel'' AFTER `chambre_id`',
        'SELECT 1')
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'details_vente' AND COLUMN_NAME = 'ligne_type'
), 'SELECT 1');
PREPARE stmt FROM @add; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @add := IFNULL((
    SELECT IF(COUNT(*) = 0,
        'ALTER TABLE `details_vente` ADD COLUMN `libelle` VARCHAR(255) DEFAULT NULL
            COMMENT ''Libelle fige au moment de la facturation'' AFTER `ligne_type`',
        'SELECT 1')
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'details_vente' AND COLUMN_NAME = 'libelle'
), 'SELECT 1');
PREPARE stmt FROM @add; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Index (FK ajoutee plus bas : MySQL refuse une FK sur une colonne qui
-- n'existe pas encore au moment du meme ALTER).
SET @add := IFNULL((
    SELECT IF(COUNT(*) = 0,
        'ALTER TABLE `details_vente` ADD KEY `idx_details_vente_sejour` (`sejour_id`)',
        'SELECT 1')
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'details_vente' AND INDEX_NAME = 'idx_details_vente_sejour'
), 'SELECT 1');
PREPARE stmt FROM @add; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @add := IFNULL((
    SELECT IF(COUNT(*) = 0,
        'ALTER TABLE `details_vente` ADD KEY `idx_details_vente_ligne_type` (`ligne_type`)',
        'SELECT 1')
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'details_vente' AND INDEX_NAME = 'idx_details_vente_ligne_type'
), 'SELECT 1');
PREPARE stmt FROM @add; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ----------------------------------------------------------------------------
-- 2. Marqueur metier "facture hotel" sur `ventes`
-- ----------------------------------------------------------------------------
-- Remplace le 'HB' comme signal "facture hotel". Le type fiscal reel est
-- conserve dans `ventes.invoice_type` (FV, EV, FT, FA, EA, ET).
SET @add := IFNULL((
    SELECT IF(COUNT(*) = 0,
        'ALTER TABLE `ventes` ADD COLUMN `est_hotel` TINYINT(1) NOT NULL DEFAULT 0
            COMMENT ''1 = facture hotel (nuitees + extras). Le type fiscal reste dans invoice_type.'' AFTER `invoice_type`',
        'SELECT 1')
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ventes' AND COLUMN_NAME = 'est_hotel'
), 'SELECT 1');
PREPARE stmt FROM @add; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @add := IFNULL((
    SELECT IF(COUNT(*) = 0,
        'ALTER TABLE `ventes` ADD KEY `idx_ventes_est_hotel` (`est_hotel`)',
        'SELECT 1')
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ventes' AND INDEX_NAME = 'idx_ventes_est_hotel'
), 'SELECT 1');
PREPARE stmt FROM @add; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ----------------------------------------------------------------------------
-- 3. Contrainte FK sur `details_vente.sejour_id`
-- ----------------------------------------------------------------------------
-- Nullable + ON DELETE SET NULL : la suppression d'un sejour ne doit pas
-- bloquer (meme politique que `ventes_sejour_fk`).
SET @fk_exists := (
  SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
  WHERE CONSTRAINT_SCHEMA = DATABASE()
    AND TABLE_NAME = 'details_vente'
    AND CONSTRAINT_NAME = 'fk_details_vente_sejour'
);
SET @fk_sql := IF(@fk_exists = 0,
  'ALTER TABLE `details_vente` ADD CONSTRAINT `fk_details_vente_sejour` FOREIGN KEY (`sejour_id`) REFERENCES `hotel_sejours` (`id`) ON DELETE SET NULL ON UPDATE CASCADE',
  'DO 0');
PREPARE stmt FROM @fk_sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ----------------------------------------------------------------------------
-- 4. Backfill des factures hotel deja emises
-- ----------------------------------------------------------------------------
-- Les factures hotel emises avant cette migration ont `ventes.invoice_type='HB'`
-- et `ventes.sejour_id = NULL`. On les marque `est_hotel = 1` et on rattache
-- leur `sejour_id` via `hotel_factures.vente_id` (FK deja presente).
--
-- ⚠️ `ventes.sejour_id` est deja utilise pour les EXTRA hotel (invoice_type='EX').
-- On ne le renseigne QUE sur les ventes explicitement identifiees comme
-- factures hotel via `hotel_factures` — la jointure se fait sur
-- `hotel_factures.vente_id`, jamais sur le type (donc jamais de collision
-- avec les extras).
UPDATE `ventes` v
INNER JOIN `hotel_factures` hf ON hf.vente_id = v.id
SET v.est_hotel = 1,
    v.sejour_id  = hf.sejour_id;

-- ----------------------------------------------------------------------------
-- 5. Backfill du type fiscal reel des factures hotel
-- ----------------------------------------------------------------------------
-- `hotel_factures.invoice_type` vaut 'HB' par defaut. On ne peut pas deviner
-- le type choisi par l'utilisateur a l'epoque : on bascule sur 'FV'
-- (Facture de Vente), qui est le defaut du modal "Informations Facture".
-- Seul `est_hotel` conserve le marqueur metier.
UPDATE `ventes` v
INNER JOIN `hotel_factures` hf ON hf.vente_id = v.id
SET v.invoice_type = 'FV'
WHERE v.invoice_type = 'HB';

UPDATE `hotel_factures`
SET invoice_type = 'FV'
WHERE invoice_type = 'HB';

-- ----------------------------------------------------------------------------
-- 6. Backfill de la tracabilite des lignes de detail des factures hotel
-- ----------------------------------------------------------------------------
-- On rattache les lignes de detail des ventes hotel a leur sejour / chambre.
-- A ce jour les lignes de detail d'une facture hotel sont uniquement des
-- nuitees (cf. HotelFactureController::store — une ligne par nuit, avec
-- `produit_id = NULL`). Les extras sont factures separement (ventes 'EX' /
-- table `hotel_extras`).
UPDATE `details_vente` d
INNER JOIN `ventes` v ON v.id = d.vente_id
INNER JOIN `hotel_factures` hf ON hf.vente_id = v.id
INNER JOIN `hotel_sejours` s ON s.id = hf.sejour_id
SET d.sejour_id  = s.id,
    d.chambre_id = s.chambre_id,
    d.ligne_type = 'nuit'
WHERE d.sejour_id IS NULL
  AND v.est_hotel = 1;

-- Libelle : "Nuit <JJ/MM> - Chambre <numero>".
-- La date de la nuit = date d'arrivee du sejour + rang de la ligne dans la
-- vente (l'ordre d'insertion des details_vente suit l'ordre des nuits).
--
-- ⚠️ On utilise une table dérivée (`rangs`) au lieu d'une sous-requête
-- corrélée sur la table mise à jour : MySQL refuse (erreur 1093) qu'un
-- UPDATE lise la table qu'il modifie.
UPDATE `details_vente` d
INNER JOIN (
    SELECT d2.id,
           d2.vente_id,
           d2.id - MIN(d2.id) OVER (PARTITION BY d2.vente_id) AS rang
    FROM `details_vente` d2
) rangs ON rangs.id = d.id
INNER JOIN `ventes` v ON v.id = d.vente_id
INNER JOIN `hotel_factures` hf ON hf.vente_id = v.id
INNER JOIN `hotel_sejours` s ON s.id = hf.sejour_id
INNER JOIN `hotel_chambres` c ON c.id = s.chambre_id
SET d.libelle = CONCAT(
      'Nuit ',
      DATE_FORMAT(
        DATE_ADD(s.date_arrivee, INTERVAL rangs.rang DAY),
        '%d/%m'
      ),
      ' - Chambre ', c.numero
    )
WHERE v.est_hotel = 1
  AND d.ligne_type = 'nuit'
  AND (d.libelle IS NULL OR d.libelle = '');

-- ----------------------------------------------------------------------------
-- 7. Colonnes jumelles sur les tables d'archive
-- ----------------------------------------------------------------------------
-- `scripts/archive_data.php` copie une liste FIXE de colonnes : sans ces
-- colonnes jumelles, une facture hôtel archivée perdrait son type fiscal et
-- ses libellés de lignes (la facture imprimée d'une archive afficherait
-- « Article » au lieu de « Nuit 05/11 - Chambre 12 »).
--
-- Idempotent : pattern INFORMATION_SCHEMA (cf. migration 2026_10_27).

SET @add := IFNULL((
    SELECT IF(COUNT(*) = 0,
        'ALTER TABLE `ventes_archive` ADD COLUMN `est_hotel` TINYINT(1) NOT NULL DEFAULT 0
            COMMENT ''1 = facture hotel (nuitees + extras)''',
        'SELECT 1')
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ventes_archive' AND COLUMN_NAME = 'est_hotel'
), 'SELECT 1');
PREPARE stmt FROM @add; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @add := IFNULL((
    SELECT IF(COUNT(*) = 0,
        'ALTER TABLE `details_vente_archive` ADD COLUMN `sejour_id` INT NULL
            COMMENT ''Sejour hotel facture''',
        'SELECT 1')
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'details_vente_archive' AND COLUMN_NAME = 'sejour_id'
), 'SELECT 1');
PREPARE stmt FROM @add; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @add := IFNULL((
    SELECT IF(COUNT(*) = 0,
        'ALTER TABLE `details_vente_archive` ADD COLUMN `chambre_id` INT NULL
            COMMENT ''Chambre concernee par la ligne''',
        'SELECT 1')
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'details_vente_archive' AND COLUMN_NAME = 'chambre_id'
), 'SELECT 1');
PREPARE stmt FROM @add; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @add := IFNULL((
    SELECT IF(COUNT(*) = 0,
        'ALTER TABLE `details_vente_archive` ADD COLUMN `ligne_type` VARCHAR(20) NOT NULL DEFAULT ''produit''
            COMMENT ''produit | nuit | extra_caisse | extra_manuel''',
        'SELECT 1')
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'details_vente_archive' AND COLUMN_NAME = 'ligne_type'
), 'SELECT 1');
PREPARE stmt FROM @add; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @add := IFNULL((
    SELECT IF(COUNT(*) = 0,
        'ALTER TABLE `details_vente_archive` ADD COLUMN `libelle` VARCHAR(255) NULL
            COMMENT ''Libelle fige au moment de la facturation''',
        'SELECT 1')
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'details_vente_archive' AND COLUMN_NAME = 'libelle'
), 'SELECT 1');
PREPARE stmt FROM @add; EXECUTE stmt; DEALLOCATE PREPARE stmt;
