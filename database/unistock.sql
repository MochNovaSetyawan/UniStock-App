-- ============================================
-- UNISTOCK - University Inventory System
-- Database Schema
-- ============================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

CREATE DATABASE IF NOT EXISTS `unistock` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `unistock`;

-- ============================================
-- TABLE: users
-- ============================================
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL UNIQUE,
  `password` varchar(255) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL UNIQUE,
  `phone` varchar(20) DEFAULT NULL,
  `role` enum('superadmin','admin','worker') NOT NULL DEFAULT 'worker',
  `department` varchar(100) DEFAULT NULL,
  `avatar` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `last_login` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- TABLE: categories
-- ============================================
CREATE TABLE `categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `code` varchar(20) NOT NULL UNIQUE,
  `description` text DEFAULT NULL,
  `color` varchar(7) DEFAULT '#6366f1',
  `icon` varchar(50) DEFAULT 'box',
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- TABLE: locations
-- ============================================
CREATE TABLE `locations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `code` varchar(20) NOT NULL UNIQUE,
  `building` varchar(100) DEFAULT NULL,
  `floor` varchar(10) DEFAULT NULL,
  `room` varchar(50) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `capacity` int(11) DEFAULT NULL,
  `pic_name` varchar(100) DEFAULT NULL,
  `pic_phone` varchar(20) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- TABLE: items (inventory)
-- ============================================
CREATE TABLE `items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `code` varchar(50) NOT NULL UNIQUE,
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
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`category_id`) REFERENCES `categories`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`location_id`) REFERENCES `locations`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`updated_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- TABLE: transactions (borrow/return/transfer)
-- ============================================
CREATE TABLE `transactions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `code` varchar(50) NOT NULL UNIQUE,
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
  `status` enum('pending','approved','active','returned','overdue','rejected','cancelled') NOT NULL DEFAULT 'pending',
  `notes` text DEFAULT NULL,
  `requested_by` int(11) DEFAULT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `returned_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`item_id`) REFERENCES `items`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`from_location_id`) REFERENCES `locations`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`to_location_id`) REFERENCES `locations`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`requested_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`approved_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`returned_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- TABLE: maintenance
-- ============================================
CREATE TABLE `maintenance` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `code` varchar(50) NOT NULL UNIQUE,
  `item_id` int(11) NOT NULL,
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
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`item_id`) REFERENCES `items`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`requested_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`assigned_to`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- TABLE: audit_logs
-- ============================================
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
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- TABLE: settings
-- ============================================
CREATE TABLE `settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `key` varchar(100) NOT NULL UNIQUE,
  `value` text DEFAULT NULL,
  `description` varchar(255) DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`updated_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- TABLE: notifications
