-- MySQL dump 10.13  Distrib 8.0.33, for Win64 (x86_64)
--
-- Host: 127.0.0.1    Database: pos_system
-- ------------------------------------------------------
-- Server version	9.1.0

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `categories`
--

DROP TABLE IF EXISTS `categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `categories` (
  `id` int NOT NULL AUTO_INCREMENT,
  `category` varchar(120) NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `categories`
--

/*!40000 ALTER TABLE `categories` DISABLE KEYS */;
INSERT INTO `categories` VALUES (1,'Comestible','2026-04-23 13:47:06','2026-04-23 13:47:06'),(3,'Service','2026-04-23 13:47:06','2026-04-23 13:47:06'),(4,'mortel','2026-04-24 14:21:31','2026-04-24 14:21:31'),(5,'mangeable','2026-04-24 14:22:22','2026-04-24 14:22:22');
/*!40000 ALTER TABLE `categories` ENABLE KEYS */;

--
-- Table structure for table `clients`
--

DROP TABLE IF EXISTS `clients`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `clients` (
  `id` int NOT NULL AUTO_INCREMENT,
  `numero` varchar(30) NOT NULL,
  `nom_client` varchar(50) NOT NULL,
  `code_client` varchar(20) NOT NULL,
  `type_client_id` int NOT NULL,
  `nif` text,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code_client` (`code_client`),
  KEY `fk_type_client` (`type_client_id`),
  CONSTRAINT `fk_type_client` FOREIGN KEY (`type_client_id`) REFERENCES `type_client` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `clients`
--

/*!40000 ALTER TABLE `clients` DISABLE KEYS */;
INSERT INTO `clients` VALUES (1,'0816069107','Mufasa','CLI-001',1,NULL),(2,'0816810070','test','cli-2',2,NULL),(3,'0895511485','cesar','CLI-003',1,'12345688'),(4,'0895510485','kkk','CLI-004',1,'');
/*!40000 ALTER TABLE `clients` ENABLE KEYS */;

--
-- Table structure for table `details_vente`
--

