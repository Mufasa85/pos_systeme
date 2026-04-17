-- Création de la base de données
DROP DATABASE IF EXISTS `pos_system`;
CREATE DATABASE IF NOT EXISTS `pos_system`
DEFAULT CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci;

USE `pos_system`;

-- Table : utilisateurs
CREATE TABLE IF NOT EXISTS `utilisateurs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nom_utilisateur` varchar(50) NOT NULL UNIQUE,
  `mot_de_passe` varchar(255) NOT NULL,
  `nom_complet` varchar(100) NOT NULL,
  `role` enum('admin','vendeur') NOT NULL DEFAULT 'vendeur',
  `actif` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insertion de l'utilisateur par défaut (admin / admin123)
-- Le mot de passe ici est hashé avec password_hash() pour 'admin123'
INSERT INTO `utilisateurs` (`nom_utilisateur`, `mot_de_passe`, `nom_complet`, `role`, `actif`) VALUES
('admin', '$2y$10$tZ9c2QZ5Ie6o9.UqX9tPpeR6Y4Qk/5O0vU9Tj6H1V9H5Xk0qYwFkO', 'Administrateur', 'admin', 1),
('vendeur1', '$2y$10$G0NZZ6vE/8x9Tq6M8R2Iq.6Z5/O1k5N6J1H1v0V5P1C9D6B8X2W5q', 'Mohammed Alami', 'vendeur', 1);

-- Table : produits
CREATE TABLE IF NOT EXISTS `produits` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `code_barres` varchar(50) NOT NULL UNIQUE,
  `nom` varchar(100) NOT NULL,
  `categorie` varchar(50) NOT NULL,
  `prix` decimal(10,2) NOT NULL,
  `stock` int(11) NOT NULL DEFAULT 0,
  `stock_minimum` int(11) NOT NULL DEFAULT 10,
  `image` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insertion de produits initiaux
INSERT INTO `produits` (`code_barres`, `nom`, `categorie`, `prix`, `stock`, `stock_minimum`, `image`) VALUES
('6111245001', 'Coca-Cola 1.5L', 'Boissons', 12.00, 50, 10, 'https://images.unsplash.com/photo-1629203851122-3726ecdf080e?w=200&h=200&fit=crop'),
('6111245002', 'Fanta Orange 1.5L', 'Boissons', 11.00, 45, 10, 'https://images.unsplash.com/photo-1624517452488-04869289c4ca?w=200&h=200&fit=crop'),
('6111245005', 'Lait Frais 1L', 'Alimentation', 8.50, 60, 15, 'https://images.unsplash.com/photo-1563636619-e9143da7973b?w=200&h=200&fit=crop'),
('6111245013', 'Savon de Toilette', 'Hygiène', 18.00, 30, 10, 'https://images.unsplash.com/photo-1600857544200-b2f666a9a2ec?w=200&h=200&fit=crop'),
('6111245017', 'Eau de Javel 1L', 'Ménage', 12.00, 5, 10, 'https://images.unsplash.com/photo-1585421514284-efb74c2b69ba?w=200&h=200&fit=crop');

-- Table : ventes
CREATE TABLE IF NOT EXISTS `ventes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `numero_facture` varchar(50) NOT NULL UNIQUE,
  `sous_total_ht` decimal(10,2) NOT NULL,
  `tva` decimal(10,2) NOT NULL,
  `total` decimal(10,2) NOT NULL,
  `vendeur_id` int(11) NOT NULL,
  `date` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`vendeur_id`) REFERENCES `utilisateurs`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table : details_vente
CREATE TABLE IF NOT EXISTS `details_vente` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `vente_id` int(11) NOT NULL,
  `produit_id` int(11) NOT NULL,
  `quantite` int(11) NOT NULL,
  `prix` decimal(10,2) NOT NULL,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`vente_id`) REFERENCES `ventes`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`produit_id`) REFERENCES `produits`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE tokkens_csrf (
  id INT PRIMARY KEY AUTO_INCREMENT,
  tokken VARCHAR(65),
  created_at DATETIME,
  expired_at DATETIME
);

