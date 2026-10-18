-- ============================================================================
-- MIGRATION : Lien client POS ↔ guest hôtel
-- Date       : 2026-10-17
-- Référence  : discussion 2026-10-17 — option A (lien souple 1 client → N guests)
--
-- Ajoute une colonne `client_id` (FK nullable vers `clients.id`) dans
-- `hotel_guests`. Permet à un guest d'être rattaché à un client commercial
-- existant (utilisé pour recharges / bill payment depuis la fiche client
-- et pour imputer les extras du séjour au même client côté POS).
--
-- Règles :
--  - nullable : un guest sans compte client reste valide (clients de passage)
--  - ON DELETE SET NULL : si le client POS est supprimé, on garde le guest
--    (historique comptable) et on perd juste le lien
--  - ON UPDATE CASCADE : pas critique ici (PK stable), mais propre
-- ============================================================================

SET NAMES utf8mb4;

ALTER TABLE `hotel_guests`
  ADD COLUMN `client_id` INT NULL
    COMMENT 'FK vers clients.id (POS). NULL = guest sans compte client.'
    AFTER `nationalite`,
  ADD KEY `hotel_guests_client_idx` (`client_id`),
  ADD CONSTRAINT `hotel_guests_client_fk`
    FOREIGN KEY (`client_id`) REFERENCES `clients`(`id`)
    ON DELETE SET NULL
    ON UPDATE CASCADE;