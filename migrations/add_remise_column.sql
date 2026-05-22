-- Migration: Ajouter la colonne remise à la table produits
-- Permet de sauvegarder les différentes remises par produit

ALTER TABLE produits
ADD COLUMN remise DECIMAL(5, 2) DEFAULT 0.00 COMMENT 'Remise en pourcentage (0-100)';

-- Mettre à jour les index si nécessaire
CREATE INDEX IF NOT EXISTS idx_produits_remise ON produits (remise);