DROP TABLE IF EXISTS `details_vente`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `details_vente` (
  `id` int NOT NULL AUTO_INCREMENT,
  `vente_id` int NOT NULL,
  `produit_id` int NOT NULL,
  `quantite` int NOT NULL,
  `prix` decimal(10,2) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `vente_id` (`vente_id`),
  KEY `produit_id` (`produit_id`),
  CONSTRAINT `details_vente_ibfk_1` FOREIGN KEY (`vente_id`) REFERENCES `ventes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `details_vente_ibfk_2` FOREIGN KEY (`produit_id`) REFERENCES `produits` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=128 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `details_vente`
--

/*!40000 ALTER TABLE `details_vente` DISABLE KEYS */;
INSERT INTO `details_vente` VALUES (2,2,3,1,8.50),(7,4,3,1,8.50),(8,5,3,1,8.50),(17,11,3,3,8.50),(20,13,3,1,8.50),(33,22,3,1,8.50),(36,23,3,2,8.50),(43,26,3,2,8.50),(45,27,3,2,8.50),(47,28,3,3,8.50),(48,28,6,2,50.00),(52,31,3,3,8.50),(53,32,3,1,8.50),(54,33,3,1,8.50),(55,34,3,1,8.50),(57,36,3,3,8.50),(59,37,3,2,8.50),(62,39,3,2,8.50),(65,40,3,2,8.50),(68,42,3,1,8.50),(72,44,6,1,50.00),(73,44,3,1,8.50),(74,45,6,1,50.00),(75,46,3,2,8.50),(76,47,3,3,8.50),(77,48,3,1,8.50),(78,49,3,2,8.50),(79,50,3,2,8.50),(80,51,3,2,8.50),(81,52,3,2,8.50),(82,53,3,4,8.50),(83,54,3,3,8.50),(84,55,3,1,8.50),(85,56,3,1,8.50),(86,57,3,2,8.50),(87,58,3,1,8.50),(88,59,6,2,50.00),(91,61,3,1,8.50),(92,61,6,1,50.00),(94,62,3,3,8.50),(95,63,3,1,8.50),(97,63,6,1,50.00),(99,64,6,1,50.00),(100,64,3,1,8.50),(102,65,3,1,8.50),(103,65,6,1,50.00),(105,66,3,1,8.50),(113,71,3,2,8.50),(114,72,11,1,800.00),(115,72,6,1,50.00),(116,73,6,2,50.00),(117,73,11,1,800.00),(118,74,3,2,8.50),(119,75,3,1,8.50),(120,76,3,2,8.50),(121,77,3,2,8.50),(122,77,19,1,10000.00),(123,77,17,1,900.00),(124,78,3,2,8.50),(125,78,19,2,10000.00),(126,79,3,2,8.50),(127,79,19,3,10000.00);
/*!40000 ALTER TABLE `details_vente` ENABLE KEYS */;

--
-- Table structure for table `produits`
--

DROP TABLE IF EXISTS `produits`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `produits` (
  `id` int NOT NULL AUTO_INCREMENT,
  `code_barres` varchar(50) NOT NULL,
  `nom` varchar(100) NOT NULL,
  `category_id` int NOT NULL,
  `prix` decimal(10,2) NOT NULL,
  `stock` int NOT NULL DEFAULT '0',
  `stock_minimum` int NOT NULL DEFAULT '10',
  `image` varchar(255) DEFAULT NULL,
  `taxe_id` int DEFAULT '1',
  PRIMARY KEY (`id`),
  UNIQUE KEY `code_barres` (`code_barres`),
  KEY `category_id` (`category_id`),
  CONSTRAINT `produits_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=20 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `produits`
--

/*!40000 ALTER TABLE `produits` DISABLE KEYS */;
INSERT INTO `produits` VALUES (3,'6111245005','Lait Frais 1L',1,8.50,9,15,'https://images.unsplash.com/photo-1563636619-e9143da7973b?w=200',2),(6,'379961440160','the good things',4,50.00,89,5,'assets/img/products/the_good_things_1777049124.jpg',1),(8,'908680352130','vitalo',1,5000.00,15,1,'',1),(11,'910147686092','thé',4,800.00,48,2,'',1),(17,'912582452121','test2',4,900.00,29,1,'',1),(19,'965589898103','randy',4,10000.00,194,1,'',1);
/*!40000 ALTER TABLE `produits` ENABLE KEYS */;

--
-- Table structure for table `settings`
--

DROP TABLE IF EXISTS `settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `settings` (
  `id` int NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(100) NOT NULL,
  `value` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting_key` (`setting_key`)
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `settings`
--

/*!40000 ALTER TABLE `settings` DISABLE KEYS */;
INSERT INTO `settings` VALUES (1,'store_name','SuperMarche Express','2026-04-25 11:14:06','2026-04-25 11:14:06'),(2,'store_address','Ngaliema','2026-04-25 11:14:06','2026-04-25 11:19:01'),(3,'store_phone','+243 81 00 00 000','2026-04-25 11:14:06','2026-04-25 11:24:22'),(4,'store_ice','001234567890123','2026-04-25 11:14:06','2026-04-25 11:14:06'),(5,'tax_rate','16','2026-04-25 11:14:06','2026-04-25 11:14:06'),(6,'store_rccm','RCCM 2024','2026-05-01 15:23:50','2026-05-01 15:36:34'),(7,'store_isf','A900001T','2026-05-01 15:25:21','2026-05-01 15:36:34');
/*!40000 ALTER TABLE `settings` ENABLE KEYS */;

--
-- Table structure for table `taxes`
--

DROP TABLE IF EXISTS `taxes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `taxes` (
  `id` int NOT NULL AUTO_INCREMENT,
  `groupe_taxe` varchar(50) NOT NULL,
  `etiquette` varchar(100) NOT NULL,
  `description` text,
  `taux` decimal(5,2) NOT NULL DEFAULT '0.00',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `taxes`
--

/*!40000 ALTER TABLE `taxes` DISABLE KEYS */;
INSERT INTO `taxes` VALUES (1,'Groupe A','A','Exonéré et hors champ',0.00),(2,'Groupe B','B','Taxable',16.00),(3,'Groupe C','C','Taxable',5.00),(4,'Groupe D','D','Régimes dérogatoires TVA',0.00),(5,'Groupe E','E','Exportation et opération assimilées',0.00),(6,'Groupe F','F','TVA marché public à financement exterieur mais payé par crédit impot',16.00),(7,'Groupe G','G','TVA marché public à financement exterieur mais payé par crédit impot',5.00),(8,'Groupe H','H','consignation/déconsignation emballage',0.00),(9,'Groupe I','I','Garantie et caution',0.00),(10,'Groupe J','J','Débours',0.00),(11,'Groupe K','K','Opérations réalisées par les non-assujettis',0.00),(12,'Groupe L','L','Prélèvements sur les ventes',0.00),(13,'Groupe M','M','Ventes réglemntées TVA spécifique NB:seul le montant hors taxes est facturé',0.00),(14,'Groupe N','N','TVA spécifique NB:seul le montant de la TVA spéfique est facturé',0.00),(15,'Groupe O','O','Taxable',1.00),(16,'Groupe P','P','TVA marché public à financement extérieur NB: mais payée par crédit impot',1.00);
/*!40000 ALTER TABLE `taxes` ENABLE KEYS */;

--
-- Table structure for table `type_client`
--

DROP TABLE IF EXISTS `type_client`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `type_client` (
  `id` int NOT NULL AUTO_INCREMENT,
  `code` varchar(50) NOT NULL,
  `description` text,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `type_client`
--

/*!40000 ALTER TABLE `type_client` DISABLE KEYS */;
INSERT INTO `type_client` VALUES (1,'PP','personne physique'),(2,'PM','personne morale'),(3,'PC','personne physique commerçante'),(4,'PL','profession libérale'),(5,'AO','Ambassades et organisations internationales');
/*!40000 ALTER TABLE `type_client` ENABLE KEYS */;

--
-- Table structure for table `utilisateurs`
--

DROP TABLE IF EXISTS `utilisateurs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `utilisateurs` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nom_utilisateur` varchar(50) NOT NULL,
  `mot_de_passe` varchar(255) NOT NULL,
  `nom_complet` varchar(100) NOT NULL,
  `role` enum('admin','vendeur') NOT NULL DEFAULT 'vendeur',
  `actif` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`),
  UNIQUE KEY `nom_utilisateur` (`nom_utilisateur`)
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `utilisateurs`
--