-- ============================================
CREATE TABLE `notifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `title` varchar(150) NOT NULL,
  `message` text NOT NULL,
  `type` enum('info','success','warning','danger') DEFAULT 'info',
  `link` varchar(255) DEFAULT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- SEED DATA
-- ============================================

-- Default Superadmin (password: admin123)
INSERT INTO `users` (`username`, `password`, `full_name`, `email`, `role`, `department`, `is_active`) VALUES
('superadmin', '$2y$10$xRUlcz5BRf8WAtHMaaHGLeLwycrTonnpI8vHbfCdUTNsFovReYPKK', 'Super Administrator', 'superadmin@unistock.ac.id', 'superadmin', 'IT Department', 1),
('admin', '$2y$10$xRUlcz5BRf8WAtHMaaHGLeLwycrTonnpI8vHbfCdUTNsFovReYPKK', 'Administrator', 'admin@unistock.ac.id', 'admin', 'General Affairs', 1),
('worker1', '$2y$10$xRUlcz5BRf8WAtHMaaHGLeLwycrTonnpI8vHbfCdUTNsFovReYPKK', 'Budi Santoso', 'budi@unistock.ac.id', 'worker', 'Faculty of Engineering', 1);

-- Default Categories
INSERT INTO `categories` (`name`, `code`, `description`, `color`, `icon`, `created_by`) VALUES
('Electronics', 'ELEC', 'Electronic devices and equipment', '#6366f1', 'cpu', 1),
('Furniture', 'FURN', 'Office and classroom furniture', '#10b981', 'layout', 1),
('Stationery', 'STAT', 'Office supplies and stationery', '#f59e0b', 'file-text', 1),
('Laboratory Equipment', 'LABE', 'Scientific laboratory instruments', '#3b82f6', 'flask', 1),
('Sports Equipment', 'SPRT', 'Sports and recreation equipment', '#ef4444', 'activity', 1),
('Audio Visual', 'AVMT', 'Audio and visual media equipment', '#8b5cf6', 'monitor', 1);

-- Default Locations
INSERT INTO `locations` (`name`, `code`, `building`, `floor`, `room`, `description`, `created_by`) VALUES
('Gedung Rektorat Lt.1', 'GR-L1', 'Gedung Rektorat', '1', 'R. Administrasi', 'Kantor administrasi pusat', 1),
('Gedung Rektorat Lt.2', 'GR-L2', 'Gedung Rektorat', '2', 'R. Pimpinan', 'Ruang pimpinan universitas', 1),
('Fakultas Teknik - Lab Komputer', 'FT-LK', 'Gedung Fakultas Teknik', '1', 'Lab. 101', 'Laboratorium komputer FT', 1),
('Fakultas Teknik - Ruang Kelas A', 'FT-KA', 'Gedung Fakultas Teknik', '2', 'Kelas 201', 'Ruang kelas reguler', 1),
('Perpustakaan Pusat', 'PERP', 'Gedung Perpustakaan', '1', 'Main Hall', 'Perpustakaan utama universitas', 1),
('Gudang Umum', 'GDG', 'Gedung Logistik', 'G', 'Gudang Utama', 'Gudang penyimpanan barang umum', 1),
('Aula Utama', 'AULA', 'Gedung Serbaguna', '1', 'Aula', 'Aula untuk kegiatan besar', 1),
('Ruang Olahraga', 'SPRT', 'Gedung Olahraga', '1', 'Hall', 'Fasilitas olahraga indoor', 1);

-- Default Settings
INSERT INTO `settings` (`key`, `value`, `description`) VALUES
('app_name', 'Unistock', 'Application name'),
('university_name', 'Universitas Nusantara', 'University full name'),
('university_logo', '', 'University logo path'),
('app_version', '1.0.0', 'Application version'),
('items_per_page', '15', 'Items displayed per page'),
('borrow_max_days', '14', 'Maximum borrowing period in days'),
('low_stock_threshold', '5', 'Alert when stock below this value'),
('allow_worker_borrow', '1', 'Allow workers to submit borrow requests'),
('require_approval', '1', 'Require admin approval for borrow requests'),
('timezone', 'Asia/Jakarta', 'Application timezone');

-- Sample Items
INSERT INTO `items` (`code`, `name`, `brand`, `model`, `category_id`, `location_id`, `quantity`, `quantity_available`, `condition`, `purchase_date`, `purchase_price`, `created_by`) VALUES
('ELEC-001', 'Laptop Dell Latitude', 'Dell', 'Latitude 5520', 1, 3, 20, 18, 'good', '2023-01-15', 15000000, 1),
('ELEC-002', 'Proyektor Epson', 'Epson', 'EB-X51', 1, 4, 8, 7, 'good', '2023-03-10', 8500000, 1),
('ELEC-003', 'Printer HP LaserJet', 'HP', 'LaserJet Pro M404n', 1, 1, 5, 5, 'good', '2022-06-20', 4200000, 1),
('FURN-001', 'Meja Kerja', 'Olympic', 'WD-120', 2, 1, 50, 50, 'good', '2021-08-01', 1200000, 1),
('FURN-002', 'Kursi Kantor Ergonomis', 'Brother', 'EC-200', 2, 1, 80, 80, 'good', '2021-08-01', 1800000, 1),
('LABE-001', 'Mikroskop Digital', 'Olympus', 'CX23', 4, 3, 10, 9, 'good', '2023-05-15', 25000000, 1),
('AVMT-001', 'Sound System', 'Yamaha', 'StagePas 400BT', 6, 7, 3, 3, 'good', '2022-11-30', 12000000, 1),
('SPRT-001', 'Bola Basket', 'Spalding', 'TF-150', 5, 8, 15, 15, 'good', '2023-02-14', 350000, 1);

-- ============================================
-- MESSAGES TABLE
-- ============================================
CREATE TABLE IF NOT EXISTS `messages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `from_user_id` int(11) NOT NULL,
  `to_user_id` int(11) NOT NULL,
  `message` text NOT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `read_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_from` (`from_user_id`),
  KEY `idx_to` (`to_user_id`),
  KEY `idx_to_read` (`to_user_id`, `is_read`),
  CONSTRAINT `messages_from_fk` FOREIGN KEY (`from_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `messages_to_fk` FOREIGN KEY (`to_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

COMMIT;
