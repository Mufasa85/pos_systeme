-- ============================================================================
-- MIGRATION : Module Hôtel (PMS) V1 — fondations
-- Auteur     : Hermes / projet POS
-- Date       : 2026-10-15
-- Référence  : docs/PLAN_HOTEL_V1.md
--
-- Crée les 6 tables du module hôtel + le seed initial de 20 chambres
-- (10 simples + 10 doubles). Les rôles `receptionniste` et `gouvernante`
-- sont gérés via la colonne `role` existante de la table `utilisateurs`
-- (cf. AGENT.md section 3).
-- ============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ----------------------------------------------------------------------------
-- 1. Table des chambres
-- ----------------------------------------------------------------------------
DROP TABLE IF EXISTS `hotel_chambres`;
CREATE TABLE `hotel_chambres` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `numero` VARCHAR(10) NOT NULL,
  `type` ENUM('simple','double') NOT NULL DEFAULT 'simple',
  `prix_base` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `etat` ENUM('disponible','a_nettoyer','hors_service') NOT NULL DEFAULT 'disponible',
  `notes` TEXT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `hotel_chambres_numero_unique` (`numero`),
  KEY `hotel_chambres_etat_idx` (`etat`),
  KEY `hotel_chambres_type_idx` (`type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Chambres physiques de l''hôtel';

-- ----------------------------------------------------------------------------
-- 2. Table des clients de passage (guests)
-- ----------------------------------------------------------------------------
DROP TABLE IF EXISTS `hotel_guests`;
CREATE TABLE `hotel_guests` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `nom` VARCHAR(100) NOT NULL,
  `prenom` VARCHAR(100) NULL,
  `telephone` VARCHAR(30) NULL,
  `piece_identite` VARCHAR(50) NULL,
  `nationalite` VARCHAR(50) NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `hotel_guests_nom_idx` (`nom`),
  KEY `hotel_guests_telephone_idx` (`telephone`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Clients ayant séjourné à l''hôtel';

-- ----------------------------------------------------------------------------
-- 3. Table des séjours (= guest dans chambre sur période)
-- ----------------------------------------------------------------------------
DROP TABLE IF EXISTS `hotel_sejours`;
CREATE TABLE `hotel_sejours` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `chambre_id` INT NOT NULL,
  `guest_id` INT NOT NULL,
  `date_arrivee` DATETIME NOT NULL,
  `date_depart_prevue` DATE NOT NULL,
  `date_depart_reelle` DATETIME NULL,
  `statut` ENUM('reserve','en_cours','termine','annule','no_show') NOT NULL DEFAULT 'en_cours',
  `prix_par_nuit` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `remise_pct` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
  `notes` TEXT NULL,
  `user_id` INT NOT NULL COMMENT 'Utilisateur (réceptionniste) qui a créé le séjour',
  `version` INT NOT NULL DEFAULT 1 COMMENT 'Verrou optimiste anti-concurrence',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `hotel_sejours_chambre_idx` (`chambre_id`),
  KEY `hotel_sejours_guest_idx` (`guest_id`),
  KEY `hotel_sejours_statut_idx` (`statut`),
  KEY `hotel_sejours_date_arrivee_idx` (`date_arrivee`),
  CONSTRAINT `hotel_sejours_chambre_fk` FOREIGN KEY (`chambre_id`) REFERENCES `hotel_chambres`(`id`),
  CONSTRAINT `hotel_sejours_guest_fk` FOREIGN KEY (`guest_id`) REFERENCES `hotel_guests`(`id`),
  CONSTRAINT `hotel_sejours_user_fk` FOREIGN KEY (`user_id`) REFERENCES `utilisateurs`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Séjours = un guest dans une chambre sur une période';

-- ----------------------------------------------------------------------------
-- 4. Table des extras consommés (pont vers la caisse POS)
-- ----------------------------------------------------------------------------
DROP TABLE IF EXISTS `hotel_extras`;
CREATE TABLE `hotel_extras` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `sejour_id` INT NOT NULL,
  `vente_id` INT NULL COMMENT 'ID de la vente POS si imputée via la caisse',
  `description` VARCHAR(255) NOT NULL,
  `montant` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `date_consommation` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `user_id` INT NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `hotel_extras_sejour_idx` (`sejour_id`),
  KEY `hotel_extras_vente_idx` (`vente_id`),
  CONSTRAINT `hotel_extras_sejour_fk` FOREIGN KEY (`sejour_id`) REFERENCES `hotel_sejours`(`id`) ON DELETE CASCADE,
  CONSTRAINT `hotel_extras_vente_fk` FOREIGN KEY (`vente_id`) REFERENCES `ventes`(`id`) ON DELETE SET NULL,
  CONSTRAINT `hotel_extras_user_fk` FOREIGN KEY (`user_id`) REFERENCES `utilisateurs`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Extras (bar, restaurant) rattachés à un séjour';

-- ----------------------------------------------------------------------------
-- 5. Table des paiements partiels (acompte, soldes)
-- ----------------------------------------------------------------------------
DROP TABLE IF EXISTS `hotel_paiements`;
CREATE TABLE `hotel_paiements` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `sejour_id` INT NOT NULL,
  `montant` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `mode_paiement` ENUM('cash','mobile_money','carte','autre') NOT NULL DEFAULT 'cash',
  `user_id` INT NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `hotel_paiements_sejour_idx` (`sejour_id`),
  KEY `hotel_paiements_created_idx` (`created_at`),
  CONSTRAINT `hotel_paiements_sejour_fk` FOREIGN KEY (`sejour_id`) REFERENCES `hotel_sejours`(`id`) ON DELETE CASCADE,
  CONSTRAINT `hotel_paiements_user_fk` FOREIGN KEY (`user_id`) REFERENCES `utilisateurs`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Paiements partiels sur un séjour';

-- ----------------------------------------------------------------------------
-- 6. Table des tâches housekeeping
-- ----------------------------------------------------------------------------
DROP TABLE IF EXISTS `hotel_housekeeping`;
CREATE TABLE `hotel_housekeeping` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `chambre_id` INT NOT NULL,
  `sejour_id` INT NULL COMMENT 'Séjour qui vient de libérer la chambre (null si maintenance)',
  `type` ENUM('nettoyage','inspection','maintenance') NOT NULL DEFAULT 'nettoyage',
  `statut` ENUM('a_faire','en_cours','fait','bloque') NOT NULL DEFAULT 'a_faire',
  `assigne_a` INT NULL COMMENT 'ID de la gouvernante',
  `date_creation` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `date_fait` DATETIME NULL,
  `notes` TEXT NULL,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `hotel_housekeeping_chambre_idx` (`chambre_id`),
  KEY `hotel_housekeeping_sejour_idx` (`sejour_id`),
  KEY `hotel_housekeeping_assigne_idx` (`assigne_a`),
  KEY `hotel_housekeeping_statut_idx` (`statut`),
  CONSTRAINT `hotel_housekeeping_chambre_fk` FOREIGN KEY (`chambre_id`) REFERENCES `hotel_chambres`(`id`),
  CONSTRAINT `hotel_housekeeping_sejour_fk` FOREIGN KEY (`sejour_id`) REFERENCES `hotel_sejours`(`id`) ON DELETE SET NULL,
  CONSTRAINT `hotel_housekeeping_assigne_fk` FOREIGN KEY (`assigne_a`) REFERENCES `utilisateurs`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Tâches de ménage / inspection / maintenance';

-- ----------------------------------------------------------------------------
-- 7. Table des saisons tarifaires
-- ----------------------------------------------------------------------------
DROP TABLE IF EXISTS `hotel_tarifs_saison`;
CREATE TABLE `hotel_tarifs_saison` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `nom` VARCHAR(50) NOT NULL,
  `date_debut` DATE NOT NULL,
  `date_fin` DATE NOT NULL,
  `multiplicateur` DECIMAL(4,2) NOT NULL DEFAULT 1.00,
  `actif` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `hotel_tarifs_saison_dates_idx` (`date_debut`, `date_fin`),
  KEY `hotel_tarifs_saison_actif_idx` (`actif`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Multiplicateurs saisonniers (haute/basse saison)';

-- ----------------------------------------------------------------------------
-- 8. Seed : 20 chambres (10 simples + 10 doubles)
-- ----------------------------------------------------------------------------
-- Prix par défaut en FC (Francs Congolais). Modifiables depuis le manager.
INSERT INTO `hotel_chambres` (`numero`, `type`, `prix_base`, `etat`) VALUES
  -- 10 chambres simples (étage 1, numéros 101-110)
  ('101', 'simple', 30000.00, 'disponible'),
  ('102', 'simple', 30000.00, 'disponible'),
  ('103', 'simple', 30000.00, 'disponible'),
  ('104', 'simple', 30000.00, 'disponible'),
  ('105', 'simple', 30000.00, 'disponible'),
  ('106', 'simple', 30000.00, 'disponible'),
  ('107', 'simple', 30000.00, 'disponible'),
  ('108', 'simple', 30000.00, 'disponible'),
  ('109', 'simple', 30000.00, 'disponible'),
  ('110', 'simple', 30000.00, 'disponible'),
  -- 10 chambres doubles (étage 2, numéros 201-210)
  ('201', 'double', 50000.00, 'disponible'),
  ('202', 'double', 50000.00, 'disponible'),
  ('203', 'double', 50000.00, 'disponible'),
  ('204', 'double', 50000.00, 'disponible'),
  ('205', 'double', 50000.00, 'disponible'),
  ('206', 'double', 50000.00, 'disponible'),
  ('207', 'double', 50000.00, 'disponible'),
  ('208', 'double', 50000.00, 'disponible'),
  ('209', 'double', 50000.00, 'disponible'),
  ('210', 'double', 50000.00, 'disponible');

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================================
-- FIN DE LA MIGRATION
-- ============================================================================
