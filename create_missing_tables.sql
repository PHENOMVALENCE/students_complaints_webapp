-- Create Missing Tables for Student Complaint Management System
-- Use this if you already have departments and complaint_categories tables
-- This creates the missing users and complaints tables

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE IF NOT EXISTS `users` (
  `user_id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('student','teacher','admin','department_officer') NOT NULL,
  `approved` tinyint(1) NOT NULL DEFAULT 0,
  `department_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `username` (`username`),
  KEY `department_id` (`department_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Add foreign key constraint for department_id (only if it doesn't exist)
SET @dbname = DATABASE();
SET @exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS 
               WHERE TABLE_SCHEMA = @dbname 
               AND TABLE_NAME = 'users' 
               AND CONSTRAINT_NAME = 'users_ibfk_dept');
SET @sqlstmt = IF(@exists = 0, 
    'ALTER TABLE `users` ADD CONSTRAINT `users_ibfk_dept` FOREIGN KEY (`department_id`) REFERENCES `departments` (`department_id`) ON DELETE SET NULL',
    'SELECT "Foreign key users_ibfk_dept already exists"');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- --------------------------------------------------------

--
-- Table structure for table `complaints`
--

CREATE TABLE IF NOT EXISTS `complaints` (
  `complaint_id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(200) NOT NULL DEFAULT '',
  `student_username` varchar(50) NOT NULL,
  `complaint` text NOT NULL,
  `category_id` int(11) DEFAULT NULL,
  `department_id` int(11) NOT NULL,
  `routed_at` timestamp NULL DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'pending',
  `response` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`complaint_id`),
  KEY `student_username` (`student_username`),
  KEY `category_id` (`category_id`),
  KEY `department_id` (`department_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Add foreign key constraints (only if they don't exist)
SET @exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS 
               WHERE TABLE_SCHEMA = @dbname 
               AND TABLE_NAME = 'complaints' 
               AND CONSTRAINT_NAME = 'complaints_ibfk_1');
SET @sqlstmt = IF(@exists = 0, 
    'ALTER TABLE `complaints` ADD CONSTRAINT `complaints_ibfk_1` FOREIGN KEY (`student_username`) REFERENCES `users` (`username`) ON DELETE CASCADE',
    'SELECT "Foreign key complaints_ibfk_1 already exists"');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS 
               WHERE TABLE_SCHEMA = @dbname 
               AND TABLE_NAME = 'complaints' 
               AND CONSTRAINT_NAME = 'complaints_ibfk_category');
SET @sqlstmt = IF(@exists = 0, 
    'ALTER TABLE `complaints` ADD CONSTRAINT `complaints_ibfk_category` FOREIGN KEY (`category_id`) REFERENCES `complaint_categories` (`category_id`) ON DELETE SET NULL',
    'SELECT "Foreign key complaints_ibfk_category already exists"');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS 
               WHERE TABLE_SCHEMA = @dbname 
               AND TABLE_NAME = 'complaints' 
               AND CONSTRAINT_NAME = 'complaints_ibfk_department');
SET @sqlstmt = IF(@exists = 0, 
    'ALTER TABLE `complaints` ADD CONSTRAINT `complaints_ibfk_department` FOREIGN KEY (`department_id`) REFERENCES `departments` (`department_id`) ON DELETE RESTRICT',
    'SELECT "Foreign key complaints_ibfk_department already exists"');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- --------------------------------------------------------

--
-- Table structure for table `complaint_history`
--

CREATE TABLE IF NOT EXISTS `complaint_history` (
  `history_id` int(11) NOT NULL AUTO_INCREMENT,
  `complaint_id` int(11) NOT NULL,
  `action` varchar(50) NOT NULL,
  `performed_by` varchar(50) NOT NULL,
  `old_status` varchar(20) DEFAULT NULL,
  `new_status` varchar(20) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`history_id`),
  KEY `complaint_id` (`complaint_id`),
  KEY `performed_by` (`performed_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Add foreign key constraints (only if they don't exist)
SET @exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS 
               WHERE TABLE_SCHEMA = @dbname 
               AND TABLE_NAME = 'complaint_history' 
               AND CONSTRAINT_NAME = 'complaint_history_ibfk_complaint');
SET @sqlstmt = IF(@exists = 0, 
    'ALTER TABLE `complaint_history` ADD CONSTRAINT `complaint_history_ibfk_complaint` FOREIGN KEY (`complaint_id`) REFERENCES `complaints` (`complaint_id`) ON DELETE CASCADE',
    'SELECT "Foreign key complaint_history_ibfk_complaint already exists"');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS 
               WHERE TABLE_SCHEMA = @dbname 
               AND TABLE_NAME = 'complaint_history' 
               AND CONSTRAINT_NAME = 'complaint_history_ibfk_user');
SET @sqlstmt = IF(@exists = 0, 
    'ALTER TABLE `complaint_history` ADD CONSTRAINT `complaint_history_ibfk_user` FOREIGN KEY (`performed_by`) REFERENCES `users` (`username`) ON DELETE CASCADE',
    'SELECT "Foreign key complaint_history_ibfk_user already exists"');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