/*!40000 ALTER TABLE `utilisateurs` DISABLE KEYS */;
INSERT INTO `utilisateurs` VALUES (1,'Musafa','$2y$10$SsetsYT1nTdu6La2YqqtRuWm1Ao6ibYvlhNimGbThci1ZHC475GsW','the king mufasa','admin',1),(3,'vendeur2','$2y$10$C.Wn4hGDdFfYcPSiAQM9q.FqLTbqgFC4OvH02VYqdikF/y9gckVPG','Amina Kaba','vendeur',1),(4,'vendeur3','$2y$10$C.Wn4hGDdFfYcPSiAQM9q.FqLTbqgFC4OvH02VYqdikF/y9gckVPG','Jean-Pierre Moke','vendeur',1),(6,'vendeur5','$2y$10$C.Wn4hGDdFfYcPSiAQM9q.FqLTbqgFC4OvH02VYqdikF/y9gckVPG','David Nsimba','vendeur',1),(7,'vendeur6','$2y$10$C.Wn4hGDdFfYcPSiAQM9q.FqLTbqgFC4OvH02VYqdikF/y9gckVPG','Sarah Mbala','vendeur',1),(8,'vendeur7','$2y$10$C.Wn4hGDdFfYcPSiAQM9q.FqLTbqgFC4OvH02VYqdikF/y9gckVPG','Pauline Tshibanda','vendeur',1),(11,'cesar','$2y$10$/KIaginOlMFhOlW1xwRe1Oqs8YVjfJoldNLJJwzLd091oozDn6p92','cesar','admin',1),(13,'hydra07777','$2y$10$jUVffmur07n0ik3E9K5HguZumsT0DG6HRW9b.rjA2Hw6QUJw0YXnS','César Paysayo','vendeur',1);
/*!40000 ALTER TABLE `utilisateurs` ENABLE KEYS */;

