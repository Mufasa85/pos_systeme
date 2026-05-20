-- Migration: Add product_type column to produits table
-- Date: 2026-05-20
-- Description: Ajoute la colonne product_type pour identifier si un produit est vendable sans unité (poids variable)
-- Types: 'unite' (vente à l'unité), 'poids' (vente au kilo/gramme)
-- Utilisé pour les produits comme charcuterie, fromage, etc.

ALTER TABLE produits
ADD COLUMN product_type VARCHAR(20) DEFAULT 'unite' COMMENT 'Type de vente: unite (à l''unité) ou poids (au kilo/gramme)';

-- Index pour optimiser les requêtes par type de produit
CREATE INDEX idx_produits_product_type ON produits (product_type);