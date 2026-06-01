-- Migration: Change stock column from INT to FLOAT
-- Allows fractional quantities (e.g., 0.5 for half products like coupe)

ALTER TABLE produits MODIFY COLUMN stock FLOAT NOT NULL DEFAULT 0;

-- Also update stock_minimum to float for consistency
ALTER TABLE produits
MODIFY COLUMN stock_minimum FLOAT NOT NULL DEFAULT 0;