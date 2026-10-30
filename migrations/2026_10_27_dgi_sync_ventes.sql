-- ============================================================================
-- MIGRATION : Synchronisation DGI différée (cas "update" renvoyé par OSAT)
-- Date       : 2026-10-27
-- Référence  : DGI indisponible => OSAT renvoie un fallback (store_homologation = 2)
--               et met la demande en attente de son côté. Quand la DGI revient,
--               OSAT ré-requête la DGI puis vient chercher la mise à jour chez
--               nous (POST /api/dgi/update) pour compléter la ligne `ventes`.
--
-- PROBLÈME
-- --------
-- 1. AUCUNE colonne ne portait l'état "en attente de résolution DGI".
--    `ventes` conserve bien les éléments de sécurité (counters, codeDEFDGI, nim,
--    qrCode, dateDGI), mais rien ne distingue :
--      - une vente réellement enregistrée chez la DGI ;
--      - une vente en attente (fallback technique car la DGI était down) ;
--      - une vente "non assujettie" (boutique non homologuée => structurel).
--    Sans cet état, il faut redériver la situation à chaque lecture en croisant
--    5 colonnes, et les ventes historiques (avant la migration) restent
--    indéterminables.
--
-- 2. LA CLÉ DGI N'EXISTAIT NULLE PART EN BASE.
--    Le front envoie `invoice_number` à OSAT (cf. app.js : payload DGI), mais
--    ce numéro n'est PAS transmis au backend : le serveur regénère son propre
--    `numero_facture` (SaleController::create => Sale::generateInvoiceNumber).
--    Or generateInvoiceNumber() fait "SELECT ... ORDER BY id DESC LIMIT 1"
--    SANS verrou : deux caisses simultanées obtiennent deux numéros différents.
--    Résultat : OSAT a mémorisé une clé, la base en contient une autre => la
--    relecture ne retrouve jamais la facture.
--    => `dgi_invoice_number` FIGE la clé telle qu'envoyée à OSAT.
--
-- 3. AUCUNE TRACE de la résolution : impossible de savoir quand une facture a
--    été complétée, ni combien de tentatives de relecture ont échoué (donc
--    impossible de doser/protéger l'API OSAT d'un martèlement).
--
-- DÉCISION
-- --------
-- 6 colonnes + 1 index sur `ventes` :
--   dgi_invoice_number : clé DGI figée à la création (= invoice_number OSAT)
--   dgi_status         : pending | synced | not_applicable | NULL (=historique)
--   dgi_synced_at      : date de résolution DGI (≠ date de vente)
--   dgi_attempts       : compteur de relectures échouées (backoff / protection)
--   dgi_last_error     : diagnostic ("Facture introuvable" ≠ timeout ≠ token)
--   dgi_response       : JSON brut de la réponse OSAT (permet de re-rendre le
--                         ticket enrichi SANS rappeler l'API, et de survivre à
--                         une panne OSAT)
--
-- `ventes_archive` reçoit les mêmes colonnes : le script d'archivage copie
-- une liste FIXE de colonnes (scripts/archive_data.php) — sans cela, l'info
-- DGI serait perdue au bout de 3 mois.
--
-- VALEURS DE `dgi_status`
-- ----------------------
--   synced          : les éléments de sécurité sont présents (counters /
--                     codeDEFDGI / nim) => facture enregistrée chez la DGI.
--   pending         : OSAT a signalé un cas d'update (store_homologation = 2)
--                     => on attend la résolution + relecture.
--   not_applicable  : boutique non homologuée (structurel, rien n'arrivera
--                     jamais) ou vente sans DGI (extra hôtel 'EX').
--   NULL            : vente historique, état inconnu => on ne la traite pas.
--
-- RÉTRO-COMPATIBILITÉ
--   - Aucune ligne existante n'est modifiée (dgi_status reste NULL).
--   - Les vues existantes continuent de fonctionner : `isDgiRegistered`
--     (facture.php:6 / facture-ticket.php:7) reste le détecteur de référence.
--   - Le script est idempotent : il peut être rejoué sans risque.
--
-- EXÉCUTION
--   mysql -u root -p pos_system < migrations/2026_10_27_dgi_sync_ventes.sql
-- ============================================================================

SET NAMES utf8mb4;
SET @sql_mode = (SELECT @@SESSION.sql_mode);
SET SESSION sql_mode = '';

-- ----------------------------------------------------------------------------
-- Helper : ajoute une colonne seulement si elle n'existe pas déjà.
-- (même pattern idempotent que la migration 2026_10_25)
-- ----------------------------------------------------------------------------

