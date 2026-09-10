-- Migration : Ajout de la colonne invoice_type a la table ventes
-- Date : 2026-09-10
-- Description :
--   Le type de facture fiscale (FV, FA, FT, EA, EV, ET) etait connu
--   cote frontend et utilise pour la logique de signe, mais jamais
--   sauvegarde en base. On l'ajoute ici pour permettre le filtrage
--   dans les rapports fiscaux (Z/X/A).

ALTER TABLE ventes
ADD COLUMN invoice_type VARCHAR(5) DEFAULT 'FV' COMMENT 'Type de facture fiscale (FV, FA, FT, EA, EV, ET)'
AFTER numero_facture;

ALTER TABLE ventes_archive
ADD COLUMN invoice_type VARCHAR(5) DEFAULT 'FV' COMMENT 'Type de facture fiscale (FV, FA, FT, EA, EV, ET)'
AFTER numero_facture;

-- Index pour le filtrage par type dans les rapports
CREATE INDEX idx_ventes_invoice_type ON ventes (invoice_type);
CREATE INDEX idx_archive_invoice_type ON ventes_archive (invoice_type);
