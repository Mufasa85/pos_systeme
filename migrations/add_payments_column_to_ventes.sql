ALTER TABLE ventes
ADD COLUMN payments JSON NULL COMMENT 'Détail des modes de paiement (JSON)' AFTER total;
