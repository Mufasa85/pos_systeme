-- Migration: Extension des services pressing
-- Date: 2026-08-09
-- Description: Ajoute les services manquants à l'ENUM `pressing_articles.service`
--              (catalogue d'articles géré côté front en JS, pas de nouvelle table).
--              N'affecte aucune table existante en dehors de `pressing_articles`.

ALTER TABLE `pressing_articles`
  MODIFY COLUMN `service` ENUM(
    'lavage',
    'repassage',
    'lavage_repassage',
    'nettoyage_sec',
    'detachage',
    'desinfection',
    'blanchiment',
    'anti_odeur',
    'express',
    'pliage',
    'emballage_cintre'
  ) NOT NULL;
