-- Database Updates for Student Complaint Management System
-- Based on SRS Requirements
-- Run this file to update your existing database

-- ============================================
-- 1. CREATE DEPARTMENTS TABLE
-- ============================================
CREATE TABLE IF NOT EXISTS `departments` (
  `department_id` int(11) NOT NULL AUTO_INCREMENT,
  `department_name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`department_id`),
  UNIQUE KEY `department_name` (`department_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Insert sample departments
INSERT INTO `departments` (`department_name`, `description`) VALUES
('Academic Affairs', 'Handles academic-related complaints including courses, grades, and curriculum'),
('Student Affairs', 'Manages student welfare, housing, and general student concerns'),
('IT Services', 'Handles technology-related issues including network, software, and hardware problems'),
('Finance Department', 'Manages financial complaints including fees, payments, and scholarships'),
('Facilities Management', 'Handles complaints about buildings, maintenance, and infrastructure'),
('Library Services', 'Manages library-related complaints and requests'),
('Security Department', 'Handles safety and security concerns'),
('Cafeteria Services', 'Manages food service complaints');

-- ============================================
-- 2. CREATE COMPLAINT CATEGORIES TABLE
-- ============================================
CREATE TABLE IF NOT EXISTS `complaint_categories` (
  `category_id` int(11) NOT NULL AUTO_INCREMENT,
  `category_name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`category_id`),
  UNIQUE KEY `category_name` (`category_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Insert sample categories
INSERT INTO `complaint_categories` (`category_name`, `description`) VALUES
('Academic Issues', 'Complaints related to courses, grades, instructors, or academic policies'),
('Facilities', 'Issues with buildings, classrooms, equipment, or infrastructure'),
('Financial', 'Complaints about fees, payments, refunds, or financial aid'),
('Technology', 'IT-related problems including network, software, or hardware issues'),
('Housing', 'Complaints about dormitories, accommodation, or housing services'),
('Food Services', 'Issues with cafeteria, dining halls, or food quality'),
('Security', 'Safety and security concerns on campus'),
('Administrative', 'General administrative complaints and concerns');

-- ============================================
-- 3. UPDATE USERS TABLE
-- ============================================
-- Add department_officer role and department_id field
ALTER TABLE `users` 
  MODIFY `role` enum('student','teacher','admin','department_officer') NOT NULL;

ALTER TABLE `users` 
  ADD COLUMN `department_id` int(11) DEFAULT NULL AFTER `approved`,
  ADD KEY `department_id` (`department_id`),
  ADD CONSTRAINT `users_ibfk_dept` FOREIGN KEY (`department_id`) REFERENCES `departments` (`department_id`) ON DELETE SET NULL;

-- ============================================
-- 4. UPDATE COMPLAINTS TABLE
-- ============================================
-- Add new fields: title, category_id, department_id, updated_at
ALTER TABLE `complaints` 
  ADD COLUMN `title` varchar(200) NOT NULL AFTER `complaint_id`,
  ADD COLUMN `category_id` int(11) DEFAULT NULL AFTER `title`,
  ADD COLUMN `department_id` int(11) NOT NULL AFTER `category_id`,
  ADD COLUMN `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  ADD COLUMN `routed_at` timestamp NULL DEFAULT NULL AFTER `department_id`,
  ADD KEY `category_id` (`category_id`),
  ADD KEY `department_id` (`department_id`),
  ADD CONSTRAINT `complaints_ibfk_category` FOREIGN KEY (`category_id`) REFERENCES `complaint_categories` (`category_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `complaints_ibfk_department` FOREIGN KEY (`department_id`) REFERENCES `departments` (`department_id`) ON DELETE RESTRICT;

-- Update existing complaints to have a default title (using first 50 chars of complaint)
UPDATE `complaints` SET `title` = SUBSTRING(`complaint`, 1, 50) WHERE `title` = '';

-- Set default department for existing complaints (you may want to manually update these)
UPDATE `complaints` SET `department_id` = 1 WHERE `department_id` IS NULL;

-- ============================================
-- 5. CREATE COMPLAINT HISTORY TABLE
-- ============================================
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
  KEY `performed_by` (`performed_by`),
  CONSTRAINT `complaint_history_ibfk_complaint` FOREIGN KEY (`complaint_id`) REFERENCES `complaints` (`complaint_id`) ON DELETE CASCADE,
  CONSTRAINT `complaint_history_ibfk_user` FOREIGN KEY (`performed_by`) REFERENCES `users` (`username`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================
-- 6. UPDATE STATUS VALUES
-- ============================================
-- Update status values to match SRS: pending, in_progress, resolved
-- Note: This will update existing 'on_process' to 'in_progress'
UPDATE `complaints` SET `status` = 'in_progress' WHERE `status` = 'on_process';

-- ============================================
-- NOTES:
-- ============================================
-- 1. After running this script, you may need to:
--    - Manually assign departments to existing complaints
--    - Assign department_id to department officers in the users table
--    - Update any existing 'on_process' status references in code to 'in_progress'
--
-- 2. To assign a department to a user (department officer):
--    UPDATE users SET department_id = 1 WHERE username = 'officer_username';
--
-- 3. The system now supports automatic routing:
--    - Students select a department when submitting complaints
--    - Complaints are automatically routed to that department
--    - Department officers only see complaints for their department

