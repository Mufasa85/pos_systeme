-- Migration: lier une chambre hôtel à une ligne de la table `taxes`
-- (FK `taxe_id`) afin de récupérer directement la valeur du taux
-- applicable à la chambre (TVA type B hôtellerie = 16% en standard).
--
-- Pourquoi :
--  - Avant cette migration, le front /api/hotel/factures faisait un
--    fetch /api/taxes séparé pour lire le taux B. Désormais, la valeur
--    est portée par la chambre (snapshot figé à la création) et héritée
--    automatiquement par chaque séjour via la jointure.
--  - Une seule source de vérité : si l'admin modifie le taux d'une taxe
--    dans la table `taxes`, on peut faire un UPDATE ciblé sur la chambre
--    (ou un backfill global) pour propager. Les chambres qui n'ont pas
--    de taxe_id restent NULL et la DGI retombe sur son propre défaut.
--
-- Comportement par défaut : on tente de backfiller `taxe_id` avec la
-- taxe B (etiquette='B') pour les chambres existantes, si elle existe.
-- Si la table `taxes` ne contient pas de ligne B, on laisse NULL.

ALTER TABLE `hotel_chambres`
  ADD COLUMN `taxe_id` INT NULL DEFAULT NULL AFTER `taxe_specifique_value`,
  ADD KEY `idx_hotel_chambres_taxe_id` (`taxe_id`),
  ADD CONSTRAINT `fk_hotel_chambres_taxe`
    FOREIGN KEY (`taxe_id`) REFERENCES `taxes` (`id`)
    ON DELETE SET NULL
    ON UPDATE CASCADE;

-- Backfill : on essaye de pointer chaque chambre vers la taxe B (id=2
-- dans la base seed), si elle existe. Si la requête UPDATE ne touche
-- aucune ligne (pas de chambre, ou pas de taxe B), c'est sans impact.
UPDATE `hotel_chambres` hc
  INNER JOIN `taxes` t ON t.etiquette = 'B'
SET hc.taxe_id = t.id
WHERE hc.taxe_id IS NULL;
