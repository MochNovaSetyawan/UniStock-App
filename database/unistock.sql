-- ============================================
-- UNISTOCK - University Inventory System
-- Database Schema (synced with phpMyAdmin)
-- Last updated: 2026-05-16
-- ============================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

CREATE DATABASE IF NOT EXISTS `unistock` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `unistock`;

-- ============================================
-- TABLE: users
-- ============================================
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================
-- TABLE: categories
-- ============================================
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================
-- TABLE: locations
-- ============================================
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================
-- TABLE: items (inventory)
-- ============================================
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================
-- TABLE: item_units (per-unit tracking)
-- ============================================
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================
-- TABLE: transactions (borrow/return/transfer)
-- ============================================
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
  `return_requested_at` datetime DEFAULT NULL,
  `return_requested_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================
-- TABLE: transaction_units
-- ============================================
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================
-- TABLE: maintenance
-- ============================================
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
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================
-- TABLE: settings
-- ============================================
CREATE TABLE `settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `key` varchar(100) NOT NULL,
  `value` text DEFAULT NULL,
  `description` varchar(255) DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `key` (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================
-- TABLE: messages
-- ============================================
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- SEED DATA
-- ============================================

-- Default users (password: admin123)
INSERT INTO `users` (`username`, `password`, `full_name`, `email`, `role`, `department`, `is_active`) VALUES
('superadmin', '$2y$10$xRUlcz5BRf8WAtHMaaHGLeLwycrTonnpI8vHbfCdUTNsFovReYPKK', 'Super Administrator', 'superadmin@unistock.ac.id', 'superadmin', 'IT Department', 1),
('admin',      '$2y$10$xRUlcz5BRf8WAtHMaaHGLeLwycrTonnpI8vHbfCdUTNsFovReYPKK', 'Administrator',       'admin@unistock.ac.id',      'admin',      'General Affairs', 1),
('worker1',    '$2y$10$xRUlcz5BRf8WAtHMaaHGLeLwycrTonnpI8vHbfCdUTNsFovReYPKK', 'Budi Santoso',        'budi@unistock.ac.id',       'worker',     'Faculty of Engineering', 1);

-- Default Settings
INSERT INTO `settings` (`key`, `value`, `description`) VALUES
('app_name',            'UniStock',         'Nama aplikasi'),
('university_name',     'Universitas Anda', 'Nama lengkap universitas'),
('university_logo',     '',                 'Path logo universitas'),
('app_version',         '1.0.0',            'Versi aplikasi'),
('items_per_page',      '15',               'Jumlah item per halaman'),
('borrow_max_days',     '14',               'Maksimal hari peminjaman'),
('low_stock_threshold', '5',                'Alert stok di bawah nilai ini'),
('allow_worker_borrow', '1',                'Izinkan worker mengajukan pinjam'),
('require_approval',    '1',                'Butuh persetujuan admin untuk pinjam'),
('timezone',            'Asia/Jakarta',     'Timezone aplikasi');

COMMIT;
