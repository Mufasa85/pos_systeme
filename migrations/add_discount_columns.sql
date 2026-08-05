-- Migration: ajout des champs remise_type et remise_value pour les produits et les lignes de vente
-- remise_type : '%' pour un pourcentage, 'CDF' pour un montant fixe
-- remise_value : valeur numerique de la remise

-- Ajout des colonnes sur la table produits
ALTER TABLE produits
ADD COLUMN remise_type VARCHAR(10) NULL DEFAULT '%' AFTER remise,
ADD COLUMN remise_value DECIMAL(10,2) NULL DEFAULT 0 AFTER remise_type;

-- Migration des anciennes remises en pourcentage vers la nouvelle structure
UPDATE produits
SET remise_type = '%',
    remise_value = remise
WHERE remise IS NOT NULL;

-- Suppression de l'ancienne colonne remise unique
ALTER TABLE produits DROP COLUMN remise;

-- Ajout des colonnes sur la table details_vente
ALTER TABLE details_vente
ADD COLUMN remise_type VARCHAR(10) NULL DEFAULT '%' AFTER prix,
ADD COLUMN remise_value DECIMAL(10,2) NULL DEFAULT 0 AFTER remise_type;
