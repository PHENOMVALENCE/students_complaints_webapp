-- Standalone SQL for complaint_history table
-- Use this if you only need to create/repair the complaint_history table
-- Make sure 'complaints' and 'users' tables exist first!

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

