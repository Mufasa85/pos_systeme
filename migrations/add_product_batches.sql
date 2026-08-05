-- Migration : gestion des lots et dates d'expiration par produit
-- La table produits.stock reste un cache mis a jour par les operations sur les lots.

ALTER TABLE `produits`
  ADD COLUMN IF NOT EXISTS `date_expiration` date DEFAULT NULL;

CREATE TABLE IF NOT EXISTS `product_batches` (
  `id` int NOT NULL AUTO_INCREMENT,
  `product_id` int NOT NULL,
  `batch_number` varchar(50) DEFAULT NULL,
  `stock` float NOT NULL DEFAULT '0',
  `date_expiration` date DEFAULT NULL,
  `date_reception` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `product_id` (`product_id`),
  KEY `date_expiration` (`date_expiration`),
  CONSTRAINT `fk_product_batches_product` FOREIGN KEY (`product_id`) REFERENCES `produits` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Migration des donnees existantes : creer un lot initial pour chaque produit en stock
INSERT INTO `product_batches` (`product_id`, `stock`, `date_expiration`, `date_reception`)
SELECT `id`, `stock`, NULL, CURDATE()
FROM `produits`
WHERE `stock` > 0;
