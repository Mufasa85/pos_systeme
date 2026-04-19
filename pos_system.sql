-- ==============================
-- RESET (optionnel si tu recommences)
-- ==============================
DROP TABLE IF EXISTS details_vente;
DROP TABLE IF EXISTS ventes;
DROP TABLE IF EXISTS produits;
DROP TABLE IF EXISTS categories;
DROP TABLE IF EXISTS utilisateurs;

-- ==============================
-- TABLE : utilisateurs
-- ==============================
CREATE TABLE IF NOT EXISTS `utilisateurs` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `nom_utilisateur` VARCHAR(50) NOT NULL UNIQUE,
  `mot_de_passe` VARCHAR(255) NOT NULL,
  `nom_complet` VARCHAR(100) NOT NULL,
  `role` ENUM('admin','vendeur') NOT NULL DEFAULT 'vendeur',
  `actif` TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ==============================
-- TABLE : categories
-- ==============================
CREATE TABLE IF NOT EXISTS `categories` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `category` VARCHAR(120) NOT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ==============================
-- INSERT categories
-- ==============================
INSERT INTO `categories` (`category`) VALUES
('Comestible'),
('Non Comestible'),
('Service');

-- ==============================
-- INSERT utilisateurs
-- ==============================
INSERT INTO `utilisateurs`
(`nom_utilisateur`, `mot_de_passe`, `nom_complet`, `role`, `actif`)
VALUES
('Musafa', '$2y$10$C.Wn4hGDdFfYcPSiAQM9q.FqLTbqgFC4OvH02VYqdikF/y9gckVPG', 'Administrateur', 'admin', 1),
('vendeur1', '$2y$10$ryKoYm12Fcr7aOUPFowW.u/doihYwl9u8DRPAwyx6wX2Laqu0m64i', 'Mohammed Alami', 'vendeur', 1);

-- ==============================
-- TABLE : produits
-- ==============================
CREATE TABLE IF NOT EXISTS `produits` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `code_barres` VARCHAR(50) NOT NULL UNIQUE,
  `nom` VARCHAR(100) NOT NULL,
  `category_id` INT NOT NULL,
  `prix` DECIMAL(10,2) NOT NULL,
  `stock` INT NOT NULL DEFAULT 0,
  `stock_minimum` INT NOT NULL DEFAULT 10,
  `image` VARCHAR(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`category_id`) REFERENCES `categories`(`id`)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ==============================
-- INSERT produits
-- ==============================
INSERT INTO `produits`
(`code_barres`, `nom`, `category_id`, `prix`, `stock`, `stock_minimum`, `image`)
VALUES
('6111245001', 'Coca-Cola 1.5L', 1, 12.00, 50, 10, 'https://images.unsplash.com/photo-1629203851122-3726ecdf080e?w=200'),
('6111245002', 'Fanta Orange 1.5L', 1, 11.00, 45, 10, 'https://images.unsplash.com/photo-1624517452488-04869289c4ca?w=200'),
('6111245005', 'Lait Frais 1L', 1, 8.50, 60, 15, 'https://images.unsplash.com/photo-1563636619-e9143da7973b?w=200'),
('6111245013', 'Savon de Toilette', 2, 18.00, 30, 10, 'https://images.unsplash.com/photo-1600857544200-b2f666a9a2ec?w=200'),
('6111245017', 'Eau de Javel 1L', 2, 12.00, 5, 10, 'https://images.unsplash.com/photo-1585421514284-efb74c2b69ba?w=200');

-- ==============================
-- TABLE : ventes
-- ==============================
CREATE TABLE IF NOT EXISTS `ventes` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `numero_facture` VARCHAR(50) NOT NULL UNIQUE,
  `sous_total_ht` DECIMAL(10,2) NOT NULL,
  `tva` DECIMAL(10,2) NOT NULL,
  `total` DECIMAL(10,2) NOT NULL,
  `vendeur_id` INT NOT NULL,
  `date` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`vendeur_id`) REFERENCES `utilisateurs`(`id`)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ==============================
-- TABLE : details_vente
-- ==============================
CREATE TABLE IF NOT EXISTS `details_vente` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `vente_id` INT NOT NULL,
  `produit_id` INT NOT NULL,
  `quantite` INT NOT NULL,
  `prix` DECIMAL(10,2) NOT NULL,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`vente_id`) REFERENCES `ventes`(`id`)
    ON DELETE CASCADE,
  FOREIGN KEY (`produit_id`) REFERENCES `produits`(`id`)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;