--
-- Table structure for table `ventes`
--

DROP TABLE IF EXISTS `ventes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ventes` (
  `id` int NOT NULL AUTO_INCREMENT,
  `numero_facture` varchar(50) NOT NULL,
  `sous_total_ht` decimal(10,2) NOT NULL,
  `tva` decimal(10,2) NOT NULL,
  `total` decimal(10,2) NOT NULL,
  `vendeur_id` int NOT NULL,
  `date` datetime DEFAULT CURRENT_TIMESTAMP,
  `dateDGI` varchar(100) DEFAULT NULL,
  `qrCode` text,
  `codeDEFDGI` varchar(100) DEFAULT NULL,
  `counters` varchar(100) DEFAULT NULL,
  `nim` varchar(100) DEFAULT NULL,
  `client_id` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `numero_facture` (`numero_facture`),
  KEY `vendeur_id` (`vendeur_id`),
  KEY `fk_ventes_client` (`client_id`),
  CONSTRAINT `fk_ventes_client` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `ventes_ibfk_1` FOREIGN KEY (`vendeur_id`) REFERENCES `utilisateurs` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=80 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `ventes`
--

/*!40000 ALTER TABLE `ventes` DISABLE KEYS */;
INSERT INTO `ventes` VALUES (1,'FAC-001000',15.00,3.00,18.00,11,'2026-04-23 16:25:18',NULL,NULL,NULL,NULL,NULL,NULL),(2,'FAC-001001',37.08,7.42,44.50,11,'2026-04-23 16:29:40',NULL,NULL,NULL,NULL,NULL,NULL),(3,'FAC-001002',19.17,3.83,23.00,11,'2026-04-23 16:45:04',NULL,NULL,NULL,NULL,NULL,NULL),(4,'FAC-001003',16.25,3.25,19.50,11,'2026-04-23 16:51:52',NULL,NULL,NULL,NULL,NULL,NULL),(5,'FAC-001004',22.08,4.42,26.50,11,'2026-04-23 16:56:09',NULL,NULL,NULL,NULL,NULL,NULL),(6,'FAC-001005',50.00,10.00,60.00,11,'2026-04-23 17:06:07',NULL,NULL,NULL,NULL,NULL,NULL),(7,'FAC-001006',20.00,4.00,24.00,11,'2026-04-23 17:09:44',NULL,NULL,NULL,NULL,NULL,NULL),(8,'FAC-001007',20.00,4.00,24.00,11,'2026-04-23 17:23:04',NULL,NULL,NULL,NULL,NULL,NULL),(9,'FAC-001008',20.00,4.00,24.00,11,'2026-04-23 17:34:55',NULL,NULL,NULL,NULL,NULL,NULL),(10,'FAC-001009',5.00,1.00,6.00,11,'2026-04-23 17:47:18',NULL,NULL,NULL,NULL,NULL,NULL),(11,'FAC-001010',21.25,4.25,25.50,11,'2026-04-23 17:47:46',NULL,NULL,NULL,NULL,NULL,NULL),(12,'FAC-001011',43.33,8.67,52.00,11,'2026-04-23 18:06:07',NULL,NULL,NULL,NULL,NULL,NULL),(13,'FAC-001012',16.25,3.25,19.50,11,'2026-04-23 18:14:32',NULL,NULL,NULL,NULL,NULL,NULL),(14,'FAC-001013',45.00,9.00,54.00,11,'2026-04-23 18:18:19',NULL,NULL,NULL,NULL,NULL,NULL),(15,'FAC-001014',24.17,4.83,29.00,11,'2026-04-23 18:18:59',NULL,NULL,NULL,NULL,NULL,NULL),(16,'FAC-001015',18.33,3.67,22.00,11,'2026-04-23 18:19:14',NULL,NULL,NULL,NULL,NULL,NULL),(17,'FAC-001016',18.33,3.67,22.00,11,'2026-04-23 18:25:12',NULL,NULL,NULL,NULL,NULL,NULL),(18,'FAC-001017',74.17,14.83,89.00,11,'2026-04-23 18:26:49',NULL,NULL,NULL,NULL,NULL,NULL),(19,'FAC-001018',9.17,1.83,11.00,11,'2026-04-23 18:27:41',NULL,NULL,NULL,NULL,NULL,NULL),(20,'FAC-001019',9.17,1.83,11.00,11,'2026-04-23 18:28:25',NULL,NULL,NULL,NULL,NULL,NULL),(21,'FAC-001020',18.33,3.67,22.00,11,'2026-04-23 18:30:55',NULL,NULL,NULL,NULL,NULL,NULL),(22,'FAC-001021',74.58,14.92,89.50,11,'2026-04-23 18:34:45',NULL,NULL,NULL,NULL,NULL,NULL),(23,'FAC-001022',68.33,13.67,82.00,11,'2026-04-23 18:43:35',NULL,NULL,NULL,NULL,NULL,NULL),(24,'FAC-001023',70.83,14.17,85.00,11,'2026-04-23 18:44:36',NULL,NULL,NULL,NULL,NULL,NULL),(25,'FAC-001024',50.00,10.00,60.00,11,'2026-04-24 15:54:30',NULL,NULL,NULL,NULL,NULL,NULL),(26,'FAC-001025',82.50,16.50,99.00,11,'2026-04-24 16:01:30',NULL,NULL,NULL,NULL,NULL,NULL),(27,'FAC-001026',50.83,10.17,61.00,11,'2026-04-24 16:02:16',NULL,NULL,NULL,NULL,NULL,NULL),(28,'FAC-001027',122.92,24.58,147.50,11,'2026-04-24 16:34:43',NULL,NULL,NULL,NULL,NULL,NULL),(29,'FAC-001028',59.17,11.83,71.00,11,'2026-04-24 16:57:51',NULL,NULL,NULL,NULL,NULL,NULL),(30,'FAC-001029',18.33,3.67,22.00,11,'2026-04-24 16:59:05',NULL,NULL,NULL,NULL,NULL,NULL),(31,'FAC-001030',21.25,4.25,25.50,11,'2026-04-24 17:17:56',NULL,NULL,NULL,NULL,NULL,NULL),(32,'FAC-001031',7.08,1.42,8.50,11,'2026-04-24 17:32:12',NULL,NULL,NULL,NULL,NULL,NULL),(33,'FAC-001032',7.08,1.42,8.50,11,'2026-04-24 17:32:49',NULL,NULL,NULL,NULL,NULL,NULL),(34,'FAC-001033',7.08,1.42,8.50,11,'2026-04-24 17:37:25',NULL,NULL,NULL,NULL,NULL,NULL),(35,'FAC-001034',27.50,5.50,33.00,11,'2026-04-24 17:38:07',NULL,NULL,NULL,NULL,NULL,NULL),(36,'FAC-001035',30.42,6.08,36.50,11,'2026-04-24 17:42:26',NULL,NULL,NULL,NULL,NULL,NULL),(37,'FAC-001036',14.17,2.83,17.00,11,'2026-04-24 17:43:31',NULL,NULL,NULL,NULL,NULL,NULL),(38,'FAC-001037',27.50,5.50,33.00,11,'2026-04-24 17:46:05',NULL,NULL,NULL,NULL,NULL,NULL),(39,'FAC-001038',73.33,14.67,88.00,11,'2026-04-24 17:46:48',NULL,NULL,NULL,NULL,NULL,NULL),(40,'FAC-001039',64.17,12.83,77.00,11,'2026-04-24 17:50:24',NULL,NULL,NULL,NULL,NULL,NULL),(41,'FAC-001040',18.33,3.67,22.00,11,'2026-04-24 17:53:25',NULL,NULL,NULL,NULL,NULL,NULL),(42,'FAC-001041',84.58,16.92,101.50,11,'2026-04-24 17:54:11',NULL,NULL,NULL,NULL,NULL,NULL),(43,'FAC-001042',27.50,5.50,33.00,11,'2026-04-24 17:54:34',NULL,NULL,NULL,NULL,NULL,NULL),(44,'FAC-001043',67.08,13.42,80.50,11,'2026-04-24 17:56:03',NULL,NULL,NULL,NULL,NULL,NULL),(45,'FAC-001044',41.67,8.33,50.00,11,'2026-04-24 17:59:41',NULL,NULL,NULL,NULL,NULL,NULL),(46,'FAC-001045',14.17,2.83,17.00,11,'2026-04-24 17:59:47',NULL,NULL,NULL,NULL,NULL,NULL),(47,'FAC-001046',21.25,4.25,25.50,11,'2026-04-24 18:01:31',NULL,NULL,NULL,NULL,NULL,NULL),(48,'FAC-001047',7.08,1.42,8.50,11,'2026-04-24 18:02:33',NULL,NULL,NULL,NULL,NULL,NULL),(49,'FAC-001048',14.17,2.83,17.00,11,'2026-04-24 18:03:06',NULL,NULL,NULL,NULL,NULL,NULL),(50,'FAC-001049',14.17,2.83,17.00,11,'2026-04-24 18:04:28',NULL,NULL,NULL,NULL,NULL,NULL),(51,'FAC-001050',14.17,2.83,17.00,11,'2026-04-24 18:06:11',NULL,NULL,NULL,NULL,NULL,NULL),(52,'FAC-001051',14.17,2.83,17.00,11,'2026-04-24 18:11:41',NULL,NULL,NULL,NULL,NULL,NULL),(53,'FAC-001052',28.33,5.67,34.00,11,'2026-04-24 18:18:20',NULL,NULL,NULL,NULL,NULL,NULL),(54,'FAC-001053',21.25,4.25,25.50,11,'2026-04-24 18:21:58',NULL,NULL,NULL,NULL,NULL,NULL),(55,'FAC-001054',7.08,1.42,8.50,11,'2026-04-24 18:23:24',NULL,NULL,NULL,NULL,NULL,NULL),(56,'FAC-001055',7.08,1.42,8.50,11,'2026-04-24 18:24:29',NULL,NULL,NULL,NULL,NULL,NULL),(57,'FAC-001056',14.17,2.83,17.00,11,'2026-04-24 18:24:36',NULL,NULL,NULL,NULL,NULL,NULL),(58,'FAC-001057',7.08,1.42,8.50,11,'2026-04-24 18:31:13',NULL,NULL,NULL,NULL,NULL,NULL),(59,'FAC-001058',83.33,16.67,100.00,11,'2026-04-24 18:34:17',NULL,NULL,NULL,NULL,NULL,NULL),(60,'FAC-001059',50.00,10.00,60.00,11,'2026-04-24 18:36:25',NULL,NULL,NULL,NULL,NULL,NULL),(61,'FAC-001060',107.92,21.58,129.50,11,'2026-04-24 18:39:38',NULL,NULL,NULL,NULL,NULL,NULL),(62,'FAC-001061',21.25,4.25,25.50,11,'2026-04-24 18:41:52',NULL,NULL,NULL,NULL,NULL,NULL),(63,'FAC-001062',98.75,19.75,118.50,11,'2026-04-24 18:49:08',NULL,NULL,NULL,NULL,NULL,NULL),(64,'FAC-001063',98.75,19.75,118.50,11,'2026-04-24 18:53:34',NULL,NULL,NULL,NULL,NULL,NULL),(65,'FAC-001064',98.75,19.75,118.50,11,'2026-04-24 18:57:31',NULL,NULL,NULL,NULL,NULL,NULL),(66,'FAC-001065',200016.25,40003.25,240019.50,11,'2026-04-24 19:00:52',NULL,NULL,NULL,NULL,NULL,NULL),(67,'FAC-001066',133333.33,26666.67,160000.00,11,'2026-04-24 19:03:59',NULL,NULL,NULL,NULL,NULL,NULL),(68,'FAC-001067',133333.33,26666.67,160000.00,11,'2026-04-24 19:13:24',NULL,NULL,NULL,NULL,NULL,NULL),(69,'FAC-001068',200000.00,32000.00,232000.00,11,'2026-04-24 19:21:30',NULL,NULL,NULL,NULL,NULL,NULL),(70,'FAC-001069',200000.00,32000.00,200000.00,11,'2026-04-24 19:29:03',NULL,NULL,NULL,NULL,NULL,NULL),(71,'FAC-001070',17.00,2.72,17.00,11,'2026-04-25 12:36:41','25/04/2026 11:36:38','RDCF01;CD01004983-1;TESTFACTURESHVM5HVJTMIPY;A1720894F;20260425113638','TEST-FACT-URES-HVM5-HVJT-MIPY','274/276 FV','CD01004983-1',NULL),(72,'FAC-001071',850.00,136.00,850.00,11,'2026-05-01 11:52:24','2026-05-01','[\n    {\n        \"name\": \"th\\u00e9\",\n        \"quantity\": 1,\n        \"price\": 800\n    },\n    {\n        \"name\": \"the good things\",\n        \"quantity\": 1,\n        \"price\": 50\n    }\n]','RDC KINSHASA','661',NULL,NULL),(73,'FAC-001072',900.00,144.00,900.00,11,'2026-05-01 11:54:17','2026-05-01','[\n    {\n        \"name\": \"the good things\",\n        \"quantity\": 2,\n        \"price\": 50\n    },\n    {\n        \"name\": \"th\\u00e9\",\n        \"quantity\": 1,\n        \"price\": 800\n    }\n]','RDC KINSHASA','159',NULL,NULL),(74,'FAC-001073',17.00,2.72,19.72,11,'2026-05-02 00:11:39','2026-05-01','[\n    {\n        \"name\": \"Lait Frais 1L\",\n        \"quantity\": 2,\n        \"price\": 8.5\n    }\n]','RDC KINSHASA','894',NULL,3),(75,'FAC-001074',8.50,1.36,9.86,11,'2026-05-02 00:14:38','2026-05-01','[\n    {\n        \"name\": \"Lait Frais 1L\",\n        \"quantity\": 1,\n        \"price\": 8.5\n    }\n]','RDC KINSHASA','609',NULL,3),(76,'FAC-001075',17.00,2.72,19.72,11,'2026-05-02 00:18:01','2026-05-01','[\n    {\n        \"name\": \"Lait Frais 1L\",\n        \"quantity\": 2,\n        \"price\": 8.5\n    }\n]','RDC KINSHASA','655',NULL,3),(77,'FAC-001076',10917.00,0.00,10917.00,11,'2026-05-02 08:20:06','2026-05-02','[\n    {\n        \"name\": \"Lait Frais 1L\",\n        \"quantity\": 2,\n        \"price\": 8.5\n    },\n    {\n        \"name\": \"randy\",\n        \"quantity\": 1,\n        \"price\": 10000\n    },\n    {\n        \"name\": \"test2\",\n        \"quantity\": 1,\n        \"price\": 900\n    }\n]','RDC KINSHASA','506',NULL,3),(78,'FAC-001077',20017.00,2.72,20019.72,11,'2026-05-02 08:21:54','2026-05-02','[\n    {\n        \"name\": \"Lait Frais 1L\",\n        \"quantity\": 2,\n        \"price\": 8.5\n    },\n    {\n        \"name\": \"randy\",\n        \"quantity\": 2,\n        \"price\": 10000\n    }\n]','RDC KINSHASA','931',NULL,3),(79,'FAC-001078',30017.00,2.72,30019.72,11,'2026-05-02 08:25:27','2026-05-02','[\n    {\n        \"name\": \"Lait Frais 1L\",\n        \"quantity\": 2,\n        \"price\": 8.5\n    },\n    {\n        \"name\": \"randy\",\n        \"quantity\": 3,\n        \"price\": 10000\n    }\n]','RDC KINSHASA','516',NULL,3);
/*!40000 ALTER TABLE `ventes` ENABLE KEYS */;

--
-- Dumping routines for database 'pos_system'
--
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-05-02 15:13:14
