ALTER TABLE produits
ADD COLUMN taxe_specifique_type VARCHAR(10) NULL DEFAULT '%' AFTER remise_value,
ADD COLUMN taxe_specifique_value DECIMAL(10,2) NULL DEFAULT 0 AFTER taxe_specifique_type;

ALTER TABLE details_vente
ADD COLUMN taxe_specifique_type VARCHAR(10) NULL DEFAULT '%' AFTER remise_value,
ADD COLUMN taxe_specifique_value DECIMAL(10,2) NULL DEFAULT 0 AFTER taxe_specifique_type;
