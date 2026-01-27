-- SCRMS Database Updates
-- This script adds all new tables and columns required for the full SCRMS implementation

-- 1. Add anonymity column to complaints table (if not exists)
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'complaints' 
    AND COLUMN_NAME = 'is_anonymous');
SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE `complaints` ADD COLUMN `is_anonymous` tinyint(1) NOT NULL DEFAULT 0 AFTER `status`',
    'SELECT "Column is_anonymous already exists"');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 2. Create complaint_attachments table for evidence files
CREATE TABLE IF NOT EXISTS `complaint_attachments` (
  `attachment_id` int(11) NOT NULL AUTO_INCREMENT,
  `complaint_id` int(11) NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_path` varchar(500) NOT NULL,
  `file_type` varchar(50) NOT NULL,
  `file_size` int(11) NOT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`attachment_id`),
  KEY `complaint_id` (`complaint_id`),
  CONSTRAINT `complaint_attachments_ibfk` FOREIGN KEY (`complaint_id`) REFERENCES `complaints` (`complaint_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 3. Create complaint_feedback table for ratings and feedback
CREATE TABLE IF NOT EXISTS `complaint_feedback` (
  `feedback_id` int(11) NOT NULL AUTO_INCREMENT,
  `complaint_id` int(11) NOT NULL,
  `student_username` varchar(50) NOT NULL,
  `rating` tinyint(1) NOT NULL COMMENT '1-5 rating',
  `feedback_text` text DEFAULT NULL,
  `submitted_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`feedback_id`),
  UNIQUE KEY `complaint_id` (`complaint_id`),
  KEY `student_username` (`student_username`),
  CONSTRAINT `complaint_feedback_ibfk_complaint` FOREIGN KEY (`complaint_id`) REFERENCES `complaints` (`complaint_id`) ON DELETE CASCADE,
  CONSTRAINT `complaint_feedback_ibfk_user` FOREIGN KEY (`student_username`) REFERENCES `users` (`username`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 4. Create collaboration_notes table for internal staff notes
CREATE TABLE IF NOT EXISTS `collaboration_notes` (
  `note_id` int(11) NOT NULL AUTO_INCREMENT,
  `complaint_id` int(11) NOT NULL,
  `created_by` varchar(50) NOT NULL,
  `note_text` text NOT NULL,
  `is_internal` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1=internal (staff only), 0=visible to student',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`note_id`),
  KEY `complaint_id` (`complaint_id`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `collaboration_notes_ibfk_complaint` FOREIGN KEY (`complaint_id`) REFERENCES `complaints` (`complaint_id`) ON DELETE CASCADE,
  CONSTRAINT `collaboration_notes_ibfk_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`username`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 5. Create information_requests table for staff to request more info from students
CREATE TABLE IF NOT EXISTS `information_requests` (
  `request_id` int(11) NOT NULL AUTO_INCREMENT,
  `complaint_id` int(11) NOT NULL,
  `requested_by` varchar(50) NOT NULL,
  `request_message` text NOT NULL,
  `status` enum('pending','responded','closed') NOT NULL DEFAULT 'pending',
  `student_response` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `responded_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`request_id`),
  KEY `complaint_id` (`complaint_id`),
  KEY `requested_by` (`requested_by`),
  CONSTRAINT `information_requests_ibfk_complaint` FOREIGN KEY (`complaint_id`) REFERENCES `complaints` (`complaint_id`) ON DELETE CASCADE,
  CONSTRAINT `information_requests_ibfk_user` FOREIGN KEY (`requested_by`) REFERENCES `users` (`username`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 6. Add status 'awaiting_student_response' to complaints status handling
-- Note: This is handled in application code, but we ensure the column can handle it
-- The status column is varchar(20), so it can already accept this value

-- 7. Create uploads directory structure (this is handled by PHP, but documented here)
-- Directory: uploads/complaints/{complaint_id}/
-- Permissions: 755
