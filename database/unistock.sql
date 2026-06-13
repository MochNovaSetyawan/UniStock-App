-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: localhost    Database: unistock
-- ------------------------------------------------------
-- Server version	10.4.32-MariaDB

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Current Database: `unistock`
--

/*!40000 DROP DATABASE IF EXISTS `unistock`*/;

CREATE DATABASE /*!32312 IF NOT EXISTS*/ `unistock` /*!40100 DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci */;

USE `unistock`;

--
-- Table structure for table `audit_logs`
--

DROP TABLE IF EXISTS `audit_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `audit_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `module` varchar(50) NOT NULL,
  `record_id` int(11) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `old_data` longtext DEFAULT NULL,
  `new_data` longtext DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=24 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `audit_logs`
--

LOCK TABLES `audit_logs` WRITE;
/*!40000 ALTER TABLE `audit_logs` DISABLE KEYS */;
INSERT INTO `audit_logs` VALUES (1,2,'CREATE','categories',1,'Category created: Elektronik',NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-05-14 22:18:13'),(2,2,'CREATE','locations',1,'Location created: Esa Unggul Citra Raya',NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-05-14 22:18:56'),(3,2,'CREATE','inventory',1,'Item created: Laptop',NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-05-14 22:20:10'),(4,3,'CREATE','transactions',1,'Borrow request: TRX-2026050001 (1 unit)',NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-05-14 22:21:09'),(5,2,'UPDATE','transactions',1,'Transaction rejected',NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-05-14 22:21:27'),(6,3,'CREATE','transactions',2,'Borrow request: TRX-2026050002 (1 unit)',NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-05-14 22:21:44'),(7,2,'UPDATE','transactions',2,'Transaction approved (units: 1)',NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-05-14 22:21:53'),(8,3,'UPDATE','transactions',2,'Return requested by worker',NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-05-14 22:22:31'),(9,2,'UPDATE','transactions',2,'Return request rejected by admin',NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-05-14 22:22:54'),(10,2,'UPDATE','transactions',2,'Item returned (unit system)',NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-05-14 22:26:31'),(11,3,'CREATE','transactions',3,'Borrow request: TRX-2026050003 (1 unit)',NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-05-14 22:41:07'),(12,2,'UPDATE','transactions',3,'Transaction approved (units: 1)',NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-05-14 22:41:32'),(13,3,'CREATE','maintenance',7,'Maintenance created: rusak',NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-05-14 22:42:04'),(14,2,'UPDATE','maintenance',7,'Maintenance started: MNT-2026050001',NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-05-14 22:42:30'),(15,2,'LOGOUT','auth',2,'User logged out',NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-05-14 22:56:43'),(16,1,'LOGIN','auth',1,'User logged in',NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-05-14 22:56:51'),(17,2,'LOGIN','auth',2,'User logged in',NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-05-14 23:15:02'),(18,2,'UPDATE','maintenance',7,'Maintenance completed: MNT-2026050001',NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-05-14 23:23:56'),(19,1,'LOGIN','auth',1,'User logged in',NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-05-16 03:56:07'),(20,1,'LOGOUT','auth',1,'User logged out',NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-05-16 03:59:26'),(21,3,'LOGIN','auth',3,'User logged in',NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-05-16 03:59:35'),(22,3,'LOGOUT','auth',3,'User logged out',NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-05-16 04:00:31'),(23,1,'LOGIN','auth',1,'User logged in',NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-05-16 04:00:43');
/*!40000 ALTER TABLE `audit_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `categories`
--

DROP TABLE IF EXISTS `categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `code` varchar(20) NOT NULL,
  `description` text DEFAULT NULL,
  `color` varchar(7) DEFAULT '#6366f1',
  `icon` varchar(50) DEFAULT 'box',
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `categories`
--

LOCK TABLES `categories` WRITE;
/*!40000 ALTER TABLE `categories` DISABLE KEYS */;
INSERT INTO `categories` VALUES (1,'Elektronik','ELEC','','#6366f1','box',2,'2026-05-14 22:18:13','2026-05-14 22:18:13');
/*!40000 ALTER TABLE `categories` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `item_units`
--

DROP TABLE IF EXISTS `item_units`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `item_units` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `item_id` int(11) NOT NULL,
  `unit_number` int(11) NOT NULL,
  `unit_code` varchar(20) NOT NULL,
  `full_code` varchar(200) NOT NULL,
  `status` enum('available','reserved','borrowed','maintenance','damaged','disposed','lost') NOT NULL DEFAULT 'available',
  `condition` enum('good','fair','poor','damaged') NOT NULL DEFAULT 'good',
  `serial_number` varchar(100) DEFAULT NULL,
  `location_id` int(11) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `acquired_date` date DEFAULT NULL,
  `purchase_price` decimal(15,2) DEFAULT NULL,
  `supplier` varchar(200) DEFAULT NULL,
  `disposed_date` date DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `full_code` (`full_code`),
  KEY `item_id` (`item_id`),
  KEY `status` (`status`),
  CONSTRAINT `fk_unit_item` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `item_units`
--

LOCK TABLES `item_units` WRITE;
/*!40000 ALTER TABLE `item_units` DISABLE KEYS */;
INSERT INTO `item_units` VALUES (1,1,1,'U001','ELEC-LAP-DEL-SUP-U001','available','good',NULL,1,NULL,NULL,5000000.00,NULL,NULL,'2026-05-14 22:20:10','2026-05-14 23:23:56'),(2,1,2,'U002','ELEC-LAP-DEL-SUP-U002','available','good',NULL,1,NULL,NULL,5000000.00,NULL,NULL,'2026-05-14 22:20:10','2026-05-14 22:20:10'),(3,1,3,'U003','ELEC-LAP-DEL-SUP-U003','available','good',NULL,1,NULL,NULL,5000000.00,NULL,NULL,'2026-05-14 22:20:10','2026-05-14 22:20:10'),(4,1,4,'U004','ELEC-LAP-DEL-SUP-U004','available','good',NULL,1,NULL,NULL,5000000.00,NULL,NULL,'2026-05-14 22:20:10','2026-05-14 22:20:10'),(5,1,5,'U005','ELEC-LAP-DEL-SUP-U005','available','good',NULL,1,NULL,NULL,5000000.00,NULL,NULL,'2026-05-14 22:20:10','2026-05-14 22:20:10'),(6,1,6,'U006','ELEC-LAP-DEL-SUP-U006','available','good',NULL,1,NULL,NULL,5000000.00,NULL,NULL,'2026-05-14 22:20:10','2026-05-14 22:20:10'),(7,1,7,'U007','ELEC-LAP-DEL-SUP-U007','available','good',NULL,1,NULL,NULL,5000000.00,NULL,NULL,'2026-05-14 22:20:10','2026-05-14 22:20:10'),(8,1,8,'U008','ELEC-LAP-DEL-SUP-U008','available','good',NULL,1,NULL,NULL,5000000.00,NULL,NULL,'2026-05-14 22:20:10','2026-05-14 22:20:10'),(9,1,9,'U009','ELEC-LAP-DEL-SUP-U009','available','good',NULL,1,NULL,NULL,5000000.00,NULL,NULL,'2026-05-14 22:20:10','2026-05-14 22:20:10'),(10,1,10,'U010','ELEC-LAP-DEL-SUP-U010','available','good',NULL,1,NULL,NULL,5000000.00,NULL,NULL,'2026-05-14 22:20:10','2026-05-14 22:20:10');
/*!40000 ALTER TABLE `item_units` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `items`
--

DROP TABLE IF EXISTS `items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `code` varchar(50) NOT NULL,
  `name` varchar(150) NOT NULL,
  `brand` varchar(100) DEFAULT NULL,
  `model` varchar(100) DEFAULT NULL,
  `serial_number` varchar(100) DEFAULT NULL,
  `category_id` int(11) DEFAULT NULL,
  `location_id` int(11) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `quantity` int(11) NOT NULL DEFAULT 1,
  `quantity_available` int(11) NOT NULL DEFAULT 1,
  `unit` varchar(20) DEFAULT 'unit',
  `condition` enum('good','fair','poor','damaged','lost') NOT NULL DEFAULT 'good',
  `status` enum('active','inactive','disposed') NOT NULL DEFAULT 'active',
  `purchase_date` date DEFAULT NULL,
  `purchase_price` decimal(15,2) DEFAULT NULL,
  `supplier` varchar(150) DEFAULT NULL,
  `warranty_expiry` date DEFAULT NULL,
  `image` varchar(255) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `min_stock` int(11) DEFAULT 0,
  `created_by` int(11) DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `items`
--

LOCK TABLES `items` WRITE;
/*!40000 ALTER TABLE `items` DISABLE KEYS */;
INSERT INTO `items` VALUES (1,'LAP-DEL-SUP','Laptop','Dell','Super Novas',NULL,1,1,'',10,10,'unit','good','active',NULL,5000000.00,'',NULL,NULL,'',1,2,NULL,'2026-05-14 22:20:10','2026-05-14 23:23:56');
/*!40000 ALTER TABLE `items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `locations`
--

DROP TABLE IF EXISTS `locations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `locations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `code` varchar(20) NOT NULL,
  `building` varchar(100) DEFAULT NULL,
  `floor` varchar(10) DEFAULT NULL,
  `room` varchar(50) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `capacity` int(11) DEFAULT NULL,
  `pic_name` varchar(100) DEFAULT NULL,
  `pic_phone` varchar(20) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `locations`
--

LOCK TABLES `locations` WRITE;
/*!40000 ALTER TABLE `locations` DISABLE KEYS */;
INSERT INTO `locations` VALUES (1,'Esa Unggul Citra Raya','EUCR','','','','',NULL,'','',2,'2026-05-14 22:18:56','2026-05-14 22:18:56');
/*!40000 ALTER TABLE `locations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `maintenance`
--

DROP TABLE IF EXISTS `maintenance`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `maintenance` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `code` varchar(50) NOT NULL,
  `item_id` int(11) NOT NULL,
  `unit_id` int(11) DEFAULT NULL,
  `unit_prev_status` varchar(20) DEFAULT NULL,
  `type` enum('preventive','corrective','inspection') NOT NULL DEFAULT 'corrective',
  `priority` enum('low','medium','high','critical') NOT NULL DEFAULT 'medium',
  `title` varchar(150) NOT NULL,
  `description` text DEFAULT NULL,
  `scheduled_date` date DEFAULT NULL,
  `completed_date` date DEFAULT NULL,
  `technician` varchar(100) DEFAULT NULL,
  `cost` decimal(15,2) DEFAULT NULL,
  `status` enum('pending','in_progress','completed','cancelled') NOT NULL DEFAULT 'pending',
  `resolution` text DEFAULT NULL,
  `requested_by` int(11) DEFAULT NULL,
  `assigned_to` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `maintenance`
--

LOCK TABLES `maintenance` WRITE;
/*!40000 ALTER TABLE `maintenance` DISABLE KEYS */;
/*!40000 ALTER TABLE `maintenance` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `messages`
--

DROP TABLE IF EXISTS `messages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `messages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `from_user_id` int(11) NOT NULL,
  `to_user_id` int(11) NOT NULL,
  `message` text NOT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `read_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_from` (`from_user_id`),
  KEY `idx_to` (`to_user_id`),
  KEY `idx_to_read` (`to_user_id`,`is_read`),
  CONSTRAINT `messages_from_fk` FOREIGN KEY (`from_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `messages_to_fk` FOREIGN KEY (`to_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `messages`
--

LOCK TABLES `messages` WRITE;
/*!40000 ALTER TABLE `messages` DISABLE KEYS */;
INSERT INTO `messages` VALUES (1,1,2,'halo',1,'2026-04-25 17:18:41','2026-04-25 17:18:13'),(2,2,1,'test',1,'2026-04-25 17:25:56','2026-04-25 17:25:47'),(3,1,2,'ngapain bro',1,'2026-04-25 17:26:03','2026-04-25 17:26:01'),(4,2,1,'ngopi',1,'2026-04-25 17:29:03','2026-04-25 17:28:27'),(5,1,3,'oiii',1,'2026-05-14 21:05:18','2026-04-25 17:33:05'),(6,1,3,'halo',1,'2026-05-14 21:05:18','2026-05-02 22:31:51');
/*!40000 ALTER TABLE `messages` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `notifications`
--

DROP TABLE IF EXISTS `notifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `notifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `title` varchar(150) NOT NULL,
  `message` text NOT NULL,
  `type` enum('info','success','warning','danger') DEFAULT 'info',
  `link` varchar(255) DEFAULT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `notifications`
--

LOCK TABLES `notifications` WRITE;
/*!40000 ALTER TABLE `notifications` DISABLE KEYS */;
INSERT INTO `notifications` VALUES (1,2,'Pesan baru dari Super Administrator','halo','info','http://localhost/Unistock-App/modules/messages/index.php?with=1',1,'2026-04-25 17:18:13'),(2,1,'Pesan baru dari Administrator','test','info','http://localhost/Unistock-App/modules/messages/index.php?with=2',1,'2026-04-25 17:25:47'),(3,2,'Pesan baru dari Super Administrator','ngapain bro','info','http://localhost/Unistock-App/modules/messages/index.php?with=1',1,'2026-04-25 17:26:01');
/*!40000 ALTER TABLE `notifications` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `settings`
--

DROP TABLE IF EXISTS `settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `key` varchar(100) NOT NULL,
  `value` text DEFAULT NULL,
  `description` varchar(255) DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `key` (`key`)
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `settings`
--

LOCK TABLES `settings` WRITE;
/*!40000 ALTER TABLE `settings` DISABLE KEYS */;
INSERT INTO `settings` VALUES (1,'app_name','UniStock','Nama aplikasi',1,'2026-04-10 20:11:10'),(2,'university_name','Universitas Esa Unggul','Nama lengkap universitas',1,'2026-05-14 22:12:32'),(3,'university_logo','','Path logo universitas',NULL,'2026-04-10 15:19:09'),(4,'app_version','1.0.0','Versi aplikasi',NULL,'2026-04-10 15:19:09'),(5,'items_per_page','15','Jumlah item per halaman',1,'2026-04-10 20:11:10'),(6,'borrow_max_days','14','Maksimal hari peminjaman',1,'2026-04-10 20:11:10'),(7,'low_stock_threshold','5','Alert stok di bawah nilai ini',1,'2026-04-10 20:11:10'),(8,'allow_worker_borrow','1','Izinkan worker mengajukan pinjam',1,'2026-04-10 20:11:10'),(9,'require_approval','1','Butuh persetujuan admin untuk pinjam',1,'2026-04-10 20:11:10'),(10,'timezone','Asia/Jakarta','Timezone aplikasi',1,'2026-04-10 20:11:10'),(11,'app_logo','logo/69d9000b330f2_1775829003.jpg',NULL,1,'2026-04-10 20:50:03');
/*!40000 ALTER TABLE `settings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `transaction_units`
--

DROP TABLE IF EXISTS `transaction_units`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `transaction_units` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `transaction_id` int(11) NOT NULL,
  `unit_id` int(11) NOT NULL,
  `return_condition` enum('good','fair','poor','damaged') DEFAULT NULL,
  `return_notes` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `tx_unit` (`transaction_id`,`unit_id`),
  KEY `unit_id` (`unit_id`),
  CONSTRAINT `fk_txu_tx` FOREIGN KEY (`transaction_id`) REFERENCES `transactions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_txu_unit` FOREIGN KEY (`unit_id`) REFERENCES `item_units` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `transaction_units`
--

LOCK TABLES `transaction_units` WRITE;
/*!40000 ALTER TABLE `transaction_units` DISABLE KEYS */;
INSERT INTO `transaction_units` VALUES (1,1,1,NULL,NULL),(2,2,1,'good','bagus'),(3,3,1,NULL,NULL);
/*!40000 ALTER TABLE `transaction_units` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `transactions`
--

DROP TABLE IF EXISTS `transactions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `transactions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `code` varchar(50) NOT NULL,
  `type` enum('borrow','return','transfer','dispose') NOT NULL,
  `item_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 1,
  `from_location_id` int(11) DEFAULT NULL,
  `to_location_id` int(11) DEFAULT NULL,
  `borrower_name` varchar(100) DEFAULT NULL,
  `borrower_id_number` varchar(50) DEFAULT NULL,
  `borrower_department` varchar(100) DEFAULT NULL,
  `borrower_phone` varchar(20) DEFAULT NULL,
  `purpose` text DEFAULT NULL,
  `borrow_date` datetime DEFAULT NULL,
  `expected_return` datetime DEFAULT NULL,
  `actual_return` datetime DEFAULT NULL,
  `return_condition` enum('good','fair','poor','damaged') DEFAULT NULL,
  `return_notes` text DEFAULT NULL,
  `status` enum('pending','approved','active','returned','overdue','rejected','cancelled','return_requested') NOT NULL DEFAULT 'pending',
  `notes` text DEFAULT NULL,
  `requested_by` int(11) DEFAULT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `returned_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `return_requested_at` datetime DEFAULT NULL,
  `return_requested_by` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `transactions`
--

LOCK TABLES `transactions` WRITE;
/*!40000 ALTER TABLE `transactions` DISABLE KEYS */;
INSERT INTO `transactions` VALUES (1,'TRX-2026050001','borrow',1,1,NULL,NULL,'Budi Santoso','','Faculty of Engineering','','','2026-05-14 22:20:00','2026-05-28 22:20:00',NULL,NULL,NULL,'rejected','',3,2,'2026-05-14 22:21:27',NULL,'2026-05-14 22:21:09','2026-05-14 22:21:27',NULL,NULL),(2,'TRX-2026050002','borrow',1,1,NULL,NULL,'Budi Santoso','','Faculty of Engineering','','','2026-05-14 22:21:00','2026-05-28 22:21:00','2026-05-14 22:26:31','good','sudah selesai','returned','',3,2,'2026-05-14 22:21:53',2,'2026-05-14 22:21:44','2026-05-14 22:26:31',NULL,NULL),(3,'TRX-2026050003','borrow',1,1,NULL,NULL,'Budi Santoso','','Faculty of Engineering','','','2026-05-14 22:40:00','2028-05-28 22:40:00',NULL,NULL,NULL,'active','',3,2,'2026-05-14 22:41:32',NULL,'2026-05-14 22:41:07','2026-05-14 22:41:32',NULL,NULL);
/*!40000 ALTER TABLE `transactions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `role` enum('superadmin','admin','worker') NOT NULL DEFAULT 'worker',
  `department` varchar(100) DEFAULT NULL,
  `avatar` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `last_login` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,'superadmin','$2y$10$xRUlcz5BRf8WAtHMaaHGLeLwycrTonnpI8vHbfCdUTNsFovReYPKK','Super Administrator','superadmin@unistock.ac.id',NULL,'superadmin','IT Department',NULL,1,'2026-05-16 04:00:43','2026-04-10 15:19:09','2026-05-16 04:00:43'),(2,'admin','$2y$10$xRUlcz5BRf8WAtHMaaHGLeLwycrTonnpI8vHbfCdUTNsFovReYPKK','Administrator','admin@unistock.ac.id',NULL,'admin','General Affairs',NULL,1,'2026-05-14 23:15:02','2026-04-10 15:19:09','2026-05-14 23:15:02'),(3,'worker1','$2y$10$xRUlcz5BRf8WAtHMaaHGLeLwycrTonnpI8vHbfCdUTNsFovReYPKK','Budi Santoso','budi@unistock.ac.id',NULL,'worker','Faculty of Engineering',NULL,1,'2026-05-16 03:59:35','2026-04-10 15:19:09','2026-05-16 03:59:35');
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping routines for database 'unistock'
--
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-06-13 17:59:05
