-- ============================================================================
-- MIGRATION : Séparation des rôles métier Hôtel (PMS)
-- Date       : 2026-10-15
-- Référence  : docs/PLAN_HOTEL_V1.md
--
-- Ajoute une colonne `hotel_role` à la table `utilisateurs` pour distinguer
-- le rôle métier dans le module Hôtel (manager / réceptionniste / gouvernante)
-- du rôle applicatif existant (`role` : super_admin / admin / vendeur).
--
-- Pourquoi :
--   - Un `admin` est automatiquement `manager` de l'hôtel.
--   - Un `vendeur` peut se voir assigner un rôle hôtel sans changer de rôle
--     applicatif (ex. vendeuse qui devient réceptionniste le soir).
--   - Un `super_admin` a accès à l'hôtel partout, tous shops, tous rôles.
--
-- Aucun rôle n'est rétro-attribué automatiquement. C'est le manager qui
-- décide via la page /hotel/roles. Les `admin` et `super_admin` existants
-- gardent leur accès via requireHotelAccess (manager virtuel).
-- ============================================================================

SET NAMES utf8mb4;

ALTER TABLE `utilisateurs`
  ADD COLUMN `hotel_role` ENUM('manager', 'receptionniste', 'gouvernante')
    NULL DEFAULT NULL
    COMMENT 'Rôle métier dans le module Hôtel. NULL = pas accès hôtel.'
    AFTER `role`,
  ADD KEY `utilisateurs_hotel_role_idx` (`hotel_role`);
