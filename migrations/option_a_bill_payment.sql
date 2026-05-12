-- ==============================================
-- MIGRATION: Option A - Bill Payment dans `ventes`
-- Date: 2026-05-11
-- ==============================================

-- ===================================
-- TABLE: service_providers
-- Fournisseurs (SNEL, REGIDESO)
-- ===================================
CREATE TABLE IF NOT EXISTS `service_providers` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `code` VARCHAR(20) NOT NULL UNIQUE COMMENT 'Code: SNEL, REGIDESO',
    `nom` VARCHAR(100) NOT NULL,
    `type_service` ENUM('electricity', 'water') NOT NULL COMMENT 'electricity ou water',
    `api_endpoint` VARCHAR(255) NULL COMMENT 'URL API',
    `api_key` VARCHAR(255) NULL COMMENT 'Clé API',
    `actif` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;

INSERT INTO
    `service_providers` (`code`, `nom`, `type_service`)
VALUES (
        'SNEL',
        'Société Nationale d\'Electricité',
        'electricity'
    ),
    (
        'REGIDESO',
        'Régie de Distribution d\'Eau',
        'water'
    );

-- ===================================
-- MODIFICATION: Table `ventes`
-- Ajouter champs pour factures
-- ===================================
ALTER TABLE `ventes`
ADD COLUMN `type_vente` ENUM('product', 'bill_payment') DEFAULT 'product' COMMENT 'Type de vente',
ADD COLUMN `provider_id` INT NULL COMMENT 'Fournisseur SNEL/REGIDESO',
ADD COLUMN `numero_compteur` VARCHAR(50) NULL COMMENT 'N° compteur facture',
ADD COLUMN `client_reference` VARCHAR(100) NULL COMMENT 'Réf client fournisseur',
ADD COLUMN `api_response` TEXT NULL COMMENT 'Réponse brute API JSON';

-- Ajouter FK si service_providers existe
-- ALTER TABLE `ventes` ADD FOREIGN KEY (`provider_id`) REFERENCES `service_providers`(`id`) ON DELETE SET NULL;

-- ===================================
-- INDEX pour performance
-- ===================================
CREATE INDEX idx_ventes_type ON `ventes` (`type_vente`);

CREATE INDEX idx_ventes_compteur ON `ventes` (`numero_compteur`);

CREATE INDEX idx_ventes_provider ON `ventes` (`provider_id`);

-- ===================================
-- Note: Pour les détails_vente
-- Les mois sélectionnés seront stockés avec:
-- - produit_id = NULL ou ID spécial (ex: -1 = "mois facture")
-- - quantite = 1 (unitaire)
-- - prix = montant du mois
--
-- Pour identifier le mois, utiliser le champ `comment` existant
-- ou créer une nouvelle colonne dans details_vente:
-- ===================================
-- ALTER TABLE `details_vente` ADD COLUMN `mois_annee` VARCHAR(20) NULL COMMENT 'Ex: "04/2026"';