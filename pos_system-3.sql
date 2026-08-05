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
-- Table structure for table `audit_logs`
--

DROP TABLE IF EXISTS `audit_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `audit_logs` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int DEFAULT NULL,
  `shop_id` int DEFAULT NULL,
  `action` varchar(50) NOT NULL,
  `entity` varchar(50) NOT NULL,
  `entity_id` int DEFAULT NULL,
  `details` json DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_audit_user` (`user_id`),
  KEY `idx_audit_shop` (`shop_id`),
  KEY `idx_audit_action` (`action`),
  KEY `idx_audit_date` (`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=204 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `audit_logs`
--

LOCK TABLES `audit_logs` WRITE;
/*!40000 ALTER TABLE `audit_logs` DISABLE KEYS */;
INSERT INTO `audit_logs` VALUES (1,11,1,'login','utilisateur',11,NULL,'::1','2026-07-17 02:09:51'),(2,11,1,'update','utilisateur',1,NULL,'::1','2026-07-17 02:10:41'),(3,11,1,'logout','utilisateur',11,NULL,'::1','2026-07-17 02:11:10'),(4,11,1,'login','utilisateur',11,NULL,'::1','2026-07-17 02:11:59'),(5,11,1,'update','utilisateur',1,NULL,'::1','2026-07-17 02:15:11'),(6,11,1,'update','utilisateur',1,NULL,'::1','2026-07-17 02:15:13'),(7,11,1,'update','utilisateur',3,NULL,'::1','2026-07-17 02:16:04'),(8,11,1,'logout','utilisateur',11,NULL,'::1','2026-07-17 02:16:10'),(9,3,1,'login','utilisateur',3,NULL,'::1','2026-07-17 02:16:48'),(10,3,1,'logout','utilisateur',3,NULL,'::1','2026-07-17 02:17:45'),(11,1,1,'login','utilisateur',1,NULL,'::1','2026-07-17 02:18:38'),(12,1,1,'logout','utilisateur',1,NULL,'::1','2026-07-17 02:18:58'),(13,11,1,'login','utilisateur',11,NULL,'::1','2026-07-17 02:29:04'),(14,11,1,'logout','utilisateur',11,NULL,'::1','2026-07-17 02:29:32'),(15,11,1,'login','utilisateur',11,NULL,'::1','2026-07-17 02:29:44'),(16,11,1,'login','utilisateur',11,NULL,'::1','2026-07-17 02:29:45'),(17,11,1,'update','settings',NULL,'{\"keys\": [\"store_name\", \"store_address\", \"store_email\", \"pdv\", \"store_phone\", \"store_ice\", \"store_rccm\", \"store_isf\", \"nid\", \"token\", \"port\", \"service_type\"]}','::1','2026-07-17 02:31:13'),(18,11,1,'update','settings',NULL,'{\"keys\": [\"store_name\", \"store_address\", \"store_email\", \"pdv\", \"store_phone\", \"store_ice\", \"store_rccm\", \"store_isf\", \"nid\", \"token\", \"port\", \"service_type\"]}','::1','2026-07-17 02:31:36'),(19,11,1,'logout','utilisateur',11,NULL,'::1','2026-07-17 12:35:26'),(20,11,1,'login','utilisateur',11,NULL,'::1','2026-07-17 12:44:29'),(21,11,1,'logout','utilisateur',11,NULL,'::1','2026-07-17 12:48:26'),(22,3,1,'login','utilisateur',3,NULL,'::1','2026-07-17 12:49:02'),(23,3,1,'logout','utilisateur',3,NULL,'::1','2026-07-17 12:49:40'),(24,11,1,'login','utilisateur',11,NULL,'::1','2026-07-17 12:52:04'),(25,3,1,'login','utilisateur',3,NULL,'::1','2026-07-17 12:55:05'),(26,11,1,'logout','utilisateur',11,NULL,'::1','2026-07-17 13:06:22'),(27,NULL,NULL,'password_reset','utilisateur',1,NULL,'::1','2026-07-17 13:08:33'),(28,1,1,'login','utilisateur',1,NULL,'::1','2026-07-17 13:09:19'),(29,1,1,'logout','utilisateur',1,NULL,'::1','2026-07-17 13:11:15'),(30,11,1,'login','utilisateur',11,NULL,'::1','2026-07-17 13:26:21'),(31,11,1,'logout','utilisateur',11,NULL,'::1','2026-07-17 13:46:17'),(32,3,1,'logout','utilisateur',3,NULL,'::1','2026-07-17 13:46:28'),(33,3,1,'login','utilisateur',3,NULL,'::1','2026-07-17 13:50:42'),(34,3,1,'create','shop',2,'{\"nom\": \"Mon magasin\", \"code\": \"SHOP01\"}','::1','2026-07-17 13:53:03'),(35,3,1,'logout','utilisateur',3,NULL,'::1','2026-07-17 14:05:39'),(36,3,1,'login','utilisateur',3,NULL,'::1','2026-07-18 23:58:49'),(37,3,1,'create','utilisateur',NULL,'{\"role\": \"admin\", \"username\": \"paul\"}','::1','2026-07-19 00:00:14'),(38,14,2,'login','utilisateur',14,NULL,'::1','2026-07-19 00:01:31'),(39,14,2,'create','utilisateur',NULL,'{\"role\": \"vendeur\", \"username\": \"musafiri\"}','::1','2026-07-19 00:08:01'),(40,14,2,'update','settings',NULL,'{\"keys\": [\"store_name\", \"store_address\", \"store_email\", \"pdv\", \"store_phone\", \"store_ice\", \"store_rccm\", \"store_isf\", \"nid\", \"token\", \"port\", \"service_type\"]}','::1','2026-07-19 00:09:30'),(41,3,1,'update','settings',NULL,'{\"keys\": [\"store_name\", \"store_address\", \"store_email\", \"pdv\", \"store_phone\", \"store_ice\", \"store_rccm\", \"store_isf\", \"nid\", \"token\", \"port\", \"service_type\"]}','::1','2026-07-19 00:10:13'),(42,3,1,'update','settings',NULL,'{\"keys\": [\"store_name\", \"store_address\", \"store_email\", \"pdv\", \"store_phone\", \"store_ice\", \"store_rccm\", \"store_isf\", \"nid\", \"token\", \"port\", \"service_type\"]}','::1','2026-07-19 00:16:45'),(43,11,1,'login','utilisateur',11,NULL,'::1','2026-07-19 00:27:02'),(44,3,1,'logout','utilisateur',3,NULL,'::1','2026-07-19 02:00:46'),(45,11,1,'logout','utilisateur',11,NULL,'::1','2026-07-19 02:01:05'),(46,14,2,'logout','utilisateur',14,NULL,'::1','2026-07-19 02:01:11'),(47,3,1,'login','utilisateur',3,NULL,'::1','2026-07-19 13:24:41'),(48,15,2,'login','utilisateur',15,NULL,'::1','2026-07-19 13:25:28'),(49,11,1,'login','utilisateur',11,NULL,'::1','2026-07-19 13:26:24'),(50,3,1,'update','settings',NULL,'{\"keys\": [\"store_name\", \"store_address\", \"store_email\", \"pdv\", \"store_phone\", \"store_ice\", \"store_rccm\", \"store_isf\", \"nid\", \"token\", \"port\", \"service_type\"]}','::1','2026-07-19 13:39:59'),(51,3,1,'update','settings',NULL,'{\"keys\": [\"store_name\", \"store_address\", \"store_email\", \"pdv\", \"store_phone\", \"store_ice\", \"store_rccm\", \"store_isf\", \"nid\", \"token\", \"port\", \"service_type\"]}','::1','2026-07-19 13:45:22'),(52,11,1,'update','settings',NULL,'{\"keys\": [\"store_name\", \"store_address\", \"store_email\", \"pdv\", \"store_phone\", \"store_ice\", \"store_rccm\", \"store_isf\", \"nid\", \"token\", \"port\", \"service_type\"]}','::1','2026-07-19 13:46:19'),(53,15,2,'logout','utilisateur',15,NULL,'::1','2026-07-19 13:52:39'),(54,14,2,'login','utilisateur',14,NULL,'::1','2026-07-19 13:53:10'),(55,3,1,'update','settings',NULL,'{\"keys\": [\"store_name\", \"store_address\", \"store_email\", \"pdv\", \"store_phone\", \"store_ice\", \"store_rccm\", \"store_isf\", \"nid\", \"token\", \"port\", \"service_type\"]}','::1','2026-07-19 13:58:18'),(56,3,1,'update','settings',NULL,'{\"keys\": [\"store_name\", \"store_address\", \"store_email\", \"pdv\", \"store_phone\", \"store_ice\", \"store_rccm\", \"store_isf\", \"nid\", \"token\", \"port\", \"service_type\"]}','::1','2026-07-19 13:59:17'),(57,3,1,'update','company_info',1,'{\"ice\": \"\", \"isf\": \"\", \"nid\": \"\", \"pdv\": \"\", \"name\": \"SuperMarché Express\", \"port\": \"\", \"rccm\": \"\", \"email\": \"\", \"phone\": \"\", \"token\": \"\", \"address\": \"bandal\"}','::1','2026-07-19 14:09:48'),(58,11,1,'update','settings',NULL,'{\"keys\": [\"store_name\", \"store_address\", \"store_email\", \"pdv\", \"store_phone\", \"store_ice\", \"store_rccm\", \"store_isf\", \"nid\", \"token\", \"port\", \"service_type\"]}','::1','2026-07-19 14:32:56'),(59,14,2,'update','settings',NULL,'{\"keys\": [\"store_name\", \"store_address\", \"store_email\", \"pdv\", \"store_phone\", \"store_ice\", \"store_rccm\", \"store_isf\", \"nid\", \"token\", \"port\", \"service_type\"]}','::1','2026-07-19 14:50:49'),(60,14,2,'update','settings',NULL,'{\"keys\": [\"store_name\", \"store_address\", \"store_email\", \"pdv\", \"store_phone\", \"store_ice\", \"store_rccm\", \"store_isf\", \"nid\", \"token\", \"port\", \"service_type\"]}','::1','2026-07-19 14:50:54'),(61,3,1,'update','company_info',1,'{\"ice\": \"\", \"isf\": \"\", \"nid\": \"\", \"pdv\": \"\", \"name\": \"SuperMarché Express\", \"port\": \"\", \"rccm\": \"\", \"email\": \"\", \"phone\": \"\", \"token\": \"\", \"address\": \"bandal\"}','::1','2026-07-19 15:01:54'),(62,3,1,'update','shop',1,'{\"ice\": \"001234567890123\", \"isf\": \"A900001T\", \"nom\": \"SuperMarché\", \"code\": \"MAIN\", \"rccm\": \"RCCM 2024\", \"actif\": \"1\", \"email\": \"mail@mailtest.com\", \"adresse\": \"Ngaliema, 09 av des Oliviers\", \"telephone\": \"+243 81 00 00 000\", \"service_type_id\": 5}','::1','2026-07-19 15:07:59'),(63,3,1,'update','company_info',1,'{\"ice\": \"001234567890124 \", \"isf\": \"A900011T\", \"nid\": \"NID900011\", \"pdv\": \"PDV0004\", \"name\": \"SuperMarché Express\", \"port\": \"\", \"rccm\": \"2025\", \"email\": \"satmos@mailtest.com\", \"phone\": \"0810000100\", \"token\": \"\", \"address\": \"bandal , 10 av \"}','::1','2026-07-19 17:50:30'),(64,3,1,'logout','utilisateur',3,NULL,'::1','2026-07-19 18:22:50'),(65,14,2,'logout','utilisateur',14,NULL,'::1','2026-07-19 18:22:57'),(66,11,1,'logout','utilisateur',11,NULL,'::1','2026-07-19 18:23:03'),(67,3,1,'login','utilisateur',3,NULL,'::1','2026-07-20 14:02:25'),(68,14,2,'login','utilisateur',14,NULL,'::1','2026-07-20 14:03:11'),(69,11,1,'login','utilisateur',11,NULL,'::1','2026-07-20 14:03:54'),(70,3,1,'update','utilisateur',3,NULL,'::1','2026-07-20 15:05:35'),(71,3,1,'update','utilisateur',11,NULL,'::1','2026-07-20 15:06:12'),(72,3,1,'upload_profile_image','utilisateur',3,'{\"image\": \"profile_6a5e1f5831c751.59653305.jpg\"}','::1','2026-07-20 15:15:04'),(73,11,1,'update','utilisateur',6,NULL,'::1','2026-07-20 15:16:48'),(74,11,1,'update','utilisateur',6,NULL,'::1','2026-07-20 15:16:52'),(75,3,1,'upload_profile_image','utilisateur',3,'{\"image\": \"profile_6a5e2083529a61.32224104.jpg\"}','::1','2026-07-20 15:20:03'),(76,14,2,'upload_profile_image','utilisateur',14,'{\"image\": \"profile_6a5e25ccc28484.79868900.jpg\"}','::1','2026-07-20 15:42:36'),(77,11,1,'upload_profile_image','utilisateur',11,'{\"image\": \"profile_6a5e25ee60b093.72076555.jpg\"}','::1','2026-07-20 15:43:10'),(78,3,1,'update_profile','utilisateur',3,NULL,'::1','2026-07-20 18:14:44'),(79,3,1,'login','utilisateur',3,NULL,'::1','2026-07-20 18:15:31'),(80,3,1,'logout','utilisateur',3,NULL,'::1','2026-07-20 18:20:58'),(81,NULL,NULL,'logout','utilisateur',NULL,NULL,'::1','2026-07-20 18:21:07'),(82,3,1,'login','utilisateur',3,NULL,'::1','2026-07-20 18:21:35'),(83,3,1,'logout','utilisateur',3,NULL,'::1','2026-07-20 18:28:44'),(84,NULL,NULL,'password_reset','utilisateur',4,NULL,'::1','2026-07-20 18:30:04'),(85,4,1,'login','utilisateur',4,NULL,'::1','2026-07-20 18:31:09'),(86,4,1,'logout','utilisateur',4,NULL,'::1','2026-07-20 18:31:57'),(87,4,1,'login','utilisateur',4,NULL,'::1','2026-07-20 18:32:34'),(88,4,1,'upload_profile_image','utilisateur',4,'{\"image\": \"profile_6a5e4dc1692778.87888640.jpg\"}','::1','2026-07-20 18:33:05'),(89,3,1,'logout','utilisateur',3,NULL,'::1','2026-07-20 19:00:05'),(90,4,1,'update','company_info',1,'{\"ice\": \"001234567890124 \", \"isf\": \"A900011T\", \"nid\": \"NID900011\", \"pdv\": \"PDV0004\", \"name\": \"OSAT SFE\", \"port\": \"\", \"rccm\": \"2025\", \"email\": \"satmos@mailtest.com\", \"phone\": \"0810000100\", \"token\": \"\", \"address\": \"bandal , 10 av \"}','::1','2026-07-20 19:00:39'),(91,11,1,'logout','utilisateur',11,NULL,'::1','2026-07-20 19:01:32'),(92,11,1,'login','utilisateur',11,NULL,'::1','2026-07-20 19:07:28'),(93,11,NULL,'logout','utilisateur',11,NULL,'::1','2026-07-20 22:34:16'),(94,11,1,'login','utilisateur',11,NULL,'::1','2026-07-20 22:35:18'),(95,11,1,'create','vente',489,'{\"total\": 1100000, \"type_facture\": \"FV\", \"numero_facture\": \"2026/000476\"}','::1','2026-07-20 22:37:37'),(96,11,1,'login','utilisateur',11,NULL,'::1','2026-07-21 11:04:29'),(97,11,1,'create','vente',490,'{\"total\": 1102210, \"type_facture\": \"FV\", \"numero_facture\": \"2026/000477\"}','::1','2026-07-21 11:05:43'),(98,11,1,'create','lot_produit',20,'{\"produit\": \"allohhh\", \"quantite\": 51, \"date_expiration\": \"2026-07-31\"}','::1','2026-07-21 14:08:35'),(99,11,1,'delete','lot_produit',10,NULL,'::1','2026-07-21 14:09:31'),(100,11,1,'create','lot_produit',21,'{\"produit\": \"allohhh\", \"quantite\": 5, \"date_expiration\": null}','::1','2026-07-21 14:09:55'),(101,11,1,'delete','lot_produit',21,NULL,'::1','2026-07-21 14:10:44'),(102,11,1,'delete','lot_produit',20,NULL,'::1','2026-07-21 14:10:45'),(103,11,1,'update','produit',24,'{\"nom\": \"allohhh (test)\"}','::1','2026-07-21 15:31:15'),(104,11,1,'create','lot_produit',22,'{\"produit\": \"allohhh (test)\", \"quantite\": 5, \"date_expiration\": \"2026-07-31\"}','::1','2026-07-21 15:31:37'),(105,11,1,'update','produit',24,'{\"nom\": \"allohhh (test)\"}','::1','2026-07-21 15:31:39'),(106,11,1,'create','produit',25,'{\"nom\": \"XXL\"}','::1','2026-07-21 15:32:47'),(107,11,1,'create','lot_produit',23,'{\"produit\": \"XXL\", \"quantite\": 50, \"date_expiration\": null}','::1','2026-07-21 15:33:10'),(108,11,1,'update','produit',25,'{\"nom\": \"XXL\"}','::1','2026-07-21 15:33:27'),(109,11,1,'create','lot_produit',24,'{\"produit\": \"XXL\", \"quantite\": 2, \"date_expiration\": \"2026-07-22\"}','::1','2026-07-21 15:33:47'),(110,11,1,'update','produit',25,'{\"nom\": \"XXL\"}','::1','2026-07-21 15:33:49'),(111,11,1,'login','utilisateur',11,NULL,'::1','2026-07-25 18:50:51'),(112,11,1,'create','produit',26,'{\"nom\": \"test produit\"}','::1','2026-07-25 18:51:51'),(113,11,1,'update','produit',26,'{\"nom\": \"test produit\"}','::1','2026-07-25 18:52:39'),(114,11,1,'create','lot_produit',26,'{\"produit\": \"test produit\", \"quantite\": 50, \"date_expiration\": \"2026-07-26\"}','::1','2026-07-25 18:53:05'),(115,11,1,'delete','lot_produit',26,NULL,'::1','2026-07-25 18:53:12'),(116,11,1,'create','lot_produit',27,'{\"produit\": \"test produit\", \"quantite\": 50, \"date_expiration\": \"2026-08-09\"}','::1','2026-07-25 18:53:22'),(117,11,1,'update','produit',26,'{\"nom\": \"test produit\"}','::1','2026-07-25 18:53:25'),(118,11,1,'create','vente',491,'{\"total\": 5000, \"type_facture\": \"FV\", \"numero_facture\": \"2026/000478\"}','::1','2026-07-25 18:53:58'),(119,11,1,'login','utilisateur',11,NULL,'::1','2026-07-28 12:21:31'),(120,11,1,'update','settings',NULL,'{\"keys\": [\"store_name\", \"store_address\", \"store_email\", \"pdv\", \"store_phone\", \"store_ice\", \"store_rccm\", \"store_isf\", \"nid\", \"token\", \"port\", \"service_type\"]}','::1','2026-07-28 17:15:47'),(121,11,1,'update','settings',NULL,'{\"keys\": [\"store_name\", \"store_address\", \"store_email\", \"pdv\", \"store_phone\", \"store_ice\", \"store_rccm\", \"store_isf\", \"nid\", \"token\", \"port\", \"service_type\"]}','::1','2026-07-28 17:21:44'),(122,11,1,'update','settings',NULL,'{\"keys\": [\"store_name\", \"store_address\", \"store_email\", \"pdv\", \"store_phone\", \"store_ice\", \"store_rccm\", \"store_isf\", \"nid\", \"token\", \"port\", \"service_type\"]}','::1','2026-07-28 17:23:34'),(123,14,2,'login','utilisateur',14,NULL,'::1','2026-07-28 17:25:43'),(124,14,2,'update','settings',NULL,'{\"keys\": [\"store_name\", \"store_address\", \"store_email\", \"pdv\", \"store_phone\", \"store_ice\", \"store_rccm\", \"store_isf\", \"nid\", \"token\", \"port\", \"service_type\"]}','::1','2026-07-28 17:26:35'),(125,3,1,'update','shop',2,'{\"ice\": \"001234567890124\", \"isf\": \"A900011T\", \"nid\": \"nid15236\", \"nom\": \"Mon magasin\", \"pdv\": \"pdv1532456\", \"code\": \"SHOP01\", \"port\": \"4344\", \"rccm\": \"RCCM 2025\", \"actif\": \"1\", \"email\": \"randy@mailtest.com\", \"token\": \"ezrtyuinhbjdcgq\", \"adresse\": \"lemba, 10 av terminus\", \"telephone\": \"+243 81 60 00 100\", \"service_type_id\": 4}','::1','2026-07-28 17:37:22'),(126,14,2,'logout','utilisateur',14,NULL,'::1','2026-07-28 17:54:02'),(127,3,1,'logout','utilisateur',3,NULL,'::1','2026-07-28 17:54:07'),(128,11,1,'logout','utilisateur',11,NULL,'::1','2026-07-28 17:54:11'),(129,1,1,'login','utilisateur',1,NULL,'::1','2026-07-28 19:13:57'),(130,1,1,'logout','utilisateur',1,NULL,'::1','2026-07-28 19:14:08'),(131,3,1,'login','utilisateur',3,NULL,'::1','2026-07-28 19:14:28'),(132,11,1,'login','utilisateur',11,NULL,'::1','2026-07-28 19:15:57'),(133,11,1,'create','produit',27,'{\"nom\": \"ppp\"}','::1','2026-07-28 19:18:09'),(134,11,1,'create','lot_produit',29,'{\"produit\": \"ppp\", \"quantite\": 85, \"date_expiration\": \"2026-08-07\"}','::1','2026-07-28 19:18:54'),(135,11,1,'update','produit',27,'{\"nom\": \"ppp\"}','::1','2026-07-28 19:19:09'),(136,11,1,'update','produit',27,'{\"nom\": \"ppp\"}','::1','2026-07-28 19:21:31'),(137,11,1,'create','vente',492,'{\"total\": 140, \"type_facture\": \"FV\", \"numero_facture\": \"2026/000479\"}','::1','2026-07-28 19:21:53'),(138,3,1,'login','utilisateur',3,NULL,'::1','2026-07-28 21:47:45'),(139,11,1,'login','utilisateur',11,NULL,'::1','2026-07-28 21:49:10'),(140,14,2,'login','utilisateur',14,NULL,'::1','2026-07-28 22:02:59'),(141,14,2,'create','categorie',NULL,'{\"nom\": \"test\"}','::1','2026-07-28 22:41:09'),(142,14,2,'create','produit',28,'{\"nom\": \"americano\"}','::1','2026-07-28 22:43:18'),(143,14,2,'create','produit',29,'{\"nom\": \"bavaria\"}','::1','2026-07-28 22:44:17'),(144,14,2,'create','produit',30,'{\"nom\": \"Jack Daniel&#039;s\"}','::1','2026-07-28 22:46:24'),(145,14,2,'create','produit',31,'{\"nom\": \"Vodka \"}','::1','2026-07-28 22:48:52'),(146,14,2,'update','produit',30,'{\"nom\": \"Jack Daniel\"}','::1','2026-07-28 22:49:09'),(147,14,2,'create','produit',32,'{\"nom\": \"Baileys\"}','::1','2026-07-28 22:49:45'),(148,14,2,'create','produit',33,'{\"nom\": \"Black Label\"}','::1','2026-07-28 22:50:52'),(149,14,2,'create','produit',34,'{\"nom\": \"castel\"}','::1','2026-07-28 22:51:45'),(150,14,2,'create','produit',35,'{\"nom\": \"Red Label\"}','::1','2026-07-28 22:53:04'),(151,14,2,'update','produit',28,'{\"nom\": \"americano\"}','::1','2026-07-28 23:08:20'),(152,14,2,'create','lot_produit',30,'{\"produit\": \"americano\", \"quantite\": 100, \"date_expiration\": null}','::1','2026-07-28 23:08:35'),(153,14,2,'update','produit',28,'{\"nom\": \"americano\"}','::1','2026-07-28 23:08:38'),(154,14,2,'create','lot_produit',31,'{\"produit\": \"Baileys\", \"quantite\": 100, \"date_expiration\": null}','::1','2026-07-28 23:08:48'),(155,14,2,'update','produit',32,'{\"nom\": \"Baileys\"}','::1','2026-07-28 23:08:54'),(156,14,2,'create','lot_produit',32,'{\"produit\": \"bavaria\", \"quantite\": 100, \"date_expiration\": null}','::1','2026-07-28 23:09:05'),(157,14,2,'update','produit',29,'{\"nom\": \"bavaria\"}','::1','2026-07-28 23:09:07'),(158,14,2,'create','lot_produit',33,'{\"produit\": \"Black Label\", \"quantite\": 100, \"date_expiration\": null}','::1','2026-07-28 23:09:17'),(159,14,2,'update','produit',33,'{\"nom\": \"Black Label\"}','::1','2026-07-28 23:09:19'),(160,14,2,'create','lot_produit',34,'{\"produit\": \"castel\", \"quantite\": 100, \"date_expiration\": null}','::1','2026-07-28 23:09:29'),(161,14,2,'update','produit',34,'{\"nom\": \"castel\"}','::1','2026-07-28 23:09:32'),(162,14,2,'create','lot_produit',35,'{\"produit\": \"Jack Daniel\", \"quantite\": 100, \"date_expiration\": null}','::1','2026-07-28 23:09:42'),(163,14,2,'update','produit',30,'{\"nom\": \"Jack Daniel\"}','::1','2026-07-28 23:09:47'),(164,14,2,'create','lot_produit',36,'{\"produit\": \"Red Label\", \"quantite\": 100, \"date_expiration\": null}','::1','2026-07-28 23:09:58'),(165,14,2,'update','produit',35,'{\"nom\": \"Red Label\"}','::1','2026-07-28 23:10:00'),(166,14,2,'create','lot_produit',37,'{\"produit\": \"Vodka \", \"quantite\": 100, \"date_expiration\": null}','::1','2026-07-28 23:10:08'),(167,14,2,'update','produit',31,'{\"nom\": \"Vodka \"}','::1','2026-07-28 23:10:10'),(168,14,2,'login','utilisateur',14,NULL,'::1','2026-07-29 11:58:00'),(169,3,1,'login','utilisateur',3,NULL,'::1','2026-07-29 11:58:11'),(170,11,1,'login','utilisateur',11,NULL,'::1','2026-07-29 11:58:24'),(171,14,2,'create','produit',36,'{\"nom\": \"beaufort\"}','::1','2026-07-29 12:05:31'),(172,14,2,'create','produit',37,'{\"nom\": \"double Label\"}','::1','2026-07-29 12:06:29'),(173,3,1,'update','service_type',4,'{\"name\": \"Bar\"}','::1','2026-07-29 12:07:58'),(174,14,2,'create','produit',38,'{\"nom\": \"Hennessy \"}','::1','2026-07-29 12:09:14'),(175,14,2,'logout','utilisateur',14,NULL,'::1','2026-07-29 12:34:14'),(176,14,2,'login','utilisateur',14,NULL,'::1','2026-07-29 12:34:18'),(177,3,1,'logout','utilisateur',3,NULL,'::1','2026-07-29 19:19:53'),(178,14,2,'logout','utilisateur',14,NULL,'::1','2026-07-29 19:20:00'),(179,11,1,'logout','utilisateur',11,NULL,'::1','2026-07-29 19:20:39'),(180,3,1,'login','utilisateur',3,NULL,'::1','2026-07-29 23:19:05'),(181,11,1,'login','utilisateur',11,NULL,'::1','2026-07-29 23:19:13'),(182,14,2,'login','utilisateur',14,NULL,'::1','2026-07-29 23:19:40'),(183,3,1,'login','utilisateur',3,NULL,'::1','2026-07-30 15:13:42'),(184,14,2,'login','utilisateur',14,NULL,'::1','2026-07-30 15:17:44'),(185,3,1,'login','utilisateur',3,NULL,'::1','2026-07-30 17:06:12'),(186,14,2,'login','utilisateur',14,NULL,'::1','2026-07-30 18:26:44'),(187,3,1,'update_profile','utilisateur',3,NULL,'::1','2026-07-30 19:41:38'),(188,3,1,'logout','utilisateur',3,NULL,'::1','2026-07-30 19:42:22'),(189,14,2,'logout','utilisateur',14,NULL,'::1','2026-07-30 19:42:26'),(190,11,1,'login','utilisateur',11,NULL,'::1','2026-07-31 14:31:00'),(191,11,1,'login','utilisateur',11,NULL,'::1','2026-07-31 20:46:06'),(192,11,1,'login','utilisateur',11,NULL,'::1','2026-07-31 20:46:33'),(193,11,1,'update','settings',NULL,'{\"keys\": [\"store_name\", \"store_address\", \"store_email\", \"pdv\", \"store_phone\", \"store_ice\", \"store_rccm\", \"store_isf\", \"nid\", \"token\", \"port\", \"service_type\"]}','::1','2026-07-31 21:19:53'),(194,11,1,'update','settings',NULL,'{\"keys\": [\"store_name\", \"store_address\", \"store_email\", \"pdv\", \"store_phone\", \"store_ice\", \"store_rccm\", \"store_isf\", \"nid\", \"token\", \"port\", \"service_type\"]}','::1','2026-07-31 21:21:33'),(195,11,1,'update','settings',NULL,'{\"keys\": [\"store_name\", \"store_address\", \"store_email\", \"pdv\", \"store_phone\", \"store_ice\", \"store_rccm\", \"store_isf\", \"nid\", \"token\", \"port\", \"service_type\"]}','::1','2026-07-31 21:23:02'),(196,11,1,'update','settings',NULL,'{\"keys\": [\"store_name\", \"store_address\", \"store_email\", \"pdv\", \"store_phone\", \"store_ice\", \"store_rccm\", \"store_isf\", \"nid\", \"token\", \"port\", \"service_type\"]}','::1','2026-07-31 21:23:52'),(197,11,1,'update','settings',NULL,'{\"keys\": [\"store_name\", \"store_address\", \"store_email\", \"pdv\", \"store_phone\", \"store_ice\", \"store_rccm\", \"store_isf\", \"nid\", \"token\", \"port\", \"service_type\"]}','::1','2026-07-31 21:26:05'),(198,11,1,'logout','utilisateur',11,NULL,'::1','2026-07-31 21:28:26'),(199,11,1,'login','utilisateur',11,NULL,'::1','2026-07-31 21:28:32'),(200,11,1,'update','settings',NULL,'{\"keys\": [\"store_name\", \"store_address\", \"store_email\", \"pdv\", \"store_phone\", \"store_ice\", \"store_rccm\", \"store_isf\", \"nid\", \"token\", \"port\", \"service_type\"]}','::1','2026-07-31 21:29:34'),(201,11,1,'logout','utilisateur',11,NULL,'::1','2026-08-01 12:54:04'),(202,11,1,'login','utilisateur',11,NULL,'::1','2026-08-01 12:54:15'),(203,11,1,'login','utilisateur',11,NULL,'::1','2026-08-01 19:51:08');
/*!40000 ALTER TABLE `audit_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `categories`
--

DROP TABLE IF EXISTS `categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `categories` (
  `id` int NOT NULL AUTO_INCREMENT,
  `category` varchar(120) NOT NULL,
  `shop_id` int DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_cat_shop` (`shop_id`),
  CONSTRAINT `fk_cat_shop` FOREIGN KEY (`shop_id`) REFERENCES `shops` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `categories`
--

LOCK TABLES `categories` WRITE;
/*!40000 ALTER TABLE `categories` DISABLE KEYS */;
INSERT INTO `categories` VALUES (1,'Comestible',1,'2026-04-23 13:47:06','2026-07-16 23:59:36'),(3,'Service',1,'2026-04-23 13:47:06','2026-07-16 23:59:36'),(4,'Non comestible',1,'2026-04-24 14:21:31','2026-07-16 23:59:36'),(6,'Boissons',2,'2026-07-28 22:41:09','2026-07-28 22:47:07');
/*!40000 ALTER TABLE `categories` ENABLE KEYS */;
UNLOCK TABLES;

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
  `adresse` varchar(255) DEFAULT NULL,
  `shop_id` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code_client` (`code_client`),
  KEY `fk_type_client` (`type_client_id`),
  KEY `idx_client_shop` (`shop_id`),
  CONSTRAINT `fk_client_shop` FOREIGN KEY (`shop_id`) REFERENCES `shops` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_type_client` FOREIGN KEY (`type_client_id`) REFERENCES `type_client` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `clients`
--

LOCK TABLES `clients` WRITE;
/*!40000 ALTER TABLE `clients` DISABLE KEYS */;
INSERT INTO `clients` VALUES (1,'0816069107','Mufasa','CLI-001',1,NULL,NULL,1),(2,'0816810070','test','cli-2',2,NULL,NULL,1),(3,'0895511485','cesar','CLI-003',1,'12345688',NULL,1),(4,'0895510485','kkk','CLI-004',1,'',NULL,1),(5,'0000','NULL','NULL',1,'NULL',NULL,1),(6,'00000','test_innit','CLI-006',3,'0101',NULL,1),(7,'0895511486','cesar2','CLI-007',1,'12345688',NULL,1),(8,'0819648854','Nzuiya','CLI-008',1,'12345688','',1);
/*!40000 ALTER TABLE `clients` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `company_info`
--

DROP TABLE IF EXISTS `company_info`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `company_info` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(100) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `pdv` varchar(50) DEFAULT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `ice` varchar(50) DEFAULT NULL,
  `rccm` varchar(50) DEFAULT NULL,
  `isf` varchar(50) DEFAULT NULL,
  `nid` varchar(100) DEFAULT NULL,
  `token` varchar(255) DEFAULT NULL,
  `port` varchar(10) DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `company_info`
--

LOCK TABLES `company_info` WRITE;
/*!40000 ALTER TABLE `company_info` DISABLE KEYS */;
INSERT INTO `company_info` VALUES (1,'OSAT SFE','bandal , 10 av ','satmos@mailtest.com','PDV0004','0810000100','001234567890124 ','2025','A900011T','NID900011','','','2026-07-19 14:05:40','2026-07-20 19:00:39');
/*!40000 ALTER TABLE `company_info` ENABLE KEYS */;
UNLOCK TABLES;

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
  `remise_type` varchar(10) DEFAULT 'percent',
  `remise_value` decimal(10,2) DEFAULT '0.00',
  `taxe_specifique_type` varchar(10) DEFAULT '%',
  `taxe_specifique_value` decimal(10,2) DEFAULT '0.00',
  PRIMARY KEY (`id`),
  KEY `vente_id` (`vente_id`),
  KEY `produit_id` (`produit_id`)
) ENGINE=InnoDB AUTO_INCREMENT=616 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `details_vente`
--

LOCK TABLES `details_vente` WRITE;
/*!40000 ALTER TABLE `details_vente` DISABLE KEYS */;
INSERT INTO `details_vente` VALUES (2,2,3,1,8.50,'%',0.00,'%',0.00),(7,4,3,1,8.50,'%',0.00,'%',0.00),(8,5,3,1,8.50,'%',0.00,'%',0.00),(17,11,3,3,8.50,'%',0.00,'%',0.00),(20,13,3,1,8.50,'%',0.00,'%',0.00),(33,22,3,1,8.50,'%',0.00,'%',0.00),(36,23,3,2,8.50,'%',0.00,'%',0.00),(43,26,3,2,8.50,'%',0.00,'%',0.00),(45,27,3,2,8.50,'%',0.00,'%',0.00),(47,28,3,3,8.50,'%',0.00,'%',0.00),(48,28,6,2,50.00,'%',0.00,'%',0.00),(52,31,3,3,8.50,'%',0.00,'%',0.00),(53,32,3,1,8.50,'%',0.00,'%',0.00),(54,33,3,1,8.50,'%',0.00,'%',0.00),(55,34,3,1,8.50,'%',0.00,'%',0.00),(57,36,3,3,8.50,'%',0.00,'%',0.00),(59,37,3,2,8.50,'%',0.00,'%',0.00),(62,39,3,2,8.50,'%',0.00,'%',0.00),(65,40,3,2,8.50,'%',0.00,'%',0.00),(68,42,3,1,8.50,'%',0.00,'%',0.00),(72,44,6,1,50.00,'%',0.00,'%',0.00),(73,44,3,1,8.50,'%',0.00,'%',0.00),(74,45,6,1,50.00,'%',0.00,'%',0.00),(75,46,3,2,8.50,'%',0.00,'%',0.00),(76,47,3,3,8.50,'%',0.00,'%',0.00),(77,48,3,1,8.50,'%',0.00,'%',0.00),(78,49,3,2,8.50,'%',0.00,'%',0.00),(79,50,3,2,8.50,'%',0.00,'%',0.00),(80,51,3,2,8.50,'%',0.00,'%',0.00),(81,52,3,2,8.50,'%',0.00,'%',0.00),(82,53,3,4,8.50,'%',0.00,'%',0.00),(83,54,3,3,8.50,'%',0.00,'%',0.00),(84,55,3,1,8.50,'%',0.00,'%',0.00),(85,56,3,1,8.50,'%',0.00,'%',0.00),(86,57,3,2,8.50,'%',0.00,'%',0.00),(87,58,3,1,8.50,'%',0.00,'%',0.00),(88,59,6,2,50.00,'%',0.00,'%',0.00),(91,61,3,1,8.50,'%',0.00,'%',0.00),(92,61,6,1,50.00,'%',0.00,'%',0.00),(94,62,3,3,8.50,'%',0.00,'%',0.00),(95,63,3,1,8.50,'%',0.00,'%',0.00),(97,63,6,1,50.00,'%',0.00,'%',0.00),(99,64,6,1,50.00,'%',0.00,'%',0.00),(100,64,3,1,8.50,'%',0.00,'%',0.00),(102,65,3,1,8.50,'%',0.00,'%',0.00),(103,65,6,1,50.00,'%',0.00,'%',0.00),(105,66,3,1,8.50,'%',0.00,'%',0.00),(113,71,3,2,8.50,'%',0.00,'%',0.00),(114,72,11,1,800.00,'%',0.00,'%',0.00),(115,72,6,1,50.00,'%',0.00,'%',0.00),(116,73,6,2,50.00,'%',0.00,'%',0.00),(117,73,11,1,800.00,'%',0.00,'%',0.00),(118,74,3,2,8.50,'%',0.00,'%',0.00),(119,75,3,1,8.50,'%',0.00,'%',0.00),(120,76,3,2,8.50,'%',0.00,'%',0.00),(121,77,3,2,8.50,'%',0.00,'%',0.00),(122,77,19,1,10000.00,'%',0.00,'%',0.00),(123,77,17,1,900.00,'%',0.00,'%',0.00),(124,78,3,2,8.50,'%',0.00,'%',0.00),(125,78,19,2,10000.00,'%',0.00,'%',0.00),(126,79,3,2,8.50,'%',0.00,'%',0.00),(127,79,19,3,10000.00,'%',0.00,'%',0.00),(128,80,3,2,8.50,'%',0.00,'%',0.00),(129,81,3,4,8.50,'%',0.00,'%',0.00),(130,82,19,2,10000.00,'%',0.00,'%',0.00),(131,83,17,2,900.00,'%',0.00,'%',0.00),(132,84,19,3,10000.00,'%',0.00,'%',0.00),(133,85,3,3,8.50,'%',0.00,'%',0.00),(134,86,19,3,10000.00,'%',0.00,'%',0.00),(135,87,19,2,10000.00,'%',0.00,'%',0.00),(136,88,17,2,900.00,'%',0.00,'%',0.00),(137,89,19,2,10000.00,'%',0.00,'%',0.00),(138,90,3,1,8.50,'%',0.00,'%',0.00),(139,91,3,2,8.50,'%',0.00,'%',0.00),(140,92,3,2,8.50,'%',0.00,'%',0.00),(141,93,3,3,8.50,'%',0.00,'%',0.00),(142,94,3,2,8.50,'%',0.00,'%',0.00),(145,124,3,6,8.50,'%',0.00,'%',0.00),(146,126,3,3,8.50,'%',0.00,'%',0.00),(147,127,19,1,10000.00,'%',0.00,'%',0.00),(148,128,8,1,5000.00,'%',0.00,'%',0.00),(149,129,19,1,10000.00,'%',0.00,'%',0.00),(150,130,3,10,8.50,'%',0.00,'%',0.00),(151,131,19,1,10000.00,'%',0.00,'%',0.00),(152,132,19,1,10000.00,'%',0.00,'%',0.00),(153,133,19,1,10000.00,'%',0.00,'%',0.00),(154,133,17,1,900.00,'%',0.00,'%',0.00),(155,133,3,9,8.50,'%',0.00,'%',0.00),(156,134,19,65,10000.00,'%',0.00,'%',0.00),(157,134,17,1,900.00,'%',0.00,'%',0.00),(158,134,6,1,50.00,'%',0.00,'%',0.00),(159,134,3,1,8.50,'%',0.00,'%',0.00),(160,136,17,1,900.00,'%',0.00,'%',0.00),(161,137,19,1,10000.00,'%',0.00,'%',0.00),(162,137,17,1,900.00,'%',0.00,'%',0.00),(163,138,17,2,900.00,'%',0.00,'%',0.00),(164,139,19,2,10000.00,'%',0.00,'%',0.00),(165,139,17,1,900.00,'%',0.00,'%',0.00),(166,139,6,1,50.00,'%',0.00,'%',0.00),(167,140,17,2,900.00,'%',0.00,'%',0.00),(168,140,6,1,50.00,'%',0.00,'%',0.00),(169,140,11,1,800.00,'%',0.00,'%',0.00),(170,141,19,1,10000.00,'%',0.00,'%',0.00),(171,141,6,1,50.00,'%',0.00,'%',0.00),(172,142,11,1,800.00,'%',0.00,'%',0.00),(173,142,17,1,900.00,'%',0.00,'%',0.00),(174,143,19,1,10000.00,'%',0.00,'%',0.00),(175,145,6,1,50.00,'%',0.00,'%',0.00),(176,145,11,1,800.00,'%',0.00,'%',0.00),(177,145,19,1,10000.00,'%',0.00,'%',0.00),(178,145,17,1,900.00,'%',0.00,'%',0.00),(179,145,8,1,5000.00,'%',0.00,'%',0.00),(180,146,19,1,10000.00,'%',0.00,'%',0.00),(181,146,17,1,900.00,'%',0.00,'%',0.00),(182,147,17,1,900.00,'%',0.00,'%',0.00),(183,147,19,1,10000.00,'%',0.00,'%',0.00),(184,148,3,1,8.50,'%',0.00,'%',0.00),(185,148,17,1,900.00,'%',0.00,'%',0.00),(186,148,6,1,50.00,'%',0.00,'%',0.00),(187,148,11,1,800.00,'%',0.00,'%',0.00),(188,148,8,1,5000.00,'%',0.00,'%',0.00),(189,149,17,1,900.00,'%',0.00,'%',0.00),(190,149,19,1,10000.00,'%',0.00,'%',0.00),(191,150,17,1,900.00,'%',0.00,'%',0.00),(192,150,19,1,10000.00,'%',0.00,'%',0.00),(193,150,8,1,5000.00,'%',0.00,'%',0.00),(194,151,19,1,10000.00,'%',0.00,'%',0.00),(195,152,19,1,10000.00,'%',0.00,'%',0.00),(196,152,17,1,900.00,'%',0.00,'%',0.00),(197,153,11,3,800.00,'%',0.00,'%',0.00),(198,154,11,1,800.00,'%',0.00,'%',0.00),(199,155,3,1,8.50,'%',0.00,'%',0.00),(200,156,3,1,8.50,'%',0.00,'%',0.00),(201,157,3,1,8.50,'%',0.00,'%',0.00),(202,158,3,1,8.50,'%',0.00,'%',0.00),(203,159,3,1,8.50,'%',0.00,'%',0.00),(204,160,3,1,8.50,'%',0.00,'%',0.00),(205,160,19,1,10000.00,'%',0.00,'%',0.00),(206,160,17,1,900.00,'%',0.00,'%',0.00),(207,161,3,1,8.50,'%',0.00,'%',0.00),(208,162,3,1,8.50,'%',0.00,'%',0.00),(209,163,3,1,8.50,'%',0.00,'%',0.00),(210,164,3,11,8.50,'%',0.00,'%',0.00),(211,165,6,1,50.00,'%',0.00,'%',0.00),(212,166,6,1,50.00,'%',0.00,'%',0.00),(213,166,11,1,800.00,'%',0.00,'%',0.00),(214,166,17,1,900.00,'%',0.00,'%',0.00),(215,166,19,1,10000.00,'%',0.00,'%',0.00),(216,166,3,1,8.50,'%',0.00,'%',0.00),(217,167,3,1,8.50,'%',0.00,'%',0.00),(218,168,11,1,800.00,'%',0.00,'%',0.00),(219,168,17,1,900.00,'%',0.00,'%',0.00),(220,169,19,1,10000.00,'%',0.00,'%',0.00),(221,170,19,1,10000.00,'%',0.00,'%',0.00),(222,171,19,1,10000.00,'%',0.00,'%',0.00),(223,171,3,1,8.50,'%',0.00,'%',0.00),(224,172,19,1,10000.00,'%',0.00,'%',0.00),(225,172,3,1,8.50,'%',0.00,'%',0.00),(226,172,6,1,50.00,'%',0.00,'%',0.00),(227,173,3,1,8.50,'%',0.00,'%',0.00),(228,174,17,1,900.00,'%',0.00,'%',0.00),(229,174,11,1,800.00,'%',0.00,'%',0.00),(230,175,3,1,8.50,'%',0.00,'%',0.00),(231,176,3,11,8.50,'%',0.00,'%',0.00),(232,176,19,1,10000.00,'%',0.00,'%',0.00),(233,176,17,1,900.00,'%',0.00,'%',0.00),(234,176,11,1,800.00,'%',0.00,'%',0.00),(235,176,6,1,50.00,'%',0.00,'%',0.00),(236,177,3,1,8.50,'%',0.00,'%',0.00),(237,178,3,1,8.50,'%',0.00,'%',0.00),(238,179,3,1,8.50,'%',0.00,'%',0.00),(239,180,3,1,8.50,'%',0.00,'%',0.00),(240,181,3,1,8.50,'%',0.00,'%',0.00),(241,182,3,1,8.50,'%',0.00,'%',0.00),(242,183,3,1,8.50,'%',0.00,'%',0.00),(243,184,3,9,8.50,'%',0.00,'%',0.00),(244,184,19,1,10000.00,'%',0.00,'%',0.00),(245,185,3,10,8.50,'%',0.00,'%',0.00),(246,186,3,1,8.50,'%',0.00,'%',0.00),(247,187,3,1,8.50,'%',0.00,'%',0.00),(248,188,3,1,8.50,'%',0.00,'%',0.00),(249,189,3,1,8.50,'%',0.00,'%',0.00),(250,190,19,1,10000.00,'%',0.00,'%',0.00),(251,191,3,1,8.50,'%',0.00,'%',0.00),(252,192,17,1,900.00,'%',0.00,'%',0.00),(253,193,17,1,900.00,'%',0.00,'%',0.00),(254,194,19,1,10000.00,'%',0.00,'%',0.00),(255,195,3,4,8.50,'%',0.00,'%',0.00),(256,195,19,1,10000.00,'%',0.00,'%',0.00),(257,196,3,0,8.50,'%',0.00,'%',0.00),(258,197,3,1,8.50,'%',0.00,'%',0.00),(259,198,3,9,8.50,'%',0.00,'%',0.00),(260,199,19,1,10000.00,'%',0.00,'%',0.00),(261,200,17,1,900.00,'%',0.00,'%',0.00),(262,201,19,1,10000.00,'%',0.00,'%',0.00),(263,202,19,1,10000.00,'%',0.00,'%',0.00),(264,203,19,1,10000.00,'%',0.00,'%',0.00),(265,204,19,1,10000.00,'%',0.00,'%',0.00),(266,204,17,1,900.00,'%',0.00,'%',0.00),(267,205,3,1,8.50,'%',0.00,'%',0.00),(268,205,19,1,10000.00,'%',0.00,'%',0.00),(269,205,17,1,900.00,'%',0.00,'%',0.00),(270,206,19,1,10000.00,'%',0.00,'%',0.00),(271,207,19,1,10000.00,'%',0.00,'%',0.00),(272,207,17,1,900.00,'%',0.00,'%',0.00),(273,207,3,1,8.50,'%',0.00,'%',0.00),(274,208,19,1,10000.00,'%',0.00,'%',0.00),(275,209,17,1,900.00,'%',0.00,'%',0.00),(276,210,17,1,900.00,'%',0.00,'%',0.00),(277,211,19,3,10000.00,'%',0.00,'%',0.00),(278,211,17,1,900.00,'%',0.00,'%',0.00),(279,212,17,1,900.00,'%',0.00,'%',0.00),(280,213,19,1,10000.00,'%',0.00,'%',0.00),(281,214,19,1,10000.00,'%',0.00,'%',0.00),(282,215,19,1,10000.00,'%',0.00,'%',0.00),(283,216,19,1,10000.00,'%',0.00,'%',0.00),(284,217,19,1,10000.00,'%',0.00,'%',0.00),(285,218,19,1,10000.00,'%',0.00,'%',0.00),(286,219,19,2,10000.00,'%',0.00,'%',0.00),(287,220,19,2,10000.00,'%',0.00,'%',0.00),(288,221,19,1,10000.00,'%',0.00,'%',0.00),(289,222,19,1,10000.00,'%',0.00,'%',0.00),(290,223,19,1,10000.00,'%',0.00,'%',0.00),(291,224,19,1,10000.00,'%',0.00,'%',0.00),(292,225,19,1,10000.00,'%',0.00,'%',0.00),(293,226,19,1,10000.00,'%',0.00,'%',0.00),(294,227,19,1,10000.00,'%',0.00,'%',0.00),(295,228,19,2,10000.00,'%',0.00,'%',0.00),(296,229,19,3,10000.00,'%',0.00,'%',0.00),(297,230,19,2,10000.00,'%',0.00,'%',0.00),(298,231,19,1,10000.00,'%',0.00,'%',0.00),(299,232,19,1,10000.00,'%',0.00,'%',0.00),(300,233,19,1,10000.00,'%',0.00,'%',0.00),(301,234,19,1,10000.00,'%',0.00,'%',0.00),(302,235,19,2,10000.00,'%',0.00,'%',0.00),(303,235,3,1,8.50,'%',0.00,'%',0.00),(304,237,19,1,10000.00,'%',0.00,'%',0.00),(305,242,3,1,8.50,'%',0.00,'%',0.00),(306,247,3,1,8.50,'%',0.00,'%',0.00),(307,248,3,1,8.50,'%',0.00,'%',0.00),(308,249,3,1,8.50,'%',0.00,'%',0.00),(309,250,3,1,8.50,'%',0.00,'%',0.00),(310,251,19,2,10000.00,'%',0.00,'%',0.00),(311,256,3,1,8.50,'%',0.00,'%',0.00),(312,260,19,1,10000.00,'%',0.00,'%',0.00),(313,261,19,1,10000.00,'%',0.00,'%',0.00),(314,262,19,1,10000.00,'%',0.00,'%',0.00),(315,263,19,1,10000.00,'%',0.00,'%',0.00),(316,264,19,1,10000.00,'%',0.00,'%',0.00),(317,265,19,1,10000.00,'%',0.00,'%',0.00),(318,266,19,1,10000.00,'%',0.00,'%',0.00),(319,267,19,1,10000.00,'%',0.00,'%',0.00),(320,268,19,1,10000.00,'%',0.00,'%',0.00),(321,269,19,1,10000.00,'%',0.00,'%',0.00),(322,270,19,1,10000.00,'%',0.00,'%',0.00),(323,271,19,1,10000.00,'%',0.00,'%',0.00),(324,272,19,1,10000.00,'%',0.00,'%',0.00),(325,273,19,1,10000.00,'%',0.00,'%',0.00),(326,274,19,1,10000.00,'%',0.00,'%',0.00),(327,275,19,1,10000.00,'%',0.00,'%',0.00),(328,276,19,1,10000.00,'%',0.00,'%',0.00),(329,277,19,1,10000.00,'%',0.00,'%',0.00),(330,278,19,1,10000.00,'%',0.00,'%',0.00),(331,279,19,1,10000.00,'%',0.00,'%',0.00),(332,280,19,1,10000.00,'%',0.00,'%',0.00),(333,281,19,1,10000.00,'%',0.00,'%',0.00),(334,281,20,1,50.00,'%',0.00,'%',0.00),(335,281,3,1,8.50,'%',0.00,'%',0.00),(336,281,21,1,80.00,'%',0.00,'%',0.00),(337,281,22,1,0.01,'%',0.00,'%',0.00),(338,281,6,1,50.00,'%',0.00,'%',0.00),(339,281,8,1,5000.00,'%',0.00,'%',0.00),(340,282,20,1,50.00,'%',0.00,'%',0.00),(341,283,21,1,80.00,'%',0.00,'%',0.00),(342,283,20,1,50.00,'%',0.00,'%',0.00),(343,284,21,1,80.00,'%',0.00,'%',0.00),(344,284,20,1,50.00,'%',0.00,'%',0.00),(345,285,21,1,80.00,'%',0.00,'%',0.00),(346,285,20,1,50.00,'%',0.00,'%',0.00),(347,285,6,1,50.00,'%',0.00,'%',0.00),(348,286,21,1,80.00,'%',0.00,'%',0.00),(349,287,19,2,10000.00,'%',0.00,'%',0.00),(350,287,20,1,50.00,'%',0.00,'%',0.00),(351,287,22,1,0.01,'%',0.00,'%',0.00),(352,288,19,1,10000.00,'%',0.00,'%',0.00),(353,289,19,1,10000.00,'%',0.00,'%',0.00),(354,290,21,1,80.00,'%',0.00,'%',0.00),(355,290,19,1,10000.00,'%',0.00,'%',0.00),(356,290,20,1,50.00,'%',0.00,'%',0.00),(357,290,17,1,900.00,'%',0.00,'%',0.00),(358,290,22,1,0.01,'%',0.00,'%',0.00),(359,290,6,1,50.00,'%',0.00,'%',0.00),(360,290,8,1,5000.00,'%',0.00,'%',0.00),(361,291,21,1,80.00,'%',0.00,'%',0.00),(362,292,21,1,80.00,'%',0.00,'%',0.00),(363,293,21,1,80.00,'%',0.00,'%',0.00),(364,294,21,1,80.00,'%',0.00,'%',0.00),(365,295,21,1,80.00,'%',0.00,'%',0.00),(366,296,19,17,10000.00,'%',0.00,'%',0.00),(367,297,21,1,80.00,'%',0.00,'%',0.00),(368,297,22,1,0.01,'%',0.00,'%',0.00),(369,297,6,1,50.00,'%',0.00,'%',0.00),(370,297,8,1,5000.00,'%',0.00,'%',0.00),(371,298,21,1,80.00,'%',0.00,'%',0.00),(372,299,21,1,80.00,'%',0.00,'%',0.00),(373,300,21,1,80.00,'%',0.00,'%',0.00),(374,302,21,2,80.00,'%',0.00,'%',0.00),(375,303,21,1,80.00,'%',0.00,'%',0.00),(376,304,21,1,80.00,'%',0.00,'%',0.00),(377,305,21,1,80.00,'%',0.00,'%',0.00),(378,306,21,1,80.00,'%',0.00,'%',0.00),(379,307,21,-1,80.00,'%',0.00,'%',0.00),(380,308,21,-1,80.00,'%',0.00,'%',0.00),(381,309,21,1,80.00,'%',0.00,'%',0.00),(382,310,21,-1,80.00,'%',0.00,'%',0.00),(383,311,21,1,80.00,'%',0.00,'%',0.00),(384,312,21,1,80.00,'%',0.00,'%',0.00),(385,313,21,1,80.00,'%',0.00,'%',0.00),(386,314,21,1,80.00,'%',0.00,'%',0.00),(387,315,21,-1,80.00,'%',0.00,'%',0.00),(388,316,21,1,80.00,'%',0.00,'%',0.00),(389,317,21,-1,80.00,'%',0.00,'%',0.00),(390,318,21,1,80.00,'%',0.00,'%',0.00),(391,319,21,-1,80.00,'%',0.00,'%',0.00),(392,320,21,1,80.00,'%',0.00,'%',0.00),(393,321,21,-1,80.00,'%',0.00,'%',0.00),(394,322,21,1,80.00,'%',0.00,'%',0.00),(395,323,21,-1,80.00,'%',0.00,'%',0.00),(396,324,21,1,80.00,'%',0.00,'%',0.00),(397,325,21,-1,80.00,'%',0.00,'%',0.00),(398,326,21,1,80.00,'%',0.00,'%',0.00),(399,327,21,-1,80.00,'%',0.00,'%',0.00),(400,328,21,1,80.00,'%',0.00,'%',0.00),(401,329,21,-1,80.00,'%',0.00,'%',0.00),(402,330,21,1,80.00,'%',0.00,'%',0.00),(403,331,21,-1,80.00,'%',0.00,'%',0.00),(404,332,21,1,80.00,'%',0.00,'%',0.00),(405,333,21,1,80.00,'%',0.00,'%',0.00),(406,334,21,1,80.00,'%',0.00,'%',0.00),(407,335,21,1,80.00,'%',0.00,'%',0.00),(408,336,21,1,80.00,'%',0.00,'%',0.00),(409,337,21,1,80.00,'%',0.00,'%',0.00),(410,338,21,1,80.00,'%',0.00,'%',0.00),(411,339,21,1,80.00,'%',0.00,'%',0.00),(412,340,21,1,80.00,'%',0.00,'%',0.00),(413,341,3,2,8.50,'%',0.00,'%',0.00),(414,342,21,2,80.00,'%',0.00,'%',0.00),(415,343,21,1,80.00,'%',0.00,'%',0.00),(416,344,21,1,80.00,'%',0.00,'%',0.00),(417,345,21,1,80.00,'%',0.00,'%',0.00),(418,346,21,-1,80.00,'%',0.00,'%',0.00),(419,347,21,1,80.00,'%',0.00,'%',0.00),(420,348,21,1,80.00,'%',0.00,'%',0.00),(421,349,21,1,80.00,'%',0.00,'%',0.00),(422,350,21,1,80.00,'%',0.00,'%',0.00),(423,351,21,1,80.00,'%',0.00,'%',0.00),(424,352,21,-1,80.00,'%',0.00,'%',0.00),(425,353,21,1,80.00,'%',0.00,'%',0.00),(426,354,21,-1,80.00,'%',0.00,'%',0.00),(427,362,21,1,80.00,'%',0.00,'%',0.00),(428,362,3,4,8.50,'%',0.00,'%',0.00),(429,363,21,4,80.00,'%',0.00,'%',0.00),(430,364,21,1,80.00,'%',0.00,'%',0.00),(431,364,3,2,8.50,'%',0.00,'%',0.00),(432,365,3,2,8.50,'%',0.00,'%',0.00),(433,366,21,1,80.00,'%',0.00,'%',0.00),(434,367,21,-1,80.00,'%',0.00,'%',0.00),(435,368,21,-1,80.00,'%',0.00,'%',0.00),(436,369,21,1,80.00,'%',0.00,'%',0.00),(437,370,21,-1,80.00,'%',0.00,'%',0.00),(438,371,21,1,80.00,'%',0.00,'%',0.00),(439,372,21,8,80.00,'%',0.00,'%',0.00),(440,373,3,3,8.50,'%',0.00,'%',0.00),(441,373,8,1,5000.00,'%',0.00,'%',0.00),(442,374,21,1,80.00,'%',0.00,'%',0.00),(443,377,20,1,50.00,'%',0.00,'%',0.00),(444,377,21,1,80.00,'%',0.00,'%',0.00),(445,388,21,1,80.00,'%',0.00,'%',0.00),(446,389,21,1,80.00,'%',0.00,'%',0.00),(447,390,21,1,80.00,'%',0.00,'%',0.00),(448,391,21,1,80.00,'%',0.00,'%',0.00),(449,392,21,1,80.00,'%',0.00,'%',0.00),(450,393,21,6,80000.00,'%',0.00,'%',0.00),(451,393,20,3,5000.00,'%',0.00,'%',0.00),(452,394,21,1,80000.00,'%',0.00,'%',0.00),(453,395,21,3,80000.00,'%',0.00,'%',0.00),(454,395,20,3,5000.00,'%',0.00,'%',0.00),(455,396,21,1,80000.00,'%',0.00,'%',0.00),(456,397,21,2,80000.00,'%',0.00,'%',0.00),(457,398,21,1,80000.00,'%',0.00,'%',0.00),(458,398,20,2,5000.00,'%',0.00,'%',0.00),(459,399,21,1,80000.00,'%',0.00,'%',0.00),(460,400,21,-1,80000.00,'%',0.00,'%',0.00),(461,401,21,-1,80000.00,'%',0.00,'%',0.00),(462,402,21,5,8900000.00,'%',0.00,'%',0.00),(463,403,21,1,8900000.00,'%',3000.00,'%',0.00),(464,404,21,1,8900000.00,'%',3000.00,'%',0.00),(465,404,19,1,10000.00,'%',10.00,'%',0.00),(466,405,21,2,8900000.00,'%',3000.00,'%',0.00),(467,405,19,2,10000.00,'%',10.00,'%',0.00),(468,406,3,2,80000.50,'%',0.00,'%',0.00),(469,407,21,1,8900000.00,'%',0.00,'%',0.00),(470,407,19,1,10000.00,'%',10.00,'%',0.00),(471,408,21,1,8900000.00,'%',0.00,'%',0.00),(472,409,3,3,80000.50,'%',0.00,'%',0.00),(473,409,21,1,8900000.00,'%',0.00,'%',0.00),(474,410,19,1,10000.00,'%',10.00,'%',0.00),(475,411,21,1,8900000.00,'%',0.00,'%',0.00),(476,412,21,1,8900000.00,'%',0.00,'%',0.00),(477,413,21,1,8900000.00,'%',0.00,'%',0.00),(478,414,24,1,5000.00,'%',50.00,'%',0.00),(479,415,21,1,8900000.00,'%',0.00,'%',0.00),(480,416,24,1,5000.00,'%',50.00,'%',0.00),(481,416,21,1,8900000.00,'%',0.00,'%',0.00),(482,416,3,2,80000.50,'%',0.00,'%',0.00),(483,416,19,1,10000.00,'%',10.00,'%',0.00),(484,416,20,1,5000.00,'%',4.99,'%',0.00),(485,416,17,1,900.00,'%',0.00,'%',0.00),(486,417,3,3,80000.50,'%',0.00,'%',0.00),(487,419,24,1,5000.00,'%',50.00,'%',0.00),(488,420,24,1,5000.00,'%',50.00,'%',0.00),(489,422,24,1,5000.00,'%',50.00,'%',0.00),(490,423,24,-1,5000.00,'%',50.00,'%',0.00),(491,424,24,1,5000.00,'%',50.00,'%',0.00),(492,425,24,1,5000.00,'%',50.00,'%',0.00),(493,425,21,1,8900000.00,'%',0.00,'%',0.00),(494,425,19,1,10000.00,'%',10.00,'%',0.00),(495,426,24,3,862.70,'%',0.00,'CDF',100.00),(496,426,21,2,952.38,'%',10.00,'%',10.00),(497,426,19,1,1000.00,'%',10.00,'%',0.00),(498,427,24,3,862.70,'%',0.00,'CDF',100.00),(499,427,21,2,952.38,'%',10.00,'%',10.00),(500,427,19,1,1000.00,'%',10.00,'%',0.00),(501,428,24,1,862.70,'%',0.00,'CDF',100.00),(502,428,21,1,952.38,'%',10.00,'%',10.00),(503,428,19,1,1000.00,'%',10.00,'%',0.00),(504,429,24,3,862.70,'%',0.00,'CDF',100.00),(505,429,21,2,952.38,'%',10.00,'%',10.00),(506,430,24,3,862.70,'%',0.00,'CDF',100.00),(507,430,21,2,952.38,'%',10.00,'%',10.00),(508,430,19,1,1000.00,'%',10.00,'%',0.00),(509,431,24,3,862.70,'%',0.00,'CDF',100.00),(510,431,21,2,952.38,'%',10.00,'%',10.00),(511,431,19,1,1000.00,'%',10.00,'%',0.00),(512,432,24,3,862.70,'%',0.00,'CDF',100.00),(513,432,21,2,952.38,'%',10.00,'%',10.00),(514,432,19,1,1000.00,'%',10.00,'%',0.00),(515,433,24,1,862.70,'%',0.00,'CDF',100.00),(516,433,21,2,952.38,'%',10.00,'%',10.00),(517,433,19,1,1000.00,'%',10.00,'%',0.00),(518,434,21,1,952.38,'%',10.00,'%',10.00),(519,435,21,1,952.38,'%',10.00,'%',10.00),(520,436,21,1,952.38,'%',10.00,'%',10.00),(521,437,24,3,862.70,'%',0.00,'CDF',100.00),(522,437,21,2,952.38,'%',10.00,'%',10.00),(523,437,19,1,1000.00,'%',10.00,'%',0.00),(524,438,24,3,862.70,'%',0.00,'CDF',100.00),(525,438,21,2,952.38,'%',10.00,'%',10.00),(526,438,19,1,1000.00,'%',10.00,'%',0.00),(527,439,24,3,862.70,'%',0.00,'CDF',100.00),(528,439,21,2,952.38,'%',10.00,'%',10.00),(529,439,19,1,1000.00,'%',10.00,'%',0.00),(530,440,19,1,1000.00,'%',10.00,'%',0.00),(531,441,21,1,2000.00,'%',10.00,'%',10.00),(532,442,24,1,1000.00,'%',0.00,'CDF',100.00),(533,443,24,2,1000.00,'%',0.00,'CDF',100.00),(534,444,24,1,1000.00,'%',0.00,'CDF',100.00),(535,444,21,1,2000.00,'%',10.00,'%',10.00),(536,445,24,1,1000.00,'%',0.00,'CDF',100.00),(537,445,21,1,2000.00,'%',10.00,'%',10.00),(538,446,24,1,1000.00,'%',0.00,'CDF',100.00),(539,446,21,1,2000.00,'%',10.00,'%',10.00),(540,447,24,1,1000.00,'%',0.00,'CDF',100.00),(541,447,21,1,2000.00,'%',10.00,'CDF',100.00),(542,448,24,1,1000.00,'%',0.00,'CDF',100.00),(543,448,21,1,2000.00,'%',0.00,'CDF',100.00),(544,449,24,1,1000.00,'%',0.00,'CDF',100.00),(545,449,21,1,2000.00,'%',10.00,'CDF',100.00),(546,450,24,1,1000.00,'%',0.00,'CDF',100.00),(547,450,21,1,2000.00,'%',50.00,'CDF',100.00),(548,451,24,1,1000.00,'%',0.00,'CDF',100.00),(549,451,21,1,2000.00,'%',50.00,'CDF',100.00),(550,452,24,1,1000.00,'CDF',100.00,'CDF',100.00),(551,452,21,1,2000.00,'%',50.00,'CDF',100.00),(552,453,24,1,1000.00,'CDF',100.00,'CDF',100.00),(553,453,21,1,2000.00,'%',50.00,'CDF',100.00),(554,454,24,5,1000.00,'CDF',100.00,'CDF',100.00),(555,454,21,5,2000.00,'%',50.00,'CDF',100.00),(556,455,24,1,1000.00,'CDF',100.00,'CDF',100.00),(557,455,21,1,2000.00,'%',50.00,'%',10.00),(558,456,24,1,1000.00,'CDF',100.00,'CDF',100.00),(559,456,21,1,2000.00,'%',50.00,'%',10.00),(560,457,24,1,1000.00,'CDF',100.00,'CDF',100.00),(561,457,21,1,2000.00,'%',50.00,'%',10.00),(562,458,24,1,1000.00,'CDF',100.00,'CDF',100.00),(563,458,21,1,2000.00,'%',50.00,'%',10.00),(564,459,24,1,1000.00,'CDF',100.00,'CDF',100.00),(565,459,21,1,2000.00,'%',50.00,'%',10.00),(566,459,3,2,80000.50,'%',0.00,'%',0.00),(567,459,19,1,1000.00,'%',10.00,'%',20.00),(568,460,24,1,1000.00,'CDF',100.00,'CDF',100.00),(569,460,21,1,2000.00,'%',50.00,'%',10.00),(570,461,19,1,1000.00,'%',10.00,'%',20.00),(571,462,24,1,1000.00,'CDF',100.00,'CDF',100.00),(572,463,21,1,2000.00,'%',50.00,'%',10.00),(573,464,21,1,2000.00,'%',50.00,'%',10.00),(574,465,21,1,2000.00,'%',40.00,'%',10.00),(575,466,21,1,2000.00,'%',40.00,'%',10.00),(576,467,21,1,2000.00,'%',40.00,'%',10.00),(577,468,24,1,1000.00,'CDF',100.00,'CDF',100.00),(578,468,21,1,2000.00,'%',40.00,'%',10.00),(579,469,24,1,1000.00,'CDF',100.00,'CDF',100.00),(580,470,24,1,1000.00,'CDF',100.00,'CDF',100.00),(581,471,24,1,1000.00,'CDF',100.00,'CDF',100.00),(582,471,21,1,2000.00,'%',40.00,'%',10.00),(583,472,24,1,1000.00,'CDF',100.00,'CDF',100.00),(584,472,21,1,2000.00,'%',40.00,'%',10.00),(585,473,24,1,1000.00,'CDF',100.00,'CDF',100.00),(586,474,24,1,1000.00,'CDF',100.00,'%',10.00),(587,475,24,1,1000.00,'CDF',100.00,'%',10.00),(588,476,24,2,1000.00,'CDF',100.00,'%',10.00),(589,476,21,1,2000.00,'%',40.00,'%',10.00),(590,477,24,1,1000.00,'CDF',100.00,'%',10.00),(591,477,21,1,2000.00,'%',40.00,'%',10.00),(592,478,24,1,1000.00,'CDF',100.00,'%',10.00),(593,478,21,1,2000.00,'%',40.00,'%',10.00),(594,479,24,5,1000000.00,'CDF',100.00,'%',10.00),(595,480,24,50,1000000.00,'CDF',100.00,'%',10.00),(596,481,24,1,1000000.00,'CDF',100.00,'%',10.00),(597,481,21,1,2000.00,'%',40.00,'%',10.00),(598,481,19,1,1000.00,'%',10.00,'%',20.00),(599,482,24,1,1000000.00,'CDF',100.00,'%',10.00),(600,482,21,1,2000.00,'%',40.00,'%',10.00),(601,482,19,1,1000.00,'%',10.00,'%',20.00),(602,482,3,1,80000.50,'%',0.00,'%',0.00),(603,483,24,1,1000000.00,'CDF',100.00,'%',10.00),(604,483,21,1,2000.00,'%',40.00,'%',10.00),(605,484,24,2,1000000.00,'CDF',100.00,'%',10.00),(606,485,24,3,1000000.00,'CDF',100.00,'%',10.00),(607,485,21,1,2000.00,'%',40.00,'%',10.00),(608,486,3,2,80000.50,'%',0.00,'%',0.00),(609,487,3,2,80000.50,'%',0.00,'%',0.00),(610,488,3,2,80000.50,'%',0.00,'%',0.00),(611,489,24,1,1000000.00,'CDF',100.00,'%',10.00),(612,490,24,1,1000000.00,'CDF',100.00,'%',10.00),(613,490,21,1,2000.00,'%',40.00,'%',10.00),(614,491,26,1,5000.00,'%',0.00,'%',0.00),(615,492,27,14,10.00,'%',0.00,'%',0.00);
/*!40000 ALTER TABLE `details_vente` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `details_vente_archive`
--

DROP TABLE IF EXISTS `details_vente_archive`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `details_vente_archive` (
  `id` int NOT NULL,
  `vente_id` int NOT NULL,
  `produit_id` int NOT NULL,
  `quantite` int NOT NULL,
  `prix` decimal(10,2) NOT NULL,
  `remise_type` varchar(10) DEFAULT 'percent',
  `remise_value` decimal(10,2) DEFAULT '0.00',
  `taxe_specifique_type` varchar(10) DEFAULT '%',
  `taxe_specifique_value` decimal(10,2) DEFAULT '0.00',
  PRIMARY KEY (`id`),
  KEY `idx_detail_archive_vente` (`vente_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `details_vente_archive`
--

LOCK TABLES `details_vente_archive` WRITE;
/*!40000 ALTER TABLE `details_vente_archive` DISABLE KEYS */;
/*!40000 ALTER TABLE `details_vente_archive` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `login_attempts`
--

DROP TABLE IF EXISTS `login_attempts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `login_attempts` (
  `id` int NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `attempted_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_login_user` (`username`),
  KEY `idx_login_ip` (`ip_address`),
  KEY `idx_login_date` (`attempted_at`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `login_attempts`
--

LOCK TABLES `login_attempts` WRITE;
/*!40000 ALTER TABLE `login_attempts` DISABLE KEYS */;
/*!40000 ALTER TABLE `login_attempts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `notifications`
--

DROP TABLE IF EXISTS `notifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `notifications` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int DEFAULT NULL,
  `shop_id` int DEFAULT NULL,
  `type` enum('stock_low','sale_target','suspicious_action','system') NOT NULL,
  `title` varchar(150) NOT NULL,
  `message` text NOT NULL,
  `link` varchar(255) DEFAULT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT '0',
  `sent_email` tinyint(1) NOT NULL DEFAULT '0',
  `sent_sms` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_notif_user` (`user_id`),
  KEY `idx_notif_shop` (`shop_id`),
  KEY `idx_notif_type` (`type`),
  KEY `idx_notif_read` (`is_read`),
  KEY `idx_notif_date` (`created_at`),
  CONSTRAINT `fk_notif_shop` FOREIGN KEY (`shop_id`) REFERENCES `shops` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_notif_user` FOREIGN KEY (`user_id`) REFERENCES `utilisateurs` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `notifications`
--

LOCK TABLES `notifications` WRITE;
/*!40000 ALTER TABLE `notifications` DISABLE KEYS */;
/*!40000 ALTER TABLE `notifications` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `otp_codes`
--

DROP TABLE IF EXISTS `otp_codes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `otp_codes` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `code` varchar(6) NOT NULL,
  `type` enum('login','password_reset') NOT NULL DEFAULT 'login',
  `channel` enum('email','sms') NOT NULL DEFAULT 'email',
  `expires_at` datetime NOT NULL,
  `used` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_otp_user` (`user_id`),
  KEY `idx_otp_code` (`code`),
  KEY `idx_otp_expires` (`expires_at`),
  CONSTRAINT `fk_otp_user` FOREIGN KEY (`user_id`) REFERENCES `utilisateurs` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=72 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `otp_codes`
--

LOCK TABLES `otp_codes` WRITE;
/*!40000 ALTER TABLE `otp_codes` DISABLE KEYS */;
INSERT INTO `otp_codes` VALUES (1,11,'338670','login','email','2026-07-16 22:12:04',1,'2026-07-17 00:07:04'),(2,11,'860561','login','email','2026-07-16 22:16:36',1,'2026-07-17 00:11:36'),(3,11,'132213','login','email','2026-07-16 22:17:09',1,'2026-07-17 00:12:09'),(4,1,'482754','password_reset','sms','2026-07-16 22:21:14',1,'2026-07-17 00:16:14'),(5,11,'975111','login','email','2026-07-16 22:30:31',1,'2026-07-17 00:25:31'),(6,11,'343243','login','email','2026-07-16 22:30:42',1,'2026-07-17 00:25:42'),(7,11,'949101','login','email','2026-07-16 22:48:31',1,'2026-07-17 00:43:31'),(8,11,'552987','login','email','2026-07-16 23:29:55',1,'2026-07-17 01:24:55'),(9,11,'950142','login','email','2026-07-17 00:00:02',1,'2026-07-17 01:55:02'),(10,11,'947650','login','email','2026-07-17 00:01:04',1,'2026-07-17 01:56:04'),(11,11,'390927','login','email','2026-07-17 00:03:40',1,'2026-07-17 01:58:40'),(12,1,'849056','password_reset','email','2026-07-17 00:11:23',1,'2026-07-17 02:06:23'),(13,11,'192062','login','email','2026-07-17 00:13:23',1,'2026-07-17 02:08:23'),(14,3,'550553','login','email','2026-07-17 10:46:37',1,'2026-07-17 12:41:37'),(15,3,'722301','login','email','2026-07-17 12:53:43',1,'2026-07-17 12:48:43'),(16,3,'832261','login','email','2026-07-17 12:59:43',1,'2026-07-17 12:54:43'),(17,1,'358850','password_reset','sms','2026-07-17 13:12:31',1,'2026-07-17 13:07:31'),(18,1,'277839','login','email','2026-07-17 13:13:52',1,'2026-07-17 13:08:52'),(19,11,'127821','login','email','2026-07-17 13:30:59',1,'2026-07-17 13:25:59'),(20,3,'381620','login','email','2026-07-17 13:54:31',1,'2026-07-17 13:49:31'),(21,3,'316852','login','email','2026-07-19 00:03:01',1,'2026-07-18 23:58:01'),(22,14,'327390','login','email','2026-07-19 00:05:56',1,'2026-07-19 00:00:56'),(23,11,'635120','login','email','2026-07-19 00:31:30',1,'2026-07-19 00:26:30'),(24,3,'905232','login','email','2026-07-19 13:29:10',1,'2026-07-19 13:24:10'),(25,15,'669126','login','email','2026-07-19 13:30:05',1,'2026-07-19 13:25:05'),(26,11,'857413','login','email','2026-07-19 13:30:52',1,'2026-07-19 13:25:52'),(27,14,'440456','login','email','2026-07-19 13:57:46',1,'2026-07-19 13:52:46'),(28,3,'784602','login','email','2026-07-20 14:06:16',1,'2026-07-20 14:01:16'),(29,14,'735695','login','email','2026-07-20 14:07:41',1,'2026-07-20 14:02:41'),(30,11,'632473','login','email','2026-07-20 14:08:30',1,'2026-07-20 14:03:30'),(31,1,'256801','password_reset','email','2026-07-20 14:30:12',1,'2026-07-20 14:25:12'),(32,1,'127647','password_reset','sms','2026-07-20 14:31:38',1,'2026-07-20 14:26:38'),(33,1,'272082','password_reset','email','2026-07-20 14:33:12',1,'2026-07-20 14:28:12'),(34,1,'349257','password_reset','sms','2026-07-20 17:21:07',1,'2026-07-20 17:16:07'),(35,1,'324398','password_reset','sms','2026-07-20 17:26:53',1,'2026-07-20 17:21:53'),(36,1,'674827','password_reset','sms','2026-07-20 17:27:30',1,'2026-07-20 17:22:30'),(37,1,'773516','password_reset','sms','2026-07-20 17:28:15',1,'2026-07-20 17:23:15'),(38,1,'145396','password_reset','sms','2026-07-20 17:48:10',1,'2026-07-20 17:43:10'),(39,1,'663056','password_reset','sms','2026-07-20 17:48:30',1,'2026-07-20 17:43:30'),(40,1,'442421','password_reset','sms','2026-07-20 17:49:54',1,'2026-07-20 17:44:54'),(41,11,'477948','password_reset','sms','2026-07-20 17:50:47',0,'2026-07-20 17:45:47'),(42,1,'174757','password_reset','sms','2026-07-20 17:51:46',1,'2026-07-20 17:46:46'),(43,1,'774113','password_reset','sms','2026-07-20 17:56:35',1,'2026-07-20 17:51:35'),(44,1,'481000','password_reset','sms','2026-07-20 18:01:02',1,'2026-07-20 17:56:02'),(45,3,'362786','login','email','2026-07-20 18:03:07',1,'2026-07-20 17:58:07'),(46,3,'066934','login','email','2026-07-20 18:09:32',1,'2026-07-20 18:04:32'),(47,3,'140927','login','email','2026-07-20 18:09:57',1,'2026-07-20 18:04:57'),(48,3,'032528','login','email','2026-07-20 18:16:03',1,'2026-07-20 18:11:03'),(49,3,'481078','login','email','2026-07-20 18:16:20',1,'2026-07-20 18:11:20'),(50,3,'196948','login','email','2026-07-20 18:17:05',1,'2026-07-20 18:12:05'),(51,3,'631556','login','email','2026-07-20 18:20:07',1,'2026-07-20 18:15:07'),(52,3,'354150','login','sms','2026-07-20 18:26:13',1,'2026-07-20 18:21:13'),(53,4,'030465','password_reset','sms','2026-07-20 18:34:19',1,'2026-07-20 18:29:19'),(54,4,'293126','login','sms','2026-07-20 18:35:28',1,'2026-07-20 18:30:28'),(55,4,'799659','login','sms','2026-07-20 18:37:11',1,'2026-07-20 18:32:11'),(56,11,'267631','login','sms','2026-07-20 19:07:05',1,'2026-07-20 19:02:05'),(57,11,'105343','login','sms','2026-07-20 19:07:16',1,'2026-07-20 19:02:16'),(58,11,'065094','login','sms','2026-07-20 19:07:27',1,'2026-07-20 19:02:27'),(59,11,'512784','login','sms','2026-07-20 19:07:28',1,'2026-07-20 19:02:28'),(60,11,'296322','login','sms','2026-07-20 19:07:28',1,'2026-07-20 19:02:28'),(61,11,'381971','login','sms','2026-07-20 19:07:28',1,'2026-07-20 19:02:28'),(62,11,'412182','login','sms','2026-07-20 19:11:58',1,'2026-07-20 19:06:58'),(63,11,'733560','login','sms','2026-07-20 22:39:40',1,'2026-07-20 22:34:40'),(64,11,'291276','login','sms','2026-07-20 22:39:45',1,'2026-07-20 22:34:45'),(65,11,'296854','login','sms','2026-07-21 10:45:44',1,'2026-07-21 10:40:44'),(66,11,'997379','login','sms','2026-07-21 11:09:03',1,'2026-07-21 11:04:03'),(67,11,'555565','login','sms','2026-07-25 18:55:22',1,'2026-07-25 18:50:22'),(68,11,'888730','login','sms','2026-07-28 12:26:09',1,'2026-07-28 12:21:09'),(69,3,'543441','login','sms','2026-07-28 19:16:59',1,'2026-07-28 19:11:59'),(70,3,'014718','login','sms','2026-07-28 19:17:20',0,'2026-07-28 19:12:20'),(71,1,'393615','login','sms','2026-07-28 19:17:33',0,'2026-07-28 19:12:33');
/*!40000 ALTER TABLE `otp_codes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `password_resets`
--

DROP TABLE IF EXISTS `password_resets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `password_resets` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `token` varchar(64) NOT NULL,
  `channel` enum('email','sms') NOT NULL,
  `expires_at` datetime NOT NULL,
  `used` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_reset_token` (`token`),
  KEY `idx_reset_user` (`user_id`),
  KEY `idx_reset_expires` (`expires_at`),
  CONSTRAINT `fk_reset_user` FOREIGN KEY (`user_id`) REFERENCES `utilisateurs` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `password_resets`
--

LOCK TABLES `password_resets` WRITE;
/*!40000 ALTER TABLE `password_resets` DISABLE KEYS */;
INSERT INTO `password_resets` VALUES (1,1,'377870d90b64423b0be7f12f518368b7ae8829470a793b638c66e6a2ffc954b5','sms','2026-07-16 22:31:14',1,'2026-07-17 00:16:14'),(2,1,'dc36cffad1f91b87d2a1edb470339d9bccccdc16d59e6eb0dda1388862e6557d','email','2026-07-17 00:21:23',1,'2026-07-17 02:06:23'),(3,1,'33b42cd5e79b5a4ae97f0d9d2c7595ec4c1a2bb8bdcc8e960b6a8aba5a3c1194','sms','2026-07-17 11:22:31',1,'2026-07-17 13:07:31'),(4,1,'295291061d7484bcb17b88a97cfff8542606b6c3aee06a8733b6b80470d96e77','email','2026-07-20 12:40:12',1,'2026-07-20 14:25:12'),(5,1,'0d2b3cee8aa8a1524128027604dc0c57956d408ee1e4da9eb96509f4be63d5c2','sms','2026-07-20 12:41:38',1,'2026-07-20 14:26:38'),(6,1,'fc162fff1566f698daf9f39a443b4ccee0572756f1873a6c12342f486ddcfe2b','email','2026-07-20 12:43:12',1,'2026-07-20 14:28:12'),(7,1,'be6b2118f9ce4a5e3671d119ff633a9151c8a9aabc9c943189120e43d9422cf7','sms','2026-07-20 15:31:07',1,'2026-07-20 17:16:07'),(8,1,'db74bda21bfb325d0c8589dc75ed52b965a161955a5031a1baaad70d2faffcfd','sms','2026-07-20 15:36:53',1,'2026-07-20 17:21:53'),(9,1,'9fe4e8b207d52d2225189a1f72359b88a103cc7a68ca7cb1b04e430b780ad2a2','sms','2026-07-20 15:37:30',1,'2026-07-20 17:22:30'),(10,1,'b97f911217fe2be0c59e102de87738a30d57f337918d35a4d0b19821b35bd02f','sms','2026-07-20 15:38:15',1,'2026-07-20 17:23:15'),(11,1,'8d1b3c2094619b805cc9911af2f430d6d7f848a6dc5e2dac65796bf45993dc66','sms','2026-07-20 15:58:10',1,'2026-07-20 17:43:10'),(12,1,'f352f41eabc79c57a8c13968476f60b468aaffe41e08c27ace0b6304fc256372','sms','2026-07-20 15:58:30',1,'2026-07-20 17:43:30'),(13,1,'e7e69212784fcd1bd45206a1531e8b66b52b3bb5a932fa25c21255086f4def20','sms','2026-07-20 15:59:54',1,'2026-07-20 17:44:54'),(14,11,'c35d96f69f2cd2870dbe2a520b608904de08b9cc10153ed2ab53c80f7d6aeef7','sms','2026-07-20 16:00:47',0,'2026-07-20 17:45:47'),(15,1,'8a63c54e0ae464057a0fed6c6628d8eb847e3577464944e74d597ab6d34e0428','sms','2026-07-20 16:01:46',1,'2026-07-20 17:46:46'),(16,1,'385956ef2e4f5b18f3d9e1d89187deecf251ee200499b8137e46043f549ba425','sms','2026-07-20 16:06:35',1,'2026-07-20 17:51:35'),(17,1,'9c5feb70e32843be5a452cb9ea99a9fef6213cd7c374ee793a4e4796d2dde04d','sms','2026-07-20 16:11:02',0,'2026-07-20 17:56:02'),(18,4,'6e60779b5a745f9a2bf70d51f6efacda1feaa629acd3b36a94a4efe9e4aa4a8c','sms','2026-07-20 16:44:19',1,'2026-07-20 18:29:19');
/*!40000 ALTER TABLE `password_resets` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `payroll_absences`
--

DROP TABLE IF EXISTS `payroll_absences`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `payroll_absences` (
  `id` int NOT NULL AUTO_INCREMENT,
  `shop_id` int NOT NULL,
  `employee_id` int NOT NULL,
  `payroll_period_id` int DEFAULT NULL,
  `type` enum('paid_leave','sick','unpaid','unjustified','other') NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `days` decimal(5,2) NOT NULL,
  `is_paid` tinyint(1) NOT NULL DEFAULT '0',
  `notes` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `payroll_absences_employee_dates_idx` (`employee_id`,`start_date`),
  KEY `payroll_absences_period_fk` (`payroll_period_id`),
  CONSTRAINT `payroll_absences_employee_fk` FOREIGN KEY (`employee_id`) REFERENCES `payroll_employees` (`id`) ON DELETE CASCADE,
  CONSTRAINT `payroll_absences_period_fk` FOREIGN KEY (`payroll_period_id`) REFERENCES `payroll_periods` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `payroll_absences`
--

LOCK TABLES `payroll_absences` WRITE;
/*!40000 ALTER TABLE `payroll_absences` DISABLE KEYS */;
INSERT INTO `payroll_absences` VALUES (1,1,4,1,'unjustified','2026-07-28','2026-07-29',4.00,1,NULL,'2026-07-29 23:30:58'),(2,1,4,1,'unjustified','2026-07-28','2026-07-29',4.00,1,NULL,'2026-07-29 23:31:06');
/*!40000 ALTER TABLE `payroll_absences` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `payroll_allowances`
--

DROP TABLE IF EXISTS `payroll_allowances`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `payroll_allowances` (
  `id` int NOT NULL AUTO_INCREMENT,
  `shop_id` int DEFAULT NULL COMMENT 'NULL = valeur globale',
  `code` varchar(30) NOT NULL,
  `label` varchar(120) NOT NULL,
  `calculation_type` enum('fixed','percent_base') NOT NULL DEFAULT 'fixed',
  `amount` decimal(15,2) NOT NULL DEFAULT '0.00',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `payroll_allowances_code_shop_idx` (`code`,`shop_id`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `payroll_allowances`
--

LOCK TABLES `payroll_allowances` WRITE;
/*!40000 ALTER TABLE `payroll_allowances` DISABLE KEYS */;
INSERT INTO `payroll_allowances` VALUES (6,NULL,'TRANSPORT','Prime de transport','fixed',0.00,1,'2026-07-29 22:44:12','2026-07-29 22:44:12'),(7,NULL,'LOGEMENT','Allocation logement','fixed',0.00,1,'2026-07-29 22:44:12','2026-07-29 22:44:12'),(8,NULL,'RISQUE','Prime de risque','fixed',0.00,1,'2026-07-29 22:44:12','2026-07-29 22:44:12'),(9,NULL,'REPAS','Prime de repas','fixed',0.00,1,'2026-07-29 22:44:12','2026-07-29 22:44:12'),(10,NULL,'OUTILLAGE','Prime d\'outillage','fixed',0.00,1,'2026-07-29 22:44:12','2026-07-29 22:44:12');
/*!40000 ALTER TABLE `payroll_allowances` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `payroll_attendance`
--

DROP TABLE IF EXISTS `payroll_attendance`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `payroll_attendance` (
  `id` int NOT NULL AUTO_INCREMENT,
  `shop_id` int NOT NULL,
  `employee_id` int NOT NULL,
  `payroll_period_id` int NOT NULL,
  `worked_days` decimal(5,2) NOT NULL DEFAULT '0.00',
  `worked_hours` decimal(8,2) NOT NULL DEFAULT '0.00',
  `expected_working_days` decimal(5,2) NOT NULL DEFAULT '22.00',
  `paid_days` decimal(5,2) DEFAULT NULL COMMENT 'Jours rémunérés calculés',
  `notes` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `payroll_attendance_employee_period_idx` (`employee_id`,`payroll_period_id`),
  KEY `payroll_attendance_period_fk` (`payroll_period_id`),
  CONSTRAINT `payroll_attendance_employee_fk` FOREIGN KEY (`employee_id`) REFERENCES `payroll_employees` (`id`) ON DELETE CASCADE,
  CONSTRAINT `payroll_attendance_period_fk` FOREIGN KEY (`payroll_period_id`) REFERENCES `payroll_periods` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `payroll_attendance`
--

LOCK TABLES `payroll_attendance` WRITE;
/*!40000 ALTER TABLE `payroll_attendance` DISABLE KEYS */;
INSERT INTO `payroll_attendance` VALUES (1,1,2,1,22.00,176.00,22.00,22.00,'','2026-07-29 22:29:51','2026-07-29 23:22:12');
/*!40000 ALTER TABLE `payroll_attendance` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `payroll_contracts`
--

DROP TABLE IF EXISTS `payroll_contracts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `payroll_contracts` (
  `id` int NOT NULL AUTO_INCREMENT,
  `employee_id` int NOT NULL,
  `type` varchar(50) NOT NULL DEFAULT 'CDI',
  `start_date` date NOT NULL,
  `end_date` date DEFAULT NULL,
  `base_salary` decimal(15,2) NOT NULL,
  `sursalary` decimal(15,2) NOT NULL DEFAULT '0.00',
  `pay_type` enum('monthly','daily','hourly') NOT NULL DEFAULT 'monthly',
  `currency` varchar(10) NOT NULL DEFAULT 'XOF',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `payroll_contracts_employee_idx` (`employee_id`,`is_active`),
  CONSTRAINT `payroll_contracts_employee_fk` FOREIGN KEY (`employee_id`) REFERENCES `payroll_employees` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `payroll_contracts`
--

LOCK TABLES `payroll_contracts` WRITE;
/*!40000 ALTER TABLE `payroll_contracts` DISABLE KEYS */;
INSERT INTO `payroll_contracts` VALUES (1,2,'CDI','2026-07-01',NULL,500000.00,0.00,'monthly','Fc',1,'2026-07-29 22:29:51','2026-07-29 22:29:51');
/*!40000 ALTER TABLE `payroll_contracts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `payroll_contribution_rates`
--

DROP TABLE IF EXISTS `payroll_contribution_rates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `payroll_contribution_rates` (
  `id` int NOT NULL AUTO_INCREMENT,
  `shop_id` int DEFAULT NULL COMMENT 'NULL = valeur globale',
  `code` varchar(30) NOT NULL,
  `label` varchar(120) NOT NULL,
  `employee_rate` decimal(8,4) NOT NULL DEFAULT '0.0000' COMMENT 'Taux salarié %',
  `employer_rate` decimal(8,4) NOT NULL DEFAULT '0.0000' COMMENT 'Taux employeur %',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `payroll_contrib_code_shop_idx` (`code`,`shop_id`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `payroll_contribution_rates`
--

LOCK TABLES `payroll_contribution_rates` WRITE;
/*!40000 ALTER TABLE `payroll_contribution_rates` DISABLE KEYS */;
INSERT INTO `payroll_contribution_rates` VALUES (5,NULL,'CNSS','Caisse nationale de sécurité sociale',0.0000,0.0000,1,'2026-07-29 22:44:12','2026-07-29 22:44:12'),(6,NULL,'INPP','Institut national de préparation professionnelle',0.0000,0.0000,1,'2026-07-29 22:44:12','2026-07-29 22:44:12'),(7,NULL,'ONEM','Office national de l\'emploi',0.0000,0.0000,1,'2026-07-29 22:44:12','2026-07-29 22:44:12'),(8,NULL,'INSS','Institut national de sécurité sociale',0.0000,0.0000,1,'2026-07-29 22:44:12','2026-07-29 22:44:12');
/*!40000 ALTER TABLE `payroll_contribution_rates` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `payroll_deductions`
--

DROP TABLE IF EXISTS `payroll_deductions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `payroll_deductions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `shop_id` int DEFAULT NULL COMMENT 'NULL = valeur globale',
  `code` varchar(30) NOT NULL,
  `label` varchar(120) NOT NULL,
  `calculation_type` enum('fixed','percent_gross') NOT NULL DEFAULT 'fixed',
  `amount` decimal(15,2) NOT NULL DEFAULT '0.00',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `payroll_deductions_code_shop_idx` (`code`,`shop_id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `payroll_deductions`
--

LOCK TABLES `payroll_deductions` WRITE;
/*!40000 ALTER TABLE `payroll_deductions` DISABLE KEYS */;
INSERT INTO `payroll_deductions` VALUES (4,NULL,'AVANCE','Avance sur salaire','fixed',0.00,1,'2026-07-29 22:44:12','2026-07-29 22:44:12'),(5,NULL,'PRET','Prêt entreprise','fixed',0.00,1,'2026-07-29 22:44:12','2026-07-29 22:44:12'),(6,NULL,'ABSENCE','Absence non payée','fixed',0.00,1,'2026-07-29 22:44:12','2026-07-29 22:44:12');
/*!40000 ALTER TABLE `payroll_deductions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `payroll_departments`
--

DROP TABLE IF EXISTS `payroll_departments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `payroll_departments` (
  `id` int NOT NULL AUTO_INCREMENT,
  `shop_id` int DEFAULT NULL COMMENT 'NULL = valeur globale',
  `name` varchar(120) NOT NULL,
  `code` varchar(30) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `payroll_departments_name_shop_idx` (`name`,`shop_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `payroll_departments`
--

LOCK TABLES `payroll_departments` WRITE;
/*!40000 ALTER TABLE `payroll_departments` DISABLE KEYS */;
/*!40000 ALTER TABLE `payroll_departments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `payroll_employees`
--

DROP TABLE IF EXISTS `payroll_employees`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `payroll_employees` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL COMMENT 'FK vers utilisateurs.id',
  `shop_id` int NOT NULL COMMENT 'Boutique de rattachement',
  `matricule` varchar(50) NOT NULL,
  `device_user_id` varchar(50) DEFAULT NULL COMMENT 'ID pointeuse biométrique',
  `hire_date` date NOT NULL,
  `iban` varchar(50) DEFAULT NULL,
  `cnss_number` varchar(50) DEFAULT NULL,
  `direction` varchar(120) DEFAULT NULL,
  `job_title` varchar(120) DEFAULT NULL,
  `sitaf` tinyint unsigned NOT NULL DEFAULT '0',
  `tax_dependents` tinyint unsigned NOT NULL DEFAULT '0',
  `cat_leg` varchar(50) DEFAULT NULL,
  `cat_prof` varchar(50) DEFAULT NULL,
  `ier_rate` decimal(8,4) NOT NULL DEFAULT '0.0000' COMMENT 'Taux IER employeur %',
  `department_id` int DEFAULT NULL,
  `job_category_id` int DEFAULT NULL,
  `status` enum('active','suspended','left') NOT NULL DEFAULT 'active',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `payroll_employees_matricule_shop_idx` (`matricule`,`shop_id`),
  UNIQUE KEY `payroll_employees_user_idx` (`user_id`),
  UNIQUE KEY `payroll_employees_device_shop_idx` (`device_user_id`,`shop_id`),
  KEY `payroll_employees_status_hire_idx` (`status`,`hire_date`),
  KEY `payroll_employees_shop_fk` (`shop_id`),
  KEY `payroll_employees_department_fk` (`department_id`),
  KEY `payroll_employees_job_category_fk` (`job_category_id`),
  CONSTRAINT `payroll_employees_department_fk` FOREIGN KEY (`department_id`) REFERENCES `payroll_departments` (`id`) ON DELETE SET NULL,
  CONSTRAINT `payroll_employees_job_category_fk` FOREIGN KEY (`job_category_id`) REFERENCES `payroll_job_categories` (`id`) ON DELETE SET NULL,
  CONSTRAINT `payroll_employees_shop_fk` FOREIGN KEY (`shop_id`) REFERENCES `shops` (`id`) ON DELETE CASCADE,
  CONSTRAINT `payroll_employees_user_fk` FOREIGN KEY (`user_id`) REFERENCES `utilisateurs` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `payroll_employees`
--

LOCK TABLES `payroll_employees` WRITE;
/*!40000 ALTER TABLE `payroll_employees` DISABLE KEYS */;
INSERT INTO `payroll_employees` VALUES (1,15,2,'100100',NULL,'2026-07-29',NULL,NULL,NULL,NULL,0,0,NULL,NULL,0.0000,NULL,NULL,'active','2026-07-29 15:26:12','2026-07-29 15:26:12'),(2,13,1,'1100011',NULL,'2026-07-29',NULL,NULL,'','',0,0,NULL,NULL,0.0000,NULL,NULL,'active','2026-07-29 18:15:49','2026-07-29 23:20:53'),(3,1,1,'AG001',NULL,'2026-07-29',NULL,NULL,NULL,NULL,0,0,NULL,NULL,0.0000,NULL,NULL,'active','2026-07-29 22:13:43','2026-07-29 22:13:43'),(4,6,1,'EMP-6',NULL,'2026-07-29',NULL,NULL,'','',0,0,NULL,NULL,0.0000,NULL,NULL,'active','2026-07-29 22:13:43','2026-07-29 23:29:09'),(5,7,1,'AG004',NULL,'2026-07-29',NULL,NULL,NULL,NULL,0,0,NULL,NULL,0.0000,NULL,NULL,'active','2026-07-29 22:13:43','2026-07-29 22:13:43'),(6,8,1,'EMP-8',NULL,'2026-07-29',NULL,NULL,NULL,NULL,0,0,NULL,NULL,0.0000,NULL,NULL,'active','2026-07-29 22:13:43','2026-07-29 22:13:43');
/*!40000 ALTER TABLE `payroll_employees` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `payroll_job_categories`
--

DROP TABLE IF EXISTS `payroll_job_categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `payroll_job_categories` (
  `id` int NOT NULL AUTO_INCREMENT,
  `shop_id` int DEFAULT NULL COMMENT 'NULL = valeur globale',
  `name` varchar(120) NOT NULL,
  `code` varchar(30) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `payroll_job_cat_name_shop_idx` (`name`,`shop_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `payroll_job_categories`
--

LOCK TABLES `payroll_job_categories` WRITE;
/*!40000 ALTER TABLE `payroll_job_categories` DISABLE KEYS */;
/*!40000 ALTER TABLE `payroll_job_categories` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `payroll_overtimes`
--

DROP TABLE IF EXISTS `payroll_overtimes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `payroll_overtimes` (
  `id` int NOT NULL AUTO_INCREMENT,
  `shop_id` int NOT NULL,
  `employee_id` int NOT NULL,
  `payroll_period_id` int NOT NULL,
  `work_date` date NOT NULL,
  `hours` decimal(6,2) NOT NULL,
  `rate_type` enum('normal_25','night_50','holiday_100','custom') NOT NULL DEFAULT 'normal_25',
  `multiplier` decimal(5,2) NOT NULL DEFAULT '1.25',
  `amount` decimal(15,2) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `payroll_overtimes_employee_period_idx` (`employee_id`,`payroll_period_id`),
  KEY `payroll_overtimes_period_fk` (`payroll_period_id`),
  CONSTRAINT `payroll_overtimes_employee_fk` FOREIGN KEY (`employee_id`) REFERENCES `payroll_employees` (`id`) ON DELETE CASCADE,
  CONSTRAINT `payroll_overtimes_period_fk` FOREIGN KEY (`payroll_period_id`) REFERENCES `payroll_periods` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `payroll_overtimes`
--

LOCK TABLES `payroll_overtimes` WRITE;
/*!40000 ALTER TABLE `payroll_overtimes` DISABLE KEYS */;
INSERT INTO `payroll_overtimes` VALUES (1,1,2,1,'2026-07-15',2.00,'normal_25',1.25,NULL,'2026-07-29 22:29:51');
/*!40000 ALTER TABLE `payroll_overtimes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `payroll_payment_methods`
--

DROP TABLE IF EXISTS `payroll_payment_methods`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `payroll_payment_methods` (
  `id` int NOT NULL AUTO_INCREMENT,
  `shop_id` int DEFAULT NULL COMMENT 'NULL = valeur globale',
  `code` varchar(30) NOT NULL,
  `label` varchar(100) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `payroll_pay_methods_code_shop_idx` (`code`,`shop_id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `payroll_payment_methods`
--

LOCK TABLES `payroll_payment_methods` WRITE;
/*!40000 ALTER TABLE `payroll_payment_methods` DISABLE KEYS */;
INSERT INTO `payroll_payment_methods` VALUES (1,1,'CASH','Espèces',1,'2026-07-29 22:29:51');
/*!40000 ALTER TABLE `payroll_payment_methods` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `payroll_payments`
--

DROP TABLE IF EXISTS `payroll_payments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `payroll_payments` (
  `id` int NOT NULL AUTO_INCREMENT,
  `shop_id` int NOT NULL,
  `payslip_id` int NOT NULL,
  `payment_method_id` int DEFAULT NULL,
  `amount` decimal(15,2) NOT NULL,
  `paid_at` date NOT NULL,
  `reference` varchar(120) DEFAULT NULL,
  `status` enum('pending','paid','cancelled') NOT NULL DEFAULT 'pending',
  `created_by` int DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `payroll_payments_payslip_idx` (`payslip_id`),
  KEY `payroll_payments_method_fk` (`payment_method_id`),
  KEY `payroll_payments_user_fk` (`created_by`),
  CONSTRAINT `payroll_payments_method_fk` FOREIGN KEY (`payment_method_id`) REFERENCES `payroll_payment_methods` (`id`) ON DELETE SET NULL,
  CONSTRAINT `payroll_payments_payslip_fk` FOREIGN KEY (`payslip_id`) REFERENCES `payroll_payslips` (`id`) ON DELETE CASCADE,
  CONSTRAINT `payroll_payments_user_fk` FOREIGN KEY (`created_by`) REFERENCES `utilisateurs` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `payroll_payments`
--

LOCK TABLES `payroll_payments` WRITE;
/*!40000 ALTER TABLE `payroll_payments` DISABLE KEYS */;
INSERT INTO `payroll_payments` VALUES (1,1,1,1,507102.27,'2026-07-29','PAY-001','paid',1,'2026-07-29 22:46:51'),(2,1,1,NULL,507102.27,'2026-07-29','001','paid',11,'2026-07-29 23:27:56');
/*!40000 ALTER TABLE `payroll_payments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `payroll_payslip_lines`
--

DROP TABLE IF EXISTS `payroll_payslip_lines`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `payroll_payslip_lines` (
  `id` int NOT NULL AUTO_INCREMENT,
  `payslip_id` int NOT NULL,
  `code` varchar(30) NOT NULL,
  `label` varchar(190) NOT NULL,
  `type` enum('earning','deduction','employer') NOT NULL,
  `quantity` decimal(10,2) DEFAULT NULL,
  `rate` decimal(15,4) DEFAULT NULL,
  `amount` decimal(15,2) NOT NULL,
  `sort_order` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `payroll_payslip_lines_payslip_idx` (`payslip_id`),
  CONSTRAINT `payroll_payslip_lines_payslip_fk` FOREIGN KEY (`payslip_id`) REFERENCES `payroll_payslips` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=159 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `payroll_payslip_lines`
--

LOCK TABLES `payroll_payslip_lines` WRITE;
/*!40000 ALTER TABLE `payroll_payslip_lines` DISABLE KEYS */;
INSERT INTO `payroll_payslip_lines` VALUES (138,1,'BASE','Salaire de base','earning',22.00,22727.2727,500000.00,0),(139,1,'SURSAL','Sursalaire','earning',NULL,NULL,0.00,50),(140,1,'LOGEMENT','Allocation logement','earning',NULL,0.0000,0.00,200),(141,1,'OUTILLAGE','Prime d\'outillage','earning',NULL,0.0000,0.00,200),(142,1,'REPAS','Prime de repas','earning',NULL,0.0000,0.00,200),(143,1,'RISQUE','Prime de risque','earning',NULL,0.0000,0.00,200),(144,1,'TRANSPORT','Prime de transport','earning',NULL,0.0000,0.00,200),(145,1,'ANCIEN','Ancienneté (0.0054794520547945 ans)','earning',0.01,0.0000,0.00,300),(146,1,'HS','Heures supplémentaires','earning',NULL,NULL,7102.27,400),(147,1,'ABSENCE','Absence non payée','deduction',NULL,0.0000,0.00,500),(148,1,'AVANCE','Avance sur salaire','deduction',NULL,0.0000,0.00,500),(149,1,'PRET','Prêt entreprise','deduction',NULL,0.0000,0.00,500),(150,1,'CNSS_SAL','Caisse nationale de sécurité sociale (salarié)','deduction',NULL,0.0000,0.00,600),(151,1,'CNSS_PAT','Caisse nationale de sécurité sociale (employeur)','employer',NULL,0.0000,0.00,700),(152,1,'INPP_SAL','Institut national de préparation professionnelle (salarié)','deduction',NULL,0.0000,0.00,600),(153,1,'INPP_PAT','Institut national de préparation professionnelle (employeur)','employer',NULL,0.0000,0.00,700),(154,1,'INSS_SAL','Institut national de sécurité sociale (salarié)','deduction',NULL,0.0000,0.00,600),(155,1,'INSS_PAT','Institut national de sécurité sociale (employeur)','employer',NULL,0.0000,0.00,700),(156,1,'ONEM_SAL','Office national de l\'emploi (salarié)','deduction',NULL,0.0000,0.00,600),(157,1,'ONEM_PAT','Office national de l\'emploi (employeur)','employer',NULL,0.0000,0.00,700),(158,1,'IER','IER employeur','employer',NULL,0.0000,0.00,900);
/*!40000 ALTER TABLE `payroll_payslip_lines` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `payroll_payslips`
--

DROP TABLE IF EXISTS `payroll_payslips`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `payroll_payslips` (
  `id` int NOT NULL AUTO_INCREMENT,
  `shop_id` int NOT NULL,
  `employee_id` int NOT NULL,
  `payroll_period_id` int NOT NULL,
  `gross_amount` decimal(15,2) NOT NULL DEFAULT '0.00',
  `taxable_amount` decimal(15,2) NOT NULL DEFAULT '0.00',
  `cnss_base` decimal(15,2) NOT NULL DEFAULT '0.00',
  `total_deductions` decimal(15,2) NOT NULL DEFAULT '0.00',
  `employer_charges` decimal(15,2) NOT NULL DEFAULT '0.00',
  `employer_cost` decimal(15,2) NOT NULL DEFAULT '0.00',
  `net_amount` decimal(15,2) NOT NULL DEFAULT '0.00',
  `status` enum('draft','calculated','validated','paid') NOT NULL DEFAULT 'draft',
  `pdf_path` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `payroll_payslips_employee_period_idx` (`employee_id`,`payroll_period_id`),
  KEY `payroll_payslips_period_fk` (`payroll_period_id`),
  CONSTRAINT `payroll_payslips_employee_fk` FOREIGN KEY (`employee_id`) REFERENCES `payroll_employees` (`id`) ON DELETE CASCADE,
  CONSTRAINT `payroll_payslips_period_fk` FOREIGN KEY (`payroll_period_id`) REFERENCES `payroll_periods` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `payroll_payslips`
--

LOCK TABLES `payroll_payslips` WRITE;
/*!40000 ALTER TABLE `payroll_payslips` DISABLE KEYS */;
INSERT INTO `payroll_payslips` VALUES (1,1,2,1,507102.27,507102.27,507102.27,0.00,0.00,507102.27,507102.27,'calculated','C:\\laragon\\www\\Php_Pure\\pos_systeme\\storage/payslips/1/2026/07/bulletin_1_1100011.pdf','2026-07-29 22:45:49','2026-07-31 14:37:41');
/*!40000 ALTER TABLE `payroll_payslips` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `payroll_periods`
--

DROP TABLE IF EXISTS `payroll_periods`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `payroll_periods` (
  `id` int NOT NULL AUTO_INCREMENT,
  `shop_id` int NOT NULL,
  `month` tinyint unsigned NOT NULL,
  `year` smallint unsigned NOT NULL,
  `working_days` decimal(5,2) NOT NULL DEFAULT '22.00',
  `status` enum('draft','calculated','validated','paid','closed') NOT NULL DEFAULT 'draft',
  `calculated_at` datetime DEFAULT NULL,
  `validated_at` datetime DEFAULT NULL,
  `closed_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `payroll_periods_month_year_shop_idx` (`year`,`month`,`shop_id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `payroll_periods`
--

LOCK TABLES `payroll_periods` WRITE;
/*!40000 ALTER TABLE `payroll_periods` DISABLE KEYS */;
INSERT INTO `payroll_periods` VALUES (1,1,7,2026,22.00,'calculated','2026-07-31 14:37:41','2026-07-29 22:52:05','2026-07-29 23:27:56','2026-07-29 22:29:51'),(3,1,6,2026,30.00,'draft',NULL,NULL,NULL,'2026-07-30 17:20:52');
/*!40000 ALTER TABLE `payroll_periods` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `payroll_seniority_bands`
--

DROP TABLE IF EXISTS `payroll_seniority_bands`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `payroll_seniority_bands` (
  `id` int NOT NULL AUTO_INCREMENT,
  `shop_id` int DEFAULT NULL COMMENT 'NULL = valeur globale',
  `min_years` decimal(5,2) NOT NULL,
  `max_years` decimal(5,2) DEFAULT NULL COMMENT 'NULL = pas de plafond',
  `percent` decimal(8,4) NOT NULL,
  `label` varchar(120) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `payroll_seniority_bands`
--

LOCK TABLES `payroll_seniority_bands` WRITE;
/*!40000 ALTER TABLE `payroll_seniority_bands` DISABLE KEYS */;
INSERT INTO `payroll_seniority_bands` VALUES (1,NULL,0.00,2.00,0.0000,'0 à 2 ans','2026-07-29 22:22:48'),(2,NULL,2.00,5.00,0.0000,'2 à 5 ans','2026-07-29 22:22:48'),(3,NULL,5.00,10.00,0.0000,'5 à 10 ans','2026-07-29 22:22:48'),(4,NULL,10.00,15.00,0.0000,'10 à 15 ans','2026-07-29 22:22:48'),(5,NULL,15.00,NULL,0.0000,'15 ans et +','2026-07-29 22:22:48');
/*!40000 ALTER TABLE `payroll_seniority_bands` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `payroll_time_clock_events`
--

DROP TABLE IF EXISTS `payroll_time_clock_events`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `payroll_time_clock_events` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `shop_id` int NOT NULL,
  `employee_id` int NOT NULL,
  `event_type` enum('IN','OUT','BREAK_START','BREAK_END','UNKNOWN') NOT NULL DEFAULT 'UNKNOWN',
  `event_at` datetime NOT NULL,
  `source` enum('manual','device_usb','device_network','web','import_csv') NOT NULL DEFAULT 'manual',
  `verify_mode` enum('FP','CARD','PIN','OTHER') DEFAULT NULL,
  `device_sn` varchar(100) DEFAULT NULL,
  `latitude` decimal(10,7) DEFAULT NULL,
  `longitude` decimal(10,7) DEFAULT NULL,
  `in_zone` tinyint(1) DEFAULT NULL,
  `identity_verified` tinyint(1) NOT NULL DEFAULT '0',
  `validated` tinyint(1) NOT NULL DEFAULT '1',
  `import_batch_id` int DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `payroll_time_clock_dedup_unique` (`employee_id`,`event_at`,`event_type`),
  KEY `payroll_time_clock_employee_event_idx` (`employee_id`,`event_at`),
  KEY `payroll_time_clock_import_fk` (`import_batch_id`),
  CONSTRAINT `payroll_time_clock_employee_fk` FOREIGN KEY (`employee_id`) REFERENCES `payroll_employees` (`id`) ON DELETE CASCADE,
  CONSTRAINT `payroll_time_clock_import_fk` FOREIGN KEY (`import_batch_id`) REFERENCES `payroll_time_clock_imports` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `payroll_time_clock_events`
--

LOCK TABLES `payroll_time_clock_events` WRITE;
/*!40000 ALTER TABLE `payroll_time_clock_events` DISABLE KEYS */;
/*!40000 ALTER TABLE `payroll_time_clock_events` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `payroll_time_clock_imports`
--

DROP TABLE IF EXISTS `payroll_time_clock_imports`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `payroll_time_clock_imports` (
  `id` int NOT NULL AUTO_INCREMENT,
  `shop_id` int NOT NULL,
  `filename` varchar(255) NOT NULL,
  `imported_by` int DEFAULT NULL,
  `rows_total` int unsigned NOT NULL DEFAULT '0',
  `rows_ok` int unsigned NOT NULL DEFAULT '0',
  `rows_skipped` int unsigned NOT NULL DEFAULT '0',
  `rows_error` int unsigned NOT NULL DEFAULT '0',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `payroll_time_clock_imports_user_fk` (`imported_by`),
  CONSTRAINT `payroll_time_clock_imports_user_fk` FOREIGN KEY (`imported_by`) REFERENCES `utilisateurs` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `payroll_time_clock_imports`
--

LOCK TABLES `payroll_time_clock_imports` WRITE;
/*!40000 ALTER TABLE `payroll_time_clock_imports` DISABLE KEYS */;
/*!40000 ALTER TABLE `payroll_time_clock_imports` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `product_batches`
--

DROP TABLE IF EXISTS `product_batches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `product_batches` (
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
) ENGINE=InnoDB AUTO_INCREMENT=41 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `product_batches`
--

LOCK TABLES `product_batches` WRITE;
/*!40000 ALTER TABLE `product_batches` DISABLE KEYS */;
INSERT INTO `product_batches` VALUES (1,3,NULL,66.7987,NULL,'2026-07-21','2026-07-21 12:52:57'),(2,6,NULL,75,NULL,'2026-07-21','2026-07-21 12:52:57'),(3,8,NULL,7,NULL,'2026-07-21','2026-07-21 12:52:57'),(4,11,NULL,36,NULL,'2026-07-21','2026-07-21 12:52:57'),(5,17,NULL,98,NULL,'2026-07-21','2026-07-21 12:52:57'),(6,19,NULL,88,NULL,'2026-07-21','2026-07-21 12:52:57'),(7,20,NULL,63,NULL,'2026-07-21','2026-07-21 12:52:57'),(8,21,NULL,962,NULL,'2026-07-21','2026-07-21 12:52:57'),(9,22,NULL,76,NULL,'2026-07-21','2026-07-21 12:52:57'),(22,24,'lot-001',5,'2026-07-31','2026-07-21','2026-07-21 14:31:37'),(23,25,'lot-001',50,NULL,'2026-07-21','2026-07-21 14:33:10'),(24,25,'lot-002',2,'2026-07-22','2026-07-21','2026-07-21 14:33:47'),(25,26,NULL,9,'2026-07-30','2026-07-25','2026-07-25 17:51:51'),(27,26,'lot-002',50,'2026-08-09','2026-07-25','2026-07-25 17:53:22'),(28,27,NULL,71,'2026-07-31','2026-07-28','2026-07-28 17:18:09'),(29,27,'lot-mi aout',85,'2026-08-07','2026-07-28','2026-07-28 17:18:54'),(30,28,NULL,100,NULL,'2026-07-28','2026-07-28 21:08:35'),(31,32,NULL,100,NULL,'2026-07-28','2026-07-28 21:08:48'),(32,29,NULL,100,NULL,'2026-07-28','2026-07-28 21:09:05'),(33,33,NULL,100,NULL,'2026-07-28','2026-07-28 21:09:17'),(34,34,NULL,100,NULL,'2026-07-28','2026-07-28 21:09:29'),(35,30,NULL,100,NULL,'2026-07-28','2026-07-28 21:09:42'),(36,35,NULL,100,NULL,'2026-07-28','2026-07-28 21:09:58'),(37,31,NULL,100,NULL,'2026-07-28','2026-07-28 21:10:08'),(38,36,NULL,100,NULL,'2026-07-29','2026-07-29 10:05:31'),(39,37,NULL,100,NULL,'2026-07-29','2026-07-29 10:06:29'),(40,38,NULL,100,NULL,'2026-07-29','2026-07-29 10:09:14');
/*!40000 ALTER TABLE `product_batches` ENABLE KEYS */;
UNLOCK TABLES;

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
  `shop_id` int DEFAULT NULL,
  `prix` decimal(10,2) NOT NULL,
  `stock` float NOT NULL DEFAULT '0',
  `stock_minimum` float NOT NULL DEFAULT '0',
  `image` varchar(255) DEFAULT NULL,
  `taxe_id` int DEFAULT '1',
  `product_type` varchar(20) DEFAULT 'unite' COMMENT 'Type de vente: unite (à l''unité) ou poids (au kilo/gramme)',
  `prod_service` enum('BIE','SER','TAX') DEFAULT NULL,
  `remise_type` varchar(10) DEFAULT 'percent',
  `remise_value` decimal(10,2) DEFAULT '0.00',
  `taxe_specifique_type` varchar(10) DEFAULT '%',
  `taxe_specifique_value` decimal(10,2) DEFAULT '0.00',
  `date_expiration` date DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code_barres` (`code_barres`),
  KEY `category_id` (`category_id`),
  KEY `idx_produits_product_type` (`product_type`),
  KEY `idx_prod_shop` (`shop_id`),
  CONSTRAINT `fk_prod_shop` FOREIGN KEY (`shop_id`) REFERENCES `shops` (`id`) ON DELETE CASCADE,
  CONSTRAINT `produits_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=39 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `produits`
--

LOCK TABLES `produits` WRITE;
/*!40000 ALTER TABLE `produits` DISABLE KEYS */;
INSERT INTO `produits` VALUES (3,'051111407591','Lait Frais 1L',1,1,80000.50,66.7987,15,'https://images.unsplash.com/photo-1563636619-e9143da7973b?w=200',2,'coupe','BIE','%',0.00,'%',0.00,NULL),(6,'379961440160','the good things',4,1,50.00,75,5,'assets/img/products/the_good_things_1777049124.jpg',1,'unite','BIE','%',0.00,'%',0.00,NULL),(8,'908680352130','vitalo',1,1,5000.00,7,1,'',1,'unite','SER','%',0.00,'%',0.00,NULL),(11,'910147686092','thé',4,1,800.00,36,2,'',6,'unite',NULL,'%',0.00,'%',0.00,NULL),(17,'912582452121','test2',4,1,900.00,98,1,'',1,'unite','BIE','%',0.00,'%',0.00,NULL),(19,'965589898103','randy',4,1,1000.00,88,1,'',14,'unite','SER','%',10.00,'%',20.00,NULL),(20,'559646266015','test 1',3,1,5000.00,63,5,'',1,'unite','BIE','%',4.99,'%',0.00,NULL),(21,'051111407592','ceoe',1,1,2000.00,962,3,'',3,'unite','BIE','%',40.00,'%',10.00,NULL),(22,'560582660850','tetggg',3,1,0.01,76,5,'',1,'unite','BIE','%',0.00,'%',0.00,NULL),(23,'922355516734','test',1,1,50.00,0,4,'',1,'unite',NULL,'%',0.00,'%',0.00,NULL),(24,'806292503321','allohhh (test)',3,1,1000000.00,5,5,'',8,'unite','BIE','CDF',100.00,'%',10.00,NULL),(25,'443375897847','XXL',1,1,50.00,52,10,'',3,'unite',NULL,'%',0.00,'%',0.00,NULL),(26,'018730396877','test produit',1,1,5000.00,59,20,'',4,'unite','SER','%',0.00,'%',0.00,'2026-07-30'),(27,'590541465536','ppp',3,1,10.00,156,5,'',3,'unite','BIE','%',0.00,'%',0.00,'2026-07-31'),(28,'712954028012','americano',6,2,5000.00,100,1,'assets/img/products/americano_1785271398.png',2,'unite',NULL,'CDF',5.00,'%',15.00,NULL),(29,'714296792945','bavaria',6,2,5000.00,100,1,'assets/img/products/bavaria_1785271457.jpeg',2,'unite',NULL,'%',0.00,'%',0.00,NULL),(30,'715599445040','Jack Daniel',6,2,4500.00,100,1,'assets/img/products/jack_daniel_039_s_1785271584.jpeg',2,'unite',NULL,'%',0.00,'%',0.00,NULL),(31,'716683712498','Vodka ',6,2,6500.00,100,1,'assets/img/products/vodka_1785271732.jpeg',2,'unite',NULL,'%',0.00,'%',0.00,NULL),(32,'717659064698','Baileys',6,2,4500.00,100,1,'assets/img/products/baileys_1785271785.jpeg',2,'unite',NULL,'%',0.00,'%',0.00,NULL),(33,'718325036511','Black Label',6,2,3500.00,100,1,'assets/img/products/black_label_1785271852.jpeg',2,'unite',NULL,'%',0.00,'%',0.00,NULL),(34,'718675903303','castel',6,2,3500.00,100,1,'assets/img/products/castel_1785271905.jpeg',2,'unite',NULL,'%',0.00,'%',0.00,NULL),(35,'719640001389','Red Label',6,2,4500.00,100,1,'assets/img/products/red_label_1785271984.jpeg',2,'unite',NULL,'%',0.00,'%',0.00,NULL),(36,'194972055109','beaufort',6,2,2500.00,100,1,'assets/img/products/beaufort_1785319531.jpeg',2,'unite',NULL,'%',0.00,'%',0.00,NULL),(37,'195641186995','double Label',6,2,8500.00,100,1,'assets/img/products/double_label_1785319589.jpeg',2,'unite',NULL,'%',0.00,'%',0.00,NULL),(38,'197281560345','Hennessy ',6,2,6500.00,100,1,'assets/img/products/hennessy_1785319754.jpeg',2,'unite',NULL,'%',0.00,'%',0.00,NULL);
/*!40000 ALTER TABLE `produits` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `service_providers`
--

DROP TABLE IF EXISTS `service_providers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `service_providers` (
  `id` int NOT NULL AUTO_INCREMENT,
  `code` varchar(20) NOT NULL COMMENT 'Code: SNEL, REGIDESO',
  `nom` varchar(100) NOT NULL,
  `type_service` enum('electricity','water') NOT NULL COMMENT 'electricity ou water',
  `api_endpoint` varchar(255) DEFAULT NULL COMMENT 'URL API',
  `api_key` varchar(255) DEFAULT NULL COMMENT 'Clé API',
  `actif` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `service_providers`
--

LOCK TABLES `service_providers` WRITE;
/*!40000 ALTER TABLE `service_providers` DISABLE KEYS */;
INSERT INTO `service_providers` VALUES (1,'SNEL','Société Nationale d\'Electricité','electricity',NULL,NULL,1,'2026-05-11 18:06:41'),(2,'REGIDESO','Régie de Distribution d\'Eau','water',NULL,NULL,1,'2026-05-11 18:06:41');
/*!40000 ALTER TABLE `service_providers` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `service_types`
--

DROP TABLE IF EXISTS `service_types`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `service_types` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_service_name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `service_types`
--

LOCK TABLES `service_types` WRITE;
/*!40000 ALTER TABLE `service_types` DISABLE KEYS */;
INSERT INTO `service_types` VALUES (1,'Caisse','2026-07-19 13:43:50','2026-07-19 13:43:50'),(2,'Quincaillerie','2026-07-19 13:43:50','2026-07-19 13:43:50'),(3,'Restaurant','2026-07-19 13:43:50','2026-07-19 13:43:50'),(4,'Bar','2026-07-19 13:43:50','2026-07-29 12:07:58'),(5,'Livraison','2026-07-19 13:43:50','2026-07-19 13:43:50'),(6,'Bijouterie','2026-07-19 13:43:50','2026-07-19 13:43:50'),(7,'Coiffure','2026-07-19 13:43:50','2026-07-19 13:43:50');
/*!40000 ALTER TABLE `service_types` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `settings`
--

DROP TABLE IF EXISTS `settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `settings` (
  `id` int NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(100) NOT NULL,
  `shop_id` int DEFAULT NULL,
  `value` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_setting_shop` (`setting_key`,`shop_id`),
  KEY `idx_setting_shop` (`shop_id`),
  CONSTRAINT `fk_setting_shop` FOREIGN KEY (`shop_id`) REFERENCES `shops` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=1834 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `settings`
--

LOCK TABLES `settings` WRITE;
/*!40000 ALTER TABLE `settings` DISABLE KEYS */;
INSERT INTO `settings` VALUES (5,'tax_rate',1,'16','2026-04-25 11:14:06','2026-07-16 21:59:36'),(9,'theme',1,'red','2026-05-25 14:55:57','2026-07-28 15:34:20'),(510,'paper_type',1,'57mm','2026-06-11 17:01:15','2026-07-31 20:19:53'),(1384,'service_type',1,'Caisse','2026-07-16 17:29:42','2026-07-28 15:15:47'),(1613,'theme',NULL,'purple','2026-07-17 11:33:45','2026-07-20 17:13:09'),(1625,'service_type',2,'Caisse','2026-07-18 22:09:30','2026-07-28 15:26:35'),(1626,'paper_type',2,'80mm','2026-07-18 22:09:30','2026-07-18 22:09:30'),(1627,'theme',2,'ice','2026-07-18 22:09:55','2026-07-28 15:47:52');
/*!40000 ALTER TABLE `settings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `shops`
--

DROP TABLE IF EXISTS `shops`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `shops` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nom` varchar(100) NOT NULL,
  `code` varchar(20) NOT NULL,
  `adresse` varchar(255) DEFAULT NULL,
  `telephone` varchar(30) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `ice` varchar(50) DEFAULT NULL,
  `rccm` varchar(50) DEFAULT NULL,
  `isf` varchar(50) DEFAULT NULL,
  `pdv` varchar(100) DEFAULT NULL,
  `nid` varchar(100) DEFAULT NULL,
  `token` varchar(255) DEFAULT NULL,
  `port` varchar(255) DEFAULT NULL,
  `service_type_id` int DEFAULT NULL,
  `actif` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_shop_code` (`code`),
  KEY `idx_shop_service_type` (`service_type_id`),
  CONSTRAINT `fk_shop_service_type` FOREIGN KEY (`service_type_id`) REFERENCES `service_types` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `shops`
--

LOCK TABLES `shops` WRITE;
/*!40000 ALTER TABLE `shops` DISABLE KEYS */;
INSERT INTO `shops` VALUES (1,'SuperMarché','MAIN','Ngaliema, 09 av des Oliviers','+243 81 00 00 000','mail@mailtest.com','001234567890123','RCCM 2024','A900001T','PDV30006','NID1234','1236420202','443',5,1,'2026-07-16 23:59:36','2026-07-31 21:29:34'),(2,'Mon magasin','SHOP01','lemba, 10 av terminus','+243 81 60 00 100','randy@mailtest.com','001234567890124','RCCM 2025','A900011T','pdv1532456','nid15236','ezrtyuinhbjdcgq','4344',4,1,'2026-07-17 13:53:03','2026-07-28 17:37:22');
/*!40000 ALTER TABLE `shops` ENABLE KEYS */;
UNLOCK TABLES;

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
  `couleur` varchar(7) DEFAULT '#64748B',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `taxes`
--

LOCK TABLES `taxes` WRITE;
/*!40000 ALTER TABLE `taxes` DISABLE KEYS */;
INSERT INTO `taxes` VALUES (1,'Groupe A','A','Exonéré et hors champ',0.00,'#64748B'),(2,'Groupe B','B','Taxable',16.00,'#64748B'),(3,'Groupe C','C','Taxable',5.00,'#64748B'),(4,'Groupe D','D','Régimes dérogatoires TVA',0.00,'#64748B'),(5,'Groupe E','E','Exportation et opération assimilées',0.00,'#64748B'),(6,'Groupe F','F','TVA marché public à financement exterieur ',16.00,'#64748B'),(7,'Groupe G','G','TVA marché public à financement exterieur ',5.00,'#64748B'),(8,'Groupe H','H','consignation/déconsignation emballage',0.00,'#64748B'),(9,'Groupe I','I','Garantie et caution',0.00,'#64748B'),(10,'Groupe J','J','Débours',0.00,'#64748B'),(11,'Groupe K','K','Opérations réalisées par les non-assujettis',0.00,'#64748B'),(12,'Groupe L','L','Prélèvements sur les ventes',0.00,'#64748B'),(13,'Groupe M','M','Ventes réglemntées TVA spécifique NB:seul le montant hors taxes est facturé',0.00,'#64748B'),(14,'Groupe N','N','TVA spécifique NB:seul le montant de la TVA spéfique est facturé',0.00,'#64748B'),(15,'Groupe O','O','Taxable',1.00,'#64748B'),(16,'Groupe P','P','TVA marché public à financement extérieur ',1.00,'#64748B'),(17,'GROUPE Q','Q','taxe personnalise',50.00,'#64748B'),(18,'GROUPE R','R','test',20.00,'#64748B');
/*!40000 ALTER TABLE `taxes` ENABLE KEYS */;
UNLOCK TABLES;

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

LOCK TABLES `type_client` WRITE;
/*!40000 ALTER TABLE `type_client` DISABLE KEYS */;
INSERT INTO `type_client` VALUES (1,'PP','personne physique'),(2,'PM','personne morale'),(3,'PC','personne physique commerçante'),(4,'PL','profession libérale'),(5,'AO','Ambassades et organisations internationales');
/*!40000 ALTER TABLE `type_client` ENABLE KEYS */;
UNLOCK TABLES;

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
  `role` enum('super_admin','admin','vendeur') NOT NULL DEFAULT 'vendeur',
  `shop_id` int DEFAULT NULL,
  `actif` tinyint(1) NOT NULL DEFAULT '1',
  `agent_code` varchar(50) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `telephone` varchar(30) DEFAULT NULL,
  `two_factor_enabled` tinyint(1) NOT NULL DEFAULT '1',
  `profile_image` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `nom_utilisateur` (`nom_utilisateur`),
  UNIQUE KEY `agent_code` (`agent_code`),
  KEY `idx_user_shop` (`shop_id`),
  CONSTRAINT `fk_user_shop` FOREIGN KEY (`shop_id`) REFERENCES `shops` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `utilisateurs`
--

LOCK TABLES `utilisateurs` WRITE;
/*!40000 ALTER TABLE `utilisateurs` DISABLE KEYS */;
INSERT INTO `utilisateurs` VALUES (1,'mufasa','$2y$10$AjgOMKyu8naWtCFBQ1XETuO8ja87vN0BFlFjcPV5SPWe/0URZQT9e','mufasa','vendeur',1,1,'AG001','rmusafiri30@gmail.com','0816069107',0,NULL),(3,'randy','$2y$10$SSGaIaH518E7Edqa3CouJe4rTkI8QPGKE5ttMM7YHtHIqUxNVkPLa','randy M','super_admin',1,1,'AG003','RM@gmail.com','0816069107',0,'/uploads/profiles/profile_6a5e2083529a61.32224104.jpg'),(4,'satmos','$2y$10$45bwrY4EVPxofgmGQVmCGeonAUaz0IfxYLfjRejA6m/sp0BsANyQW','satmos','super_admin',1,1,'AG002',NULL,'0974763940',1,'/uploads/profiles/profile_6a5e4dc1692778.87888640.jpg'),(6,'vendeur5','$2y$10$C.Wn4hGDdFfYcPSiAQM9q.FqLTbqgFC4OvH02VYqdikF/y9gckVPG','David Nsimba','vendeur',1,1,NULL,NULL,NULL,1,NULL),(7,'vendeur6','$2y$10$C.Wn4hGDdFfYcPSiAQM9q.FqLTbqgFC4OvH02VYqdikF/y9gckVPG','Sarah Mbala','vendeur',1,1,'AG004',NULL,NULL,1,NULL),(8,'vendeur7','$2y$10$C.Wn4hGDdFfYcPSiAQM9q.FqLTbqgFC4OvH02VYqdikF/y9gckVPG','Pauline Tshibanda','vendeur',1,1,NULL,NULL,NULL,1,NULL),(11,'cesar','$2y$10$/KIaginOlMFhOlW1xwRe1Oqs8YVjfJoldNLJJwzLd091oozDn6p92','cesar','admin',1,1,'AF666','rmusafiri30@gmail.com','0895511485',0,'/uploads/profiles/profile_6a5e25ee60b093.72076555.jpg'),(13,'hydra07777','$2y$10$jUVffmur07n0ik3E9K5HguZumsT0DG6HRW9b.rjA2Hw6QUJw0YXnS','César Paysayo','vendeur',1,1,NULL,NULL,NULL,1,NULL),(14,'paul','$2y$10$FgrIkug6PFuQKnkPohm.MeeYKl6DDRQc0u/L48ToPssRem3IKhjiO','paul','admin',2,1,'AG010','paul@gmail.com','',0,'/uploads/profiles/profile_6a5e25ccc28484.79868900.jpg'),(15,'musafiri','$2y$10$Uqt8DQMcs8AXloU11i961OkQLr8GoDwJ1oyzr2S6QLr5.32xleOMO','musafiri','vendeur',2,1,'AG011','musafiri@exemple.com','0812345679',1,NULL);
/*!40000 ALTER TABLE `utilisateurs` ENABLE KEYS */;
UNLOCK TABLES;

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
  `payments` json DEFAULT NULL COMMENT 'Détail des modes de paiement (JSON)',
  `vendeur_id` int NOT NULL,
  `shop_id` int DEFAULT NULL,
  `date` datetime DEFAULT CURRENT_TIMESTAMP,
  `dateDGI` varchar(100) DEFAULT NULL,
  `qrCode` text,
  `codeDEFDGI` varchar(100) DEFAULT NULL,
  `counters` varchar(100) DEFAULT NULL,
  `nim` varchar(100) DEFAULT NULL,
  `comment` text,
  `client_id` int DEFAULT NULL,
  `type_vente` enum('product','bill_payment') DEFAULT 'product' COMMENT 'Type de vente',
  `provider_id` int DEFAULT NULL COMMENT 'Fournisseur SNEL/REGIDESO',
  `numero_compteur` varchar(50) DEFAULT NULL COMMENT 'N° compteur facture',
  `client_reference` varchar(100) DEFAULT NULL COMMENT 'Réf client fournisseur',
  `api_response` text COMMENT 'Réponse brute API JSON',
  `service` varchar(50) DEFAULT NULL COMMENT 'Service provider (Eau, Electricite) pour les recharges',
  PRIMARY KEY (`id`),
  UNIQUE KEY `numero_facture` (`numero_facture`),
  KEY `vendeur_id` (`vendeur_id`),
  KEY `fk_ventes_client` (`client_id`),
  KEY `idx_ventes_type` (`type_vente`),
  KEY `idx_ventes_compteur` (`numero_compteur`),
  KEY `idx_ventes_provider` (`provider_id`),
  KEY `idx_ventes_service` (`service`),
  KEY `idx_vente_shop` (`shop_id`),
  CONSTRAINT `fk_vente_shop` FOREIGN KEY (`shop_id`) REFERENCES `shops` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_ventes_client` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `ventes_ibfk_1` FOREIGN KEY (`vendeur_id`) REFERENCES `utilisateurs` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=493 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `ventes`
--

LOCK TABLES `ventes` WRITE;
/*!40000 ALTER TABLE `ventes` DISABLE KEYS */;
INSERT INTO `ventes` VALUES (1,'FAC-001000',15.00,3.00,18.00,NULL,11,1,'2026-04-23 16:25:18',NULL,NULL,NULL,NULL,NULL,NULL,NULL,'product',NULL,NULL,NULL,NULL,NULL),(2,'FAC-001001',37.08,7.42,44.50,NULL,11,1,'2026-04-23 16:29:40',NULL,NULL,NULL,NULL,NULL,NULL,NULL,'product',NULL,NULL,NULL,NULL,NULL),(3,'FAC-001002',19.17,3.83,23.00,NULL,11,1,'2026-04-23 16:45:04',NULL,NULL,NULL,NULL,NULL,NULL,NULL,'product',NULL,NULL,NULL,NULL,NULL),(4,'FAC-001003',16.25,3.25,19.50,NULL,11,1,'2026-04-23 16:51:52',NULL,NULL,NULL,NULL,NULL,NULL,NULL,'product',NULL,NULL,NULL,NULL,NULL),(5,'FAC-001004',22.08,4.42,26.50,NULL,11,1,'2026-04-23 16:56:09',NULL,NULL,NULL,NULL,NULL,NULL,NULL,'product',NULL,NULL,NULL,NULL,NULL),(6,'FAC-001005',50.00,10.00,60.00,NULL,11,1,'2026-04-23 17:06:07',NULL,NULL,NULL,NULL,NULL,NULL,NULL,'product',NULL,NULL,NULL,NULL,NULL),(7,'FAC-001006',20.00,4.00,24.00,NULL,11,1,'2026-04-23 17:09:44',NULL,NULL,NULL,NULL,NULL,NULL,NULL,'product',NULL,NULL,NULL,NULL,NULL),(8,'FAC-001007',20.00,4.00,24.00,NULL,11,1,'2026-04-23 17:23:04',NULL,NULL,NULL,NULL,NULL,NULL,NULL,'product',NULL,NULL,NULL,NULL,NULL),(9,'FAC-001008',20.00,4.00,24.00,NULL,11,1,'2026-04-23 17:34:55',NULL,NULL,NULL,NULL,NULL,NULL,NULL,'product',NULL,NULL,NULL,NULL,NULL),(10,'FAC-001009',5.00,1.00,6.00,NULL,11,1,'2026-04-23 17:47:18',NULL,NULL,NULL,NULL,NULL,NULL,NULL,'product',NULL,NULL,NULL,NULL,NULL),(11,'FAC-001010',21.25,4.25,25.50,NULL,11,1,'2026-04-23 17:47:46',NULL,NULL,NULL,NULL,NULL,NULL,NULL,'product',NULL,NULL,NULL,NULL,NULL),(12,'FAC-001011',43.33,8.67,52.00,NULL,11,1,'2026-04-23 18:06:07',NULL,NULL,NULL,NULL,NULL,NULL,NULL,'product',NULL,NULL,NULL,NULL,NULL),(13,'FAC-001012',16.25,3.25,19.50,NULL,11,1,'2026-04-23 18:14:32',NULL,NULL,NULL,NULL,NULL,NULL,NULL,'product',NULL,NULL,NULL,NULL,NULL),(14,'FAC-001013',45.00,9.00,54.00,NULL,11,1,'2026-04-23 18:18:19',NULL,NULL,NULL,NULL,NULL,NULL,NULL,'product',NULL,NULL,NULL,NULL,NULL),(15,'FAC-001014',24.17,4.83,29.00,NULL,11,1,'2026-04-23 18:18:59',NULL,NULL,NULL,NULL,NULL,NULL,NULL,'product',NULL,NULL,NULL,NULL,NULL),(16,'FAC-001015',18.33,3.67,22.00,NULL,11,1,'2026-04-23 18:19:14',NULL,NULL,NULL,NULL,NULL,NULL,NULL,'product',NULL,NULL,NULL,NULL,NULL),(17,'FAC-001016',18.33,3.67,22.00,NULL,11,1,'2026-04-23 18:25:12',NULL,NULL,NULL,NULL,NULL,NULL,NULL,'product',NULL,NULL,NULL,NULL,NULL),(18,'FAC-001017',74.17,14.83,89.00,NULL,11,1,'2026-04-23 18:26:49',NULL,NULL,NULL,NULL,NULL,NULL,NULL,'product',NULL,NULL,NULL,NULL,NULL),(19,'FAC-001018',9.17,1.83,11.00,NULL,11,1,'2026-04-23 18:27:41',NULL,NULL,NULL,NULL,NULL,NULL,NULL,'product',NULL,NULL,NULL,NULL,NULL),(20,'FAC-001019',9.17,1.83,11.00,NULL,11,1,'2026-04-23 18:28:25',NULL,NULL,NULL,NULL,NULL,NULL,NULL,'product',NULL,NULL,NULL,NULL,NULL),(21,'FAC-001020',18.33,3.67,22.00,NULL,11,1,'2026-04-23 18:30:55',NULL,NULL,NULL,NULL,NULL,NULL,NULL,'product',NULL,NULL,NULL,NULL,NULL),(22,'FAC-001021',74.58,14.92,89.50,NULL,11,1,'2026-04-23 18:34:45',NULL,NULL,NULL,NULL,NULL,NULL,NULL,'product',NULL,NULL,NULL,NULL,NULL),(23,'FAC-001022',68.33,13.67,82.00,NULL,11,1,'2026-04-23 18:43:35',NULL,NULL,NULL,NULL,NULL,NULL,NULL,'product',NULL,NULL,NULL,NULL,NULL),(24,'FAC-001023',70.83,14.17,85.00,NULL,11,1,'2026-04-23 18:44:36',NULL,NULL,NULL,NULL,NULL,NULL,NULL,'product',NULL,NULL,NULL,NULL,NULL),(25,'FAC-001024',50.00,10.00,60.00,NULL,11,1,'2026-04-24 15:54:30',NULL,NULL,NULL,NULL,NULL,NULL,NULL,'product',NULL,NULL,NULL,NULL,NULL),(26,'FAC-001025',82.50,16.50,99.00,NULL,11,1,'2026-04-24 16:01:30',NULL,NULL,NULL,NULL,NULL,NULL,NULL,'product',NULL,NULL,NULL,NULL,NULL),(27,'FAC-001026',50.83,10.17,61.00,NULL,11,1,'2026-04-24 16:02:16',NULL,NULL,NULL,NULL,NULL,NULL,NULL,'product',NULL,NULL,NULL,NULL,NULL),(28,'FAC-001027',122.92,24.58,147.50,NULL,11,1,'2026-04-24 16:34:43',NULL,NULL,NULL,NULL,NULL,NULL,NULL,'product',NULL,NULL,NULL,NULL,NULL),(29,'FAC-001028',59.17,11.83,71.00,NULL,11,1,'2026-04-24 16:57:51',NULL,NULL,NULL,NULL,NULL,NULL,NULL,'product',NULL,NULL,NULL,NULL,NULL),(30,'FAC-001029',18.33,3.67,22.00,NULL,11,1,'2026-04-24 16:59:05',NULL,NULL,NULL,NULL,NULL,NULL,NULL,'product',NULL,NULL,NULL,NULL,NULL),(31,'FAC-001030',21.25,4.25,25.50,NULL,11,1,'2026-04-24 17:17:56',NULL,NULL,NULL,NULL,NULL,NULL,NULL,'product',NULL,NULL,NULL,NULL,NULL),(32,'FAC-001031',7.08,1.42,8.50,NULL,11,1,'2026-04-24 17:32:12',NULL,NULL,NULL,NULL,NULL,NULL,NULL,'product',NULL,NULL,NULL,NULL,NULL),(33,'FAC-001032',7.08,1.42,8.50,NULL,11,1,'2026-04-24 17:32:49',NULL,NULL,NULL,NULL,NULL,NULL,NULL,'product',NULL,NULL,NULL,NULL,NULL),(34,'FAC-001033',7.08,1.42,8.50,NULL,11,1,'2026-04-24 17:37:25',NULL,NULL,NULL,NULL,NULL,NULL,NULL,'product',NULL,NULL,NULL,NULL,NULL),(35,'FAC-001034',27.50,5.50,33.00,NULL,11,1,'2026-04-24 17:38:07',NULL,NULL,NULL,NULL,NULL,NULL,NULL,'product',NULL,NULL,NULL,NULL,NULL),(36,'FAC-001035',30.42,6.08,36.50,NULL,11,1,'2026-04-24 17:42:26',NULL,NULL,NULL,NULL,NULL,NULL,NULL,'product',NULL,NULL,NULL,NULL,NULL),(37,'FAC-001036',14.17,2.83,17.00,NULL,11,1,'2026-04-24 17:43:31',NULL,NULL,NULL,NULL,NULL,NULL,NULL,'product',NULL,NULL,NULL,NULL,NULL),(38,'FAC-001037',27.50,5.50,33.00,NULL,11,1,'2026-04-24 17:46:05',NULL,NULL,NULL,NULL,NULL,NULL,NULL,'product',NULL,NULL,NULL,NULL,NULL),(39,'FAC-001038',73.33,14.67,88.00,NULL,11,1,'2026-04-24 17:46:48',NULL,NULL,NULL,NULL,NULL,NULL,NULL,'product',NULL,NULL,NULL,NULL,NULL),(40,'FAC-001039',64.17,12.83,77.00,NULL,11,1,'2026-04-24 17:50:24',NULL,NULL,NULL,NULL,NULL,NULL,NULL,'product',NULL,NULL,NULL,NULL,NULL),(41,'FAC-001040',18.33,3.67,22.00,NULL,11,1,'2026-04-24 17:53:25',NULL,NULL,NULL,NULL,NULL,NULL,NULL,'product',NULL,NULL,NULL,NULL,NULL),(42,'FAC-001041',84.58,16.92,101.50,NULL,11,1,'2026-04-24 17:54:11',NULL,NULL,NULL,NULL,NULL,NULL,NULL,'product',NULL,NULL,NULL,NULL,NULL),(43,'FAC-001042',27.50,5.50,33.00,NULL,11,1,'2026-04-24 17:54:34',NULL,NULL,NULL,NULL,NULL,NULL,NULL,'product',NULL,NULL,NULL,NULL,NULL),(44,'FAC-001043',67.08,13.42,80.50,NULL,11,1,'2026-04-24 17:56:03',NULL,NULL,NULL,NULL,NULL,NULL,NULL,'product',NULL,NULL,NULL,NULL,NULL),(45,'FAC-001044',41.67,8.33,50.00,NULL,11,1,'2026-04-24 17:59:41',NULL,NULL,NULL,NULL,NULL,NULL,NULL,'product',NULL,NULL,NULL,NULL,NULL),(46,'FAC-001045',14.17,2.83,17.00,NULL,11,1,'2026-04-24 17:59:47',NULL,NULL,NULL,NULL,NULL,NULL,NULL,'product',NULL,NULL,NULL,NULL,NULL),(47,'FAC-001046',21.25,4.25,25.50,NULL,11,1,'2026-04-24 18:01:31',NULL,NULL,NULL,NULL,NULL,NULL,NULL,'product',NULL,NULL,NULL,NULL,NULL),(48,'FAC-001047',7.08,1.42,8.50,NULL,11,1,'2026-04-24 18:02:33',NULL,NULL,NULL,NULL,NULL,NULL,NULL,'product',NULL,NULL,NULL,NULL,NULL),(49,'FAC-001048',14.17,2.83,17.00,NULL,11,1,'2026-04-24 18:03:06',NULL,NULL,NULL,NULL,NULL,NULL,NULL,'product',NULL,NULL,NULL,NULL,NULL),(50,'FAC-001049',14.17,2.83,17.00,NULL,11,1,'2026-04-24 18:04:28',NULL,NULL,NULL,NULL,NULL,NULL,NULL,'product',NULL,NULL,NULL,NULL,NULL),(51,'FAC-001050',14.17,2.83,17.00,NULL,11,1,'2026-04-24 18:06:11',NULL,NULL,NULL,NULL,NULL,NULL,NULL,'product',NULL,NULL,NULL,NULL,NULL),(52,'FAC-001051',14.17,2.83,17.00,NULL,11,1,'2026-04-24 18:11:41',NULL,NULL,NULL,NULL,NULL,NULL,NULL,'product',NULL,NULL,NULL,NULL,NULL),(53,'FAC-001052',28.33,5.67,34.00,NULL,11,1,'2026-04-24 18:18:20',NULL,NULL,NULL,NULL,NULL,NULL,NULL,'product',NULL,NULL,NULL,NULL,NULL),(54,'FAC-001053',21.25,4.25,25.50,NULL,11,1,'2026-04-24 18:21:58',NULL,NULL,NULL,NULL,NULL,NULL,NULL,'product',NULL,NULL,NULL,NULL,NULL),(55,'FAC-001054',7.08,1.42,8.50,NULL,11,1,'2026-04-24 18:23:24',NULL,NULL,NULL,NULL,NULL,NULL,NULL,'product',NULL,NULL,NULL,NULL,NULL),(56,'FAC-001055',7.08,1.42,8.50,NULL,11,1,'2026-04-24 18:24:29',NULL,NULL,NULL,NULL,NULL,NULL,NULL,'product',NULL,NULL,NULL,NULL,NULL),(57,'FAC-001056',14.17,2.83,17.00,NULL,11,1,'2026-04-24 18:24:36',NULL,NULL,NULL,NULL,NULL,NULL,NULL,'product',NULL,NULL,NULL,NULL,NULL),(58,'FAC-001057',7.08,1.42,8.50,NULL,11,1,'2026-04-24 18:31:13',NULL,NULL,NULL,NULL,NULL,NULL,NULL,'product',NULL,NULL,NULL,NULL,NULL),(59,'FAC-001058',83.33,16.67,100.00,NULL,11,1,'2026-04-24 18:34:17',NULL,NULL,NULL,NULL,NULL,NULL,NULL,'product',NULL,NULL,NULL,NULL,NULL),(60,'FAC-001059',50.00,10.00,60.00,NULL,11,1,'2026-04-24 18:36:25',NULL,NULL,NULL,NULL,NULL,NULL,NULL,'product',NULL,NULL,NULL,NULL,NULL),(61,'FAC-001060',107.92,21.58,129.50,NULL,11,1,'2026-04-24 18:39:38',NULL,NULL,NULL,NULL,NULL,NULL,NULL,'product',NULL,NULL,NULL,NULL,NULL),(62,'FAC-001061',21.25,4.25,25.50,NULL,11,1,'2026-04-24 18:41:52',NULL,NULL,NULL,NULL,NULL,NULL,NULL,'product',NULL,NULL,NULL,NULL,NULL),(63,'FAC-001062',98.75,19.75,118.50,NULL,11,1,'2026-04-24 18:49:08',NULL,NULL,NULL,NULL,NULL,NULL,NULL,'product',NULL,NULL,NULL,NULL,NULL),(64,'FAC-001063',98.75,19.75,118.50,NULL,11,1,'2026-04-24 18:53:34',NULL,NULL,NULL,NULL,NULL,NULL,NULL,'product',NULL,NULL,NULL,NULL,NULL),(65,'FAC-001064',98.75,19.75,118.50,NULL,11,1,'2026-04-24 18:57:31',NULL,NULL,NULL,NULL,NULL,NULL,NULL,'product',NULL,NULL,NULL,NULL,NULL),(66,'FAC-001065',200016.25,40003.25,240019.50,NULL,11,1,'2026-04-24 19:00:52',NULL,NULL,NULL,NULL,NULL,NULL,NULL,'product',NULL,NULL,NULL,NULL,NULL),(67,'FAC-001066',133333.33,26666.67,160000.00,NULL,11,1,'2026-04-24 19:03:59',NULL,NULL,NULL,NULL,NULL,NULL,NULL,'product',NULL,NULL,NULL,NULL,NULL),(68,'FAC-001067',133333.33,26666.67,160000.00,NULL,11,1,'2026-04-24 19:13:24',NULL,NULL,NULL,NULL,NULL,NULL,NULL,'product',NULL,NULL,NULL,NULL,NULL),(69,'FAC-001068',200000.00,32000.00,232000.00,NULL,11,1,'2026-04-24 19:21:30',NULL,NULL,NULL,NULL,NULL,NULL,NULL,'product',NULL,NULL,NULL,NULL,NULL),(70,'FAC-001069',200000.00,32000.00,200000.00,NULL,11,1,'2026-04-24 19:29:03',NULL,NULL,NULL,NULL,NULL,NULL,NULL,'product',NULL,NULL,NULL,NULL,NULL),(71,'FAC-001070',17.00,2.72,17.00,NULL,11,1,'2026-04-25 12:36:41','25/04/2026 11:36:38','RDCF01;CD01004983-1;TESTFACTURESHVM5HVJTMIPY;A1720894F;20260425113638','TEST-FACT-URES-HVM5-HVJT-MIPY','274/276 FV','CD01004983-1',NULL,NULL,'product',NULL,NULL,NULL,NULL,NULL),(72,'FAC-001071',850.00,136.00,850.00,NULL,11,1,'2026-05-01 11:52:24','2026-05-01','[\n    {\n        \"name\": \"th\\u00e9\",\n        \"quantity\": 1,\n        \"price\": 800\n    },\n    {\n        \"name\": \"the good things\",\n        \"quantity\": 1,\n        \"price\": 50\n    }\n]','RDC KINSHASA','661',NULL,NULL,NULL,'product',NULL,NULL,NULL,NULL,NULL),(73,'FAC-001072',900.00,144.00,900.00,NULL,11,1,'2026-05-01 11:54:17','2026-05-01','[\n    {\n        \"name\": \"the good things\",\n        \"quantity\": 2,\n        \"price\": 50\n    },\n    {\n        \"name\": \"th\\u00e9\",\n        \"quantity\": 1,\n        \"price\": 800\n    }\n]','RDC KINSHASA','159',NULL,NULL,NULL,'product',NULL,NULL,NULL,NULL,NULL),(74,'FAC-001073',17.00,2.72,19.72,NULL,11,1,'2026-05-02 00:11:39','2026-05-01','[\n    {\n        \"name\": \"Lait Frais 1L\",\n        \"quantity\": 2,\n        \"price\": 8.5\n    }\n]','RDC KINSHASA','894',NULL,NULL,3,'product',NULL,NULL,NULL,NULL,NULL),(75,'FAC-001074',8.50,1.36,9.86,NULL,11,1,'2026-05-02 00:14:38','2026-05-01','[\n    {\n        \"name\": \"Lait Frais 1L\",\n        \"quantity\": 1,\n        \"price\": 8.5\n    }\n]','RDC KINSHASA','609',NULL,NULL,3,'product',NULL,NULL,NULL,NULL,NULL),(76,'FAC-001075',17.00,2.72,19.72,NULL,11,1,'2026-05-02 00:18:01','2026-05-01','[\n    {\n        \"name\": \"Lait Frais 1L\",\n        \"quantity\": 2,\n        \"price\": 8.5\n    }\n]','RDC KINSHASA','655',NULL,NULL,3,'product',NULL,NULL,NULL,NULL,NULL),(77,'FAC-001076',10917.00,0.00,10917.00,NULL,11,1,'2026-05-02 08:20:06','2026-05-02','[\n    {\n        \"name\": \"Lait Frais 1L\",\n        \"quantity\": 2,\n        \"price\": 8.5\n    },\n    {\n        \"name\": \"randy\",\n        \"quantity\": 1,\n        \"price\": 10000\n    },\n    {\n        \"name\": \"test2\",\n        \"quantity\": 1,\n        \"price\": 900\n    }\n]','RDC KINSHASA','506',NULL,NULL,3,'product',NULL,NULL,NULL,NULL,NULL),(78,'FAC-001077',20017.00,2.72,20019.72,NULL,11,1,'2026-05-02 08:21:54','2026-05-02','[\n    {\n        \"name\": \"Lait Frais 1L\",\n        \"quantity\": 2,\n        \"price\": 8.5\n    },\n    {\n        \"name\": \"randy\",\n        \"quantity\": 2,\n        \"price\": 10000\n    }\n]','RDC KINSHASA','931',NULL,NULL,3,'product',NULL,NULL,NULL,NULL,NULL),(79,'FAC-001078',30017.00,2.72,30019.72,NULL,11,1,'2026-05-02 08:25:27','2026-05-02','[\n    {\n        \"name\": \"Lait Frais 1L\",\n        \"quantity\": 2,\n        \"price\": 8.5\n    },\n    {\n        \"name\": \"randy\",\n        \"quantity\": 3,\n        \"price\": 10000\n    }\n]','RDC KINSHASA','516',NULL,NULL,3,'product',NULL,NULL,NULL,NULL,NULL),(80,'FAC-001079',17.00,0.85,17.85,NULL,11,1,'2026-05-02 19:45:59','02/05/2026 18:45:58','RDCF01;CD01004983-1;TESTFACTURESDDJFW45FHBR6;A1720894F;20260502184558','TEST-FACT-URES-DDJF-W45F-HBR6','277/279 FV','CD01004983-1',NULL,3,'product',NULL,NULL,NULL,NULL,NULL),(81,'FAC-001080',34.00,1.70,34.00,NULL,11,1,'2026-05-07 18:31:59','07/05/2026 17:31:55','RDCF01;CD01004990-1;TESTFACTURES6ZB6DSLU6PSD;A1720894F;20260507173155','TEST-FACT-URES-6ZB6-DSLU-6PSD','13/14 FV','CD01004990-1',NULL,5,'product',NULL,NULL,NULL,NULL,NULL),(82,'FAC-001081',20000.00,0.00,20000.00,NULL,11,1,'2026-05-07 18:57:41','07/05/2026 17:57:37','RDCF01;CD01004990-1;TESTFACTURESJNG253KDUQHB;A1720894F;20260507175737','TEST-FACT-URES-JNG2-53KD-UQHB','14/15 FV','CD01004990-1','mes commentaires : 680',5,'product',NULL,NULL,NULL,NULL,NULL),(83,'FAC-001082',1800.00,0.00,1800.00,NULL,11,1,'2026-05-07 19:00:09','07/05/2026 18:00:06','RDCF01;CD01004990-1;TESTFACTURESLLHMOAUYT452;A1720894F;20260507180006','TEST-FACT-URES-LLHM-OAUY-T452','15/16 FV','CD01004990-1','mes commentaires : 690',5,'product',NULL,NULL,NULL,NULL,NULL),(84,'FAC-001083',30000.00,0.00,30000.00,NULL,11,1,'2026-05-07 19:00:59','07/05/2026 18:00:55','RDCF01;CD01004990-1;TESTFACTURESTCAZPP4JEGUC;A1720894F;20260507180055','TEST-FACT-URES-TCAZ-PP4J-EGUC','16/17 FV','CD01004990-1','mes commentaires : 606',5,'product',NULL,NULL,NULL,NULL,NULL),(85,'FAC-001084',25.50,1.28,25.50,NULL,11,1,'2026-05-07 19:04:54','07/05/2026 18:04:50','RDCF01;CD01004990-1;TESTFACTURESJRYPZO7LEMQV;A1720894F;20260507180450','TEST-FACT-URES-JRYP-ZO7L-EMQV','17/18 FV','CD01004990-1','mes commentaires : 941',5,'product',NULL,NULL,NULL,NULL,NULL),(86,'FAC-001085',30000.00,0.00,30000.00,NULL,11,1,'2026-05-07 19:17:10','07/05/2026 18:17:06','RDCF01;CD01004990-1;TESTFACTURESS6XYPYF2SWFP;A1720894F;20260507181706','TEST-FACT-URES-S6XY-PYF2-SWFP','18/19 FV','CD01004990-1','mes commentaires : 538',5,'product',NULL,NULL,NULL,NULL,NULL),(87,'FAC-001086',20000.00,0.00,20000.00,NULL,11,1,'2026-05-07 19:36:16','07/05/2026 18:36:12','RDCF01;CD01004990-1;TESTFACTURESGXMR2W276YZN;A1720894F;20260507183612','TEST-FACT-URES-GXMR-2W27-6YZN','19/20 FV','CD01004990-1','mes commentaires : 661',5,'product',NULL,NULL,NULL,NULL,NULL),(88,'FAC-001087',1800.00,0.00,1800.00,NULL,11,1,'2026-05-07 19:45:00','07/05/2026 18:44:56','RDCF01;CD01004990-1;TESTFACTURESYOIOCXL4UGSM;A1720894F;20260507184456','TEST-FACT-URES-YOIO-CXL4-UGSM','20/21 FV','CD01004990-1','mes commentaires : 200',5,'product',NULL,NULL,NULL,NULL,NULL),(89,'FAC-001088',20000.00,0.00,20000.00,NULL,11,1,'2026-05-08 15:26:40','08/05/2026 14:26:38','RDCF01;CD01004990-1;TESTFACTURESS2ICZUHLYQVJ;A1720894F;20260508142638','TEST-FACT-URES-S2IC-ZUHL-YQVJ','25/26 FV','CD01004990-1','mes commentaires : 513',5,'product',NULL,NULL,NULL,NULL,NULL),(90,'FAC-001089',8.50,0.43,8.50,NULL,11,1,'2026-05-08 17:18:00','08/05/2026 16:17:58','RDCF01;CD01004990-1;TESTFACTURES2XM7M2OGFOO6;A1720894F;20260508161758','TEST-FACT-URES-2XM7-M2OG-FOO6','38/39 FV','CD01004990-1','',5,'product',NULL,NULL,NULL,NULL,NULL),(91,'FAC-001090',17.00,0.85,17.00,NULL,11,1,'2026-05-11 12:43:57','11/05/2026 11:43:48','RDCF01;CD01004990-1;TESTFACTURESNMBIX5Z4IZXP;A1720894F;20260511114348','TEST-FACT-URES-NMBI-X5Z4-IZXP','80/96 FV','CD01004990-1','',5,'product',NULL,NULL,NULL,NULL,NULL),(92,'FAC-001091',17.00,0.85,17.00,NULL,11,1,'2026-05-13 07:31:37','13/05/2026 06:31:36','RDCF01;CD01004990-1;TESTFACTURESAYNGJISCGAJJ;A1720894F;20260513063136','TEST-FACT-URES-AYNG-JISC-GAJJ','83/99 FV','CD01004990-1','',5,'product',NULL,NULL,NULL,NULL,NULL),(93,'FAC-001092',25.50,1.28,25.50,NULL,11,1,'2026-05-13 10:39:37','13/05/2026 09:39:36','RDCF01;CD01004990-1;TESTFACTURESVAYPC2KOSASY;A1720894F;20260513093936','TEST-FACT-URES-VAYP-C2KO-SASY','85/101 FV','CD01004990-1','',5,'product',NULL,NULL,NULL,NULL,NULL),(94,'FAC-001093',17.00,0.85,17.00,NULL,11,1,'2026-05-13 15:01:40','13/05/2026 14:01:38','RDCF01;CD01004990-1;TESTFACTURESC6VRHFL563BT;A1720894F;20260513140138','TEST-FACT-URES-C6VR-HFL5-63BT','86/102 FV','CD01004990-1','',5,'product',NULL,NULL,NULL,NULL,NULL),(97,'FAC-001094',9843.55,0.00,9843.55,NULL,11,1,'2026-05-13 17:55:59','13/05/2026 16:55:57','RDCF01;CD01004990-1;TESTFACTURESDOY4TX4G4KQG;A1720894F;20260513165557','TEST-FACT-URES-DOY4-TX4G-4KQG','96/112 FV','CD01004990-1',NULL,NULL,'product',NULL,NULL,NULL,NULL,NULL),(98,'FAC-001097',13665.38,0.00,13665.38,NULL,11,1,'2026-05-13 18:04:06','13/05/2026 17:04:04','RDCF01;CD01004990-1;TESTFACTURESWMVVAHVFFWGM;A1720894F;20260513170404','TEST-FACT-URES-WMVV-AHVF-FWGM','97/113 FV','CD01004990-1',NULL,NULL,'product',NULL,NULL,NULL,NULL,NULL),(99,'FAC-001098',5778.82,0.00,5778.82,NULL,11,1,'2026-05-14 18:37:31','14/05/2026 17:37:26','RDCF01;CD01004990-1;TESTFACTURESHC74K335QVXZ;A1720894F;20260514173726','TEST-FACT-URES-HC74-K335-QVXZ','98/114 FV','CD01004990-1',NULL,NULL,'product',NULL,NULL,NULL,NULL,NULL),(100,'FAC-001099',6739.04,0.00,6739.04,NULL,11,1,'2026-05-14 18:39:47','14/05/2026 17:39:43','RDCF01;CD01004990-1;TESTFACTURESRSM5WKTQE6QK;A1720894F;20260514173943','TEST-FACT-URES-RSM5-WKTQ-E6QK','99/115 FV','CD01004990-1',NULL,NULL,'product',NULL,NULL,NULL,NULL,NULL),(101,'FAC-001100',5408.68,0.00,5408.68,NULL,11,1,'2026-05-14 18:51:26','14/05/2026 17:51:21','RDCF01;CD01004990-1;TESTFACTURESOKWNEWE7D7EI;A1720894F;20260514175121','TEST-FACT-URES-OKWN-EWE7-D7EI','100/116 FV','CD01004990-1',NULL,NULL,'product',NULL,NULL,NULL,NULL,NULL),(102,'FAC-001101',5408.68,0.00,5408.68,NULL,11,1,'2026-05-14 18:52:22','14/05/2026 17:52:18','RDCF01;CD01004990-1;TESTFACTURESHKSEWXEGLUWL;A1720894F;20260514175218','TEST-FACT-URES-HKSE-WXEG-LUWL','101/117 FV','CD01004990-1',NULL,NULL,'product',NULL,NULL,NULL,NULL,NULL),(103,'FAC-001102',5536.68,0.00,5536.68,NULL,11,1,'2026-05-14 19:03:28','14/05/2026 18:03:24','RDCF01;CD01004990-1;TESTFACTURESCDIPAOAN6KD7;A1720894F;20260514180324','TEST-FACT-URES-CDIP-AOAN-6KD7','102/118 FV','CD01004990-1',NULL,NULL,'product',NULL,NULL,NULL,NULL,NULL),(104,'FAC-001103',9062.92,0.00,9062.92,NULL,11,1,'2026-05-14 19:36:51','14/05/2026 18:36:47','RDCF01;CD01004990-1;TESTFACTURESGVIQQQUD2TYC;A1720894F;20260514183647','TEST-FACT-URES-GVIQ-QQUD-2TYC','103/119 FV','CD01004990-1',NULL,NULL,'product',NULL,NULL,NULL,NULL,'SNEL'),(105,'FAC-001104',9062.92,0.00,9062.92,NULL,11,1,'2026-05-14 19:40:21','14/05/2026 18:40:17','RDCF01;CD01004990-1;TESTFACTURES6DL53CU7NJ6V;A1720894F;20260514184017','TEST-FACT-URES-6DL5-3CU7-NJ6V','104/120 FV','CD01004990-1',NULL,NULL,'product',NULL,NULL,NULL,NULL,'SNEL'),(106,'FAC-001105',15944.23,0.00,15944.23,NULL,11,1,'2026-05-14 23:01:26','14/05/2026 22:01:12','RDCF01;CD01004990-1;TESTFACTURESN5QJZQMVLER4;A1720894F;20260514220112','TEST-FACT-URES-N5QJ-ZQMV-LER4','105/121 FV','CD01004990-1',NULL,NULL,'product',NULL,NULL,NULL,NULL,'SNEL'),(107,'FAC-001106',6186.01,0.00,6186.01,NULL,11,1,'2026-05-15 00:13:23','14/05/2026 23:13:23','RDCF01;CD01004990-1;TESTFACTURES5QQQ74P44BHB;A1720894F;20260514231323','TEST-FACT-URES-5QQQ-74P4-4BHB','106/122 FV','CD01004990-1',NULL,NULL,'product',NULL,NULL,NULL,NULL,'EAU'),(108,'FAC-001107',6186.01,0.00,6186.01,NULL,11,1,'2026-05-15 00:16:25','14/05/2026 23:16:25','RDCF01;CD01004990-1;TESTFACTURESCFNYSPCHEVXU;A1720894F;20260514231625','TEST-FACT-URES-CFNY-SPCH-EVXU','107/123 FV','CD01004990-1',NULL,NULL,'product',NULL,NULL,NULL,NULL,'EAU'),(109,'FAC-001108',6186.01,0.00,6186.01,NULL,11,1,'2026-05-15 00:23:29','14/05/2026 23:23:28','RDCF01;CD01004990-1;TESTFACTURESJKNSW7KDE2MH;A1720894F;20260514232328','TEST-FACT-URES-JKNS-W7KD-E2MH','108/124 FV','CD01004990-1',NULL,NULL,'product',NULL,NULL,NULL,NULL,'EAU'),(110,'FAC-001109',6186.01,0.00,6186.01,NULL,11,1,'2026-05-15 00:26:12','14/05/2026 23:26:12','RDCF01;CD01004990-1;TESTFACTURESCLBQRHTQX6RC;A1720894F;20260514232612','TEST-FACT-URES-CLBQ-RHTQ-X6RC','109/125 FV','CD01004990-1',NULL,NULL,'product',NULL,NULL,NULL,NULL,'EAU'),(111,'FAC-001110',6186.01,0.00,6186.01,NULL,11,1,'2026-05-15 00:30:42','14/05/2026 23:30:41','RDCF01;CD01004990-1;TESTFACTURESJZVTU2QVVRR6;A1720894F;20260514233041','TEST-FACT-URES-JZVT-U2QV-VRR6','110/126 FV','CD01004990-1',NULL,NULL,'product',NULL,NULL,NULL,NULL,'EAU'),(112,'FAC-001111',8625.01,0.00,8625.01,NULL,11,1,'2026-05-15 00:31:42','14/05/2026 23:31:42','RDCF01;CD01004990-1;TESTFACTURESCVJUK4A6QRJT;A1720894F;20260514233142','TEST-FACT-URES-CVJU-K4A6-QRJT','111/127 FV','CD01004990-1',NULL,NULL,'product',NULL,NULL,NULL,NULL,'EAU'),(113,'FAC-001112',8625.01,0.00,8625.01,NULL,11,1,'2026-05-15 00:40:03','14/05/2026 23:40:03','RDCF01;CD01004990-1;TESTFACTURESYHMZPZL3UTLT;A1720894F;20260514234003','TEST-FACT-URES-YHMZ-PZL3-UTLT','112/128 FV','CD01004990-1',NULL,NULL,'product',NULL,NULL,NULL,NULL,'EAU'),(114,'FAC-001113',13665.38,0.00,13665.38,NULL,11,1,'2026-05-15 12:51:04','15/05/2026 11:51:00','RDCF01;CD01004990-1;TESTFACTURESDTLLK42NWFX7;A1720894F;20260515115100','TEST-FACT-URES-DTLL-K42N-WFX7','113/129 FV','CD01004990-1',NULL,NULL,'product',NULL,NULL,NULL,NULL,'EAU'),(115,'FAC-001114',9967.90,0.00,9967.90,NULL,11,1,'2026-05-15 16:25:35','15/05/2026 15:25:31','RDCF01;CD01004990-1;TESTFACTURESMLFSGYIF5NV7;A1720894F;20260515152531','TEST-FACT-URES-MLFS-GYIF-5NV7','114/130 FV','CD01004990-1',NULL,NULL,'product',NULL,NULL,NULL,NULL,'EAU'),(116,'FAC-001115',9419.68,0.00,9419.68,NULL,11,1,'2026-05-15 16:55:32','15/05/2026 15:55:27','RDCF01;CD01004990-1;TESTFACTURESPHMYBZ4D6UG2;A1720894F;20260515155527','TEST-FACT-URES-PHMY-BZ4D-6UG2','118/134 FV','CD01004990-1',NULL,NULL,'product',NULL,NULL,NULL,NULL,'ELECTRICITE'),(117,'FAC-001116',6980.68,0.00,6980.68,NULL,11,1,'2026-05-15 17:15:46','15/05/2026 16:15:42','RDCF01;CD01004990-1;TESTFACTURESPQMBMUVZTRB7;A1720894F;20260515161542','TEST-FACT-URES-PQMB-MUVZ-TRB7','122/138 FV','CD01004990-1',NULL,NULL,'product',NULL,NULL,NULL,NULL,'EAU'),(118,'FAC-001117',8625.01,0.00,8625.01,NULL,11,1,'2026-05-15 18:37:00','15/05/2026 17:36:55','RDCF01;CD01004990-1;TESTFACTURESXSTQ5PJBE5F2;A1720894F;20260515173655','TEST-FACT-URES-XSTQ-5PJB-E5F2','125/141 FV','CD01004990-1',NULL,NULL,'product',NULL,NULL,NULL,NULL,'EAU'),(119,'FAC-001118',7776.12,0.00,7776.12,NULL,11,1,'2026-05-15 18:42:17','15/05/2026 17:42:12','RDCF01;CD01004990-1;TESTFACTURES6Z3E3B3STHT6;A1720894F;20260515174212','TEST-FACT-URES-6Z3E-3B3S-THT6','128/144 FV','CD01004990-1',NULL,NULL,'product',NULL,NULL,NULL,NULL,'EAU'),(120,'FAC-001119',10450.26,0.00,10450.26,NULL,11,1,'2026-05-15 18:49:13','15/05/2026 17:49:09','RDCF01;CD01004990-1;TESTFACTURESL625EBCJ7MMB;A1720894F;20260515174909','TEST-FACT-URES-L625-EBCJ-7MMB','129/145 FV','CD01004990-1',NULL,NULL,'product',NULL,NULL,NULL,NULL,'EAU'),(121,'FAC-001120',7764.08,0.00,7764.08,NULL,11,1,'2026-05-15 18:53:15','15/05/2026 17:53:11','RDCF01;CD01004990-1;TESTFACTURESATAF2IRTYSBE;A1720894F;20260515175311','TEST-FACT-URES-ATAF-2IRT-YSBE','130/146 FV','CD01004990-1',NULL,NULL,'product',NULL,NULL,NULL,NULL,'EAU'),(122,'FAC-001121',9134.24,0.00,9134.24,NULL,11,1,'2026-05-15 19:08:01','15/05/2026 18:07:57','RDCF01;CD01004990-1;TESTFACTURESUUOP4MDCAZFE;A1720894F;20260515180757','TEST-FACT-URES-UUOP-4MDC-AZFE','131/147 FV','CD01004990-1',NULL,NULL,'product',NULL,NULL,NULL,NULL,'ELECTRICITE'),(123,'FAC-001122',16570.36,0.00,16570.36,NULL,11,1,'2026-05-16 16:03:31','16/05/2026 15:03:27','RDCF01;CD01004990-1;TESTFACTURESSV3BPMO56GFO;A1720894F;20260516150327','TEST-FACT-URES-SV3B-PMO5-6GFO','134/150 FV','CD01004990-1',NULL,NULL,'product',NULL,NULL,NULL,NULL,'ELECTRICITE'),(124,'FAC-001123',50.15,2.51,50.15,NULL,11,1,'2026-05-20 18:01:46','20/05/2026 17:01:45','RDCF01;CD01004990-1;TESTFACTURESMODW55U5ZIUD;A1720894F;20260520170145','TEST-FACT-URES-MODW-55U5-ZIUD','144/161 FV','CD01004990-1','',5,'product',NULL,NULL,NULL,NULL,NULL),(126,'FAC-001124',21.25,1.06,21.25,NULL,11,1,'2026-05-20 19:02:00','20/05/2026 18:01:59','RDCF01;CD01004990-1;TESTFACTURESJVF4B5HESMWE;A1720894F;20260520180159','TEST-FACT-URES-JVF4-B5HE-SMWE','153/170 FV','CD01004990-1','',5,'product',NULL,NULL,NULL,NULL,NULL),(127,'FAC-001126',10000.00,1600.00,10000.00,NULL,11,1,'2026-05-20 22:57:49','20/05/2026 21:57:47','RDCF01;CD01004990-1;TESTFACTURES7LGKL76QGGDC;A1720894F;20260520215747','TEST-FACT-URES-7LGK-L76Q-GGDC','160/177 FV','CD01004990-1','',NULL,'product',NULL,NULL,NULL,NULL,NULL),(128,'FAC-001127',5000.00,0.00,5000.00,NULL,11,1,'2026-05-20 22:58:33','20/05/2026 21:58:32','RDCF01;CD01004990-1;TESTFACTURESZE7DDCZP6MRF;A1720894F;20260520215832','TEST-FACT-URES-ZE7D-DCZP-6MRF','161/178 FV','CD01004990-1','',NULL,'product',NULL,NULL,NULL,NULL,NULL),(129,'FAC-001128',10000.00,1600.00,10000.00,NULL,11,1,'2026-05-20 22:59:24','20/05/2026 21:59:22','RDCF01;CD01004990-1;TESTFACTURESRICFWXWNZKA7;A1720894F;20260520215922','TEST-FACT-URES-RICF-WXWN-ZKA7','162/179 FV','CD01004990-1','',NULL,'product',NULL,NULL,NULL,NULL,NULL),(130,'FAC-001129',84.07,4.20,84.07,NULL,11,1,'2026-05-20 23:05:04','20/05/2026 22:05:02','RDCF01;CD01004990-1;TESTFACTURESNCMNIBJ2VCSC;A1720894F;20260520220502','TEST-FACT-URES-NCMN-IBJ2-VCSC','163/180 FV','CD01004990-1','',NULL,'product',NULL,NULL,NULL,NULL,NULL),(131,'FAC-001130',10000.00,1600.00,10000.00,NULL,11,1,'2026-05-21 14:38:47','21/05/2026 13:38:48','RDCF01;CD01004990-1;TESTFACTURESCOX6IK7PZBKM;A1720894F;20260521133848','TEST-FACT-URES-COX6-IK7P-ZBKM','164/181 FV','CD01004990-1','',NULL,'product',NULL,NULL,NULL,NULL,NULL),(132,'FAC-001131',10000.00,1600.00,10000.00,NULL,11,1,'2026-05-21 16:44:48','21/05/2026 15:44:48','RDCF01;CD01004990-1;TESTFACTURESTAZFN4QDKPYA;A1720894F;20260521154448','TEST-FACT-URES-TAZF-N4QD-KPYA','165/182 FV','CD01004990-1','',NULL,'product',NULL,NULL,NULL,NULL,NULL),(133,'FAC-001132',10975.65,1603.78,10975.65,NULL,11,1,'2026-05-21 16:57:20','21/05/2026 15:57:20','RDCF01;CD01004990-1;TESTFACTURESDFP5B5JF5HP4;A1720894F;20260521155720','TEST-FACT-URES-DFP5-B5JF-5HP4','166/183 FV','CD01004990-1','',NULL,'product',NULL,NULL,NULL,NULL,NULL),(134,'FAC-001133',650954.25,104000.21,650954.25,NULL,11,1,'2026-05-21 17:16:05','21/05/2026 16:16:05','RDCF01;CD01004990-1;TESTFACTURESXHWNG4M5AUGW;A1720894F;20260521161605','TEST-FACT-URES-XHWN-G4M5-AUGW','167/184 FV','CD01004990-1','',NULL,'product',NULL,NULL,NULL,NULL,NULL),(135,'FAC-001134',1358.93,0.00,1358.93,NULL,11,1,'2026-05-21 17:17:20','21/05/2026 16:17:20','RDCF01;CD01004990-1;TESTFACTURESHCR6OUOTO3KW;A1720894F;20260521161720','TEST-FACT-URES-HCR6-OUOT-O3KW','168/185 FV','CD01004990-1','Réference Doc : Empty',NULL,'product',NULL,NULL,NULL,NULL,'ELECTRICITE'),(136,'FAC-001135',900.00,0.00,900.00,NULL,11,1,'2026-05-21 17:28:31','21/05/2026 16:28:31','RDCF01;CD01004990-1;TESTFACTURESOKQVCVSYVA25;A1720894F;20260521162831','TEST-FACT-URES-OKQV-CVSY-VA25','171/188 FV','CD01004990-1','',NULL,'product',NULL,NULL,NULL,NULL,NULL),(137,'FAC-001136',10900.00,1600.00,10900.00,NULL,11,1,'2026-05-21 17:30:02','21/05/2026 16:30:02','RDCF01;CD01004990-1;TESTFACTURESEG7S7D57MSHY;A1720894F;20260521163002','TEST-FACT-URES-EG7S-7D57-MSHY','174/191 FV','CD01004990-1','',NULL,'product',NULL,NULL,NULL,NULL,NULL),(138,'FAC-001137',1800.00,0.00,1800.00,NULL,11,1,'2026-05-21 17:33:23','21/05/2026 16:33:23','RDCF01;CD01004990-1;TESTFACTURESJCWJHLVTGRMO;A1720894F;20260521163323','TEST-FACT-URES-JCWJ-HLVT-GRMO','175/192 FV','CD01004990-1','',NULL,'product',NULL,NULL,NULL,NULL,NULL),(139,'FAC-001138',20950.00,3200.00,20950.00,NULL,11,1,'2026-05-21 17:45:31','21/05/2026 16:45:31','RDCF01;CD01004990-1;TESTFACTURES6KUST2XEIGU5;A1720894F;20260521164531','TEST-FACT-URES-6KUS-T2XE-IGU5','7/193 EV','CD01004990-1','Réference Doc : Empty',NULL,'product',NULL,NULL,NULL,NULL,NULL),(140,'FAC-001139',2650.00,128.00,2650.00,NULL,11,1,'2026-05-21 17:51:26','21/05/2026 16:51:26','RDCF01;CD01004990-1;TESTFACTURESUT4DFPVRFGAV;A1720894F;20260521165126','TEST-FACT-URES-UT4D-FPVR-FGAV','176/194 FV','CD01004990-1','',NULL,'product',NULL,NULL,NULL,NULL,NULL),(141,'FAC-001140',10050.00,1600.00,10050.00,NULL,11,1,'2026-05-21 18:01:37','21/05/2026 17:01:37','RDCF01;CD01004990-1;TESTFACTURESDQBWKZTKTL7T;A1720894F;20260521170137','TEST-FACT-URES-DQBW-KZTK-TL7T','178/196 FV','CD01004990-1','',NULL,'product',NULL,NULL,NULL,NULL,NULL),(142,'FAC-001141',1700.00,128.00,1700.00,NULL,11,1,'2026-05-21 18:05:29','21/05/2026 17:05:29','RDCF01;CD01004990-1;TESTFACTURES27X2RAKRXQC5;A1720894F;20260521170529','TEST-FACT-URES-27X2-RAKR-XQC5','179/197 FV','CD01004990-1','',NULL,'product',NULL,NULL,NULL,NULL,NULL),(143,'FAC-001142',10000.00,1600.00,10000.00,NULL,11,1,'2026-05-21 18:19:15','21/05/2026 17:19:14','RDCF01;CD01004990-1;TESTFACTURES6HNFTGRPRIPX;A1720894F;20260521171914','TEST-FACT-URES-6HNF-TGRP-RIPX','180/198 FV','CD01004990-1','',NULL,'product',NULL,NULL,NULL,NULL,NULL),(145,'FAC-001143',16750.00,1728.00,16750.00,NULL,11,1,'2026-05-21 18:20:12','21/05/2026 17:20:11','RDCF01;CD01004990-1;TESTFACTURES3IQYVCIL3SIN;A1720894F;20260521172011','TEST-FACT-URES-3IQY-VCIL-3SIN','182/200 FV','CD01004990-1','',NULL,'product',NULL,NULL,NULL,NULL,NULL),(146,'FAC-001145',10900.00,1600.00,10900.00,NULL,11,1,'2026-05-21 18:28:45','21/05/2026 17:28:45','RDCF01;CD01004990-1;TESTFACTURESQUR4TPZXBMBP;A1720894F;20260521172845','TEST-FACT-URES-QUR4-TPZX-BMBP','185/203 FV','CD01004990-1','',NULL,'product',NULL,NULL,NULL,NULL,NULL),(147,'FAC-001146',10900.00,1600.00,10900.00,NULL,11,1,'2026-05-21 18:31:02','21/05/2026 17:31:02','RDCF01;CD01004990-1;TESTFACTURESQLK2FCBQRCNN;A1720894F;20260521173102','TEST-FACT-URES-QLK2-FCBQ-RCNN','186/204 FV','CD01004990-1','',NULL,'product',NULL,NULL,NULL,NULL,NULL),(148,'FAC-001147',6757.65,128.38,6757.65,NULL,11,1,'2026-05-21 18:32:35','21/05/2026 17:32:35','RDCF01;CD01004990-1;TESTFACTURESC43RZM3ESXSU;A1720894F;20260521173235','TEST-FACT-URES-C43R-ZM3E-SXSU','187/205 FV','CD01004990-1','',NULL,'product',NULL,NULL,NULL,NULL,NULL),(149,'FAC-001148',10900.00,1600.00,10900.00,NULL,11,1,'2026-05-21 18:38:29','21/05/2026 17:38:29','RDCF01;CD01004990-1;TESTFACTURESW6OD5DT2XYYU;A1720894F;20260521173829','TEST-FACT-URES-W6OD-5DT2-XYYU','188/206 FV','CD01004990-1','',NULL,'product',NULL,NULL,NULL,NULL,NULL),(150,'FAC-001149',15900.00,1600.00,15900.00,NULL,11,1,'2026-05-21 18:39:10','21/05/2026 17:39:10','RDCF01;CD01004990-1;TESTFACTURES3H6I4W4C22XD;A1720894F;20260521173910','TEST-FACT-URES-3H6I-4W4C-22XD','189/207 FV','CD01004990-1','',NULL,'product',NULL,NULL,NULL,NULL,NULL),(151,'FAC-001150',10000.00,1600.00,10000.00,NULL,11,1,'2026-05-21 18:43:28','21/05/2026 17:43:28','RDCF01;CD01004990-1;TESTFACTURES2QIKCIOU4K6L;A1720894F;20260521174328','TEST-FACT-URES-2QIK-CIOU-4K6L','190/208 FV','CD01004990-1','',NULL,'product',NULL,NULL,NULL,NULL,NULL),(152,'FAC-001151',10900.00,1600.00,10900.00,NULL,11,1,'2026-05-21 18:46:58','21/05/2026 17:46:58','RDCF01;CD01004990-1;TESTFACTURESCBSAPC5WNZ72;A1720894F;20260521174658','TEST-FACT-URES-CBSA-PC5W-NZ72','191/209 FV','CD01004990-1','',NULL,'product',NULL,NULL,NULL,NULL,NULL),(153,'FAC-001152',2400.00,384.00,2400.00,NULL,11,1,'2026-05-21 18:54:31','21/05/2026 17:54:31','RDCF01;CD01004990-1;TESTFACTURESIOBQSEBZAKVQ;A1720894F;20260521175431','TEST-FACT-URES-IOBQ-SEBZ-AKVQ','192/210 FV','CD01004990-1','',NULL,'product',NULL,NULL,NULL,NULL,NULL),(154,'2026/000001',800.00,128.00,800.00,NULL,11,1,'2026-05-21 18:57:14','21/05/2026 17:57:14','RDCF01;CD01004990-1;TESTFACTURESXAN5SQGV3IB4;A1720894F;20260521175714','TEST-FACT-URES-XAN5-SQGV-3IB4','193/211 FV','CD01004990-1','',NULL,'product',NULL,NULL,NULL,NULL,NULL),(155,'2026/000002',4.25,0.21,4.25,NULL,11,1,'2026-05-21 19:07:34','21/05/2026 18:07:34','RDCF01;CD01004990-1;TESTFACTURESAPA3XPLC4ZYB;A1720894F;20260521180734','TEST-FACT-URES-APA3-XPLC-4ZYB','195/213 FV','CD01004990-1','',NULL,'product',NULL,NULL,NULL,NULL,NULL),(156,'2026/000003',7.31,0.37,7.31,NULL,11,1,'2026-05-21 19:11:02','21/05/2026 18:11:02','RDCF01;CD01004990-1;TESTFACTURESF3QQSDO6UNV5;A1720894F;20260521181102','TEST-FACT-URES-F3QQ-SDO6-UNV5','196/214 FV','CD01004990-1','',NULL,'product',NULL,NULL,NULL,NULL,NULL),(157,'2026/000004',4.25,0.21,4.25,NULL,11,1,'2026-05-21 19:12:20','21/05/2026 18:12:19','RDCF01;CD01004990-1;TESTFACTURES2DKNONAXCZWQ;A1720894F;20260521181219','TEST-FACT-URES-2DKN-ONAX-CZWQ','197/215 FV','CD01004990-1','',NULL,'product',NULL,NULL,NULL,NULL,NULL),(158,'2026/000005',6.80,0.34,6.80,NULL,11,1,'2026-05-21 19:22:49','21/05/2026 18:22:48','RDCF01;CD01004990-1;TESTFACTURESQOCEG2CDHSKQ;A1720894F;20260521182248','TEST-FACT-URES-QOCE-G2CD-HSKQ','198/216 FV','CD01004990-1','',NULL,'product',NULL,NULL,NULL,NULL,NULL),(159,'2026/000006',7.65,0.38,7.65,NULL,11,1,'2026-05-21 19:25:21','21/05/2026 18:25:21','RDCF01;CD01004990-1;TESTFACTURESLKP6FGRDFTRD;A1720894F;20260521182521','TEST-FACT-URES-LKP6-FGRD-FTRD','199/217 FV','CD01004990-1','',NULL,'product',NULL,NULL,NULL,NULL,NULL),(160,'2026/000007',10907.65,1600.38,10907.65,NULL,11,1,'2026-05-21 19:25:46','21/05/2026 18:25:45','RDCF01;CD01004990-1;TESTFACTURESZ2V6WH76SCFE;A1720894F;20260521182545','TEST-FACT-URES-Z2V6-WH76-SCFE','200/218 FV','CD01004990-1','',NULL,'product',NULL,NULL,NULL,NULL,NULL),(161,'2026/000008',4.25,0.21,4.25,NULL,11,1,'2026-05-21 19:31:40','21/05/2026 18:31:39','RDCF01;CD01004990-1;TESTFACTURESQOCXSMJK2AHS;A1720894F;20260521183139','TEST-FACT-URES-QOCX-SMJK-2AHS','201/219 FV','CD01004990-1','',NULL,'product',NULL,NULL,NULL,NULL,NULL),(162,'2026/000009',4.25,0.21,4.25,NULL,11,1,'2026-05-21 19:35:16','21/05/2026 18:35:16','RDCF01;CD01004990-1;TESTFACTURES6OPSGEQCAUGF;A1720894F;20260521183516','TEST-FACT-URES-6OPS-GEQC-AUGF','202/220 FV','CD01004990-1','',NULL,'product',NULL,NULL,NULL,NULL,NULL),(163,'2026/000010',4.25,0.21,4.25,NULL,11,1,'2026-05-21 19:36:27','21/05/2026 18:36:27','RDCF01;CD01004990-1;TESTFACTURESPF5NTMFBN5LE;A1720894F;20260521183627','TEST-FACT-URES-PF5N-TMFB-N5LE','203/221 FV','CD01004990-1','',NULL,'product',NULL,NULL,NULL,NULL,NULL),(164,'2026/000011',93.50,4.68,93.50,NULL,11,1,'2026-05-21 19:37:52','21/05/2026 18:37:52','RDCF01;CD01004990-1;TESTFACTURES6DBVX7K6IP3J;A1720894F;20260521183752','TEST-FACT-URES-6DBV-X7K6-IP3J','204/222 FV','CD01004990-1','',NULL,'product',NULL,NULL,NULL,NULL,NULL),(165,'2026/000012',50.00,0.00,50.00,NULL,11,1,'2026-05-21 19:38:17','21/05/2026 18:38:17','RDCF01;CD01004990-1;TESTFACTURES4C2YHJP62WZU;A1720894F;20260521183817','TEST-FACT-URES-4C2Y-HJP6-2WZU','205/223 FV','CD01004990-1','',NULL,'product',NULL,NULL,NULL,NULL,NULL),(166,'2026/000013',11757.65,1728.38,11757.65,NULL,11,1,'2026-05-21 19:48:55','21/05/2026 18:48:54','RDCF01;CD01004990-1;TESTFACTURES5GMQITMDMRIW;A1720894F;20260521184854','TEST-FACT-URES-5GMQ-ITMD-MRIW','206/224 FV','CD01004990-1','',NULL,'product',NULL,NULL,NULL,NULL,NULL),(167,'2026/000014',6.80,0.34,6.80,NULL,11,1,'2026-05-21 19:52:02','21/05/2026 18:52:01','RDCF01;CD01004990-1;TESTFACTURES2IOSBOZVUT7K;A1720894F;20260521185201','TEST-FACT-URES-2IOS-BOZV-UT7K','207/225 FV','CD01004990-1','',NULL,'product',NULL,NULL,NULL,NULL,NULL),(168,'2026/000015',1700.00,128.00,1700.00,NULL,11,1,'2026-05-22 10:06:47','22/05/2026 09:06:48','RDCF01;CD01004990-1;TESTFACTURESSAYKJ2YA73FU;A1720894F;20260522090648','TEST-FACT-URES-SAYK-J2YA-73FU','8/230 EV','CD01004990-1','Réference Doc : Empty',NULL,'product',NULL,NULL,NULL,NULL,NULL),(169,'2026/000016',10000.00,1600.00,10000.00,NULL,11,1,'2026-05-22 10:27:39','22/05/2026 09:27:35','RDCF01;CD01004990-1;TESTFACTURES3RXPOWI6JNEV;A1720894F;20260522092735','TEST-FACT-URES-3RXP-OWI6-JNEV','9/231 EV','CD01004990-1','Réference Doc : Empty',NULL,'product',NULL,NULL,NULL,NULL,NULL),(170,'2026/000017',10000.00,1600.00,10000.00,NULL,11,1,'2026-05-22 10:33:54','22/05/2026 09:33:53','RDCF01;CD01004990-1;TESTFACTURESNSYULKIPBM5M;A1720894F;20260522093353','TEST-FACT-URES-NSYU-LKIP-BM5M','212/232 FV','CD01004990-1','',NULL,'product',NULL,NULL,NULL,NULL,NULL),(171,'2026/000018',10004.25,1600.21,10004.25,NULL,11,1,'2026-05-22 10:38:17','22/05/2026 09:38:19','RDCF01;CD01004990-1;TESTFACTURESF3Q7FRSKIJKS;A1720894F;20260522093819','TEST-FACT-URES-F3Q7-FRSK-IJKS','213/233 FV','CD01004990-1','',NULL,'product',NULL,NULL,NULL,NULL,NULL),(172,'2026/000019',10057.65,1600.38,10057.65,NULL,11,1,'2026-05-22 10:53:27','22/05/2026 09:53:29','RDCF01;CD01004990-1;TESTFACTURESTFXTFFEEIJZD;A1720894F;20260522095329','TEST-FACT-URES-TFXT-FFEE-IJZD','215/235 FT','CD01004990-1',NULL,NULL,'product',NULL,NULL,NULL,NULL,NULL),(173,'2026/000020',7.65,0.38,7.65,NULL,11,1,'2026-05-22 11:32:59','22/05/2026 10:32:57','RDCF01;CD01004990-1;TESTFACTURESLLXK6ZPPX4QF;A1720894F;20260522103257','TEST-FACT-URES-LLXK-6ZPP-X4QF','216/236 FV','CD01004990-1','',NULL,'product',NULL,NULL,NULL,NULL,NULL),(174,'2026/000021',1700.00,128.00,1700.00,NULL,11,1,'2026-05-22 11:34:36','22/05/2026 10:34:34','RDCF01;CD01004990-1;TESTFACTURES7PJOMPVHVWK4;A1720894F;20260522103434','TEST-FACT-URES-7PJO-MPVH-VWK4','217/237 FV','CD01004990-1','',NULL,'product',NULL,NULL,NULL,NULL,NULL),(175,'2026/000022',7.65,0.38,7.65,NULL,11,1,'2026-05-22 11:56:51','22/05/2026 10:56:49','RDCF01;CD01004990-1;TESTFACTURESS22OX6UPAA2O;A1720894F;20260522105649','TEST-FACT-URES-S22O-X6UP-AA2O','220/241 FV','CD01004990-1','',NULL,'product',NULL,NULL,NULL,NULL,NULL),(176,'2026/000023',11840.10,1732.51,11840.10,NULL,11,1,'2026-05-22 11:57:35','22/05/2026 10:57:33','RDCF01;CD01004990-1;TESTFACTURESM5GMNVCWC65T;A1720894F;20260522105733','TEST-FACT-URES-M5GM-NVCW-C65T','221/242 FV','CD01004990-1','',NULL,'product',NULL,NULL,NULL,NULL,NULL),(177,'2026/000024',7.65,0.38,7.65,NULL,11,1,'2026-05-22 12:00:44','22/05/2026 11:00:42','RDCF01;CD01004990-1;TESTFACTURESF232GE7EEAAA;A1720894F;20260522110042','TEST-FACT-URES-F232-GE7E-EAAA','222/243 FV','CD01004990-1','',NULL,'product',NULL,NULL,NULL,NULL,NULL),(178,'2026/000025',7.65,0.38,7.65,NULL,11,1,'2026-05-22 17:12:47','22/05/2026 16:12:44','RDCF01;CD01004990-1;TESTFACTURESBVJMXC5DRA56;A1720894F;20260522161244','TEST-FACT-URES-BVJM-XC5D-RA56','245/266 FV','CD01004990-1','',NULL,'product',NULL,NULL,NULL,NULL,NULL),(179,'2026/000026',6.80,0.34,6.80,NULL,11,1,'2026-05-22 17:18:12','22/05/2026 16:18:09','RDCF01;CD01004990-1;TESTFACTURES4AVQJO5Z6S7L;A1720894F;20260522161809','TEST-FACT-URES-4AVQ-JO5Z-6S7L','249/270 FT','CD01004990-1',NULL,NULL,'product',NULL,NULL,NULL,NULL,NULL),(180,'2026/000027',4.25,0.21,4.25,NULL,11,1,'2026-05-22 17:20:37','22/05/2026 16:20:34','RDCF01;CD01004990-1;TESTFACTURES4W4JXWKYGMQ2;A1720894F;20260522162034','TEST-FACT-URES-4W4J-XWKY-GMQ2','251/272 FV','CD01004990-1','',NULL,'product',NULL,NULL,NULL,NULL,NULL),(181,'2026/000028',4.25,0.21,4.25,NULL,11,1,'2026-05-22 17:39:50','22/05/2026 16:39:46','RDCF01;CD01004990-1;TESTFACTURESIL3EVQFEDXBZ;A1720894F;20260522163946','TEST-FACT-URES-IL3E-VQFE-DXBZ','254/275 FV','CD01004990-1','',NULL,'product',NULL,NULL,NULL,NULL,NULL),(182,'2026/000029',6.80,0.34,6.80,NULL,11,1,'2026-05-22 18:02:19','22/05/2026 17:02:16','RDCF01;CD01004990-1;TESTFACTURESMBEBKVZ6724H;A1720894F;20260522170216','TEST-FACT-URES-MBEB-KVZ6-724H','259/280 FV','CD01004990-1','',NULL,'product',NULL,NULL,NULL,NULL,NULL),(183,'2026/000030',4.25,0.21,4.25,NULL,11,1,'2026-05-22 18:07:24','22/05/2026 17:07:20','RDCF01;CD01004990-1;TESTFACTURESZB6NA6VBIGJD;A1720894F;20260522170720','TEST-FACT-URES-ZB6N-A6VB-IGJD','260/281 FV','CD01004990-1','',NULL,'product',NULL,NULL,NULL,NULL,NULL),(184,'2026/000031',10075.65,1603.78,10075.65,NULL,11,1,'2026-05-22 18:43:10','22/05/2026 17:43:06','RDCF01;CD01004990-1;TESTFACTURESLXUV3CBB4B4O;A1720894F;20260522174306','TEST-FACT-URES-LXUV-3CBB-4B4O','261/282 FV','CD01004990-1','',NULL,'product',NULL,NULL,NULL,NULL,NULL),(185,'2026/000032',80.75,4.04,80.75,NULL,11,1,'2026-05-22 18:45:15','22/05/2026 17:45:12','RDCF01;CD01004990-1;TESTFACTURESGNC5GIYWQ6PW;A1720894F;20260522174512','TEST-FACT-URES-GNC5-GIYW-Q6PW','262/283 FV','CD01004990-1','',NULL,'product',NULL,NULL,NULL,NULL,NULL),(186,'2026/000033',7.65,0.38,7.65,NULL,11,1,'2026-05-23 12:04:32','23/05/2026 11:04:28','RDCF01;CD01004990-1;TESTFACTURESQFGK23DFLVM3;A1720894F;20260523110428','TEST-FACT-URES-QFGK-23DF-LVM3','271/292 FV','CD01004990-1','',NULL,'product',NULL,NULL,NULL,NULL,NULL),(187,'2026/000034',7.65,0.38,7.65,NULL,11,1,'2026-05-25 16:45:27','25/05/2026 15:45:23','RDCF01;CD01004990-1;TESTFACTURESWU4LA7F7BXRV;A1720894F;20260525154523','TEST-FACT-URES-WU4L-A7F7-BXRV','310/337 FV','CD01004990-1','',NULL,'product',NULL,NULL,NULL,NULL,NULL),(188,'2026/000035',7.65,0.38,7.65,NULL,11,1,'2026-05-25 17:17:33','25/05/2026 16:17:29','RDCF01;CD01004990-1;TESTFACTURESOKIGNKVIJKHI;A1720894F;20260525161729','TEST-FACT-URES-OKIG-NKVI-JKHI','312/339 FV','CD01004990-1','',NULL,'product',NULL,NULL,NULL,NULL,NULL),(189,'2026/000036',4.25,0.21,4.25,NULL,11,1,'2026-05-25 17:21:37','25/05/2026 16:21:33','RDCF01;CD01004990-1;TESTFACTURES5PNBTLES5NAY;A1720894F;20260525162133','TEST-FACT-URES-5PNB-TLES-5NAY','313/340 FV','CD01004990-1','',NULL,'product',NULL,NULL,NULL,NULL,NULL),(190,'2026/000037',10000.00,1600.00,10000.00,NULL,11,1,'2026-05-25 17:24:37','25/05/2026 16:24:33','RDCF01;CD01004990-1;TESTFACTURESTIZQPEH66VNY;A1720894F;20260525162433','TEST-FACT-URES-TIZQ-PEH6-6VNY','314/341 FV','CD01004990-1','',NULL,'product',NULL,NULL,NULL,NULL,NULL),(191,'2026/000038',4.25,0.21,4.25,NULL,11,1,'2026-05-25 17:26:06','25/05/2026 16:26:03','RDCF01;CD01004990-1;TESTFACTURES25I6NQSZY4JO;A1720894F;20260525162603','TEST-FACT-URES-25I6-NQSZ-Y4JO','315/342 FV','CD01004990-1','',NULL,'product',NULL,NULL,NULL,NULL,NULL),(192,'2026/000039',900.00,0.00,900.00,NULL,11,1,'2026-05-25 17:34:57','25/05/2026 16:34:53','RDCF01;CD01004990-1;TESTFACTURESZIUHFYZAQQOZ;A1720894F;20260525163453','TEST-FACT-URES-ZIUH-FYZA-QQOZ','316/343 FV','CD01004990-1','',NULL,'product',NULL,NULL,NULL,NULL,NULL),(193,'2026/000040',900.00,0.00,900.00,NULL,11,1,'2026-05-25 17:39:53','25/05/2026 16:39:49','RDCF01;CD01004990-1;TESTFACTURESSS5YOMQQMWEH;A1720894F;20260525163949','TEST-FACT-URES-SS5Y-OMQQ-MWEH','317/344 FV','CD01004990-1','',NULL,'product',NULL,NULL,NULL,NULL,NULL),(194,'2026/000041',10000.00,1600.00,10000.00,NULL,11,1,'2026-05-25 18:16:17','25/05/2026 17:16:13','RDCF01;CD01004990-1;TESTFACTURESTG3EEBN5V7A3;A1720894F;20260525171613','TEST-FACT-URES-TG3E-EBN5-V7A3','319/346 FV','CD01004990-1','',NULL,'product',NULL,NULL,NULL,NULL,NULL),(195,'2026/000042',10032.73,1601.64,10032.73,NULL,11,1,'2026-05-25 18:22:59','25/05/2026 17:22:55','RDCF01;CD01004990-1;TESTFACTURESJONMOKFOIW3N;A1720894F;20260525172255','TEST-FACT-URES-JONM-OKFO-IW3N','320/347 FV','CD01004990-1','',NULL,'product',NULL,NULL,NULL,NULL,NULL),(196,'2026/000043',2.55,0.13,2.55,NULL,11,1,'2026-05-25 18:30:08','25/05/2026 17:30:04','RDCF01;CD01004990-1;TESTFACTURESWGGSD5WZWZNR;A1720894F;20260525173004','TEST-FACT-URES-WGGS-D5WZ-WZNR','321/348 FV','CD01004990-1','',NULL,'product',NULL,NULL,NULL,NULL,NULL),(197,'2026/000044',7.65,0.38,7.65,NULL,11,1,'2026-05-25 18:46:55','25/05/2026 17:46:51','RDCF01;CD01004990-1;TESTFACTURESHTBIKZFHHOEC;A1720894F;20260525174651','TEST-FACT-URES-HTBI-KZFH-HOEC','322/349 FV','CD01004990-1','',NULL,'product',NULL,NULL,NULL,NULL,NULL),(198,'2026/000045',76.50,3.83,76.50,NULL,11,1,'2026-05-25 18:59:07','25/05/2026 17:59:03','RDCF01;CD01004990-1;TESTFACTURESO2GLUCZB3RWA;A1720894F;20260525175903','TEST-FACT-URES-O2GL-UCZB-3RWA','323/350 FV','CD01004990-1','',NULL,'product',NULL,NULL,NULL,NULL,NULL),(199,'2026/000046',10000.00,1600.00,10000.00,NULL,11,1,'2026-05-25 19:00:45','25/05/2026 18:00:40','RDCF01;CD01004990-1;TESTFACTURESLURQVRKFNFEE;A1720894F;20260525180040','TEST-FACT-URES-LURQ-VRKF-NFEE','324/351 FV','CD01004990-1','',NULL,'product',NULL,NULL,NULL,NULL,NULL),(200,'2026/000047',900.00,0.00,900.00,NULL,11,1,'2026-05-25 19:04:30','25/05/2026 18:04:26','RDCF01;CD01004990-1;TESTFACTURESS2DPE6XXNIXW;A1720894F;20260525180426','TEST-FACT-URES-S2DP-E6XX-NIXW','325/352 FV','CD01004990-1','',NULL,'product',NULL,NULL,NULL,NULL,NULL),(201,'2026/000048',10000.00,1600.00,10000.00,NULL,11,1,'2026-05-27 17:24:47','27/05/2026 16:24:46','RDCF01;CD01004990-1;TESTFACTURESLNZUSYAZW3Y6;A1720894F;20260527162446','TEST-FACT-URES-LNZU-SYAZ-W3Y6','334/361 FV','CD01004990-1','',NULL,'product',NULL,NULL,NULL,NULL,NULL),(202,'2026/000049',10000.00,1600.00,10000.00,NULL,11,1,'2026-05-27 18:06:10','27/05/2026 17:06:08','RDCF01;CD01004990-1;TESTFACTURES6CGIXMF27LXB;A1720894F;20260527170608','TEST-FACT-URES-6CGI-XMF2-7LXB','335/362 FV','CD01004990-1','',NULL,'product',NULL,NULL,NULL,NULL,NULL),(203,'2026/000050',10000.00,1600.00,10000.00,NULL,11,1,'2026-05-27 18:20:13','27/05/2026 17:20:11','RDCF01;CD01004990-1;TESTFACTURESMJLAPPFBHPV2;A1720894F;20260527172011','TEST-FACT-URES-MJLA-PPFB-HPV2','336/363 FV','CD01004990-1','',NULL,'product',NULL,NULL,NULL,NULL,NULL),(204,'2026/000051',10900.00,1744.00,10900.00,NULL,11,1,'2026-05-27 18:21:36','27/05/2026 17:21:34','RDCF01;CD01004990-1;TESTFACTURES5DANVPCWNNWY;A1720894F;20260527172134','TEST-FACT-URES-5DAN-VPCW-NNWY','337/364 FV','CD01004990-1','',NULL,'product',NULL,NULL,NULL,NULL,NULL),(205,'2026/000052',10906.80,1745.09,10906.80,NULL,11,1,'2026-05-27 18:22:25','27/05/2026 17:22:23','RDCF01;CD01004990-1;TESTFACTURESI3LIGTM2MSZ5;A1720894F;20260527172223','TEST-FACT-URES-I3LI-GTM2-MSZ5','338/365 FV','CD01004990-1','',NULL,'product',NULL,NULL,NULL,NULL,NULL),(206,'2026/000053',10000.00,0.00,10000.00,NULL,11,1,'2026-05-27 18:24:01','27/05/2026 17:23:59','RDCF01;CD01004990-1;TESTFACTURES5RE2VT3BHQO2;A1720894F;20260527172359','TEST-FACT-URES-5RE2-VT3B-HQO2','339/366 FV','CD01004990-1','',NULL,'product',NULL,NULL,NULL,NULL,NULL),(207,'2026/000054',10907.65,145.22,10907.65,NULL,11,1,'2026-05-27 18:28:48','27/05/2026 17:28:47','RDCF01;CD01004990-1;TESTFACTURESQDBHXVCHBLNW;A1720894F;20260527172847','TEST-FACT-URES-QDBH-XVCH-BLNW','340/367 FV','CD01004990-1','',NULL,'product',NULL,NULL,NULL,NULL,NULL),(208,'2026/000055',10000.00,0.00,10000.00,NULL,11,1,'2026-05-27 18:30:13','27/05/2026 17:30:11','RDCF01;CD01004990-1;TESTFACTURESYBRP3TMGUHME;A1720894F;20260527173011','TEST-FACT-URES-YBRP-3TMG-UHME','341/368 FV','CD01004990-1','',NULL,'product',NULL,NULL,NULL,NULL,NULL),(209,'2026/000056',900.00,0.00,900.00,NULL,11,1,'2026-05-27 18:37:14','27/05/2026 17:37:13','RDCF01;CD01004990-1;TESTFACTURESCZ4KHSZYXT23;A1720894F;20260527173713','TEST-FACT-URES-CZ4K-HSZY-XT23','342/369 FV','CD01004990-1','',NULL,'product',NULL,NULL,NULL,NULL,NULL),(210,'2026/000057',900.00,0.00,900.00,NULL,11,1,'2026-05-27 19:40:23','27/05/2026 18:40:19','RDCF01;CD01004990-1;TESTFACTURESZLXDAWYZ2VIQ;A1720894F;20260527184019','TEST-FACT-URES-ZLXD-AWYZ-2VIQ','343/370 FV','CD01004990-1','',NULL,'product',NULL,NULL,NULL,NULL,NULL),(211,'2026/000058',30900.00,0.00,30900.00,NULL,11,1,'2026-05-27 19:44:31','27/05/2026 18:44:29','RDCF01;CD01004990-1;TESTFACTURESIOFL4UNOI3SW;A1720894F;20260527184429','TEST-FACT-URES-IOFL-4UNO-I3SW','344/371 FV','CD01004990-1','',NULL,'product',NULL,NULL,NULL,NULL,NULL),(212,'2026/000059',900.00,0.00,900.00,NULL,11,1,'2026-05-27 19:49:22','27/05/2026 18:49:20','RDCF01;CD01004990-1;TESTFACTURES7TJTL4JURFBM;A1720894F;20260527184920','TEST-FACT-URES-7TJT-L4JU-RFBM','345/372 FV','CD01004990-1','',NULL,'product',NULL,NULL,NULL,NULL,NULL),(213,'2026/000060',10000.00,0.00,10000.00,NULL,11,1,'2026-05-27 19:54:15','27/05/2026 18:54:13','RDCF01;CD01004990-1;TESTFACTURESGKBFCQFCJNTQ;A1720894F;20260527185413','TEST-FACT-URES-GKBF-CQFC-JNTQ','347/374 FV','CD01004990-1','',NULL,'product',NULL,NULL,NULL,NULL,NULL),(214,'2026/000061',10000.00,0.00,10000.00,NULL,11,1,'2026-05-27 19:57:28','27/05/2026 18:57:26','RDCF01;CD01004990-1;TESTFACTURESSDME6UGUKDGR;A1720894F;20260527185726','TEST-FACT-URES-SDME-6UGU-KDGR','348/375 FV','CD01004990-1','',NULL,'product',NULL,NULL,NULL,NULL,NULL),(215,'2026/000062',10000.00,0.00,10000.00,NULL,11,1,'2026-05-27 19:59:41','27/05/2026 18:59:39','RDCF01;CD01004990-1;TESTFACTURESVEKJUO5PEKVT;A1720894F;20260527185939','TEST-FACT-URES-VEKJ-UO5P-EKVT','349/376 FV','CD01004990-1','',NULL,'product',NULL,NULL,NULL,NULL,NULL),(216,'2026/000063',10000.00,0.00,10000.00,NULL,11,1,'2026-05-27 20:02:13','27/05/2026 19:02:10','RDCF01;CD01004990-1;TESTFACTURESUGZ4HMJPWE2I;A1720894F;20260527190210','TEST-FACT-URES-UGZ4-HMJP-WE2I','350/377 FV','CD01004990-1','',NULL,'product',NULL,NULL,NULL,NULL,NULL),(217,'2026/000064',10000.00,0.00,10000.00,NULL,11,1,'2026-05-27 20:03:00','27/05/2026 19:02:58','RDCF01;CD01004990-1;TESTFACTURESPD576HLNQRJQ;A1720894F;20260527190258','TEST-FACT-URES-PD57-6HLN-QRJQ','351/378 FV','CD01004990-1','',NULL,'product',NULL,NULL,NULL,NULL,NULL),(218,'2026/000065',10000.00,0.00,10000.00,NULL,11,1,'2026-05-27 20:04:27','27/05/2026 19:04:25','RDCF01;CD01004990-1;TESTFACTURESMH7FHBLBXGCS;A1720894F;20260527190425','TEST-FACT-URES-MH7F-HBLB-XGCS','352/379 FV','CD01004990-1','',NULL,'product',NULL,NULL,NULL,NULL,NULL),(219,'2026/000066',20000.00,0.00,20000.00,NULL,11,1,'2026-05-27 20:05:15','27/05/2026 19:05:13','RDCF01;CD01004990-1;TESTFACTURESOB2GBRW2GLSR;A1720894F;20260527190513','TEST-FACT-URES-OB2G-BRW2-GLSR','353/380 FV','CD01004990-1','',NULL,'product',NULL,NULL,NULL,NULL,NULL),(220,'2026/000067',20000.00,0.00,20000.00,NULL,11,1,'2026-05-27 20:06:26','27/05/2026 19:06:24','RDCF01;CD01004990-1;TESTFACTURESM5AOILADDVDO;A1720894F;20260527190624','TEST-FACT-URES-M5AO-ILAD-DVDO','354/381 FV','CD01004990-1','',NULL,'product',NULL,NULL,NULL,NULL,NULL),(221,'2026/000068',10000.00,0.00,10000.00,NULL,11,1,'2026-05-27 20:10:19','27/05/2026 19:10:17','RDCF01;CD01004990-1;TESTFACTURESQH4NNQMMDN26;A1720894F;20260527191017','TEST-FACT-URES-QH4N-NQMM-DN26','355/382 FV','CD01004990-1','',NULL,'product',NULL,NULL,NULL,NULL,NULL),(222,'2026/000069',10000.00,0.00,10000.00,NULL,11,1,'2026-05-27 20:11:32','27/05/2026 19:11:30','RDCF01;CD01004990-1;TESTFACTURESW6AWIZRIWZOE;A1720894F;20260527191130','TEST-FACT-URES-W6AW-IZRI-WZOE','356/383 FV','CD01004990-1','',NULL,'product',NULL,NULL,NULL,NULL,NULL),(223,'2026/000070',10000.00,0.00,10000.00,NULL,11,1,'2026-05-27 20:12:33','27/05/2026 19:12:31','RDCF01;CD01004990-1;TESTFACTURESATW6A36G54R6;A1720894F;20260527191231','TEST-FACT-URES-ATW6-A36G-54R6','357/384 FV','CD01004990-1','',NULL,'product',NULL,NULL,NULL,NULL,NULL),(224,'2026/000071',10000.00,0.00,10000.00,NULL,11,1,'2026-05-27 20:14:34','27/05/2026 19:14:32','RDCF01;CD01004990-1;TESTFACTURESBMQSJEGYM46Z;A1720894F;20260527191432','TEST-FACT-URES-BMQS-JEGY-M46Z','358/385 FV','CD01004990-1','',NULL,'product',NULL,NULL,NULL,NULL,NULL),(225,'2026/000072',10000.00,0.00,10000.00,NULL,11,1,'2026-05-27 20:15:18','27/05/2026 19:15:16','RDCF01;CD01004990-1;TESTFACTURESJIIQWMYTCL5D;A1720894F;20260527191516','TEST-FACT-URES-JIIQ-WMYT-CL5D','359/386 FV','CD01004990-1','',NULL,'product',NULL,NULL,NULL,NULL,NULL),(226,'2026/000073',10000.00,0.00,10000.00,NULL,11,1,'2026-05-27 20:16:26','27/05/2026 19:16:24','RDCF01;CD01004990-1;TESTFACTURES46ISTEATIOJH;A1720894F;20260527191624','TEST-FACT-URES-46IS-TEAT-IOJH','360/387 FV','CD01004990-1','',NULL,'product',NULL,NULL,NULL,NULL,NULL),(227,'2026/000074',10000.00,0.00,10000.00,NULL,11,1,'2026-05-27 20:18:53','27/05/2026 19:18:51','RDCF01;CD01004990-1;TESTFACTURESNUXGX4UHO5ZJ;A1720894F;20260527191851','TEST-FACT-URES-NUXG-X4UH-O5ZJ','361/388 FV','CD01004990-1','',NULL,'product',NULL,NULL,NULL,NULL,NULL),(228,'2026/000075',20000.00,0.00,20000.00,NULL,11,1,'2026-05-27 20:20:43','27/05/2026 19:20:40','RDCF01;CD01004990-1;TESTFACTURESH7QF7AOKHAO7;A1720894F;20260527192040','TEST-FACT-URES-H7QF-7AOK-HAO7','362/389 FV','CD01004990-1','',NULL,'product',NULL,NULL,NULL,NULL,NULL),(229,'2026/000076',30000.00,0.00,30000.00,NULL,11,1,'2026-05-27 20:22:19','27/05/2026 19:22:17','RDCF01;CD01004990-1;TESTFACTURESRCEA5V3RIJSU;A1720894F;20260527192217','TEST-FACT-URES-RCEA-5V3R-IJSU','363/390 FV','CD01004990-1','',NULL,'product',NULL,NULL,NULL,NULL,NULL),(230,'2026/000077',20000.00,0.00,20000.00,NULL,11,1,'2026-05-27 20:23:15','27/05/2026 19:23:13','RDCF01;CD01004990-1;TESTFACTURES64FRM3RCWKVV;A1720894F;20260527192313','TEST-FACT-URES-64FR-M3RC-WKVV','364/391 FV','CD01004990-1','',NULL,'product',NULL,NULL,NULL,NULL,NULL),(231,'2026/000078',10000.00,0.00,10000.00,NULL,11,1,'2026-05-27 20:24:12','27/05/2026 19:24:10','RDCF01;CD01004990-1;TESTFACTURESSKO2X2CG3GZX;A1720894F;20260527192410','TEST-FACT-URES-SKO2-X2CG-3GZX','365/392 FV','CD01004990-1','',NULL,'product',NULL,NULL,NULL,NULL,NULL),(232,'2026/000079',10000.00,0.00,10000.00,NULL,11,1,'2026-05-27 20:27:07','27/05/2026 19:27:04','RDCF01;CD01004990-1;TESTFACTURESENS3FI4XCKSD;A1720894F;20260527192704','TEST-FACT-URES-ENS3-FI4X-CKSD','366/393 FV','CD01004990-1','',NULL,'product',NULL,NULL,NULL,NULL,NULL),(233,'2026/000080',10000.00,0.00,10000.00,NULL,11,1,'2026-05-27 20:27:46','27/05/2026 19:27:44','RDCF01;CD01004990-1;TESTFACTURESC3ZTLV7L4GZP;A1720894F;20260527192744','TEST-FACT-URES-C3ZT-LV7L-4GZP','367/394 FV','CD01004990-1','',NULL,'product',NULL,NULL,NULL,NULL,NULL),(234,'2026/000081',10000.00,0.00,10000.00,NULL,11,1,'2026-05-27 20:28:26','27/05/2026 19:28:23','RDCF01;CD01004990-1;TESTFACTURESQAJA3HW2XPK7;A1720894F;20260527192823','TEST-FACT-URES-QAJA-3HW2-XPK7','368/395 FV','CD01004990-1','',NULL,'product',NULL,NULL,NULL,NULL,NULL),(235,'2026/000082',20007.65,1.22,20007.65,NULL,11,1,'2026-05-27 20:29:32','27/05/2026 19:29:29','RDCF01;CD01004990-1;TESTFACTURESFY2BGLW7WLJW;A1720894F;20260527192929','TEST-FACT-URES-FY2B-GLW7-WLJW','369/396 FV','CD01004990-1','',NULL,'product',NULL,NULL,NULL,NULL,NULL),(236,'2026/000083',6980.68,0.00,6980.68,NULL,11,1,'2026-05-30 11:23:12','30/05/2026 10:23:10','RDCF01;CD01004990-1;TESTFACTURESN2OGTAY4Z6FK;A1720894F;20260530102310','TEST-FACT-URES-N2OG-TAY4-Z6FK','408/446 FV','CD01004990-1',NULL,NULL,'product',NULL,NULL,NULL,NULL,'ELECTRICITE'),(237,'2026/000084',10000.00,0.00,10000.00,NULL,11,1,'2026-05-30 11:26:36','30/05/2026 10:26:34','RDCF01;CD01004990-1;TESTFACTURESCISRJX5SZU5U;A1720894F;20260530102634','TEST-FACT-URES-CISR-JX5S-ZU5U','409/447 FV','CD01004990-1','',NULL,'product',NULL,NULL,NULL,NULL,NULL),(238,'2026/000085',6980.68,0.00,6980.68,NULL,11,1,'2026-05-30 11:35:52','30/05/2026 10:35:50','RDCF01;CD01004990-1;TESTFACTURESXTRQAO3RIWOU;A1720894F;20260530103550','TEST-FACT-URES-XTRQ-AO3R-IWOU','410/448 FV','CD01004990-1',NULL,NULL,'product',NULL,NULL,NULL,NULL,'ELECTRICITE'),(239,'2026/000086',5177.00,0.00,5177.00,NULL,11,1,'2026-05-30 11:50:02','30/05/2026 10:50:00','RDCF01;CD01004990-1;TESTFACTURESZCFLVSYJQVWP;A1720894F;20260530105000','TEST-FACT-URES-ZCFL-VSYJ-QVWP','412/450 FV','CD01004990-1',NULL,NULL,'product',NULL,NULL,NULL,NULL,'ELECTRICITE'),(240,'2026/000087',120.80,0.00,120.80,NULL,11,1,'2026-05-30 11:54:56','30/05/2026 10:54:53','RDCF01;CD01004990-1;TESTFACTURESFLNTH5TK5OEZ;A1720894F;20260530105453','TEST-FACT-URES-FLNT-H5TK-5OEZ','413/451 FV','CD01004990-1',NULL,NULL,'product',NULL,NULL,NULL,NULL,'ELECTRICITE'),(241,'2026/000088',120.80,0.00,120.80,NULL,11,1,'2026-05-30 11:58:59','30/05/2026 10:58:57','RDCF01;CD01004990-1;TESTFACTURES6JXDQGNXTZAZ;A1720894F;20260530105857','TEST-FACT-URES-6JXD-QGNX-TZAZ','414/452 FV','CD01004990-1',NULL,NULL,'product',NULL,NULL,NULL,NULL,'ELECTRICITE'),(242,'2026/000089',7.65,1.22,7.65,NULL,11,1,'2026-05-30 12:10:07','30/05/2026 11:10:05','RDCF01;CD01004990-1;TESTFACTURESKYXO6YFJWDQT;A1720894F;20260530111005','TEST-FACT-URES-KYXO-6YFJ-WDQT','415/453 FV','CD01004990-1','',NULL,'product',NULL,NULL,NULL,NULL,NULL),(243,'2026/000090',5177.00,0.00,5177.00,NULL,11,1,'2026-05-30 12:19:01','30/05/2026 11:18:59','RDCF01;CD01004990-1;TESTFACTURES2MR2R7SVXJQD;A1720894F;20260530111859','TEST-FACT-URES-2MR2-R7SV-XJQD','417/455 FV','CD01004990-1',NULL,NULL,'product',NULL,NULL,NULL,NULL,'ELECTRICITE'),(244,'2026/000091',120.80,0.00,120.80,NULL,11,1,'2026-05-30 12:30:09','30/05/2026 11:30:06','RDCF01;CD01004990-1;TESTFACTURESOBBLGOLISHKA;A1720894F;20260530113006','TEST-FACT-URES-OBBL-GOLI-SHKA','418/456 FV','CD01004990-1',NULL,NULL,'product',NULL,NULL,NULL,NULL,'ELECTRICITE'),(245,'2026/000092',120.80,0.00,120.80,NULL,11,1,'2026-05-30 12:35:26','30/05/2026 11:35:24','RDCF01;CD01004990-1;TESTFACTURESLHZRTBJEYMIC;A1720894F;20260530113524','TEST-FACT-URES-LHZR-TBJE-YMIC','419/457 FV','CD01004990-1',NULL,NULL,'product',NULL,NULL,NULL,NULL,'ELECTRICITE'),(246,'2026/000093',6415.13,0.00,6415.13,NULL,11,1,'2026-05-30 12:51:32','30/05/2026 11:51:29','RDCF01;CD01004990-1;TESTFACTURESGKYVWKCKGNQC;A1720894F;20260530115129','TEST-FACT-URES-GKYV-WKCK-GNQC','420/458 FV','CD01004990-1',NULL,NULL,'product',NULL,NULL,NULL,NULL,'ELECTRICITE'),(247,'2026/000094',5.10,0.82,5.10,NULL,11,1,'2026-05-31 18:23:47','31/05/2026 17:23:46','RDCF01;CD01004990-1;TESTFACTURESE7KV4YRYQSAS;A1720894F;20260531172346','TEST-FACT-URES-E7KV-4YRY-QSAS','421/459 FV','CD01004990-1','',NULL,'product',NULL,NULL,NULL,NULL,NULL),(248,'2026/000095',6.80,1.09,6.80,NULL,11,1,'2026-05-31 18:38:39','31/05/2026 17:38:38','RDCF01;CD01004990-1;TESTFACTURESUPBD6AFSRZ6D;A1720894F;20260531173838','TEST-FACT-URES-UPBD-6AFS-RZ6D','422/460 FV','CD01004990-1','',NULL,'product',NULL,NULL,NULL,NULL,NULL),(249,'2026/000096',7.65,1.22,7.65,NULL,11,1,'2026-05-31 18:40:13','31/05/2026 17:40:12','RDCF01;CD01004990-1;TESTFACTURES4EPWNI47D6GD;A1720894F;20260531174012','TEST-FACT-URES-4EPW-NI47-D6GD','423/461 FV','CD01004990-1','',NULL,'product',NULL,NULL,NULL,NULL,NULL),(250,'2026/000097',7.65,1.22,7.65,NULL,11,1,'2026-05-31 18:44:09','31/05/2026 17:44:08','RDCF01;CD01004990-1;TESTFACTURESOVARAW6KVTU3;A1720894F;20260531174408','TEST-FACT-URES-OVAR-AW6K-VTU3','424/462 FV','CD01004990-1','',NULL,'product',NULL,NULL,NULL,NULL,NULL),(251,'2026/000098',20000.00,0.00,20000.00,NULL,11,1,'2026-05-31 18:51:17','31/05/2026 17:51:15','RDCF01;CD01004990-1;TESTFACTURESWS3CMDTUFGYB;A1720894F;20260531175115','TEST-FACT-URES-WS3C-MDTU-FGYB','425/463 FV','CD01004990-1','',NULL,'product',NULL,NULL,NULL,NULL,NULL),(252,'2026/000099',3596.98,0.00,3596.98,NULL,11,1,'2026-05-31 19:31:43','31/05/2026 18:31:41','RDCF01;CD01004990-1;TESTFACTURES6V6GNJ5WHLG3;A1720894F;20260531183141','TEST-FACT-URES-6V6G-NJ5W-HLG3','426/464 FV','CD01004990-1',NULL,NULL,'product',NULL,NULL,NULL,NULL,'EAU'),(253,'2026/000100',3596.98,0.00,3596.98,NULL,11,1,'2026-05-31 19:39:36','31/05/2026 18:39:35','RDCF01;CD01004990-1;TESTFACTURESQ4OCDTQ6AEW7;A1720894F;20260531183935','TEST-FACT-URES-Q4OC-DTQ6-AEW7','427/465 FV','CD01004990-1',NULL,NULL,'product',NULL,NULL,NULL,NULL,'ELECTRICITE'),(254,'2026/000101',3596.98,0.00,3596.98,NULL,11,1,'2026-05-31 19:44:35','31/05/2026 18:44:33','RDCF01;CD01004990-1;TESTFACTURESSWCY3FK5KTD5;A1720894F;20260531184433','TEST-FACT-URES-SWCY-3FK5-KTD5','428/466 FV','CD01004990-1',NULL,NULL,'product',NULL,NULL,NULL,NULL,'ELECTRICITE'),(256,'2026/000102',7.65,1.22,7.65,NULL,11,1,'2026-06-02 23:01:04',NULL,'RDCF01;CD01004990-1;TESTFACTURESYDTVPGS2VUOF;A1720894F;20260602220101','TEST-FACT-URES-YDTV-PGS2-VUOF','468/506 FV','CD01004990-1','',NULL,'product',NULL,NULL,NULL,NULL,NULL),(257,'2026/000103',3596.98,0.00,3596.98,NULL,11,1,'2026-06-02 23:14:10',NULL,'RDCF01;CD01004990-1;TESTFACTURES6QHUCX5ABNYM;A1720894F;20260602221408','TEST-FACT-URES-6QHU-CX5A-BNYM','469/507 FV','CD01004990-1',NULL,NULL,'product',NULL,NULL,NULL,NULL,'ELECTRICITE'),(258,'2026/000104',3596.98,0.00,3596.98,NULL,11,1,'2026-06-03 10:35:43',NULL,'RDCF01;CD01004990-1;TESTFACTURESMEGDZGL4CLTY;A1720894F;20260603093538','TEST-FACT-URES-MEGD-ZGL4-CLTY','471/509 FV','CD01004990-1',NULL,NULL,'product',NULL,NULL,NULL,NULL,'ELECTRICITE'),(259,'2026/000105',3468.98,0.00,3468.98,NULL,11,1,'2026-06-03 10:38:11',NULL,'RDCF01;CD01004990-1;TESTFACTURES57AI5EZGTNWQ;A1720894F;20260603093806','TEST-FACT-URES-57AI-5EZG-TNWQ','472/510 FV','CD01004990-1',NULL,NULL,'product',NULL,NULL,NULL,NULL,'ELECTRICITE'),(260,'2026/000106',10000.00,0.00,10000.00,NULL,11,1,'2026-06-11 18:02:54',NULL,'RDCF01;CD01004990-1;TESTFACTURESDQSG3MVMGCNP;A1720894F;20260611170256','TEST-FACT-URES-DQSG-3MVM-GCNP','538/578 FV','CD01004990-1','',NULL,'product',NULL,NULL,NULL,NULL,NULL),(261,'2026/000107',10000.00,0.00,10000.00,NULL,11,1,'2026-06-11 19:02:07',NULL,'RDCF01;CD01004990-1;TESTFACTURESFPRNYXA53B5J;A1720894F;20260611180210','TEST-FACT-URES-FPRN-YXA5-3B5J','539/579 FV','CD01004990-1','',NULL,'product',NULL,NULL,NULL,NULL,NULL),(262,'2026/000108',10000.00,0.00,10000.00,NULL,11,1,'2026-06-11 19:04:23',NULL,'RDCF01;CD01004990-1;TESTFACTURESGXYXLJRBADUM;A1720894F;20260611180426','TEST-FACT-URES-GXYX-LJRB-ADUM','540/580 FV','CD01004990-1','',NULL,'product',NULL,NULL,NULL,NULL,NULL),(263,'2026/000109',10000.00,0.00,10000.00,NULL,11,1,'2026-06-11 19:15:29',NULL,'RDCF01;CD01004990-1;TESTFACTURESUS753LXN22HL;A1720894F;20260611181533','TEST-FACT-URES-US75-3LXN-22HL','541/581 FV','CD01004990-1','',NULL,'product',NULL,NULL,NULL,NULL,NULL),(264,'2026/000110',10000.00,0.00,10000.00,NULL,11,1,'2026-06-11 19:27:34',NULL,'RDCF01;CD01004990-1;TESTFACTURES6NC5UVG6AEMA;A1720894F;20260611182738','TEST-FACT-URES-6NC5-UVG6-AEMA','542/582 FV','CD01004990-1','',NULL,'product',NULL,NULL,NULL,NULL,NULL),(265,'2026/000111',10000.00,0.00,10000.00,NULL,11,1,'2026-06-11 19:30:04',NULL,'RDCF01;CD01004990-1;TESTFACTURESAWHD62LMXMFO;A1720894F;20260611183008','TEST-FACT-URES-AWHD-62LM-XMFO','543/583 FV','CD01004990-1','',NULL,'product',NULL,NULL,NULL,NULL,NULL),(266,'2026/000112',10000.00,0.00,10000.00,NULL,11,1,'2026-06-11 19:32:33',NULL,'RDCF01;CD01004990-1;TESTFACTUREST4BNWX2P3M5J;A1720894F;20260611183237','TEST-FACT-URES-T4BN-WX2P-3M5J','544/584 FV','CD01004990-1','',NULL,'product',NULL,NULL,NULL,NULL,NULL),(267,'2026/000113',10000.00,0.00,10000.00,NULL,11,1,'2026-06-11 19:39:21',NULL,'RDCF01;CD01004990-1;TESTFACTURES4QITECGUHZ5V;A1720894F;20260611183925','TEST-FACT-URES-4QIT-ECGU-HZ5V','545/585 FV','CD01004990-1','',NULL,'product',NULL,NULL,NULL,NULL,NULL),(268,'2026/000114',10000.00,0.00,10000.00,NULL,11,1,'2026-06-12 20:29:19',NULL,'RDCF01;CD01004990-1;TESTFACTURES5LT4H2YBVXX3;A1720894F;20260612192917','TEST-FACT-URES-5LT4-H2YB-VXX3','546/586 FV','CD01004990-1','',NULL,'product',NULL,NULL,NULL,NULL,NULL),(269,'2026/000115',10000.00,0.00,10000.00,NULL,11,1,'2026-06-12 20:45:25',NULL,'RDCF01;CD01004990-1;TESTFACTURESIDAZHL5SRYOO;A1720894F;20260612194523','TEST-FACT-URES-IDAZ-HL5S-RYOO','547/587 FV','CD01004990-1','',NULL,'product',NULL,NULL,NULL,NULL,NULL),(270,'2026/000116',10000.00,0.00,10000.00,NULL,11,1,'2026-06-12 20:58:00',NULL,'RDCF01;CD01004990-1;TESTFACTURES4IYVILLCKJ44;A1720894F;20260612195758','TEST-FACT-URES-4IYV-ILLC-KJ44','548/588 FV','CD01004990-1','',NULL,'product',NULL,NULL,NULL,NULL,NULL),(271,'2026/000117',10000.00,0.00,10000.00,NULL,11,1,'2026-06-12 21:03:11',NULL,'RDCF01;CD01004990-1;TESTFACTURESWFJQITQFF6ZE;A1720894F;20260612200306','TEST-FACT-URES-WFJQ-ITQF-F6ZE','549/589 FV','CD01004990-1','',NULL,'product',NULL,NULL,NULL,NULL,NULL),(272,'2026/000118',10000.00,0.00,10000.00,NULL,11,1,'2026-06-12 21:03:59',NULL,'RDCF01;CD01004990-1;TESTFACTURESE33BD63XYK5V;A1720894F;20260612200358','TEST-FACT-URES-E33B-D63X-YK5V','550/590 FV','CD01004990-1','',NULL,'product',NULL,NULL,NULL,NULL,NULL),(273,'2026/000119',10000.00,0.00,10000.00,NULL,11,1,'2026-06-12 21:12:03',NULL,'RDCF01;CD01004990-1;TESTFACTURES57K7FUQA6G5H;A1720894F;20260612201201','TEST-FACT-URES-57K7-FUQA-6G5H','551/591 FV','CD01004990-1','',NULL,'product',NULL,NULL,NULL,NULL,NULL),(274,'2026/000120',10000.00,0.00,10000.00,NULL,11,1,'2026-06-12 21:18:35',NULL,'RDCF01;CD01004990-1;TESTFACTURESGS5WIN5BSKF4;A1720894F;20260612201833','TEST-FACT-URES-GS5W-IN5B-SKF4','552/592 FV','CD01004990-1','',NULL,'product',NULL,NULL,NULL,NULL,NULL),(275,'2026/000121',10000.00,0.00,10000.00,NULL,11,1,'2026-06-12 21:20:27',NULL,'RDCF01;CD01004990-1;TESTFACTURESAOJX6EQ4TC52;A1720894F;20260612202025','TEST-FACT-URES-AOJX-6EQ4-TC52','553/593 FV','CD01004990-1','',NULL,'product',NULL,NULL,NULL,NULL,NULL),(276,'2026/000122',10000.00,0.00,10000.00,NULL,11,1,'2026-06-12 21:25:33',NULL,'RDCF01;CD01004990-1;TESTFACTURESAHZB33GHLL5J;A1720894F;20260612202531','TEST-FACT-URES-AHZB-33GH-LL5J','554/594 FV','CD01004990-1','',NULL,'product',NULL,NULL,NULL,NULL,NULL),(277,'2026/000123',10000.00,0.00,10000.00,NULL,11,1,'2026-06-12 22:33:18',NULL,'RDCF01;CD01004990-1;TESTFACTURESSWO2F5GEKH4V;A1720894F;20260612213316','TEST-FACT-URES-SWO2-F5GE-KH4V','555/595 FV','CD01004990-1','',NULL,'product',NULL,NULL,NULL,NULL,NULL),(278,'2026/000124',10000.00,0.00,10000.00,NULL,11,1,'2026-06-13 13:17:28',NULL,'RDCF01;CD01004990-1;TESTFACTURES5DNREFAK7JHS;A1720894F;20260613121728','TEST-FACT-URES-5DNR-EFAK-7JHS','556/596 FV','CD01004990-1','',NULL,'product',NULL,NULL,NULL,NULL,NULL),(279,'2026/000125',10000.00,0.00,10000.00,NULL,11,1,'2026-06-13 13:21:51',NULL,'RDCF01;CD01004990-1;TESTFACTURESZ43LANFZ6TY3;A1720894F;20260613122151','TEST-FACT-URES-Z43L-ANFZ-6TY3','557/597 FV','CD01004990-1','',NULL,'product',NULL,NULL,NULL,NULL,NULL),(280,'2026/000126',10000.00,0.00,10000.00,NULL,11,1,'2026-06-13 13:26:17',NULL,'RDCF01;CD01004990-1;TESTFACTURESBJBUNSM5UC4O;A1720894F;20260613122617','TEST-FACT-URES-BJBU-NSM5-UC4O','558/598 FV','CD01004990-1','',NULL,'product',NULL,NULL,NULL,NULL,NULL),(281,'2026/000127',15186.81,13.89,15186.81,NULL,11,1,'2026-06-13 15:09:45',NULL,'RDCF01;CD01004990-1;TESTFACTURESTMLDQYSKJUQS;A1720894F;20260613140945','TEST-FACT-URES-TMLD-QYSK-JUQS','559/599 FV','CD01004990-1','',NULL,'product',NULL,NULL,NULL,NULL,NULL),(282,'2026/000128',50.00,0.00,50.00,NULL,11,1,'2026-06-13 15:19:16',NULL,'RDCF01;CD01004990-1;TESTFACTURES2UHD3Q7MWGMC;A1720894F;20260613141915','TEST-FACT-URES-2UHD-3Q7M-WGMC','560/600 FV','CD01004990-1','',NULL,'product',NULL,NULL,NULL,NULL,NULL),(283,'2026/000129',130.00,12.80,130.00,NULL,11,1,'2026-06-13 15:22:17',NULL,'RDCF01;CD01004990-1;TESTFACTURES6GO24NCWX7HC;A1720894F;20260613142217','TEST-FACT-URES-6GO2-4NCW-X7HC','561/601 FV','CD01004990-1','',NULL,'product',NULL,NULL,NULL,NULL,NULL),(284,'2026/000130',130.00,12.80,130.00,NULL,11,1,'2026-06-13 15:27:20',NULL,'RDCF01;CD01004990-1;TESTFACTURESSZCLPFUYMNY2;A1720894F;20260613142720','TEST-FACT-URES-SZCL-PFUY-MNY2','562/602 FV','CD01004990-1','',NULL,'product',NULL,NULL,NULL,NULL,NULL),(285,'2026/000131',180.00,12.80,180.00,NULL,11,1,'2026-06-13 15:34:17',NULL,'RDCF01;CD01004990-1;TESTFACTURESCC6NMXNQZ7NN;A1720894F;20260613143416','TEST-FACT-URES-CC6N-MXNQ-Z7NN','563/603 FV','CD01004990-1','',NULL,'product',NULL,NULL,NULL,NULL,NULL),(286,'2026/000132',80.00,12.80,80.00,NULL,11,1,'2026-06-13 15:34:54',NULL,'RDCF01;CD01004990-1;TESTFACTURESJRDLL2UMWGOS;A1720894F;20260613143453','TEST-FACT-URES-JRDL-L2UM-WGOS','564/604 FV','CD01004990-1','',NULL,'product',NULL,NULL,NULL,NULL,NULL),(287,'2026/000133',20050.01,0.00,20050.01,NULL,11,1,'2026-06-13 15:37:21',NULL,'RDCF01;CD01004990-1;TESTFACTURESWWFBE62OL7SH;A1720894F;20260613143721','TEST-FACT-URES-WWFB-E62O-L7SH','565/605 FV','CD01004990-1','',NULL,'product',NULL,NULL,NULL,NULL,NULL),(288,'2026/000134',10000.00,0.00,10000.00,NULL,11,1,'2026-06-13 15:38:55',NULL,'RDCF01;CD01004990-1;TESTFACTURESMD7WC6KXN7HI;A1720894F;20260613143854','TEST-FACT-URES-MD7W-C6KX-N7HI','566/606 FV','CD01004990-1','',NULL,'product',NULL,NULL,NULL,NULL,NULL),(289,'2026/000135',10000.00,0.00,10000.00,NULL,11,1,'2026-06-13 15:40:34',NULL,'RDCF01;CD01004990-1;TESTFACTURES7AGWF7VXZ2YG;A1720894F;20260613144034','TEST-FACT-URES-7AGW-F7VX-Z2YG','567/607 FV','CD01004990-1','',NULL,'product',NULL,NULL,NULL,NULL,NULL),(290,'2026/000136',16080.01,12.80,16080.01,NULL,11,1,'2026-06-13 15:42:09',NULL,'RDCF01;CD01004990-1;TESTFACTURESVNRHC3QMJNL3;A1720894F;20260613144208','TEST-FACT-URES-VNRH-C3QM-JNL3','568/608 FV','CD01004990-1','',NULL,'product',NULL,NULL,NULL,NULL,NULL),(291,'2026/000137',80.00,12.80,80.00,NULL,11,1,'2026-06-13 15:49:22',NULL,'RDCF01;CD01004990-1;TESTFACTURESLT2MYNZGESEO;A1720894F;20260613144922','TEST-FACT-URES-LT2M-YNZG-ESEO','569/609 FV','CD01004990-1','',NULL,'product',NULL,NULL,NULL,NULL,NULL),(292,'2026/000138',80.00,12.80,80.00,NULL,11,1,'2026-06-13 15:53:27',NULL,'RDCF01;CD01004990-1;TESTFACTURESOMPXDDEHO7V7;A1720894F;20260613145326','TEST-FACT-URES-OMPX-DDEH-O7V7','570/610 FV','CD01004990-1','',NULL,'product',NULL,NULL,NULL,NULL,NULL),(293,'2026/000139',80.00,12.80,80.00,NULL,11,1,'2026-06-13 15:58:17',NULL,'RDCF01;CD01004990-1;TESTFACTURESM6KHNNK23I3G;A1720894F;20260613145816','TEST-FACT-URES-M6KH-NNK2-3I3G','571/611 FV','CD01004990-1','',NULL,'product',NULL,NULL,NULL,NULL,NULL),(294,'2026/000140',80.00,12.80,80.00,NULL,11,1,'2026-06-13 16:15:53',NULL,'RDCF01;CD01004990-1;TESTFACTURES7O6DFLSYXU5C;A1720894F;20260613151552','TEST-FACT-URES-7O6D-FLSY-XU5C','572/612 FV','CD01004990-1','',NULL,'product',NULL,NULL,NULL,NULL,NULL),(295,'2026/000141',80.00,12.80,80.00,NULL,11,1,'2026-06-13 16:23:27',NULL,'RDCF01;CD01004990-1;TESTFACTURESITTBEB6YA5WT;A1720894F;20260613152326','TEST-FACT-URES-ITTB-EB6Y-A5WT','573/613 FV','CD01004990-1','',NULL,'product',NULL,NULL,NULL,NULL,NULL),(296,'2026/000142',170000.00,0.00,170000.00,NULL,11,1,'2026-06-13 16:24:03',NULL,'RDCF01;CD01004990-1;TESTFACTURESKHNDSRGSN4DY;A1720894F;20260613152402','TEST-FACT-URES-KHND-SRGS-N4DY','574/614 FV','CD01004990-1','',NULL,'product',NULL,NULL,NULL,NULL,NULL),(298,'2026/000144',80.00,12.80,80.00,NULL,11,1,'2026-06-13 16:27:42',NULL,'RDCF01;CD01004990-1;TESTFACTURESPYG3LVSQWLUY;A1720894F;20260613152741','TEST-FACT-URES-PYG3-LVSQ-WLUY','576/616 FV','CD01004990-1','',NULL,'product',NULL,NULL,NULL,NULL,NULL),(300,'2026/000145',80.00,12.80,80.00,NULL,11,1,'2026-06-14 12:41:17',NULL,'RDCF01;CD01004990-1;TESTFACTURESQ77K2PIVQMJQ;A1720894F;20260614114118','TEST-FACT-URES-Q77K-2PIV-QMJQ','588/628 FV','CD01004990-1','',NULL,'product',NULL,NULL,NULL,NULL,NULL),(301,'2026/000146',120.80,0.00,120.80,NULL,11,1,'2026-06-14 16:29:17',NULL,'RDCF01;CD01004990-1;TESTFACTURESLVPKUEW35ZJF;A1720894F;20260614152917','TEST-FACT-URES-LVPK-UEW3-5ZJF','591/631 FV','CD01004990-1',NULL,NULL,'product',NULL,NULL,NULL,NULL,'ELECTRICITE'),(302,'2026/000147',160.00,25.60,160.00,NULL,11,1,'2026-06-15 12:07:52',NULL,'RDCF01;CD01004990-1;TESTFACTURESK5W6LFLW3FQL;A1720894F;20260615110749','TEST-FACT-URES-K5W6-LFLW-3FQL','643/683 FV','CD01004990-1','',3,'product',NULL,NULL,NULL,NULL,NULL),(303,'2026/000148',80.00,12.80,80.00,NULL,11,1,'2026-06-15 12:15:02',NULL,'RDCF01;CD01004990-1;TESTFACTURESOS3TDX5AVFCS;A1720894F;20260615111458','TEST-FACT-URES-OS3T-DX5A-VFCS','644/684 FV','CD01004990-1','',3,'product',NULL,NULL,NULL,NULL,NULL),(304,'2026/000149',80.00,12.80,80.00,NULL,11,1,'2026-06-15 12:53:55',NULL,'RDCF01;CD01004990-1;TESTFACTURESHTBMZVQ7R3RQ;A1720894F;20260615115351','TEST-FACT-URES-HTBM-ZVQ7-R3RQ','645/685 FV','CD01004990-1','',3,'product',NULL,NULL,NULL,NULL,NULL),(305,'2026/000150',80.00,12.80,80.00,NULL,11,1,'2026-06-16 04:55:59',NULL,'RDCF01;CD01004990-1;TESTFACTURESUDWC2VRPNMTW;A1720894F;20260616035555','TEST-FACT-URES-UDWC-2VRP-NMTW','654/694 FV','CD01004990-1','',3,'product',NULL,NULL,NULL,NULL,NULL),(306,'2026/000151',80.00,12.80,80.00,NULL,11,1,'2026-06-16 05:00:06',NULL,'RDCF01;CD01004990-1;TESTFACTURESW3SCOY2X4MUO;A1720894F;20260616040002','TEST-FACT-URES-W3SC-OY2X-4MUO','655/695 FV','CD01004990-1','',3,'product',NULL,NULL,NULL,NULL,NULL),(307,'2026/000152',-80.00,-12.80,-80.00,NULL,11,1,'2026-06-16 21:29:58',NULL,'EN ATTENTE','Nul','0/0','Nul','',3,'product',NULL,NULL,NULL,NULL,NULL),(308,'2026/000153',-80.00,-12.80,-80.00,NULL,11,1,'2026-06-16 21:55:19',NULL,'RDCF01;CD01004990-1;TESTFACTURESDDNM5JTJBVC4;A1720894F;20260616205509','TEST-FACT-URES-DDNM-5JTJ-BVC4','665/707 FV','CD01004990-1','',3,'product',NULL,NULL,NULL,NULL,NULL),(309,'2026/000154',80.00,12.80,80.00,NULL,11,1,'2026-06-16 22:06:12',NULL,'RDCF01;CD01004990-1;TESTFACTURES336N4YA7Z3QX;A1720894F;20260616210612','TEST-FACT-URES-336N-4YA7-Z3QX','666/708 FT','CD01004990-1','',3,'product',NULL,NULL,NULL,NULL,NULL),(310,'2026/000155',-80.00,-12.80,-80.00,NULL,11,1,'2026-06-16 22:13:58',NULL,'RDCF01;CD01004990-1;TESTFACTURESQYQ4SEQNDDHT;A1720894F;20260616211356','TEST-FACT-URES-QYQ4-SEQN-DDHT','25/709 FA','CD01004990-1','',3,'product',NULL,NULL,NULL,NULL,NULL),(311,'2026/000156',80.00,12.80,80.00,NULL,11,1,'2026-06-16 22:37:28',NULL,'RDCF01;CD01004990-1;TESTFACTURES2MMMW5ABR5IF;A1720894F;20260616213727','TEST-FACT-URES-2MMM-W5AB-R5IF','667/710 FT','CD01004990-1','',3,'product',NULL,NULL,NULL,NULL,NULL),(312,'2026/000157',80.00,12.80,80.00,NULL,11,1,'2026-06-16 23:37:35',NULL,'RDCF01;CD01004990-1;TESTFACTURESX2ABPJZOQSZR;A1720894F;20260616223734','TEST-FACT-URES-X2AB-PJZO-QSZR','668/712 FT','CD01004990-1','',3,'product',NULL,NULL,NULL,NULL,NULL),(313,'2026/000158',80.00,12.80,80.00,NULL,11,1,'2026-06-17 10:52:56',NULL,'RDCF01;CD01004990-1;TESTFACTURESDP65JAQQYJOP;A1720894F;20260617095254','TEST-FACT-URES-DP65-JAQQ-YJOP','680/725 FT','CD01004990-1','',3,'product',NULL,NULL,NULL,NULL,NULL),(314,'2026/000159',80.00,12.80,80.00,NULL,11,1,'2026-06-17 10:59:36',NULL,'RDCF01;CD01004990-1;TESTFACTURESGTAIOU2TGOIB;A1720894F;20260617095934','TEST-FACT-URES-GTAI-OU2T-GOIB','681/728 FT','CD01004990-1','',3,'product',NULL,NULL,NULL,NULL,NULL),(315,'2026/000175',-80.00,-12.80,-80.00,NULL,11,1,'2026-06-17 11:01:11',NULL,'RDCF01;CD01004990-1;TESTFACTURESJEJVK6NVPFZW;A1720894F;20260617100108','TEST-FACT-URES-JEJV-K6NV-PFZW','29/729 FA','CD01004990-1','',3,'product',NULL,NULL,NULL,NULL,NULL),(316,'2026/000176',80.00,12.80,80.00,NULL,11,1,'2026-06-17 11:16:30',NULL,'RDCF01;CD01004990-1;TESTFACTURESAEVQM65FA4O5;A1720894F;20260617101628','TEST-FACT-URES-AEVQ-M65F-A4O5','685/733 FT','CD01004990-1','',3,'product',NULL,NULL,NULL,NULL,NULL),(317,'2026/000177',-80.00,-12.80,-80.00,NULL,11,1,'2026-06-17 11:17:38',NULL,'RDCF01;CD01004990-1;TESTFACTURES4GWSHEIDEWEQ;A1720894F;20260617101736','TEST-FACT-URES-4GWS-HEID-EWEQ','30/734 FA','CD01004990-1','',3,'product',NULL,NULL,NULL,NULL,NULL),(318,'2026/000178',80.00,12.80,80.00,NULL,11,1,'2026-06-17 11:19:12',NULL,'RDCF01;CD01004990-1;TESTFACTURESCCOHGE3FZIYF;A1720894F;20260617101910','TEST-FACT-URES-CCOH-GE3F-ZIYF','686/735 FV','CD01004990-1','',3,'product',NULL,NULL,NULL,NULL,NULL),(319,'2026/000179',-80.00,-12.80,-80.00,NULL,11,1,'2026-06-17 11:20:04',NULL,'RDCF01;CD01004990-1;TESTFACTURESDEJOI6DLQYAI;A1720894F;20260617102001','TEST-FACT-URES-DEJO-I6DL-QYAI','31/736 FA','CD01004990-1','',NULL,'product',NULL,NULL,NULL,NULL,NULL),(320,'2026/000180',80.00,12.80,80.00,NULL,11,1,'2026-06-17 11:22:42',NULL,'RDCF01;CD01004990-1;TESTFACTURESKCOPFRYSDC7L;A1720894F;20260617102239','TEST-FACT-URES-KCOP-FRYS-DC7L','687/737 FT','CD01004990-1','',3,'product',NULL,NULL,NULL,NULL,NULL),(321,'2026/000181',-80.00,-12.80,-80.00,NULL,11,1,'2026-06-17 11:24:31',NULL,'RDCF01;CD01004990-1;TESTFACTURESIZRVF2PVGKKO;A1720894F;20260617102429','TEST-FACT-URES-IZRV-F2PV-GKKO','32/738 FA','CD01004990-1','',3,'product',NULL,NULL,NULL,NULL,NULL),(322,'2026/000182',80.00,12.80,80.00,NULL,11,1,'2026-06-17 11:46:34',NULL,'RDCF01;CD01004990-1;TESTFACTURESZY54JBOOJER7;A1720894F;20260617104632','TEST-FACT-URES-ZY54-JBOO-JER7','688/739 FT','CD01004990-1','',3,'product',NULL,NULL,NULL,NULL,NULL),(323,'2026/000183',-80.00,-12.80,-80.00,NULL,11,1,'2026-06-17 11:50:43',NULL,'RDCF01;CD01004990-1;TESTFACTURESNI26ZSLYWFAI;A1720894F;20260617105040','TEST-FACT-URES-NI26-ZSLY-WFAI','33/740 FA','CD01004990-1','',3,'product',NULL,NULL,NULL,NULL,NULL),(324,'2026/000184',80.00,12.80,80.00,NULL,11,1,'2026-06-17 12:04:27',NULL,'RDCF01;CD01004990-1;TESTFACTURESFP4AJUJJ6NAQ;A1720894F;20260617110424','TEST-FACT-URES-FP4A-JUJJ-6NAQ','689/741 FT','CD01004990-1','',3,'product',NULL,NULL,NULL,NULL,NULL),(325,'2026/000185',-80.00,-12.80,-80.00,NULL,11,1,'2026-06-17 12:05:01',NULL,'RDCF01;CD01004990-1;TESTFACTURESMHBENZFI2XYF;A1720894F;20260617110458','TEST-FACT-URES-MHBE-NZFI-2XYF','34/742 FA','CD01004990-1','',3,'product',NULL,NULL,NULL,NULL,NULL),(326,'2026/000186',80.00,12.80,80.00,NULL,11,1,'2026-06-17 12:06:04',NULL,'RDCF01;CD01004990-1;TESTFACTURES5CQPHP523KSN;A1720894F;20260617110601','TEST-FACT-URES-5CQP-HP52-3KSN','690/743 FV','CD01004990-1','',3,'product',NULL,NULL,NULL,NULL,NULL),(327,'2026/000187',-80.00,-12.80,-80.00,NULL,11,1,'2026-06-17 12:28:16',NULL,'RDCF01;CD01004990-1;TESTFACTURESJON3CFHL6YH7;A1720894F;20260617112814','TEST-FACT-URES-JON3-CFHL-6YH7','35/744 FA','CD01004990-1','',3,'product',NULL,NULL,NULL,NULL,NULL),(328,'2026/000188',80.00,12.80,80.00,NULL,11,1,'2026-06-17 12:48:55',NULL,'RDCF01;CD01004990-1;TESTFACTURES6SR7GLKMGHT3;A1720894F;20260617114853','TEST-FACT-URES-6SR7-GLKM-GHT3','691/745 FV','CD01004990-1','',3,'product',NULL,NULL,NULL,NULL,NULL),(329,'2026/000189',-80.00,-12.80,-80.00,NULL,11,1,'2026-06-17 12:49:36',NULL,'RDCF01;CD01004990-1;TESTFACTURESGD6CAYC3D2EY;A1720894F;20260617114933','TEST-FACT-URES-GD6C-AYC3-D2EY','36/746 FA','CD01004990-1','',3,'product',NULL,NULL,NULL,NULL,NULL),(330,'2026/000190',80.00,12.80,80.00,NULL,11,1,'2026-06-17 18:00:51',NULL,'RDCF01;CD01004990-1;TESTFACTURESIPN2XMIU4BE3;A1720894F;20260617170053','TEST-FACT-URES-IPN2-XMIU-4BE3','695/751 FV','CD01004990-1','',3,'product',NULL,NULL,NULL,NULL,NULL),(331,'2026/000191',-80.00,-12.80,-80.00,NULL,11,1,'2026-06-17 18:01:46',NULL,'RDCF01;CD01004990-1;TESTFACTURESHAS5XYCUSAEF;A1720894F;20260617170147','TEST-FACT-URES-HAS5-XYCU-SAEF','38/752 FA','CD01004990-1','',3,'product',NULL,NULL,NULL,NULL,NULL),(332,'2026/000192',80.00,12.80,80.00,NULL,11,1,'2026-06-17 18:06:04',NULL,'RDCF01;CD01004990-1;TESTFACTURESMPJ6MBKTBE5M;A1720894F;20260617170605','TEST-FACT-URES-MPJ6-MBKT-BE5M','696/753 FV','CD01004990-1','',3,'product',NULL,NULL,NULL,NULL,NULL),(333,'2026/000193',80.00,12.80,80.00,NULL,11,1,'2026-06-17 18:07:02',NULL,'RDCF01;CD01004990-1;TESTFACTURESEI7U2F5LB5ET;A1720894F;20260617170703','TEST-FACT-URES-EI7U-2F5L-B5ET','697/754 FV','CD01004990-1','',3,'product',NULL,NULL,NULL,NULL,NULL),(334,'2026/000194',80.00,12.80,80.00,NULL,11,1,'2026-06-17 18:08:06',NULL,'RDCF01;CD01004990-1;TESTFACTURESYXRR2AXDE22T;A1720894F;20260617170807','TEST-FACT-URES-YXRR-2AXD-E22T','698/755 FV','CD01004990-1','',3,'product',NULL,NULL,NULL,NULL,NULL),(335,'2026/000195',80.00,12.80,80.00,NULL,11,1,'2026-06-17 18:10:41',NULL,'RDCF01;CD01004990-1;TESTFACTURESUSFWFZXOJGKV;A1720894F;20260617171042','TEST-FACT-URES-USFW-FZXO-JGKV','699/756 FV','CD01004990-1','',3,'product',NULL,NULL,NULL,NULL,NULL),(336,'2026/000196',80.00,12.80,80.00,NULL,11,1,'2026-06-17 18:14:48',NULL,'RDCF01;CD01004990-1;TESTFACTURESQJKVN7QM62PK;A1720894F;20260617171449','TEST-FACT-URES-QJKV-N7QM-62PK','700/757 FV','CD01004990-1','',3,'product',NULL,NULL,NULL,NULL,NULL),(337,'2026/000197',80.00,12.80,80.00,NULL,11,1,'2026-06-17 18:16:16',NULL,'RDCF01;CD01004990-1;TESTFACTUREST4GTHFRS3KZR;A1720894F;20260617171617','TEST-FACT-URES-T4GT-HFRS-3KZR','701/758 FV','CD01004990-1','',3,'product',NULL,NULL,NULL,NULL,NULL),(338,'2026/000198',80.00,12.80,80.00,NULL,11,1,'2026-06-17 18:17:48',NULL,'RDCF01;CD01004990-1;TESTFACTURES3MKTY74A2BW3;A1720894F;20260617171750','TEST-FACT-URES-3MKT-Y74A-2BW3','702/759 FV','CD01004990-1','',3,'product',NULL,NULL,NULL,NULL,NULL),(339,'2026/000199',80.00,12.80,80.00,NULL,11,1,'2026-06-17 18:19:14',NULL,'RDCF01;CD01004990-1;TESTFACTURES4JD3T2SDYO3W;A1720894F;20260617171915','TEST-FACT-URES-4JD3-T2SD-YO3W','703/760 FV','CD01004990-1','',3,'product',NULL,NULL,NULL,NULL,NULL),(340,'2026/000200',80.00,12.80,80.00,NULL,11,1,'2026-06-17 18:22:41',NULL,'RDCF01;CD01004990-1;TESTFACTURESVNRAJIXLH35R;A1720894F;20260617172242','TEST-FACT-URES-VNRA-JIXL-H35R','704/761 FV','CD01004990-1','',3,'product',NULL,NULL,NULL,NULL,NULL),(341,'2026/000201',20.19,3.23,20.19,NULL,11,1,'2026-06-18 01:32:28',NULL,'RDCF01;CD01004990-1;TESTFACTURESFEZQAWPG6G5Q;A1720894F;20260618003228','TEST-FACT-URES-FEZQ-AWPG-6G5Q','718/780 FV','CD01004990-1','',3,'product',NULL,NULL,NULL,NULL,NULL),(342,'2026/000202',160.00,25.60,160.00,NULL,11,1,'2026-06-18 01:53:11',NULL,'RDCF01;CD01004990-1;TESTFACTURESZ5ENYJG2FHIZ;A1720894F;20260618005310','TEST-FACT-URES-Z5EN-YJG2-FHIZ','725/788 FV','CD01004990-1','',3,'product',NULL,NULL,NULL,NULL,NULL),(343,'2026/000203',80.00,12.80,80.00,NULL,11,1,'2026-06-18 01:53:31',NULL,'RDCF01;CD01004990-1;TESTFACTURESXVRGE54UBV7S;A1720894F;20260618005330','TEST-FACT-URES-XVRG-E54U-BV7S','727/790 FT','CD01004990-1','',3,'product',NULL,NULL,NULL,NULL,NULL),(344,'2026/000204',80.00,12.80,80.00,NULL,11,1,'2026-06-18 01:54:23',NULL,'RDCF01;CD01004990-1;TESTFACTURES6K7FIUYRVQDF;A1720894F;20260618005422','TEST-FACT-URES-6K7F-IUYR-VQDF','728/791 FT','CD01004990-1','',3,'product',NULL,NULL,NULL,NULL,NULL),(345,'2026/000205',80.00,12.80,80.00,NULL,11,1,'2026-06-18 01:56:31',NULL,'RDCF01;CD01004990-1;TESTFACTURESIB2IGDEHUZTS;A1720894F;20260618005630','TEST-FACT-URES-IB2I-GDEH-UZTS','729/793 FT','CD01004990-1','',3,'product',NULL,NULL,NULL,NULL,NULL),(346,'2026/000206',-80.00,-12.80,-80.00,NULL,11,1,'2026-06-18 01:56:57',NULL,'RDCF01;CD01004990-1;TESTFACTURESUG74SGOOTAXG;A1720894F;20260618005656','TEST-FACT-URES-UG74-SGOO-TAXG','46/794 FA','CD01004990-1','',3,'product',NULL,NULL,NULL,NULL,NULL),(347,'2026/000207',80.00,12.80,80.00,NULL,11,1,'2026-06-18 02:32:21',NULL,'RDCF01;CD01004990-1;TESTFACTURESJLGOFLVXSQJ4;A1720894F;20260618013220','TEST-FACT-URES-JLGO-FLVX-SQJ4','734/802 FV','CD01004990-1','',NULL,'product',NULL,NULL,NULL,NULL,NULL),(348,'2026/000208',80.00,12.80,80.00,NULL,11,1,'2026-06-19 13:13:47',NULL,'RDCF01;CD01004990-1;TESTFACTURESSFEXMFR6T62P;A1720894F;20260619121348','TEST-FACT-URES-SFEX-MFR6-T62P','746/818 FV','CD01004990-1','',3,'product',NULL,NULL,NULL,NULL,NULL),(349,'2026/000209',80.00,12.80,80.00,NULL,11,1,'2026-06-19 13:28:28',NULL,'RDCF01;CD01004990-1;TESTFACTURES4P4FIBUS7XW6;A1720894F;20260619122828','TEST-FACT-URES-4P4F-IBUS-7XW6','747/819 FV','CD01004990-1','',3,'product',NULL,NULL,NULL,NULL,NULL),(350,'2026/000210',80.00,12.80,80.00,NULL,11,1,'2026-06-19 13:56:30',NULL,'RDCF01;CD01004990-1;TESTFACTURESFXLT774YD7WS;A1720894F;20260619125630','TEST-FACT-URES-FXLT-774Y-D7WS','748/820 FV','CD01004990-1','',3,'product',NULL,NULL,NULL,NULL,NULL),(351,'2026/000211',80.00,12.80,80.00,NULL,11,1,'2026-06-19 14:02:21',NULL,'RDCF01;CD01004990-1;TESTFACTURESQFPS4OUQKFMY;A1720894F;20260619130221','TEST-FACT-URES-QFPS-4OUQ-KFMY','749/821 FV','CD01004990-1','',3,'product',NULL,NULL,NULL,NULL,NULL),(352,'2026/000212',-80.00,-12.80,-80.00,NULL,11,1,'2026-06-19 14:56:55',NULL,'RDCF01;CD01004990-1;TESTFACTURESTBZ2BKUMUWID;A1720894F;20260619135655','TEST-FACT-URES-TBZ2-BKUM-UWID','48/822 FA','CD01004990-1','',3,'product',NULL,NULL,NULL,NULL,NULL),(353,'2026/000213',80.00,12.80,80.00,NULL,11,1,'2026-06-19 15:06:17',NULL,'RDCF01;CD01004990-1;TESTFACTURESS37D2OOP6CWJ;A1720894F;20260619140617','TEST-FACT-URES-S37D-2OOP-6CWJ','750/823 FV','CD01004990-1','',3,'product',NULL,NULL,NULL,NULL,NULL),(354,'2026/000214',-80.00,-12.80,-80.00,NULL,11,1,'2026-06-19 15:07:22',NULL,'RDCF01;CD01004990-1;TESTFACTURESHNHHELR5Q2BB;A1720894F;20260619140722','TEST-FACT-URES-HNHH-ELR5-Q2BB','49/824 FA','CD01004990-1','',NULL,'product',NULL,NULL,NULL,NULL,NULL),(355,'2026/000215',-120.80,0.00,-120.80,NULL,11,1,'2026-06-20 17:06:44',NULL,'RDCF01;CD01004990-1;TESTFACTURES7CVITE6QASBB;A1720894F;20260620160643','TEST-FACT-URES-7CVI-TE6Q-ASBB','751/825 FV','CD01004990-1',NULL,NULL,'product',NULL,NULL,NULL,NULL,'ELECTRICITE'),(356,'2026/000216',120.80,0.00,120.80,NULL,11,1,'2026-06-20 17:20:06',NULL,'RDCF01;CD01004990-1;TESTFACTURESNY5DXTLAFUUL;A1720894F;20260620162005','TEST-FACT-URES-NY5D-XTLA-FUUL','752/826 FV','CD01004990-1',NULL,NULL,'product',NULL,NULL,NULL,NULL,'ELECTRICITE'),(357,'2026/000217',-120.80,0.00,-120.80,NULL,11,1,'2026-06-20 17:20:45',NULL,'RDCF01;CD01004990-1;TESTFACTURESDA6YJTTC5ZIS;A1720894F;20260620162044','TEST-FACT-URES-DA6Y-JTTC-5ZIS','50/827 FA','CD01004990-1',NULL,NULL,'product',NULL,NULL,NULL,NULL,'ELECTRICITE'),(358,'2026/000218',120.80,0.00,120.80,NULL,11,1,'2026-06-20 17:30:55',NULL,'RDCF01;CD01004990-1;TESTFACTURES35XUKPGZX7DS;A1720894F;20260620163055','TEST-FACT-URES-35XU-KPGZ-X7DS','753/828 FV','CD01004990-1',NULL,NULL,'product',NULL,NULL,NULL,NULL,'ELECTRICITE'),(359,'2026/000219',-120.80,0.00,-120.80,NULL,11,1,'2026-06-20 17:34:29',NULL,'EN ATTENTE','Nul','0/0','Nul',NULL,NULL,'product',NULL,NULL,NULL,NULL,'ELECTRICITE'),(360,'2026/000220',1358.93,0.00,1358.93,NULL,11,1,'2026-06-20 17:37:55',NULL,'RDCF01;CD01004990-1;TESTFACTURESYEZR4PRKEKB4;A1720894F;20260620163753','TEST-FACT-URES-YEZR-4PRK-EKB4','754/829 FT','CD01004990-1',NULL,NULL,'product',NULL,NULL,NULL,NULL,'ELECTRICITE'),(361,'2026/000221',-1358.93,0.00,-1358.93,NULL,11,1,'2026-06-20 17:38:52',NULL,'RDCF01;CD01004990-1;TESTFACTURESIABU5NSFNXE6;A1720894F;20260620163851','TEST-FACT-URES-IABU-5NSF-NXE6','51/830 FA','CD01004990-1',NULL,NULL,'product',NULL,NULL,NULL,NULL,'ELECTRICITE'),(362,'2026/000222',111.94,17.91,111.94,NULL,11,1,'2026-06-21 10:59:18',NULL,'RDCF01;CD01004990-1;TESTFACTURESLHN5QQX2XFBR;A1720894F;20260621095913','TEST-FACT-URES-LHN5-QQX2-XFBR','768/844 FV','CD01004990-1','',3,'product',NULL,NULL,NULL,NULL,NULL),(363,'2026/000223',320.00,51.20,320.00,NULL,11,1,'2026-06-21 11:02:27',NULL,'RDCF01;CD01004990-1;TESTFACTURES5ZQY6AN6LCZK;A1720894F;20260621100223','TEST-FACT-URES-5ZQY-6AN6-LCZK','769/845 FV','CD01004990-1','',3,'product',NULL,NULL,NULL,NULL,NULL),(364,'2026/000224',100.12,16.02,100.12,NULL,11,1,'2026-06-21 15:30:56',NULL,'RDCF01;CD01004990-1;TESTFACTURES3DU7LY6KDV47;A1720894F;20260621143055','TEST-FACT-URES-3DU7-LY6K-DV47','799/875 FV','CD01004990-1','',3,'product',NULL,NULL,NULL,NULL,NULL),(365,'2026/000225',20.12,3.22,20.12,NULL,11,1,'2026-06-21 15:34:28',NULL,'RDCF01;CD01004990-1;TESTFACTURESOIZLXFKP6SEM;A1720894F;20260621143428','TEST-FACT-URES-OIZL-XFKP-6SEM','800/876 FV','CD01004990-1','',3,'product',NULL,NULL,NULL,NULL,NULL),(366,'2026/000226',80.00,12.80,80.00,NULL,11,1,'2026-06-21 15:40:31',NULL,'RDCF01;CD01004990-1;TESTFACTURESZLPNNQUHNKKR;A1720894F;20260621144028','TEST-FACT-URES-ZLPN-NQUH-NKKR','801/877 FV','CD01004990-1','',3,'product',NULL,NULL,NULL,NULL,NULL),(367,'2026/000227',-80.00,-12.80,-80.00,NULL,11,1,'2026-06-21 15:41:33',NULL,'EN ATTENTE','Nul','0/0','Nul','',NULL,'product',NULL,NULL,NULL,NULL,NULL),(368,'2026/000228',-80.00,-12.80,-80.00,NULL,11,1,'2026-06-21 15:42:09',NULL,'EN ATTENTE','Nul','0/0','Nul','',3,'product',NULL,NULL,NULL,NULL,NULL),(369,'2026/000229',80.00,12.80,80.00,NULL,11,1,'2026-06-21 15:43:20',NULL,'RDCF01;CD01004990-1;TESTFACTURESJJJ5UZVAIVXI;A1720894F;20260621144319','TEST-FACT-URES-JJJ5-UZVA-IVXI','802/879 FT','CD01004990-1','',3,'product',NULL,NULL,NULL,NULL,NULL),(370,'2026/000230',-80.00,-12.80,-80.00,NULL,11,1,'2026-06-21 15:43:55',NULL,'RDCF01;CD01004990-1;TESTFACTURESC4UIFXWJGZLJ;A1720894F;20260621144354','TEST-FACT-URES-C4UI-FXWJ-GZLJ','53/880 FA','CD01004990-1','',NULL,'product',NULL,NULL,NULL,NULL,NULL),(371,'2026/000231',80.00,12.80,80.00,NULL,11,1,'2026-06-23 13:36:44',NULL,'RDCF01;CD01004990-1;TESTFACTURESWFIXQGI6CWS2;A1720894F;20260623123644','TEST-FACT-URES-WFIX-QGI6-CWS2','838/929 FV','CD01004990-1','',3,'product',NULL,NULL,NULL,NULL,NULL),(372,'2026/000232',640.00,102.40,640.00,NULL,11,1,'2026-06-23 13:37:21',NULL,'RDCF01;CD01004990-1;TESTFACTURESJTHRKWWA2ACS;A1720894F;20260623123721','TEST-FACT-URES-JTHR-KWWA-2ACS','839/930 FV','CD01004990-1','',3,'product',NULL,NULL,NULL,NULL,NULL),(373,'2026/000233',5021.25,3.40,5021.25,NULL,11,1,'2026-06-23 13:38:24',NULL,'RDCF01;CD01004990-1;TESTFACTURES5A4SIUFY5B55;A1720894F;20260623123824','TEST-FACT-URES-5A4S-IUFY-5B55','840/931 FV','CD01004990-1','',3,'product',NULL,NULL,NULL,NULL,NULL),(374,'2026/000234',80.00,12.80,80.00,NULL,11,1,'2026-06-23 13:55:23',NULL,'RDCF01;CD01004990-1;TESTFACTURESVU2AHVDB5ARK;A1720894F;20260623125523','TEST-FACT-URES-VU2A-HVDB-5ARK','841/932 FV','CD01004990-1','',3,'product',NULL,NULL,NULL,NULL,NULL),(375,'2026/000235',120.80,0.00,120.80,NULL,11,1,'2026-06-23 14:18:38',NULL,'RDCF01;CD01004990-1;TESTFACTURESRJRZ4DQ2K7KN;A1720894F;20260623131838','TEST-FACT-URES-RJRZ-4DQ2-K7KN','842/933 FV','CD01004990-1',NULL,NULL,'product',NULL,NULL,NULL,NULL,'ELECTRICITE'),(376,'2026/000236',11297.94,0.00,11297.94,NULL,11,1,'2026-06-23 14:19:26',NULL,'RDCF01;CD01004990-1;TESTFACTURES37TZSOGZMYUI;A1720894F;20260623131926','TEST-FACT-URES-37TZ-SOGZ-MYUI','843/934 FV','CD01004990-1',NULL,NULL,'product',NULL,NULL,NULL,NULL,'ELECTRICITE'),(377,'2026/000237',130.00,12.80,130.00,NULL,11,1,'2026-06-23 15:43:48',NULL,'RDCF01;CD01004990-1;TESTFACTURESTNFD74XXCVWC;A1720894F;20260623144348','TEST-FACT-URES-TNFD-74XX-CVWC','844/935 FV','CD01004990-1','',3,'product',NULL,NULL,NULL,NULL,NULL),(378,'2026/000238',120.80,0.00,120.80,NULL,11,1,'2026-06-23 15:47:53',NULL,'RDCF01;CD01004990-1;TESTFACTURESHVLQSVWITZIL;A1720894F;20260623144752','TEST-FACT-URES-HVLQ-SVWI-TZIL','845/936 FV','CD01004990-1',NULL,NULL,'product',NULL,NULL,NULL,NULL,'ELECTRICITE'),(379,'2026/000239',-120.80,0.00,-120.80,NULL,11,1,'2026-06-23 15:48:30',NULL,'RDCF01;CD01004990-1;TESTFACTURESXWZF5QPMGYKI;A1720894F;20260623144830','TEST-FACT-URES-XWZF-5QPM-GYKI','64/937 FA','CD01004990-1',NULL,NULL,'product',NULL,NULL,NULL,NULL,'ELECTRICITE'),(380,'2026/000240',1358.93,0.00,1358.93,NULL,11,1,'2026-06-23 15:57:33',NULL,'RDCF01;CD01004990-1;TESTFACTURES5GTSUFBBYDUD;A1720894F;20260623145732','TEST-FACT-URES-5GTS-UFBB-YDUD','848/940 FV','CD01004990-1',NULL,NULL,'product',NULL,NULL,NULL,NULL,'ELECTRICITE'),(381,'2026/000241',-1358.93,0.00,-1358.93,NULL,11,1,'2026-06-23 15:58:19',NULL,'RDCF01;CD01004990-1;TESTFACTURESHC7K3OEUGA5V;A1720894F;20260623145818','TEST-FACT-URES-HC7K-3OEU-GA5V','65/941 FA','CD01004990-1',NULL,NULL,'product',NULL,NULL,NULL,NULL,'ELECTRICITE'),(382,'2026/000242',120.80,0.00,120.80,NULL,11,1,'2026-06-23 16:00:03',NULL,'RDCF01;CD01004990-1;TESTFACTURESBVY2FKDPV2OV;A1720894F;20260623150002','TEST-FACT-URES-BVY2-FKDP-V2OV','849/942 FT','CD01004990-1',NULL,NULL,'product',NULL,NULL,NULL,NULL,'ELECTRICITE'),(383,'2026/000243',1358.93,0.00,1358.93,NULL,11,1,'2026-06-23 16:01:06',NULL,'RDCF01;CD01004990-1;TESTFACTURESSBNPROWTUATK;A1720894F;20260623150106','TEST-FACT-URES-SBNP-ROWT-UATK','850/943 FV','CD01004990-1',NULL,NULL,'product',NULL,NULL,NULL,NULL,'ELECTRICITE'),(384,'2026/000244',4762.01,0.00,4762.01,NULL,11,1,'2026-06-23 16:18:15',NULL,'RDCF01;CD01004990-1;TESTFACTURESZMS33DJXVLAC;A1720894F;20260623151814','TEST-FACT-URES-ZMS3-3DJX-VLAC','851/945 FV','CD01004990-1',NULL,NULL,'product',NULL,NULL,NULL,NULL,'ELECTRICITE'),(385,'2026/000245',-4762.01,0.00,-4762.01,NULL,11,1,'2026-06-23 16:19:24',NULL,'RDCF01;CD01004990-1;TESTFACTURESE4OKDVCR7ONK;A1720894F;20260623151924','TEST-FACT-URES-E4OK-DVCR-7ONK','67/946 FA','CD01004990-1',NULL,NULL,'product',NULL,NULL,NULL,NULL,'ELECTRICITE'),(386,'2026/000246',120.80,0.00,120.80,NULL,11,1,'2026-06-23 16:22:00',NULL,'RDCF01;CD01004990-1;TESTFACTURES7JQZWII7B2FJ;A1720894F;20260623152159','TEST-FACT-URES-7JQZ-WII7-B2FJ','852/947 FV','CD01004990-1',NULL,NULL,'product',NULL,NULL,NULL,NULL,'ELECTRICITE'),(387,'2026/000247',-120.80,0.00,-120.80,NULL,11,1,'2026-06-23 16:22:38',NULL,'RDCF01;CD01004990-1;TESTFACTURESUDFNVV6CUAM3;A1720894F;20260623152238','TEST-FACT-URES-UDFN-VV6C-UAM3','68/948 FA','CD01004990-1',NULL,NULL,'product',NULL,NULL,NULL,NULL,'ELECTRICITE'),(388,'2026/000248',80.00,12.80,80.00,NULL,1,1,'2026-06-23 16:33:31',NULL,'RDCF01;CD01004990-1;TESTFACTURES3XRZWKTNV5VR;A1720894F;20260623153331','TEST-FACT-URES-3XRZ-WKTN-V5VR','853/949 FV','CD01004990-1','',7,'product',NULL,NULL,NULL,NULL,NULL),(389,'2026/000249',80.00,12.80,80.00,NULL,11,1,'2026-06-26 17:39:06',NULL,'RDCF01;CD01004990-1;TESTFACTURESBMEQZOXNPW33;A1720894F;20260626163905','TEST-FACT-URES-BMEQ-ZOXN-PW33','869/968 FV','CD01004990-1','',7,'product',NULL,NULL,NULL,NULL,NULL),(390,'2026/000250',80.00,12.80,80.00,NULL,11,1,'2026-06-26 17:40:16',NULL,'RDCF01;CD01004990-1;TESTFACTURES3FWYNEX6OSWL;A1720894F;20260626164015','TEST-FACT-URES-3FWY-NEX6-OSWL','870/969 FV','CD01004990-1','',7,'product',NULL,NULL,NULL,NULL,NULL),(391,'2026/000251',80.00,12.80,80.00,NULL,11,1,'2026-06-26 18:06:15',NULL,'RDCF01;CD01004990-1;TESTFACTURES5QW6KZXF6NUU;A1720894F;20260626170614','TEST-FACT-URES-5QW6-KZXF-6NUU','871/970 FV','CD01004990-1','',7,'product',NULL,NULL,NULL,NULL,NULL),(392,'2026/000252',80.00,12.80,80.00,NULL,11,1,'2026-06-26 18:11:51',NULL,'RDCF01;CD01004990-1;TESTFACTURESZAQXMEZILJ44;A1720894F;20260626171150','TEST-FACT-URES-ZAQX-MEZI-LJ44','872/971 FV','CD01004990-1','',7,'product',NULL,NULL,NULL,NULL,NULL),(393,'2026/000253',495000.00,76800.00,495000.00,NULL,11,1,'2026-06-26 18:34:17',NULL,'RDCF01;CD01004990-1;TESTFACTURESQGDYRPVXIIN6;A1720894F;20260626173416','TEST-FACT-URES-QGDY-RPVX-IIN6','873/972 FV','CD01004990-1','',7,'product',NULL,NULL,NULL,NULL,NULL),(394,'2026/000254',80000.00,12800.00,80000.00,NULL,11,1,'2026-06-26 18:57:22',NULL,'RDCF01;CD01004990-1;TESTFACTURESPXAVDMGAHUYQ;A1720894F;20260626175721','TEST-FACT-URES-PXAV-DMGA-HUYQ','874/973 FV','CD01004990-1','',7,'product',NULL,NULL,NULL,NULL,NULL),(395,'2026/000255',255000.00,38400.00,255000.00,NULL,11,1,'2026-06-26 18:58:12',NULL,'RDCF01;CD01004990-1;TESTFACTURESKN4RU54BUFSL;A1720894F;20260626175811','TEST-FACT-URES-KN4R-U54B-UFSL','875/974 FV','CD01004990-1','',7,'product',NULL,NULL,NULL,NULL,NULL),(396,'2026/000256',80000.00,12800.00,80000.00,NULL,11,1,'2026-06-26 19:02:50',NULL,'RDCF01;CD01004990-1;TESTFACTURES53G6JEYGIOON;A1720894F;20260626180249','TEST-FACT-URES-53G6-JEYG-IOON','876/975 FV','CD01004990-1','',7,'product',NULL,NULL,NULL,NULL,NULL),(397,'2026/000257',160000.00,25600.00,160000.00,NULL,11,1,'2026-06-26 19:03:34',NULL,'RDCF01;CD01004990-1;TESTFACTURESADB5CTHPYHDO;A1720894F;20260626180333','TEST-FACT-URES-ADB5-CTHP-YHDO','877/976 FV','CD01004990-1','',7,'product',NULL,NULL,NULL,NULL,NULL),(398,'2026/000258',90000.00,12800.00,90000.00,NULL,11,1,'2026-06-26 19:04:39',NULL,'RDCF01;CD01004990-1;TESTFACTURES3CFEXXFUWDUQ;A1720894F;20260626180438','TEST-FACT-URES-3CFE-XXFU-WDUQ','878/977 FV','CD01004990-1','',7,'product',NULL,NULL,NULL,NULL,NULL),(399,'2026/000259',80000.00,12800.00,80000.00,NULL,11,1,'2026-06-26 19:06:44',NULL,'RDCF01;CD01004990-1;TESTFACTURESUWEYV7FZK7NE;A1720894F;20260626180643','TEST-FACT-URES-UWEY-V7FZ-K7NE','879/978 FV','CD01004990-1','',7,'product',NULL,NULL,NULL,NULL,NULL),(400,'2026/000260',-80000.00,-12800.00,-80000.00,NULL,11,1,'2026-06-26 19:07:29',NULL,'RDCF01;CD01004990-1;TESTFACTURES7A6OK67P7TSH;A1720894F;20260626180727','TEST-FACT-URES-7A6O-K67P-7TSH','72/979 FA','CD01004990-1','',7,'product',NULL,NULL,NULL,NULL,NULL),(401,'2026/000261',-80000.00,-12800.00,-80000.00,NULL,11,1,'2026-06-26 19:07:53',NULL,'EN ATTENTE','Nul','0/0','Nul','',NULL,'product',NULL,NULL,NULL,NULL,NULL),(402,'2026/000262',44500000.00,7120000.00,44500000.00,NULL,11,1,'2026-06-26 19:31:42',NULL,'RDCF01;CD01004990-1;TESTFACTURESM5GTKH2A6M3S;A1720894F;20260626183141','TEST-FACT-URES-M5GT-KH2A-6M3S','880/980 FV','CD01004990-1','',7,'product',NULL,NULL,NULL,NULL,NULL),(403,'2026/000263',8900000.00,1424000.00,8900000.00,'[{\"type\": \"cash\", \"amount\": 8900000}]',11,1,'2026-06-27 15:34:59',NULL,'RDCF01;CD01004990-1;TESTFACTURESNDNVLLCEVYHQ;A1720894F;20260627143457','TEST-FACT-URES-NDNV-LLCE-VYHQ','901/1001 FV','CD01004990-1','',3,'product',NULL,NULL,NULL,NULL,NULL),(404,'2026/000264',8910000.00,1424000.00,8910000.00,'[{\"type\": \"cash\", \"amount\": 8910000}]',11,1,'2026-06-27 15:41:21',NULL,'RDCF01;CD01004990-1;TESTFACTURESDGTB4RRSI36C;A1720894F;20260627144120','TEST-FACT-URES-DGTB-4RRS-I36C','902/1002 FV','CD01004990-1','',3,'product',NULL,NULL,NULL,NULL,NULL),(405,'2026/000265',17820000.00,2848000.00,17820000.00,'[{\"type\": \"cash\", \"amount\": 17800000}, {\"type\": \"card\", \"amount\": 20000}]',11,1,'2026-06-27 15:42:24',NULL,'RDCF01;CD01004990-1;TESTFACTURESLR2NKUVCS2KK;A1720894F;20260627144222','TEST-FACT-URES-LR2N-KUVC-S2KK','903/1003 FV','CD01004990-1','',3,'product',NULL,NULL,NULL,NULL,NULL),(406,'2026/000266',190001.19,30400.19,190001.19,'[{\"type\": \"ESPECES\", \"amount\": 190001.19}]',11,1,'2026-06-27 19:00:49',NULL,'RDCF01;CD01004990-1;TESTFACTURES7TGL3JKKYBBT;A1720894F;20260627180047','TEST-FACT-URES-7TGL-3JKK-YBBT','905/1005 FV','CD01004990-1','',3,'product',NULL,NULL,NULL,NULL,NULL),(407,'2026/000267',8910000.00,4450000.00,8910000.00,'[{\"type\": \"ESPECES\", \"amount\": 8900000}, {\"type\": \"MOBILEMONEY\", \"amount\": 10000}]',11,1,'2026-06-27 19:02:03',NULL,'RDCF01;CD01004990-1;TESTFACTURESXRJ2W4EQPXGB;A1720894F;20260627180201','TEST-FACT-URES-XRJ2-W4EQ-PXGB','906/1006 FV','CD01004990-1','',3,'product',NULL,NULL,NULL,NULL,NULL),(408,'2026/000268',8900000.00,4450000.00,8900000.00,'[{\"type\": \"ESPECES\", \"amount\": 8900000}]',11,1,'2026-06-27 19:10:19',NULL,'RDCF01;CD01004990-1;TESTFACTURESRMVLGSJSBPYC;A1720894F;20260627181017','TEST-FACT-URES-RMVL-GSJS-BPYC','907/1007 FV','CD01004990-1','',3,'product',NULL,NULL,NULL,NULL,NULL),(409,'2026/000269',9124001.40,4485840.22,9124001.40,'[{\"type\": \"ESPECES\", \"amount\": 9120000.4}, {\"type\": \"MOBILEMONEY\", \"amount\": 4001}]',11,1,'2026-06-27 19:14:31',NULL,'RDCF01;CD01004990-1;TESTFACTURESFLLL3RSUFHTG;A1720894F;20260627181429','TEST-FACT-URES-FLLL-3RSU-FHTG','908/1008 FV','CD01004990-1','',3,'product',NULL,NULL,NULL,NULL,NULL),(410,'2026/000270',10000.00,0.00,10000.00,'[{\"type\": \"ESPECES\", \"amount\": 10000}]',11,1,'2026-06-27 19:25:36',NULL,'RDCF01;CD01004990-1;TESTFACTURES35HHJNGASWEC;A1720894F;20260627182534','TEST-FACT-URES-35HH-JNGA-SWEC','909/1009 FV','CD01004990-1','',3,'product',NULL,NULL,NULL,NULL,NULL),(411,'2026/000271',8900000.00,1424000.00,8900000.00,'[{\"type\": \"ESPECES\", \"amount\": 8900000}]',11,1,'2026-06-27 22:33:28',NULL,'RDCF01;CD01004990-1;TESTFACTURESKOAUBGDWIRMJ;A1720894F;20260627213325','TEST-FACT-URES-KOAU-BGDW-IRMJ','910/1010 FV','CD01004990-1','',3,'product',NULL,NULL,NULL,NULL,NULL),(412,'2026/000272',8900000.00,1424000.00,8900000.00,'[{\"type\": \"ESPECES\", \"amount\": 8000000}, {\"type\": \"MOBILEMONEY\", \"amount\": 900000}]',11,1,'2026-06-28 15:34:36',NULL,'RDCF01;CD01004990-1;TESTFACTURES67OLVMEX7324;A1720894F;20260628143435','TEST-FACT-URES-67OL-VMEX-7324','913/1013 FV','CD01004990-1','',3,'product',NULL,NULL,NULL,NULL,NULL),(413,'2026/000400',8900000.00,1424000.00,8900000.00,'[{\"type\": \"ESPECES\", \"amount\": 8900000}]',11,1,'2026-06-28 15:41:28',NULL,'RDCF01;CD01004990-1;TESTFACTURESB6SBIUFE3UTS;A1720894F;20260628144127','TEST-FACT-URES-B6SB-IUFE-3UTS','914/1014 FV','CD01004990-1','',3,'product',NULL,NULL,NULL,NULL,NULL),(414,'2026/000401',5000.00,800.00,5000.00,'[{\"type\": \"ESPECES\", \"amount\": 3000}, {\"type\": \"CARTEBANCAIRE\", \"amount\": 2000}]',11,1,'2026-06-28 15:46:26',NULL,'RDCF01;CD01004990-1;TESTFACTURESBUC42EZG73XQ;A1720894F;20260628144625','TEST-FACT-URES-BUC4-2EZG-73XQ','915/1015 FV','CD01004990-1','',3,'product',NULL,NULL,NULL,NULL,NULL),(415,'2026/000402',8900000.00,1424000.00,8900000.00,'[{\"type\": \"CARTEBANCAIRE\", \"amount\": 8100000}, {\"type\": \"ESPECES\", \"amount\": 800000}]',11,1,'2026-06-28 16:56:45',NULL,'RDCF01;CD01004990-1;TESTFACTURESQBNVUQORSPX3;A1720894F;20260628155644','TEST-FACT-URES-QBNV-UQOR-SPX3','919/1019 FV','CD01004990-1','',3,'product',NULL,NULL,NULL,NULL,NULL),(416,'2026/000403',9110901.19,1455200.19,9110901.19,'[{\"type\": \"ESPECES\", \"amount\": 9000001.19}, {\"type\": \"CARTEBANCAIRE\", \"amount\": 10000}, {\"type\": \"VIREMENT\", \"amount\": 900}, {\"type\": \"CHEQUES\", \"amount\": 100000}]',11,1,'2026-06-28 20:36:38',NULL,'RDCF01;CD01004990-1;TESTFACTURES7WUQ7O6OSFNT;A1720894F;20260628193634','TEST-FACT-URES-7WUQ-7O6O-SFNT','920/1022 FV','CD01004990-1','',3,'product',NULL,NULL,NULL,NULL,NULL),(417,'2026/000404',214001.34,34240.21,214001.34,'[{\"type\": \"ESPECES\", \"amount\": 215001.34}]',11,1,'2026-06-28 21:15:55',NULL,'RDCF01;CD01004990-1;TESTFACTURESHJJSJARLTH7Q;A1720894F;20260628201550','TEST-FACT-URES-HJJS-JARL-TH7Q','921/1023 FV','CD01004990-1','',3,'product',NULL,NULL,NULL,NULL,NULL),(418,'2026/000405',120.80,0.00,120.80,'[{\"type\": \"ESPECES\", \"amount\": 120.8}]',11,1,'2026-06-28 22:28:46',NULL,'RDCF01;CD01004990-1;TESTFACTURESH6ZMHWBFS6JQ;A1720894F;20260628212841','TEST-FACT-URES-H6ZM-HWBF-S6JQ','922/1024 FV','CD01004990-1',NULL,NULL,'product',NULL,NULL,NULL,NULL,'ELECTRICITE'),(419,'2026/000406',5000.00,800.00,5000.00,'[{\"type\": \"ESPECES\", \"amount\": 5000}]',11,1,'2026-06-28 22:30:20',NULL,'RDCF01;CD01004990-1;TESTFACTURESR33CANKOM2BN;A1720894F;20260628213015','TEST-FACT-URES-R33C-ANKO-M2BN','923/1025 FV','CD01004990-1','',3,'product',NULL,NULL,NULL,NULL,NULL),(420,'2026/000407',5000.00,800.00,5000.00,'[{\"type\": \"ESPECES\", \"amount\": 5000}]',11,1,'2026-06-28 22:35:24',NULL,'RDCF01;CD01004990-1;TESTFACTURESKEL2VHR26PMP;A1720894F;20260628213519','TEST-FACT-URES-KEL2-VHR2-6PMP','924/1026 FV','CD01004990-1','',3,'product',NULL,NULL,NULL,NULL,NULL),(421,'2026/000408',120.80,0.00,120.80,'[{\"type\": \"ESPECES\", \"amount\": 120.8}]',11,1,'2026-06-28 22:36:38',NULL,'RDCF01;CD01004990-1;TESTFACTURESJTUFCNR5OW56;A1720894F;20260628213633','TEST-FACT-URES-JTUF-CNR5-OW56','925/1027 FV','CD01004990-1',NULL,NULL,'product',NULL,NULL,NULL,NULL,'ELECTRICITE'),(422,'2026/000409',5000.00,800.00,5000.00,'[{\"type\": \"ESPECES\", \"amount\": 5000}]',11,1,'2026-06-28 23:21:08',NULL,'RDCF01;CD01004990-1;TESTFACTURESNVTOUNNMC6NN;A1720894F;20260628222102','TEST-FACT-URES-NVTO-UNNM-C6NN','926/1028 FV','CD01004990-1','',3,'product',NULL,NULL,NULL,NULL,NULL),(423,'2026/000410',-5000.00,-800.00,-5000.00,'[{\"type\": \"ESPECES\", \"amount\": 5000}]',11,1,'2026-06-28 23:35:12',NULL,'RDCF01;CD01004990-1;TESTFACTURESABZG562PBOSG;A1720894F;20260628223507','TEST-FACT-URES-ABZG-562P-BOSG','73/1029 FA','CD01004990-1','',3,'product',NULL,NULL,NULL,NULL,NULL),(424,'2026/000411',5000.00,0.00,5000.00,'[{\"type\": \"ESPECES\", \"amount\": 5000}]',11,1,'2026-06-29 09:11:57',NULL,'RDCF01;CD01004990-1;TESTFACTURESB7AZLUZPOVVD;A1720894F;20260629081151','TEST-FACT-URES-B7AZ-LUZP-OVVD','941/1044 FV','CD01004990-1','',3,'product',NULL,NULL,NULL,NULL,NULL),(425,'2026/000412',8915000.00,1424000.00,8915000.00,'[{\"type\": \"ESPECES\", \"amount\": 8915000}]',11,1,'2026-06-29 09:15:21',NULL,'RDCF01;CD01004990-1;TESTFACTURESHUE42TJJW4R3;A1720894F;20260629081514','TEST-FACT-URES-HUE4-2TJJ-W4R3','942/1045 FV','CD01004990-1','',NULL,'product',NULL,NULL,NULL,NULL,NULL),(426,'2026/000413',5983.34,566.86,6550.19,'[{\"type\": \"ESPECES\", \"amount\": 6550.19}]',11,1,'2026-06-30 18:20:54',NULL,'RDCF01;CD01004990-1;TESTFACTURESG4KDM55CLS4Y;A1720894F;20260630172054','TEST-FACT-URES-G4KD-M55C-LS4Y','1021/1124 FV','CD01004990-1','',3,'product',NULL,NULL,NULL,NULL,NULL),(427,'2026/000414',5983.34,566.86,6550.19,'[{\"type\": \"ESPECES\", \"amount\": 6550.19}]',11,1,'2026-06-30 18:33:10',NULL,'RDCF01;CD01004990-1;TESTFACTURESRGGVFACAZCL2;A1720894F;20260630173309','TEST-FACT-URES-RGGV-FACA-ZCL2','1022/1125 FV','CD01004990-1','',3,'product',NULL,NULL,NULL,NULL,NULL),(428,'2026/000415',3010.32,206.41,3216.73,'[{\"type\": \"ESPECES\", \"amount\": 3216.73}]',11,1,'2026-06-30 22:52:55',NULL,'RDCF01;CD01004990-1;TESTFACTURESDPAQ26WEWRHH;A1720894F;20260630215254','TEST-FACT-URES-DPAQ-26WE-WRHH','1023/1126 FV','CD01004990-1','',3,'product',NULL,NULL,NULL,NULL,NULL),(429,'2026/000416',4983.34,566.86,5550.19,'[{\"type\": \"ESPECES\", \"amount\": 5550.19}]',11,1,'2026-06-30 23:09:44',NULL,'RDCF01;CD01004990-1;TESTFACTURES55P5FSKDYSPO;A1720894F;20260630220942','TEST-FACT-URES-55P5-FSKD-YSPO','1026/1129 FV','CD01004990-1','',3,'product',NULL,NULL,NULL,NULL,NULL),(430,'2026/000417',5983.34,566.86,6550.19,'[{\"type\": \"ESPECES\", \"amount\": 6550.19}]',11,1,'2026-06-30 23:21:55',NULL,'RDCF01;CD01004990-1;TESTFACTURESG2P73LCEGEP6;A1720894F;20260630222154','TEST-FACT-URES-G2P7-3LCE-GEP6','1027/1130 FV','CD01004990-1','',3,'product',NULL,NULL,NULL,NULL,NULL),(431,'2026/000418',5983.34,566.86,6550.19,'[{\"type\": \"ESPECES\", \"amount\": 6550.19}]',11,1,'2026-07-02 17:10:37',NULL,'RDCF01;CD01004990-1;TESTFACTURES3GUC3MDCUVOA;A1720894F;20260702161037','TEST-FACT-URES-3GUC-3MDC-UVOA','1039/1142 FV','CD01004990-1','',3,'product',NULL,NULL,NULL,NULL,NULL),(432,'2026/000419',5983.34,566.86,6550.19,'[{\"type\": \"ESPECES\", \"amount\": 6550.19}]',11,1,'2026-07-03 18:37:39',NULL,'RDCF01;CD01004990-1;TESTFACTURESJOVC2WM5S666;A1720894F;20260703173738','TEST-FACT-URES-JOVC-2WM5-S666','1044/1147 FV','CD01004990-1','',3,'product',NULL,NULL,NULL,NULL,NULL),(433,'2026/000420',4057.94,258.79,4316.73,'[{\"type\": \"ESPECES\", \"amount\": 4316.73}]',11,1,'2026-07-03 18:41:07',NULL,'RDCF01;CD01004990-1;TESTFACTURESP4BJYG3QYGUX;A1720894F;20260703174106','TEST-FACT-URES-P4BJ-YG3Q-YGUX','1045/1148 FV','CD01004990-1','',3,'product',NULL,NULL,NULL,NULL,NULL),(434,'2026/000421',1047.62,52.38,1100.00,'[{\"type\": \"ESPECES\", \"amount\": 1100}]',11,1,'2026-07-03 18:42:52',NULL,'RDCF01;CD01004990-1;TESTFACTURESU3VEOSF6RMI2;A1720894F;20260703174251','TEST-FACT-URES-U3VE-OSF6-RMI2','1046/1149 FV','CD01004990-1','',3,'product',NULL,NULL,NULL,NULL,NULL),(435,'2026/000422',1047.62,52.38,1100.00,'[{\"type\": \"ESPECES\", \"amount\": 1100}]',11,1,'2026-07-03 18:44:52',NULL,'RDCF01;CD01004990-1;TESTFACTURESPEOXA4W2C222;A1720894F;20260703174451','TEST-FACT-URES-PEOX-A4W2-C222','1047/1150 FV','CD01004990-1','',3,'product',NULL,NULL,NULL,NULL,NULL),(436,'2026/000423',1047.62,52.38,1100.00,'[{\"type\": \"ESPECES\", \"amount\": 1100}]',11,1,'2026-07-03 18:47:36',NULL,'RDCF01;CD01004990-1;TESTFACTURESDN4FPU5Y4UCW;A1720894F;20260703174735','TEST-FACT-URES-DN4F-PU5Y-4UCW','1048/1151 FV','CD01004990-1','',3,'product',NULL,NULL,NULL,NULL,NULL),(437,'2026/000424',5983.34,566.86,6550.19,'[{\"type\": \"ESPECES\", \"amount\": 6550.19}]',11,1,'2026-07-03 19:16:08',NULL,'RDCF01;CD01004990-1;TESTFACTURESS7WVQLHK7L5G;A1720894F;20260703181607','TEST-FACT-URES-S7WV-QLHK-7L5G','1049/1152 FV','CD01004990-1','',3,'product',NULL,NULL,NULL,NULL,NULL),(438,'2026/000425',5983.34,566.86,6550.19,'[{\"type\": \"ESPECES\", \"amount\": 6550.19}]',11,1,'2026-07-04 01:44:08',NULL,'RDCF01;CD01004990-1;TESTFACTURESAI7WAB5KMTAO;A1720894F;20260704004406','TEST-FACT-URES-AI7W-AB5K-MTAO','1050/1153 FV','CD01004990-1','',3,'product',NULL,NULL,NULL,NULL,NULL),(439,'2026/000426',5708.86,566.86,5708.86,'[{\"type\": \"ESPECES\", \"amount\": 5708.86}]',11,1,'2026-07-04 18:30:24',NULL,'RDCF01;CD01004990-1;TESTFACTURESJU2E6WIAIWHF;A1720894F;20260704173025','TEST-FACT-URES-JU2E-6WIA-IWHF','1064/1167 FV','CD01004990-1','',3,'product',NULL,NULL,NULL,NULL,NULL),(440,'2026/000427',1000.00,0.00,1000.00,'[{\"type\": \"ESPECES\", \"amount\": 1000}]',11,1,'2026-07-06 18:15:40',NULL,'RDCF01;CD01004990-1;TESTFACTURESW5IOJAKFOJZY;A1720894F;20260706171542','TEST-FACT-URES-W5IO-JAKF-OJZY','1114/1217 FV','CD01004990-1','',3,'product',NULL,NULL,NULL,NULL,NULL),(441,'2026/000428',2210.00,110.00,2210.00,'[{\"type\": \"ESPECES\", \"amount\": 2210}]',11,1,'2026-07-06 18:17:52',NULL,'RDCF01;CD01004990-1;TESTFACTURESVD74BOQ7KPFR;A1720894F;20260706171753','TEST-FACT-URES-VD74-BOQ7-KPFR','1115/1218 FV','CD01004990-1','',3,'product',NULL,NULL,NULL,NULL,NULL),(442,'2026/000429',1011.60,161.60,1011.60,'[{\"type\": \"ESPECES\", \"amount\": 1011.6}]',11,1,'2026-07-06 18:28:40',NULL,'RDCF01;CD01004990-1;TESTFACTURESV7FW24GO6WTX;A1720894F;20260706172841','TEST-FACT-URES-V7FW-24GO-6WTX','1116/1219 FV','CD01004990-1','',3,'product',NULL,NULL,NULL,NULL,NULL),(443,'2026/000430',2011.60,323.20,2011.60,'[{\"type\": \"ESPECES\", \"amount\": 2011.6}]',11,1,'2026-07-06 18:32:09',NULL,'RDCF01;CD01004990-1;TESTFACTURESLUBLO465YJWN;A1720894F;20260706173210','TEST-FACT-URES-LUBL-O465-YJWN','1117/1220 FV','CD01004990-1','',3,'product',NULL,NULL,NULL,NULL,NULL),(444,'2026/000431',3370.00,286.00,3370.00,'[{\"type\": \"ESPECES\", \"amount\": 3370}]',11,1,'2026-07-06 18:39:16',NULL,'RDCF01;CD01004990-1;TESTFACTURESSIL3TOR7J5JS;A1720894F;20260706173918','TEST-FACT-URES-SIL3-TOR7-J5JS','1118/1221 FV','CD01004990-1','',3,'product',NULL,NULL,NULL,NULL,NULL),(445,'2026/000432',3136.00,286.00,3136.00,'[{\"type\": \"ESPECES\", \"amount\": 3136}]',11,1,'2026-07-06 18:44:18',NULL,'RDCF01;CD01004990-1;TESTFACTURESDPFP4XCQF6C5;A1720894F;20260706174419','TEST-FACT-URES-DPFP-4XCQ-F6C5','1119/1222 FV','CD01004990-1','',3,'product',NULL,NULL,NULL,NULL,NULL),(446,'2026/000433',3158.00,528.00,3158.00,'[{\"type\": \"ESPECES\", \"amount\": 3158}]',11,1,'2026-07-06 18:49:08',NULL,'RDCF01;CD01004990-1;TESTFACTURESGQ43AWFXIT5R;A1720894F;20260706174909','TEST-FACT-URES-GQ43-AWFX-IT5R','1120/1223 FV','CD01004990-1','',3,'product',NULL,NULL,NULL,NULL,NULL),(447,'2026/000434',3232.00,512.00,3232.00,'[{\"type\": \"ESPECES\", \"amount\": 3232}]',11,1,'2026-07-06 19:09:13',NULL,'RDCF01;CD01004990-1;TESTFACTURESNAXESA3SPIQ6;A1720894F;20260706180914','TEST-FACT-URES-NAXE-SA3S-PIQ6','1121/1224 FV','CD01004990-1','',3,'product',NULL,NULL,NULL,NULL,NULL),(448,'2026/000435',3232.00,512.00,3232.00,'[{\"type\": \"ESPECES\", \"amount\": 3232}]',11,1,'2026-07-06 19:10:24',NULL,'RDCF01;CD01004990-1;TESTFACTURESFK6XLFTTIQ3W;A1720894F;20260706181025','TEST-FACT-URES-FK6X-LFTT-IQ3W','1122/1225 FV','CD01004990-1','',3,'product',NULL,NULL,NULL,NULL,NULL),(449,'2026/000436',3232.00,512.00,3232.00,'[{\"type\": \"ESPECES\", \"amount\": 3232}]',11,1,'2026-07-06 19:13:17',NULL,'RDCF01;CD01004990-1;TESTFACTURESK5IBS7PHA323;A1720894F;20260706181318','TEST-FACT-URES-K5IB-S7PH-A323','1123/1226 FV','CD01004990-1','',3,'product',NULL,NULL,NULL,NULL,NULL),(450,'2026/000437',3232.00,512.00,3232.00,'[{\"type\": \"ESPECES\", \"amount\": 3232}]',11,1,'2026-07-06 19:15:10',NULL,'RDCF01;CD01004990-1;TESTFACTURESZ2Q6AS5ST5R6;A1720894F;20260706181511','TEST-FACT-URES-Z2Q6-AS5S-T5R6','1124/1227 FV','CD01004990-1','',3,'product',NULL,NULL,NULL,NULL,NULL),(451,'2026/000438',3232.00,512.00,3232.00,'[{\"type\": \"ESPECES\", \"amount\": 3232}]',11,1,'2026-07-06 19:20:36',NULL,'RDCF01;CD01004990-1;TESTFACTURESQCI6PXNJAR7P;A1720894F;20260706182037','TEST-FACT-URES-QCI6-PXNJ-AR7P','1125/1228 FV','CD01004990-1','',3,'product',NULL,NULL,NULL,NULL,NULL),(452,'2026/000439',3232.00,512.00,3232.00,'[{\"type\": \"ESPECES\", \"amount\": 3232}]',11,1,'2026-07-06 19:21:22',NULL,'RDCF01;CD01004990-1;TESTFACTURESNJ5HEXWE4RA4;A1720894F;20260706182123','TEST-FACT-URES-NJ5H-EXWE-4RA4','1126/1229 FV','CD01004990-1','',3,'product',NULL,NULL,NULL,NULL,NULL),(453,'2026/000440',3232.00,512.00,3232.00,'[{\"type\": \"ESPECES\", \"amount\": 3232}]',11,1,'2026-07-06 19:30:45',NULL,'RDCF01;CD01004990-1;TESTFACTURESGRVCJQLAXP5R;A1720894F;20260706183046','TEST-FACT-URES-GRVC-JQLA-XP5R','1127/1230 FV','CD01004990-1','',3,'product',NULL,NULL,NULL,NULL,NULL),(454,'2026/000441',15232.00,2560.00,15232.00,'[{\"type\": \"ESPECES\", \"amount\": 15232}]',11,1,'2026-07-06 19:31:31',NULL,'RDCF01;CD01004990-1;TESTFACTURESBN4HYPSHCCS7;A1720894F;20260706183132','TEST-FACT-URES-BN4H-YPSH-CCS7','1128/1231 FV','CD01004990-1','',3,'product',NULL,NULL,NULL,NULL,NULL),(455,'2026/000442',3158.00,528.00,3158.00,'[{\"type\": \"ESPECES\", \"amount\": 3158}]',11,1,'2026-07-07 11:30:53',NULL,'RDCF01;CD01004990-1;TESTFACTURESJYFFNVELRF3A;A1720894F;20260707103048','TEST-FACT-URES-JYFF-NVEL-RF3A','1142/1245 FV','CD01004990-1','',3,'product',NULL,NULL,NULL,NULL,NULL),(456,'2026/000443',3348.00,528.00,3348.00,'[{\"type\": \"ESPECES\", \"amount\": 3348}]',11,1,'2026-07-07 11:46:42',NULL,'RDCF01;CD01004990-1;TESTFACTURESGX3W722DBFJ7;A1720894F;20260707104637','TEST-FACT-URES-GX3W-722D-BFJ7','1143/1246 FV','CD01004990-1','',3,'product',NULL,NULL,NULL,NULL,NULL),(457,'2026/000444',3348.00,528.00,3348.00,'[{\"type\": \"ESPECES\", \"amount\": 3348}]',11,1,'2026-07-07 11:53:09',NULL,'RDCF01;CD01004990-1;TESTFACTURESK4K2D6ZU7UXZ;A1720894F;20260707105304','TEST-FACT-URES-K4K2-D6ZU-7UXZ','1144/1247 FV','CD01004990-1','',3,'product',NULL,NULL,NULL,NULL,NULL),(458,'2026/000445',3326.00,286.00,3326.00,'[{\"type\": \"ESPECES\", \"amount\": 3326}]',11,1,'2026-07-07 11:55:44',NULL,'RDCF01;CD01004990-1;TESTFACTURES3GRY3DX3PMNP;A1720894F;20260707105539','TEST-FACT-URES-3GRY-3DX3-PMNP','1145/1248 FV','CD01004990-1','',3,'product',NULL,NULL,NULL,NULL,NULL),(459,'2026/000446',193967.18,30596.59,193967.18,'[{\"type\": \"ESPECES\", \"amount\": 193967.18}]',11,1,'2026-07-07 12:01:30',NULL,'RDCF01;CD01004990-1;TESTFACTURESVADFOCVX7MUH;A1720894F;20260707110125','TEST-FACT-URES-VADF-OCVX-7MUH','1146/1249 FV','CD01004990-1','',3,'product',NULL,NULL,NULL,NULL,NULL),(460,'2026/000447',3326.00,286.00,3326.00,'[{\"type\": \"ESPECES\", \"amount\": 3326}]',11,1,'2026-07-07 12:45:26',NULL,'RDCF01;CD01004990-1;TESTFACTURESNTRZ653KL2BL;A1720894F;20260707114521','TEST-FACT-URES-NTRZ-653K-L2BL','1147/1250 FV','CD01004990-1','',3,'product',NULL,NULL,NULL,NULL,NULL),(461,'2026/000448',1200.00,0.00,1200.00,'[{\"type\": \"ESPECES\", \"amount\": 1200}]',11,1,'2026-07-07 17:26:23',NULL,'RDCF01;CD01004990-1;TESTFACTURESL6PT6TP2FBZI;A1720894F;20260707162622','TEST-FACT-URES-L6PT-6TP2-FBZI','1149/1252 FV','CD01004990-1','',3,'product',NULL,NULL,NULL,NULL,NULL),(462,'2026/000449',1100.00,0.00,1100.00,'[{\"type\": \"ESPECES\", \"amount\": 1100}]',11,1,'2026-07-07 17:28:53',NULL,'RDCF01;CD01004990-1;TESTFACTURESES7VLM6YFWYM;A1720894F;20260707162852','TEST-FACT-URES-ES7V-LM6Y-FWYM','1150/1253 FV','CD01004990-1','',3,'product',NULL,NULL,NULL,NULL,NULL),(463,'2026/000450',2210.00,110.00,2210.00,'[{\"type\": \"ESPECES\", \"amount\": 2210}]',11,1,'2026-07-07 17:34:08',NULL,'RDCF01;CD01004990-1;TESTFACTURESOONSHUAXHDTN;A1720894F;20260707163407','TEST-FACT-URES-OONS-HUAX-HDTN','1151/1254 FV','CD01004990-1','',3,'product',NULL,NULL,NULL,NULL,NULL),(464,'2026/000451',2210.00,110.00,2210.00,'[{\"type\": \"ESPECES\", \"amount\": 2210}]',11,1,'2026-07-07 17:36:49',NULL,'RDCF01;CD01004990-1;TESTFACTURESHNNKY4UB7URP;A1720894F;20260707163648','TEST-FACT-URES-HNNK-Y4UB-7URP','1152/1255 FV','CD01004990-1','',3,'product',NULL,NULL,NULL,NULL,NULL),(465,'2026/000452',2210.00,110.00,2210.00,'[{\"type\": \"ESPECES\", \"amount\": 2210}]',11,1,'2026-07-07 17:40:57',NULL,'RDCF01;CD01004990-1;TESTFACTURES37RJRQ64OHTA;A1720894F;20260707164056','TEST-FACT-URES-37RJ-RQ64-OHTA','1153/1256 FV','CD01004990-1','',3,'product',NULL,NULL,NULL,NULL,NULL),(466,'2026/000453',2210.00,110.00,2210.00,'[{\"type\": \"ESPECES\", \"amount\": 2210}]',11,1,'2026-07-07 17:42:21',NULL,'RDCF01;CD01004990-1;TESTFACTURESORIXAIUOTJQI;A1720894F;20260707164220','TEST-FACT-URES-ORIX-AIUO-TJQI','1154/1257 FV','CD01004990-1','',3,'product',NULL,NULL,NULL,NULL,NULL),(467,'2026/000454',2210.00,110.00,2210.00,'[{\"type\": \"ESPECES\", \"amount\": 2210}]',11,1,'2026-07-07 17:53:05',NULL,'RDCF01;CD01004990-1;TESTFACTURES4NGVMFJX4KO3;A1720894F;20260707165304','TEST-FACT-URES-4NGV-MFJX-4KO3','1155/1258 FV','CD01004990-1','',3,'product',NULL,NULL,NULL,NULL,NULL),(468,'2026/000455',3310.00,110.00,3310.00,'[{\"type\": \"ESPECES\", \"amount\": 3310}]',11,1,'2026-07-07 17:55:58',NULL,'RDCF01;CD01004990-1;TESTFACTURESD4DUIDPCCRI7;A1720894F;20260707165557','TEST-FACT-URES-D4DU-IDPC-CRI7','1156/1259 FV','CD01004990-1','',3,'product',NULL,NULL,NULL,NULL,NULL),(469,'2026/000456',1100.00,0.00,1100.00,'[{\"type\": \"ESPECES\", \"amount\": 1100}]',11,1,'2026-07-07 18:29:17',NULL,'RDCF01;CD01004990-1;TESTFACTURESSCUIJ3JTIB5J;A1720894F;20260707172916','TEST-FACT-URES-SCUI-J3JT-IB5J','1157/1260 FV','CD01004990-1','',3,'product',NULL,NULL,NULL,NULL,NULL),(470,'2026/000457',1100.00,0.00,1100.00,'[{\"type\": \"ESPECES\", \"amount\": 1100}]',11,1,'2026-07-07 18:43:30',NULL,'RDCF01;CD01004990-1;TESTFACTURESFQNWE6YPBWVG;A1720894F;20260707174329','TEST-FACT-URES-FQNW-E6YP-BWVG','1158/1261 FV','CD01004990-1','',3,'product',NULL,NULL,NULL,NULL,NULL),(471,'2026/000458',3310.00,110.00,3310.00,'[{\"type\": \"ESPECES\", \"amount\": 3310}]',11,1,'2026-07-07 19:45:19',NULL,'RDCF01;CD01004990-1;TESTFACTURESMCXL4FJ2OL5I;A1720894F;20260707184517','TEST-FACT-URES-MCXL-4FJ2-OL5I','1159/1262 FV','CD01004990-1','',3,'product',NULL,NULL,NULL,NULL,NULL),(472,'2026/000459',3310.00,110.00,3310.00,'[{\"type\": \"ESPECES\", \"amount\": 3310}]',11,1,'2026-07-07 19:52:21',NULL,'RDCF01;CD01004990-1;TESTFACTURESWSQM5FPH3MJW;A1720894F;20260707185217','TEST-FACT-URES-WSQM-5FPH-3MJW','1160/1263 FV','CD01004990-1','',3,'product',NULL,NULL,NULL,NULL,NULL),(473,'2026/000460',1100.00,0.00,1100.00,'[{\"type\": \"ESPECES\", \"amount\": 1100}]',11,1,'2026-07-07 19:54:16',NULL,'RDCF01;CD01004990-1;TESTFACTURESKKSACWRMSUYZ;A1720894F;20260707185414','TEST-FACT-URES-KKSA-CWRM-SUYZ','1161/1264 FV','CD01004990-1','',3,'product',NULL,NULL,NULL,NULL,NULL),(474,'2026/000461',1100.00,0.00,1100.00,'[{\"type\": \"ESPECES\", \"amount\": 1100}]',11,1,'2026-07-08 08:38:27',NULL,'RDCF01;CD01004990-1;TESTFACTURESZLT4EOJOP3AH;A1720894F;20260708073823','TEST-FACT-URES-ZLT4-EOJO-P3AH','1164/1267 FV','CD01004990-1','',3,'product',NULL,NULL,NULL,NULL,NULL),(475,'2026/000462',1100.00,0.00,1100.00,'[{\"type\": \"ESPECES\", \"amount\": 1100}]',11,1,'2026-07-08 19:56:23',NULL,'RDCF01;CD01004990-1;TESTFACTURESUH5DUCGF27B2;A1720894F;20260708185621','TEST-FACT-URES-UH5D-UCGF-27B2','1166/1269 FV','CD01004990-1','',3,'product',NULL,NULL,NULL,NULL,NULL),(476,'2026/000463',4310.00,110.00,4310.00,'[{\"type\": \"ESPECES\", \"amount\": 4310}]',11,1,'2026-07-08 19:57:11',NULL,'RDCF01;CD01004990-1;TESTFACTURESWY6E33FGMENA;A1720894F;20260708185709','TEST-FACT-URES-WY6E-33FG-MENA','1167/1270 FV','CD01004990-1','',3,'product',NULL,NULL,NULL,NULL,NULL),(477,'2026/000464',3310.00,110.00,3310.00,'[{\"type\": \"ESPECES\", \"amount\": 3310}]',11,1,'2026-07-08 19:59:29',NULL,'RDCF01;CD01004990-1;TESTFACTURES5JJ53SJDARSH;A1720894F;20260708185927','TEST-FACT-URES-5JJ5-3SJD-ARSH','1168/1271 FV','CD01004990-1','',3,'product',NULL,NULL,NULL,NULL,NULL),(478,'2026/000465',3310.00,110.00,3310.00,'[{\"type\": \"ESPECES\", \"amount\": 3310}]',11,1,'2026-07-08 19:59:52',NULL,'RDCF01;CD01004990-1;TESTFACTURESCJISVDOOCUPW;A1720894F;20260708185951','TEST-FACT-URES-CJIS-VDOO-CUPW','1169/1272 FV','CD01004990-1','',8,'product',NULL,NULL,NULL,NULL,NULL),(479,'2026/000466',5100000.00,0.00,5100000.00,'[{\"type\": \"ESPECES\", \"amount\": 5100000}]',11,1,'2026-07-11 16:57:55',NULL,'RDCF01;CD01004990-1;TESTFACTURESKDFXTIIKONCG;A1720894F;20260711155756','TEST-FACT-URES-KDFX-TIIK-ONCG','1174/1277 FV','CD01004990-1','',3,'product',NULL,NULL,NULL,NULL,NULL),(480,'2026/000467',50100000.00,0.00,50100000.00,'[{\"type\": \"ESPECES\", \"amount\": 50100000}]',11,1,'2026-07-11 16:59:39',NULL,'RDCF01;CD01004990-1;TESTFACTURESYFA3PSB5HPFC;A1720894F;20260711155940','TEST-FACT-URES-YFA3-PSB5-HPFC','1175/1278 FV','CD01004990-1','',3,'product',NULL,NULL,NULL,NULL,NULL),(481,'2026/000468',1103410.00,110.00,1103410.00,'[{\"type\": \"ESPECES\", \"amount\": 1103410}]',11,1,'2026-07-15 10:11:50',NULL,'RDCF01;CD01004990-1;TESTFACTURESNZAMBGNMV56T;A1720894F;20260715091146','TEST-FACT-URES-NZAM-BGNM-V56T','1196/1303 FV','CD01004990-1','',3,'product',NULL,NULL,NULL,NULL,NULL),(482,'2026/000469',1143410.25,6510.04,1143410.25,'[{\"type\": \"ESPECES\", \"amount\": 1003410.25}, {\"type\": \"CARTEBANCAIRE\", \"amount\": 140000}]',11,1,'2026-07-15 11:10:34',NULL,'RDCF01;CD01004990-1;TESTFACTURESP7MGDHSBSEDC;A1720894F;20260715101030','TEST-FACT-URES-P7MG-DHSB-SEDC','1198/1305 FV','CD01004990-1','',3,'product',NULL,NULL,NULL,NULL,NULL),(483,'2026/000470',1102210.00,110.00,1102210.00,'[{\"type\": \"ESPECES\", \"amount\": 1102210}]',11,1,'2026-07-15 12:07:43',NULL,'RDCF01;CD01004990-1;TESTFACTURESYXWG24BBQEL3;A1720894F;20260715110738','TEST-FACT-URES-YXWG-24BB-QEL3','1203/1310 FV','CD01004990-1','',3,'product',NULL,NULL,NULL,NULL,NULL),(484,'2026/000471',2100000.00,0.00,2100000.00,'[{\"type\": \"ESPECES\", \"amount\": 2100000}]',11,1,'2026-07-15 12:09:00',NULL,'RDCF01;CD01004990-1;TESTFACTURES7PHMWWKRSWIJ;A1720894F;20260715110855','TEST-FACT-URES-7PHM-WWKR-SWIJ','1204/1311 FV','CD01004990-1','',3,'product',NULL,NULL,NULL,NULL,NULL),(485,'2026/000472',3102210.00,110.00,3102210.00,'[{\"type\": \"ESPECES\", \"amount\": 3102210}]',11,1,'2026-07-15 12:25:10',NULL,'RDCF01;CD01004990-1;TESTFACTURES4WR663SRUAVB;A1720894F;20260715112506','TEST-FACT-URES-4WR6-63SR-UAVB','1209/1316 FV','CD01004990-1','',3,'product',NULL,NULL,NULL,NULL,NULL),(486,'2026/000473',190001.19,30400.19,190001.19,'[{\"type\": \"ESPECES\", \"amount\": 190001.19}]',11,1,'2026-07-15 14:41:53',NULL,'RDCF01;CD01004990-1;TESTFACTURES4CIAZR4ELULH;A1720894F;20260715134148','TEST-FACT-URES-4CIA-ZR4E-LULH','1218/1328 FV','CD01004990-1','',3,'product',NULL,NULL,NULL,NULL,NULL),(487,'2026/000474',190065.19,30410.43,190065.19,'[{\"type\": \"ESPECES\", \"amount\": 190065.19}]',11,1,'2026-07-15 14:42:30',NULL,'RDCF01;CD01004990-1;TESTFACTURESNFQ3OMT7MB62;A1720894F;20260715134226','TEST-FACT-URES-NFQ3-OMT7-MB62','1219/1329 FV','CD01004990-1','',3,'product',NULL,NULL,NULL,NULL,NULL),(488,'2026/000475',188601.18,30176.19,188601.18,'[{\"type\": \"ESPECES\", \"amount\": 188601.18}]',11,1,'2026-07-15 14:49:58',NULL,'RDCF01;CD01004990-1;TESTFACTURESYYWIBSRFCRZH;A1720894F;20260715134953','TEST-FACT-URES-YYWI-BSRF-CRZH','1220/1330 FV','CD01004990-1','',3,'product',NULL,NULL,NULL,NULL,NULL),(489,'2026/000476',1100000.00,0.00,1100000.00,'[{\"type\": \"ESPECES\", \"amount\": 1100000}]',11,1,'2026-07-20 23:37:37',NULL,'RDCF01;CD01004990-1;TESTFACTURESMYUZ77E64WGL;A1720894F;20260720223736','TEST-FACT-URES-MYUZ-77E6-4WGL','1281/1391 FV','CD01004990-1','',3,'product',NULL,NULL,NULL,NULL,NULL),(490,'2026/000477',1102210.00,110.00,1102210.00,'[{\"type\": \"ESPECES\", \"amount\": 1102210}]',11,1,'2026-07-21 12:05:43',NULL,'RDCF01;CD01004990-1;TESTFACTURESWQABTVB43UWD;A1720894F;20260721110540','TEST-FACT-URES-WQAB-TVB4-3UWD','1283/1393 FV','CD01004990-1','',3,'product',NULL,NULL,NULL,NULL,NULL),(491,'2026/000478',5000.00,0.00,5000.00,'[{\"type\": \"ESPECES\", \"amount\": 5000}]',11,1,'2026-07-25 19:53:58',NULL,'RDCF01;CD01004990-1;TESTFACTURESKF2Y7RQBVPQZ;A1720894F;20260725185358','TEST-FACT-URES-KF2Y-7RQB-VPQZ','1285/1395 FV','CD01004990-1','',3,'product',NULL,NULL,NULL,NULL,NULL),(492,'2026/000479',140.00,7.00,140.00,'[{\"type\": \"ESPECES\", \"amount\": 140}]',11,1,'2026-07-28 17:21:53',NULL,'RDCF01;CD01004990-1;TESTFACTURESALZJAKPIDMDU;A1720894F;20260728182133','TEST-FACT-URES-ALZJ-AKPI-DMDU','1286/1396 FV','CD01004990-1','',3,'product',NULL,NULL,NULL,NULL,NULL);
/*!40000 ALTER TABLE `ventes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `ventes_archive`
--

DROP TABLE IF EXISTS `ventes_archive`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ventes_archive` (
  `id` int NOT NULL,
  `numero_facture` varchar(50) NOT NULL,
  `sous_total_ht` decimal(10,2) NOT NULL,
  `tva` decimal(10,2) NOT NULL,
  `total` decimal(10,2) NOT NULL,
  `payments` json DEFAULT NULL,
  `vendeur_id` int NOT NULL,
  `shop_id` int DEFAULT NULL,
  `date` datetime DEFAULT NULL,
  `dateDGI` varchar(100) DEFAULT NULL,
  `qrCode` text,
  `codeDEFDGI` varchar(100) DEFAULT NULL,
  `counters` varchar(100) DEFAULT NULL,
  `nim` varchar(100) DEFAULT NULL,
  `comment` text,
  `client_id` int DEFAULT NULL,
  `type_vente` enum('product','bill_payment') DEFAULT 'product',
  `provider_id` int DEFAULT NULL,
  `numero_compteur` varchar(50) DEFAULT NULL,
  `client_reference` varchar(100) DEFAULT NULL,
  `api_response` text,
  `service` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_archive_date` (`date`),
  KEY `idx_archive_shop` (`shop_id`),
  KEY `idx_archive_vendeur` (`vendeur_id`),
  KEY `idx_archive_facture` (`numero_facture`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `ventes_archive`
--

LOCK TABLES `ventes_archive` WRITE;
/*!40000 ALTER TABLE `ventes_archive` DISABLE KEYS */;
/*!40000 ALTER TABLE `ventes_archive` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-08-01 20:42:45
