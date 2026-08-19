-- Migration: Ajouter le type de service "Pressing"
-- Date: 2026-08-09
-- Description: "Restaurant" existe déjà dans service_types (seed multi_shop_evolution.sql).
--              Il manque "Pressing" pour activer le module Pressing sur une boutique.

INSERT INTO `service_types` (`name`)
SELECT 'Pressing' WHERE NOT EXISTS (
  SELECT 1 FROM `service_types` WHERE `name` = 'Pressing'
);
