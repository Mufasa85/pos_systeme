-- Migration: Add service column to ventes table
-- Date: 2026-05-14
-- Description: Ajoute la colonne service pour identifier les paiements de recharges (SNEL, REGIDESO)

ALTER TABLE ventes
ADD COLUMN service VARCHAR(50) DEFAULT NULL COMMENT 'Service provider (Eau, Electricite) pour les recharges';

-- Index pour optimiser les requêtes par service
CREATE INDEX idx_ventes_service ON ventes (service);