-- 1. ventes.dgi_invoice_number
SET @add := IFNULL((
    SELECT IF(COUNT(*) = 0,
        'ALTER TABLE `ventes` ADD COLUMN `dgi_invoice_number` VARCHAR(50) NULL
            COMMENT ''Clé DGI figée à la création = invoice_number envoyé à OSAT''',
        'SELECT 1')
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ventes' AND COLUMN_NAME = 'dgi_invoice_number'
), 'SELECT 1');
PREPARE stmt FROM @add; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 2. ventes.dgi_status
SET @add := IFNULL((
    SELECT IF(COUNT(*) = 0,
        'ALTER TABLE `ventes` ADD COLUMN `dgi_status` VARCHAR(20) NULL
            COMMENT ''Statut sync DGI : pending | synced | not_applicable | NULL=historique''',
        'SELECT 1')
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ventes' AND COLUMN_NAME = 'dgi_status'
), 'SELECT 1');
PREPARE stmt FROM @add; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 3. ventes.dgi_synced_at
SET @add := IFNULL((
    SELECT IF(COUNT(*) = 0,
        'ALTER TABLE `ventes` ADD COLUMN `dgi_synced_at` DATETIME NULL
            COMMENT ''Date de résolution DGI (≠ date de vente)''',
        'SELECT 1')
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ventes' AND COLUMN_NAME = 'dgi_synced_at'
), 'SELECT 1');
PREPARE stmt FROM @add; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 4. ventes.dgi_attempts
SET @add := IFNULL((
    SELECT IF(COUNT(*) = 0,
        'ALTER TABLE `ventes` ADD COLUMN `dgi_attempts` INT NOT NULL DEFAULT 0
            COMMENT ''Nombre de relectures DGI échouées (protection API)''',
        'SELECT 1')
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ventes' AND COLUMN_NAME = 'dgi_attempts'
), 'SELECT 1');
PREPARE stmt FROM @add; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 5. ventes.dgi_last_error
SET @add := IFNULL((
    SELECT IF(COUNT(*) = 0,
        'ALTER TABLE `ventes` ADD COLUMN `dgi_last_error` VARCHAR(255) NULL
            COMMENT ''Diagnostic de la dernière relecture DGI''',
        'SELECT 1')
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ventes' AND COLUMN_NAME = 'dgi_last_error'
), 'SELECT 1');
PREPARE stmt FROM @add; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 6. ventes.dgi_response
SET @add := IFNULL((
    SELECT IF(COUNT(*) = 0,
        'ALTER TABLE `ventes` ADD COLUMN `dgi_response` TEXT NULL
            COMMENT ''Réponse brute OSAT/DGI (dernière résolution) - JSON''',
        'SELECT 1')
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ventes' AND COLUMN_NAME = 'dgi_response'
), 'SELECT 1');
PREPARE stmt FROM @add; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 7. Index de travail : sert à lister les ventes à resynchroniser.
SET @add := IFNULL((
    SELECT IF(COUNT(*) = 0,
        'ALTER TABLE `ventes` ADD KEY `idx_ventes_dgi_status` (`dgi_status`, `dgi_synced_at`)',
        'SELECT 1')
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ventes' AND INDEX_NAME = 'idx_ventes_dgi_status'
), 'SELECT 1');
PREPARE stmt FROM @add; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ----------------------------------------------------------------------------
-- 8. Colonnes jumelles sur `ventes_archive` (l'archivage copie une liste
--    fixe de colonnes : sans cela, l'info DGI est perdue au bout de 3 mois).
-- ----------------------------------------------------------------------------

SET @add := IFNULL((
    SELECT IF(COUNT(*) = 0,
        'ALTER TABLE `ventes_archive` ADD COLUMN `dgi_invoice_number` VARCHAR(50) NULL
            COMMENT ''Clé DGI figée à la création = invoice_number envoyé à OSAT''',
        'SELECT 1')
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ventes_archive' AND COLUMN_NAME = 'dgi_invoice_number'
), 'SELECT 1');
PREPARE stmt FROM @add; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @add := IFNULL((
    SELECT IF(COUNT(*) = 0,
        'ALTER TABLE `ventes_archive` ADD COLUMN `dgi_status` VARCHAR(20) NULL
            COMMENT ''Statut sync DGI : pending | synced | not_applicable | NULL=historique''',
        'SELECT 1')
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ventes_archive' AND COLUMN_NAME = 'dgi_status'
), 'SELECT 1');
PREPARE stmt FROM @add; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @add := IFNULL((
    SELECT IF(COUNT(*) = 0,
        'ALTER TABLE `ventes_archive` ADD COLUMN `dgi_synced_at` DATETIME NULL
            COMMENT ''Date de résolution DGI (≠ date de vente)''',
        'SELECT 1')
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ventes_archive' AND COLUMN_NAME = 'dgi_synced_at'
), 'SELECT 1');
PREPARE stmt FROM @add; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @add := IFNULL((
    SELECT IF(COUNT(*) = 0,
        'ALTER TABLE `ventes_archive` ADD COLUMN `dgi_attempts` INT NOT NULL DEFAULT 0
            COMMENT ''Nombre de relectures DGI échouées (protection API)''',
        'SELECT 1')
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ventes_archive' AND COLUMN_NAME = 'dgi_attempts'
), 'SELECT 1');
PREPARE stmt FROM @add; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @add := IFNULL((
    SELECT IF(COUNT(*) = 0,
        'ALTER TABLE `ventes_archive` ADD COLUMN `dgi_last_error` VARCHAR(255) NULL
            COMMENT ''Diagnostic de la dernière relecture DGI''',
        'SELECT 1')
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ventes_archive' AND COLUMN_NAME = 'dgi_last_error'
), 'SELECT 1');
PREPARE stmt FROM @add; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @add := IFNULL((
    SELECT IF(COUNT(*) = 0,
        'ALTER TABLE `ventes_archive` ADD COLUMN `dgi_response` TEXT NULL
            COMMENT ''Réponse brute OSAT/DGI (dernière résolution) - JSON''',
        'SELECT 1')
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ventes_archive' AND COLUMN_NAME = 'dgi_response'
), 'SELECT 1');
PREPARE stmt FROM @add; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET SESSION sql_mode = @sql_mode;

-- ============================================================================
-- FIN DE LA MIGRATION
-- ============================================================================
