-- Database Schema for PT Data Lake Indonesia
-- Run this if you are using MySQL / phpMyAdmin

CREATE DATABASE IF NOT EXISTS `datalake_db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `datalake_db`;

-- Users Table
CREATE TABLE IF NOT EXISTS `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `username` VARCHAR(50) UNIQUE NOT NULL,
  `email` VARCHAR(100) UNIQUE NOT NULL,
  `phone` VARCHAR(30) NULL,
  `password` VARCHAR(255) NOT NULL,
  `role` VARCHAR(20) DEFAULT 'user',
  `status` VARCHAR(20) DEFAULT 'active',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `last_login` TIMESTAMP NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Inquiries Table
CREATE TABLE IF NOT EXISTS `inquiries` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `email` VARCHAR(100) NOT NULL,
  `phone` VARCHAR(30) NULL,
  `service_package` VARCHAR(100) NULL,
  `location` VARCHAR(150) NULL,
  `message` TEXT NULL,
  `status` VARCHAR(20) DEFAULT 'new',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Activity Logs Table
CREATE TABLE IF NOT EXISTS `activity_logs` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NULL,
  `action` VARCHAR(100) NOT NULL,
  `details` TEXT NULL,
  `ip_address` VARCHAR(45) NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Site Settings Table
CREATE TABLE IF NOT EXISTS `site_settings` (
  `key` VARCHAR(50) PRIMARY KEY,
  `val` TEXT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Starlink Kits Table (Dedicated PKS)
CREATE TABLE IF NOT EXISTS `starlink_kits` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `kit_number` VARCHAR(30) NOT NULL,
  `model` VARCHAR(100) DEFAULT 'Starlink Standard Gen 3 V4',
  `plan_name` VARCHAR(100) DEFAULT 'Dedicated Business PKS Enterprise',
  `location` VARCHAR(150) DEFAULT 'Terminal Operasional',
  `status` VARCHAR(20) DEFAULT 'online',
  `ip_address` VARCHAR(50) DEFAULT '100.64.12.81',
  `sla_percent` DECIMAL(5,2) DEFAULT 99.98,
  `download_speed` INT DEFAULT 285,
  `upload_speed` INT DEFAULT 45,
  `ping_ms` INT DEFAULT 24,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Default Admin Account (Password: admin123)
INSERT INTO `users` (`name`, `username`, `email`, `phone`, `password`, `role`, `status`) 
VALUES ('Administrator Data Lake', 'admin', 'admin@datalake.id', '08170117800', '$2y$10$wEkgz/e8WpL4gR6pmsLrqeSZZz5J7hY4k7Wb0pM6v3Rj7s8XqX4e.', 'admin', 'active')
ON DUPLICATE KEY UPDATE `username` = `username`;

-- Default Site Settings
INSERT INTO `site_settings` (`key`, `val`) VALUES 
('wa_number', '08170117800'),
('store_title', 'Data Lake Official Store')
ON DUPLICATE KEY UPDATE `val` = VALUES(`val`);

