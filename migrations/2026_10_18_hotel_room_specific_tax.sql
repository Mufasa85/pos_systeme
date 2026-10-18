-- Migration: ajouter une taxe spécifique (TS) sur hotel_chambres
-- Aligné sur le pattern produit (taxe_specifique_type / taxe_specifique_value).
-- La TS s'applique PAR NUITÉE au moment de la facturation hôtel (Sprint 6 — ajout 2026-10-18).
-- Si 'CDF' : montant fixe par nuit. Si '%' : pourcentage appliqué sur le prix de la nuit.
-- La colonne est stockée au niveau de la chambre : la chambre "Simple 101" a par exemple
-- 5000 Fc / nuit de taxe spécifique touristique — toutes les nuits portent la même TS.

ALTER TABLE `hotel_chambres`
  ADD COLUMN `taxe_specifique_type` VARCHAR(10) NULL DEFAULT '%' AFTER `notes`,
  ADD COLUMN `taxe_specifique_value` DECIMAL(10,2) NULL DEFAULT 0.00 AFTER `taxe_specifique_type`;
