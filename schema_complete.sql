-- ============================================
-- Gallium Solutions Limited - Loan Management System
-- Complete Database Schema (All-in-One)
-- ============================================
-- This file contains the complete database schema including all updates
-- Use this file for fresh installations

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

-- Database: `gallium_loans`
CREATE DATABASE IF NOT EXISTS `gallium_loans` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `gallium_loans`;

-- ============================================
-- Table: users
-- ============================================
DROP TABLE IF EXISTS `users`;
CREATE TABLE IF NOT EXISTS `users` (
  `user_id` int NOT NULL AUTO_INCREMENT,
  `username` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `password` varchar(255) NOT NULL,
  `user_type` enum('admin','officer','customer') DEFAULT 'customer',
  `is_active` tinyint(1) DEFAULT '1',
  `can_change_password` tinyint(1) DEFAULT '0',
  `theme_preference` enum('light', 'dark') DEFAULT 'light',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `email` (`email`),
  UNIQUE KEY `phone` (`phone`),
  KEY `user_type` (`user_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Table: customers
-- ============================================
DROP TABLE IF EXISTS `customers`;
CREATE TABLE IF NOT EXISTS `customers` (
  `customer_id` int NOT NULL AUTO_INCREMENT,
  `user_id` int DEFAULT NULL,
  `customer_name` varchar(100) NOT NULL,
  `national_id` varchar(50) DEFAULT NULL,
  `date_of_birth` date NOT NULL,
  `gender` enum('male','female') DEFAULT NULL,
  `phone` varchar(20) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `address` text,
  `occupation` varchar(100) DEFAULT NULL,
  `employer` varchar(100) DEFAULT NULL,
  `monthly_income` decimal(12,2) DEFAULT NULL,
  `security` text DEFAULT NULL COMMENT 'Security/collateral item provided by the client',
  `next_of_kin_name` varchar(100) DEFAULT NULL,
  `next_of_kin_phone` varchar(20) DEFAULT NULL,
  `next_of_kin_relationship` varchar(50) DEFAULT NULL,
  `next_of_kin_address` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`customer_id`),
  UNIQUE KEY `national_id` (`national_id`),
  KEY `user_id` (`user_id`),
  KEY `phone` (`phone`),
  CONSTRAINT `fk_customer_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Table: loans
-- ============================================
DROP TABLE IF EXISTS `loans`;
CREATE TABLE IF NOT EXISTS `loans` (
  `loan_id` int NOT NULL AUTO_INCREMENT,
  `customer_id` int NOT NULL,
  `officer_id` int DEFAULT NULL,
  `loan_amount` decimal(12,2) NOT NULL,
  `interest_rate` decimal(5,2) NOT NULL DEFAULT '10.00',
  `loan_term_months` int DEFAULT NULL,
  `loan_term_weeks` int DEFAULT NULL,
  `loan_term_type` enum('months','weeks') DEFAULT 'months',
  `purpose` varchar(255) DEFAULT NULL,
  `application_date` date NOT NULL,
  `approval_date` date DEFAULT NULL,
  `disbursement_date` date DEFAULT NULL,
  `status` enum('pending','approved','rejected','disbursed','active','completed','defaulted') DEFAULT 'pending',
  `rejection_reason` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`loan_id`),
  KEY `customer_id` (`customer_id`),
  KEY `officer_id` (`officer_id`),
  KEY `status` (`status`),
  KEY `application_date` (`application_date`),
  CONSTRAINT `fk_loan_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`customer_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_loan_officer` FOREIGN KEY (`officer_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Table: loan_payments
-- ============================================
DROP TABLE IF EXISTS `loan_payments`;
CREATE TABLE IF NOT EXISTS `loan_payments` (
  `payment_id` int NOT NULL AUTO_INCREMENT,
  `loan_id` int NOT NULL,
  `payment_amount` decimal(12,2) NOT NULL,
  `principal_amount` decimal(12,2) NOT NULL,
  `interest_amount` decimal(12,2) NOT NULL,
  `payment_date` date NOT NULL,
  `payment_method` enum('cash','bank_transfer','mobile_money','cheque') DEFAULT 'cash',
  `transaction_reference` varchar(100) DEFAULT NULL,
  `received_by` int DEFAULT NULL,
  `notes` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`payment_id`),
  KEY `loan_id` (`loan_id`),
  KEY `payment_date` (`payment_date`),
  KEY `received_by` (`received_by`),
  CONSTRAINT `fk_payment_loan` FOREIGN KEY (`loan_id`) REFERENCES `loans` (`loan_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_payment_received_by` FOREIGN KEY (`received_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Table: loan_schedule
-- ============================================
DROP TABLE IF EXISTS `loan_schedule`;
CREATE TABLE IF NOT EXISTS `loan_schedule` (
  `schedule_id` int NOT NULL AUTO_INCREMENT,
  `loan_id` int NOT NULL,
  `installment_number` int NOT NULL,
  `due_date` date NOT NULL,
  `principal_amount` decimal(12,2) NOT NULL,
  `interest_amount` decimal(12,2) NOT NULL,
  `total_amount` decimal(12,2) NOT NULL,
  `paid_amount` decimal(12,2) DEFAULT '0.00',
  `status` enum('pending','paid','overdue','partial') DEFAULT 'pending',
  `paid_date` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`schedule_id`),
  KEY `loan_id` (`loan_id`),
  KEY `due_date` (`due_date`),
  KEY `status` (`status`),
  CONSTRAINT `fk_schedule_loan` FOREIGN KEY (`loan_id`) REFERENCES `loans` (`loan_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Table: notifications
-- ============================================
DROP TABLE IF EXISTS `notifications`;
CREATE TABLE IF NOT EXISTS `notifications` (
  `notification_id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `title` varchar(100) NOT NULL,
  `message` text NOT NULL,
  `notification_type` enum('loan','payment','reminder','system') DEFAULT 'system',
  `is_read` tinyint(1) DEFAULT '0',
  `sender_id` int DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`notification_id`),
  KEY `user_id` (`user_id`),
  KEY `is_read` (`is_read`),
  KEY `sender_id` (`sender_id`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `fk_notification_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_notification_sender` FOREIGN KEY (`sender_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL,
  CONSTRAINT `fk_notification_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Table: notification_replies
-- ============================================
DROP TABLE IF EXISTS `notification_replies`;
CREATE TABLE IF NOT EXISTS `notification_replies` (
  `reply_id` int NOT NULL AUTO_INCREMENT,
  `notification_id` int NOT NULL,
  `from_user_id` int NOT NULL,
  `to_user_id` int DEFAULT NULL,
  `reply_message` text NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`reply_id`),
  KEY `notification_id` (`notification_id`),
  KEY `from_user_id` (`from_user_id`),
  KEY `to_user_id` (`to_user_id`),
  CONSTRAINT `notification_replies_ibfk_1` FOREIGN KEY (`notification_id`) REFERENCES `notifications` (`notification_id`) ON DELETE CASCADE,
  CONSTRAINT `notification_replies_ibfk_2` FOREIGN KEY (`from_user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  CONSTRAINT `notification_replies_ibfk_3` FOREIGN KEY (`to_user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Table: password_resets
-- ============================================
DROP TABLE IF EXISTS `password_resets`;
CREATE TABLE IF NOT EXISTS `password_resets` (
  `reset_id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `token` varchar(64) NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`reset_id`),
  UNIQUE KEY `token` (`token`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `fk_reset_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Table: password_reset_requests
-- ============================================
DROP TABLE IF EXISTS `password_reset_requests`;
CREATE TABLE IF NOT EXISTS `password_reset_requests` (
  `request_id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `requested_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `approved_at` timestamp NULL DEFAULT NULL,
  `approved_by` int DEFAULT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `rejection_reason` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`request_id`),
  KEY `user_id` (`user_id`),
  KEY `approved_by` (`approved_by`),
  KEY `status` (`status`),
  CONSTRAINT `fk_reset_request_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_reset_request_approver` FOREIGN KEY (`approved_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Table: password_reset_codes
-- ============================================
DROP TABLE IF EXISTS `password_reset_codes`;
CREATE TABLE IF NOT EXISTS `password_reset_codes` (
  `user_id` int NOT NULL,
  `reset_code` varchar(6) NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `fk_reset_code_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_reset_code_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Table: customer_documents
-- ============================================
DROP TABLE IF EXISTS `customer_documents`;
CREATE TABLE IF NOT EXISTS `customer_documents` (
  `document_id` int NOT NULL AUTO_INCREMENT,
  `customer_id` int NOT NULL,
  `document_name` varchar(255) NOT NULL,
  `file_path` varchar(500) NOT NULL,
  `file_type` varchar(100) DEFAULT NULL,
  `file_size` int DEFAULT NULL,
  `uploaded_by` int DEFAULT NULL,
  `description` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`document_id`),
  KEY `customer_id` (`customer_id`),
  KEY `uploaded_by` (`uploaded_by`),
  CONSTRAINT `fk_document_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`customer_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_document_uploader` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Table: audit_log
-- ============================================
DROP TABLE IF EXISTS `audit_log`;
CREATE TABLE IF NOT EXISTS `audit_log` (
  `log_id` int NOT NULL AUTO_INCREMENT,
  `user_id` int DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `table_name` varchar(50) DEFAULT NULL,
  `record_id` int DEFAULT NULL,
  `old_values` text,
  `new_values` text,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`log_id`),
  KEY `user_id` (`user_id`),
  KEY `action` (`action`),
  KEY `created_at` (`created_at`),
  CONSTRAINT `fk_audit_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

