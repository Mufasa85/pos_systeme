-- Migration: rendre details_vente.produit_id NULLABLE
-- Référence  : bug HotelFactureController::store — la facture hôtel
-- (régime B, sans extras) tente d'insérer des lignes de "nuit" avec
-- produit_id = null. Mais la colonne est NOT NULL → violation 1048.
--
-- Avant : `produit_id` int NOT NULL
--   - Bloquait l'enregistrement des factures hôtel sans extras
--     (les nuits n'ont pas de produit associé, juste un prix et une TS).
--   - Le commentaire HotelFactureController ligne 742 mentionne déjà
--     "on retombe sur produit_id=0 (sentinelle)" mais cela viole la FK.
--
-- Après : `produit_id` int NULL
--   - Aligné sur le pattern `hotel_extras.produit_id` (déjà nullable
--     cf. migration 2026_10_18_hotel_extras_product_details.sql).
--   - Le code `BillPaymentController` ligne 132 fait déjà
--     `VALUES (?, NULL, 1, ?, ?)` — c'était cassé aussi pour les
--     recharges ! La migration le répare d'un coup.
--   - Le `LEFT JOIN produits` dans Report.php et SaleDetail.php::getBySaleId
--     gère déjà le cas NULL (LEFT JOIN → colonnes NULL si pas de match).
--   - L'UI (facture.php, facturation hôtel) gère déjà le cas
--     `produit_nom = NULL` via `COALESCE` / fallback "Nuitée chambre X".
--
-- Rétro-compatibilité : tous les `produit_id` existants restent valides.
-- Les nouvelles lignes peuvent être NULL sans impact (pas de trigger,
-- pas de vue qui s'attend à un entier non-null).

SET NAMES utf8mb4;
SET @sql_mode = (SELECT @@SESSION.sql_mode);
SET SESSION sql_mode = '';

-- Drop toutes les FK éventuelles de details_vente.produit_id (peu importe
-- le nom) pour pouvoir modifier la colonne. La FK sera recréée juste après.
SET @drop_fk_sql := (
    SELECT GROUP_CONCAT(
        CONCAT('ALTER TABLE `details_vente` DROP FOREIGN KEY `', CONSTRAINT_NAME, '`')
        SEPARATOR '; '
    )
    FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'details_vente'
      AND CONSTRAINT_TYPE = 'FOREIGN KEY'
      AND CONSTRAINT_NAME IN (
          SELECT CONSTRAINT_NAME FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
          WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = 'details_vente'
            AND COLUMN_NAME = 'produit_id'
      )
);
SET @drop_fk_sql := IFNULL(@drop_fk_sql, 'SELECT 1');
PREPARE stmt FROM @drop_fk_sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Rendre produit_id NULLABLE
ALTER TABLE `details_vente`
  MODIFY COLUMN `produit_id` INT NULL DEFAULT NULL
    COMMENT 'FK vers produits. NULL pour les lignes de type service (hôtel, recharge) sans produit stocké.';

-- Recréer la FK avec ON DELETE SET NULL pour rester cohérent avec
-- le pattern déjà appliqué à hotel_extras (cf. migration 2026_10_18).
SET @fk_exists := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'details_vente'
      AND CONSTRAINT_TYPE = 'FOREIGN KEY'
      AND CONSTRAINT_NAME = 'fk_details_vente_produit'
);
SET @stmt := IF(@fk_exists = 0,
    'ALTER TABLE `details_vente`
       ADD CONSTRAINT `fk_details_vente_produit`
       FOREIGN KEY (`produit_id`) REFERENCES `produits` (`id`)
       ON DELETE SET NULL ON UPDATE CASCADE',
    'SELECT 1');
PREPARE stmt FROM @stmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET SESSION sql_mode = @sql_mode;
