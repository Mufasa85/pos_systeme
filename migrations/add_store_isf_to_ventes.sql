-- Migration: Add store_isf column to ventes / ventes_archive
-- Date: 2026-08-04
-- Description:
--   La recherche d'une facture aupres de la DGI necessite le couple
--   (store_isf, invoice_number). Jusqu'ici l'ISF etait pris depuis la session
--   de l'utilisateur connecte, ce qui echouait ("Facture introuvable") des
--   qu'un super_admin (ou un utilisateur d'une autre boutique) consultait une
--   facture emise par une autre boutique.
--   On fige donc l'ISF emetteur sur la vente au moment de sa creation.

ALTER TABLE ventes
ADD COLUMN store_isf VARCHAR(50) DEFAULT NULL COMMENT 'ISF de la boutique emettrice, fige a la creation (recherche DGI)';

ALTER TABLE ventes_archive
ADD COLUMN store_isf VARCHAR(50) DEFAULT NULL COMMENT 'ISF de la boutique emettrice, fige a la creation (recherche DGI)';

-- Index pour les recherches DGI (couple ISF + numero de facture)
CREATE INDEX idx_ventes_store_isf ON ventes (store_isf);
CREATE INDEX idx_archive_store_isf ON ventes_archive (store_isf);

-- Backfill des ventes existantes depuis la boutique rattachee
UPDATE ventes v
JOIN shops s ON v.shop_id = s.id
SET v.store_isf = s.isf
WHERE v.store_isf IS NULL
  AND s.isf IS NOT NULL
  AND s.isf <> '';

UPDATE ventes_archive va
JOIN shops s ON va.shop_id = s.id
SET va.store_isf = s.isf
WHERE va.store_isf IS NULL
  AND s.isf IS NOT NULL
  AND s.isf <> '';

-- Ventes sans shop_id : factures emises par le super_admin (non rattache a une
-- boutique) ou anciennes donnees mono-boutique. L'ISF de reference est celui de
-- la societe (table company_info).
UPDATE ventes
SET store_isf = (SELECT isf FROM company_info WHERE id = 1)
WHERE (store_isf IS NULL OR store_isf = '')
  AND shop_id IS NULL
  AND (SELECT isf FROM company_info WHERE id = 1) IS NOT NULL
  AND (SELECT isf FROM company_info WHERE id = 1) <> '';

UPDATE ventes_archive
SET store_isf = (SELECT isf FROM company_info WHERE id = 1)
WHERE (store_isf IS NULL OR store_isf = '')
  AND shop_id IS NULL
  AND (SELECT isf FROM company_info WHERE id = 1) IS NOT NULL
  AND (SELECT isf FROM company_info WHERE id = 1) <> '';

-- Verification : ventes restees sans ISF (a corriger manuellement si besoin)
SELECT COUNT(*) AS ventes_sans_store_isf FROM ventes WHERE store_isf IS NULL OR store_isf = '';
