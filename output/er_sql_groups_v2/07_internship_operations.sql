-- ER diagram group only. Tables may repeat in other groups to show relationships.
-- Do not run this file together with the other group files as a database installation script.
SET FOREIGN_KEY_CHECKS = 0;

-- Supporting organization tables repeated for standalone ER import.
CREATE TABLE `faculty` (
  `faculty_id` int(11) NOT NULL AUTO_INCREMENT,
  `faculty_abbreviation` varchar(20) NOT NULL,
  `faculty_name` varchar(255) NOT NULL,
  PRIMARY KEY (`faculty_id`),
  UNIQUE KEY `faculty_abbreviation` (`faculty_abbreviation`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `prefixes` (
  `prefix_id` int(11) NOT NULL AUTO_INCREMENT,
  `prefix_name` varchar(100) NOT NULL,
  `prefix_short` varchar(50) DEFAULT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`prefix_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `group_class` (
  `group_id` int(11) NOT NULL AUTO_INCREMENT,
  `department_id` int(11) NOT NULL,
  `group_name` varchar(100) NOT NULL,
  `advisor_employee_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`group_id`),
  UNIQUE KEY `uq_group_class_name` (`group_name`),
  KEY `fk_group_department` (`department_id`),
  KEY `fk_group_advisor` (`advisor_employee_id`),
  CONSTRAINT `fk_group_advisor` FOREIGN KEY (`advisor_employee_id`) REFERENCES `employee` (`employee_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_group_department` FOREIGN KEY (`department_id`) REFERENCES `department` (`department_id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(100) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `first_login` tinyint(1) NOT NULL DEFAULT 1,
  `status` enum('active','inactive','suspended') NOT NULL DEFAULT 'active',
  `active_session_token_hash` char(64) DEFAULT NULL,
  `active_session_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`),
  KEY `idx_users_active_session` (`active_session_token_hash`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `employee` (
  `employee_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `employee_code` varchar(50) NOT NULL,
  `prefix_id` int(11) DEFAULT NULL,
  `firstname` varchar(100) NOT NULL,
  `lastname` varchar(100) NOT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `department_id` int(11) NOT NULL,
  `employee_type` enum('teacher','staff') NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`employee_id`),
  UNIQUE KEY `user_id` (`user_id`),
  UNIQUE KEY `employee_code` (`employee_code`),
  KEY `fk_employee_prefix` (`prefix_id`),
  KEY `fk_employee_department` (`department_id`),
  CONSTRAINT `fk_employee_department` FOREIGN KEY (`department_id`) REFERENCES `department` (`department_id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_employee_prefix` FOREIGN KEY (`prefix_id`) REFERENCES `prefixes` (`prefix_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_employee_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `department` (
  `department_id` int(11) NOT NULL AUTO_INCREMENT,
  `faculty_id` int(11) NOT NULL,
  `department_abbreviation` varchar(20) NOT NULL,
  `department_name` varchar(255) NOT NULL,
  PRIMARY KEY (`department_id`),
  UNIQUE KEY `department_abbreviation` (`department_abbreviation`),
  KEY `fk_department_faculty` (`faculty_id`),
  CONSTRAINT `fk_department_faculty` FOREIGN KEY (`faculty_id`) REFERENCES `faculty` (`faculty_id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `student` (
  `student_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `student_number` varchar(50) NOT NULL,
  `prefix_id` int(11) DEFAULT NULL,
  `firstname` varchar(100) NOT NULL,
  `lastname` varchar(100) NOT NULL,
  `group_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`student_id`),
  UNIQUE KEY `user_id` (`user_id`),
  UNIQUE KEY `student_number` (`student_number`),
  KEY `fk_student_prefix` (`prefix_id`),
  KEY `fk_student_group` (`group_id`),
  CONSTRAINT `fk_student_group` FOREIGN KEY (`group_id`) REFERENCES `group_class` (`group_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_student_prefix` FOREIGN KEY (`prefix_id`) REFERENCES `prefixes` (`prefix_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_student_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `internship_calendar` (
  `calendar_id` int(11) NOT NULL AUTO_INCREMENT,
  `academic_year` smallint(6) NOT NULL,
  `semester` tinyint(4) NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`calendar_id`),
  UNIQUE KEY `uq_internship_calendar_term` (`academic_year`,`semester`),
  KEY `fk_internship_calendar_user` (`created_by`),
  CONSTRAINT `fk_internship_calendar_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `internship_profile` (
  `internship_profile_id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL,
  `request_status` enum('active','closed') NOT NULL DEFAULT 'active',
  `request_reason` enum('initial','not_accepted','relocation') NOT NULL DEFAULT 'initial',
  `previous_profile_id` int(11) DEFAULT NULL,
  `closed_reason` varchar(255) DEFAULT NULL,
  `closed_at` datetime DEFAULT NULL,
  `calendar_id` int(11) DEFAULT NULL,
  `student_phone` varchar(40) DEFAULT NULL,
  `year_level` tinyint(4) DEFAULT NULL,
  `recipient_name` varchar(255) DEFAULT NULL,
  `company_name` varchar(255) NOT NULL,
  `company_address` text DEFAULT NULL,
  `subdistrict` varchar(120) DEFAULT NULL,
  `district` varchar(120) DEFAULT NULL,
  `province` varchar(120) DEFAULT NULL,
  `postal_code` varchar(10) DEFAULT NULL,
  `company_phone` varchar(40) DEFAULT NULL,
  `company_fax` varchar(40) DEFAULT NULL,
  `contact_prefix` varchar(30) DEFAULT NULL,
  `contact_name` varchar(255) DEFAULT NULL,
  `contact_position` varchar(180) DEFAULT NULL,
  `contact_phone` varchar(40) DEFAULT NULL,
  `job_position` varchar(180) DEFAULT NULL,
  `job_description` text DEFAULT NULL,
  `capacity_note` varchar(255) DEFAULT NULL,
  `recipient_position` varchar(180) DEFAULT NULL,
  `recipient_department` varchar(180) DEFAULT NULL,
  `company_number` varchar(40) DEFAULT NULL,
  `company_moo` varchar(40) DEFAULT NULL,
  `company_building` varchar(180) DEFAULT NULL,
  `company_floor` varchar(40) DEFAULT NULL,
  `company_road` varchar(180) DEFAULT NULL,
  `company_alley` varchar(180) DEFAULT NULL,
  `company_email` varchar(255) DEFAULT NULL,
  `contact_department` varchar(180) DEFAULT NULL,
  `contact_email` varchar(255) DEFAULT NULL,
  `qualification_department` tinyint(1) NOT NULL DEFAULT 0,
  `qualification_preparation` tinyint(1) NOT NULL DEFAULT 0,
  `qualification_gpa` tinyint(1) NOT NULL DEFAULT 0,
  `qualification_company` tinyint(1) NOT NULL DEFAULT 0,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`internship_profile_id`),
  KEY `idx_internship_profile_calendar` (`calendar_id`),
  KEY `idx_internship_profile_student_status` (`student_id`,`request_status`),
  KEY `idx_internship_profile_previous` (`previous_profile_id`),
  CONSTRAINT `fk_internship_profile_calendar` FOREIGN KEY (`calendar_id`) REFERENCES `internship_calendar` (`calendar_id`) ON DELETE SET NULL,
  CONSTRAINT `fk_internship_profile_previous` FOREIGN KEY (`previous_profile_id`) REFERENCES `internship_profile` (`internship_profile_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_internship_profile_student` FOREIGN KEY (`student_id`) REFERENCES `student` (`student_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `internship_tracking` (
  `internship_tracking_id` int(11) NOT NULL AUTO_INCREMENT,
  `internship_profile_id` int(11) NOT NULL,
  `request_letter_status` enum('sent','not_sent') DEFAULT NULL,
  `response_status` enum('sent','not_sent') DEFAULT NULL,
  `application_status` enum('applied','not_applied') DEFAULT NULL,
  `send_letter_status` enum('sent','not_sent') DEFAULT NULL,
  `employment_status` enum('pending','hired','not_hired') NOT NULL DEFAULT 'pending',
  `notes` text DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`internship_tracking_id`),
  UNIQUE KEY `uq_internship_tracking_profile` (`internship_profile_id`),
  KEY `idx_internship_tracking_updated_by` (`updated_by`),
  CONSTRAINT `fk_internship_tracking_profile` FOREIGN KEY (`internship_profile_id`) REFERENCES `internship_profile` (`internship_profile_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_internship_tracking_user` FOREIGN KEY (`updated_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `internship_supervision` (
  `internship_supervision_id` int(11) NOT NULL AUTO_INCREMENT,
  `internship_profile_id` int(11) NOT NULL,
  `supervisor_employee_id` int(11) DEFAULT NULL,
  `visit1_date` date DEFAULT NULL,
  `visit1_time` time DEFAULT NULL,
  `visit1_mode` enum('Onsite','Online') DEFAULT NULL,
  `visit2_date` date DEFAULT NULL,
  `visit2_time` time DEFAULT NULL,
  `visit2_mode` enum('Onsite','Online') DEFAULT NULL,
  `supervisor_names` text DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `lock_version` int(11) NOT NULL DEFAULT 1,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`internship_supervision_id`),
  UNIQUE KEY `uq_internship_supervision_profile` (`internship_profile_id`),
  KEY `fk_internship_supervision_user` (`updated_by`),
  KEY `idx_internship_supervisor_employee` (`supervisor_employee_id`),
  CONSTRAINT `fk_internship_supervision_profile` FOREIGN KEY (`internship_profile_id`) REFERENCES `internship_profile` (`internship_profile_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_internship_supervision_user` FOREIGN KEY (`updated_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL,
  CONSTRAINT `fk_internship_supervisor_employee` FOREIGN KEY (`supervisor_employee_id`) REFERENCES `employee` (`employee_id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS internship_direct_send_import (
 internship_profile_id INT NOT NULL PRIMARY KEY,
 source_filename VARCHAR(255) NOT NULL,
 source_sha256 CHAR(64) NOT NULL,
 source_row INT NOT NULL,
 import_note TEXT NOT NULL,
 imported_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY (internship_profile_id) REFERENCES internship_profile(internship_profile_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `internship_official_document` (
  `official_document_id` int(11) NOT NULL AUTO_INCREMENT,
  `document_type` enum('request','send') NOT NULL,
  `company_name` varchar(255) NOT NULL,
  `document_number` varchar(100) NOT NULL,
  `document_date` date NOT NULL,
  `recipient_name` varchar(255) NOT NULL,
  `reference_text` varchar(255) DEFAULT NULL,
  `response_due_date` date DEFAULT NULL,
  `signatory_name` varchar(255) NOT NULL,
  `signatory_title_line1` varchar(255) NOT NULL,
  `signatory_title_line2` varchar(255) DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`official_document_id`),
  KEY `idx_official_type_date` (`document_type`,`document_date`),
  KEY `fk_official_created_by` (`created_by`),
  CONSTRAINT `fk_official_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `internship_official_student` (
  `official_document_id` int(11) NOT NULL,
  `internship_profile_id` int(11) NOT NULL,
  `sequence_no` smallint(6) NOT NULL,
  PRIMARY KEY (`official_document_id`,`internship_profile_id`),
  UNIQUE KEY `uq_official_sequence` (`official_document_id`,`sequence_no`),
  KEY `fk_official_student_profile` (`internship_profile_id`),
  CONSTRAINT `fk_official_student_document` FOREIGN KEY (`official_document_id`) REFERENCES `internship_official_document` (`official_document_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_official_student_profile` FOREIGN KEY (`internship_profile_id`) REFERENCES `internship_profile` (`internship_profile_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `internship_excel_sync` (
  `sync_id` int(11) NOT NULL AUTO_INCREMENT,
  `department_id` int(11) NOT NULL,
  `connected_by_user_id` int(11) NOT NULL,
  `refresh_token_encrypted` text NOT NULL,
  `access_token_encrypted` text DEFAULT NULL,
  `access_token_expires_at` datetime DEFAULT NULL,
  `drive_id` varchar(255) DEFAULT NULL,
  `item_id` varchar(255) DEFAULT NULL,
  `workbook_name` varchar(255) DEFAULT NULL,
  `worksheet_name` varchar(255) DEFAULT NULL,
  `share_url` text DEFAULT NULL,
  `last_synced_at` datetime DEFAULT NULL,
  `last_sync_status` varchar(20) DEFAULT NULL,
  `last_sync_message` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`sync_id`),
  UNIQUE KEY `uq_internship_excel_department` (`department_id`),
  KEY `fk_internship_excel_user` (`connected_by_user_id`),
  CONSTRAINT `fk_internship_excel_department` FOREIGN KEY (`department_id`) REFERENCES `department` (`department_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_internship_excel_user` FOREIGN KEY (`connected_by_user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
