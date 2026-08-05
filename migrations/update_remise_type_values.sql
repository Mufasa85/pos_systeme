-- Script de mise à jour des valeurs de remise_type après changement de convention
-- Ancienne convention : 'percent' / 'amount'
-- Nouvelle convention  : '%' / 'CDF'

-- Normaliser les types de remise sur la table produits
UPDATE produits
SET remise_type = '%'
WHERE remise_type NOT IN ('%', 'CDF') OR remise_type IS NULL;

UPDATE produits
SET remise_type = 'CDF'
WHERE remise_type = 'amount';

-- Normaliser les types de remise sur la table details_vente
UPDATE details_vente
SET remise_type = '%'
WHERE remise_type NOT IN ('%', 'CDF') OR remise_type IS NULL;

UPDATE details_vente
SET remise_type = 'CDF'
WHERE remise_type = 'amount';

-- Sécurité : bloquer les valeurs de remise incohérentes
-- Pourcentage : entre 0 et 100
UPDATE produits
SET remise_value = 0
WHERE remise_type = '%' AND (remise_value < 0 OR remise_value > 100);

-- Montant fixe : entre 0 et le prix du produit
UPDATE produits p
SET p.remise_value = 0
WHERE p.remise_type = 'CDF' AND (p.remise_value < 0 OR p.remise_value > p.prix);

-- S'assurer que remise_value est 0 par défaut si NULL
UPDATE produits SET remise_value = 0 WHERE remise_value IS NULL;
UPDATE details_vente SET remise_value = 0 WHERE remise_value IS NULL;
