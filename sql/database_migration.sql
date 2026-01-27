-- Database Migration Script
-- Use this if you already have departments and complaint_categories tables
-- This will create the missing users and complaints tables

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
-- Only create if it doesn't exist
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

-- Add foreign key constraint if departments table exists
SET @dbname = DATABASE();
SET @tablename = "users";
SET @columnname = "department_id";
SET @constraintname = "users_ibfk_dept";
SET @foreigntable = "departments";
SET @foreigncolumn = "department_id";

SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS 
    WHERE TABLE_SCHEMA = @dbname 
    AND TABLE_NAME = @tablename 
    AND CONSTRAINT_NAME = @constraintname
  ) > 0,
  "SELECT 'Foreign key already exists'",
  CONCAT("ALTER TABLE `", @tablename, "` ADD CONSTRAINT `", @constraintname, "` FOREIGN KEY (`", @columnname, "`) REFERENCES `", @foreigntable, "` (`", @foreigncolumn, "`) ON DELETE SET NULL")
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- --------------------------------------------------------

--
-- Table structure for table `complaints`
-- Only create if it doesn't exist
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

-- Add foreign key constraints if tables exist
SET @tablename = "complaints";

-- Foreign key for student_username -> users.username
SET @constraintname = "complaints_ibfk_1";
SET @foreigntable = "users";
SET @foreigncolumn = "username";
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS 
    WHERE TABLE_SCHEMA = @dbname 
    AND TABLE_NAME = @tablename 
    AND CONSTRAINT_NAME = @constraintname
  ) > 0,
  "SELECT 'Foreign key already exists'",
  CONCAT("ALTER TABLE `", @tablename, "` ADD CONSTRAINT `", @constraintname, "` FOREIGN KEY (`student_username`) REFERENCES `", @foreigntable, "` (`", @foreigncolumn, "`) ON DELETE CASCADE")
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Foreign key for category_id -> complaint_categories.category_id
SET @constraintname = "complaints_ibfk_category";
SET @foreigntable = "complaint_categories";
SET @foreigncolumn = "category_id";
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS 
    WHERE TABLE_SCHEMA = @dbname 
    AND TABLE_NAME = @tablename 
    AND CONSTRAINT_NAME = @constraintname
  ) > 0,
  "SELECT 'Foreign key already exists'",
  CONCAT("ALTER TABLE `", @tablename, "` ADD CONSTRAINT `", @constraintname, "` FOREIGN KEY (`category_id`) REFERENCES `", @foreigntable, "` (`", @foreigncolumn, "`) ON DELETE SET NULL")
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Foreign key for department_id -> departments.department_id
SET @constraintname = "complaints_ibfk_department";
SET @foreigntable = "departments";
SET @foreigncolumn = "department_id";
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS 
    WHERE TABLE_SCHEMA = @dbname 
    AND TABLE_NAME = @tablename 
    AND CONSTRAINT_NAME = @constraintname
  ) > 0,
  "SELECT 'Foreign key already exists'",
  CONCAT("ALTER TABLE `", @tablename, "` ADD CONSTRAINT `", @constraintname, "` FOREIGN KEY (`department_id`) REFERENCES `", @foreigntable, "` (`", @foreigncolumn, "`) ON DELETE RESTRICT")
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- --------------------------------------------------------

--
-- Table structure for table `complaint_history`
-- Only create if it doesn't exist
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

-- Add foreign key constraints for complaint_history
SET @tablename = "complaint_history";

-- Foreign key for complaint_id -> complaints.complaint_id
SET @constraintname = "complaint_history_ibfk_complaint";
SET @foreigntable = "complaints";
SET @foreigncolumn = "complaint_id";
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS 
    WHERE TABLE_SCHEMA = @dbname 
    AND TABLE_NAME = @tablename 
    AND CONSTRAINT_NAME = @constraintname
  ) > 0,
  "SELECT 'Foreign key already exists'",
  CONCAT("ALTER TABLE `", @tablename, "` ADD CONSTRAINT `", @constraintname, "` FOREIGN KEY (`complaint_id`) REFERENCES `", @foreigntable, "` (`", @foreigncolumn, "`) ON DELETE CASCADE")
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Foreign key for performed_by -> users.username
SET @constraintname = "complaint_history_ibfk_user";
SET @foreigntable = "users";
SET @foreigncolumn = "username";
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS 
    WHERE TABLE_SCHEMA = @dbname 
    AND TABLE_NAME = @tablename 
    AND CONSTRAINT_NAME = @constraintname
  ) > 0,
  "SELECT 'Foreign key already exists'",
  CONCAT("ALTER TABLE `", @tablename, "` ADD CONSTRAINT `", @constraintname, "` FOREIGN KEY (`performed_by`) REFERENCES `", @foreigntable, "` (`", @foreigncolumn, "`) ON DELETE CASCADE")
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- --------------------------------------------------------

--
-- Set AUTO_INCREMENT values
--

ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `complaints`
  MODIFY `complaint_id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `complaint_history`
  MODIFY `history_id` int(11) NOT NULL AUTO_INCREMENT;

COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

