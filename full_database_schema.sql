-- Complete Database Schema for Student Complaint Management System
-- This file creates the entire database from scratch
-- Run this in phpMyAdmin or MySQL command line

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

-- --------------------------------------------------------

--
-- Database: `complaintsystem`
-- Drop existing tables if they exist (in reverse dependency order)
--

DROP TABLE IF EXISTS `complaint_history`;
DROP TABLE IF EXISTS `complaints`;
DROP TABLE IF EXISTS `users`;
DROP TABLE IF EXISTS `complaint_categories`;
DROP TABLE IF EXISTS `departments`;

-- --------------------------------------------------------

--
-- Table structure for table `departments`
--

CREATE TABLE `departments` (
  `department_id` int(11) NOT NULL AUTO_INCREMENT,
  `department_name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`department_id`),
  UNIQUE KEY `department_name` (`department_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `complaint_categories`
--

CREATE TABLE `complaint_categories` (
  `category_id` int(11) NOT NULL AUTO_INCREMENT,
  `category_name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`category_id`),
  UNIQUE KEY `category_name` (`category_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('student','teacher','admin','department_officer') NOT NULL,
  `approved` tinyint(1) NOT NULL DEFAULT 0,
  `department_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `username` (`username`),
  KEY `department_id` (`department_id`),
  CONSTRAINT `users_ibfk_dept` FOREIGN KEY (`department_id`) REFERENCES `departments` (`department_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `complaints`
--

CREATE TABLE `complaints` (
  `complaint_id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(200) NOT NULL,
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
  KEY `department_id` (`department_id`),
  CONSTRAINT `complaints_ibfk_1` FOREIGN KEY (`student_username`) REFERENCES `users` (`username`) ON DELETE CASCADE,
  CONSTRAINT `complaints_ibfk_category` FOREIGN KEY (`category_id`) REFERENCES `complaint_categories` (`category_id`) ON DELETE SET NULL,
  CONSTRAINT `complaints_ibfk_department` FOREIGN KEY (`department_id`) REFERENCES `departments` (`department_id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `complaint_history`
--

CREATE TABLE `complaint_history` (
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

-- --------------------------------------------------------

--
-- Dumping data for table `departments`
--

INSERT INTO `departments` (`department_id`, `department_name`, `description`, `created_at`) VALUES
(1, 'Academic Affairs', 'Handles academic-related complaints including courses, grades, and curriculum', '2025-12-25 16:33:38'),
(2, 'Student Affairs', 'Manages student welfare, housing, and general student concerns', '2025-12-25 16:33:38'),
(3, 'IT Services', 'Handles technology-related issues including network, software, and hardware problems', '2025-12-25 16:33:38'),
(4, 'Finance Department', 'Manages financial complaints including fees, payments, and scholarships', '2025-12-25 16:33:38'),
(5, 'Facilities Management', 'Handles complaints about buildings, maintenance, and infrastructure', '2025-12-25 16:33:38'),
(6, 'Library Services', 'Manages library-related complaints and requests', '2025-12-25 16:33:38'),
(7, 'Security Department', 'Handles safety and security concerns', '2025-12-25 16:33:38'),
(8, 'Cafeteria Services', 'Manages food service complaints', '2025-12-25 16:33:38');

-- --------------------------------------------------------

--
-- Dumping data for table `complaint_categories`
--

INSERT INTO `complaint_categories` (`category_id`, `category_name`, `description`, `created_at`) VALUES
(1, 'Academic Issues', 'Complaints related to courses, grades, instructors, or academic policies', '2025-12-25 16:33:38'),
(2, 'Facilities', 'Issues with buildings, classrooms, equipment, or infrastructure', '2025-12-25 16:33:38'),
(3, 'Financial', 'Complaints about fees, payments, refunds, or financial aid', '2025-12-25 16:33:38'),
(4, 'Technology', 'IT-related problems including network, software, or hardware issues', '2025-12-25 16:33:38'),
(5, 'Housing', 'Complaints about dormitories, accommodation, or housing services', '2025-12-25 16:33:38'),
(6, 'Food Services', 'Issues with cafeteria, dining halls, or food quality', '2025-12-25 16:33:38'),
(7, 'Security', 'Safety and security concerns on campus', '2025-12-25 16:33:38'),
(8, 'Administrative', 'General administrative complaints and concerns', '2025-12-25 16:33:38');

-- --------------------------------------------------------

--
-- Dumping data for table `users`
-- Default admin password: Admin (hashed with bcrypt)
-- You can change this after first login
--

INSERT INTO `users` (`user_id`, `username`, `password`, `role`, `approved`, `department_id`) VALUES
(1, 'Admin', '$2y$10$iTj3YkBLuINxSzU4NXs1jOTGjrG7K4dXqKc6hv7eaU2q78hkVa8cO', 'admin', 1, NULL),
(2, 'student1', '$2y$10$59yhYNBiSNPTaTkDikjyM.Xo1KwgskkiBqmh5xVCZg6qAUpujBOPO', 'student', 1, NULL),
(3, 'officer1', '$2y$10$JIH.gAT.mcuGQEyCxB9roujUfU2ClhhuC1Nf25jFmxlhmsW7coA/a', 'department_officer', 1, 1);

-- --------------------------------------------------------

--
-- Set AUTO_INCREMENT values
--

ALTER TABLE `departments`
  MODIFY `department_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

ALTER TABLE `complaint_categories`
  MODIFY `category_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

ALTER TABLE `complaints`
  MODIFY `complaint_id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `complaint_history`
  MODIFY `history_id` int(11) NOT NULL AUTO_INCREMENT;

COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

-- --------------------------------------------------------
-- IMPORTANT NOTES:
-- --------------------------------------------------------
-- 1. Default Admin Login:
--    Username: Admin
--    Password: Admin
--    (Change this immediately after first login!)
--
-- 2. Sample Users Created:
--    - Admin (admin role, approved)
--    - student1 (student role, approved) - Password: student1
--    - officer1 (department_officer role, approved, assigned to Academic Affairs)
--
-- 3. To create more users, use the registration page or insert directly:
--    INSERT INTO users (username, password, role, approved, department_id) 
--    VALUES ('username', '$2y$10$HASHED_PASSWORD', 'role', 1, NULL);
--
-- 4. To hash passwords in PHP:
--    echo password_hash('your_password', PASSWORD_DEFAULT);
--
-- 5. All tables are created with proper foreign key relationships
-- 6. Departments and Categories are pre-populated with sample data
-- 7. The system is ready to use after running this script!

