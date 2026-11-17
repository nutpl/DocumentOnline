-- Generated from ER_DBML_GROUPS and ER_SCHEMA_CURRENT.sql
-- MySQL / MariaDB
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS `users`;
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

DROP TABLE IF EXISTS `student`;
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

DROP TABLE IF EXISTS `documents`;
CREATE TABLE `documents` (
  `document_id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL,
  `template_id` int(11) NOT NULL,
  `document_status` enum('draft','submitted','in_review','revision_requested','teacher_reviewed','approved','cancel') NOT NULL DEFAULT 'draft',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `submitted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`document_id`),
  KEY `fk_documents_student` (`student_id`),
  KEY `fk_documents_template` (`template_id`),
  CONSTRAINT `fk_documents_student` FOREIGN KEY (`student_id`) REFERENCES `student` (`student_id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_documents_template` FOREIGN KEY (`template_id`) REFERENCES `document_template` (`template_id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `internship_calendar`;
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

DROP TABLE IF EXISTS `internship_profile`;
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

DROP TABLE IF EXISTS `internship_application`;
CREATE TABLE `internship_application` (
  `internship_application_id` int(11) NOT NULL AUTO_INCREMENT,
  `internship_profile_id` int(11) NOT NULL,
  `document_id` int(11) NOT NULL,
  `request_date` date NOT NULL,
  `contact_status` enum('contacted','not_contacted') NOT NULL DEFAULT 'not_contacted',
  `delivery_method` enum('student','faculty') NOT NULL DEFAULT 'faculty',
  `status` enum('draft','in_review','waiting_signature','submitted') NOT NULL DEFAULT 'draft',
  `signed_file` varchar(255) DEFAULT NULL,
  `submitted_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`internship_application_id`),
  UNIQUE KEY `uq_internship_application_profile` (`internship_profile_id`),
  UNIQUE KEY `uq_internship_application_document` (`document_id`),
  CONSTRAINT `fk_internship_application_document` FOREIGN KEY (`document_id`) REFERENCES `documents` (`document_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_internship_application_profile` FOREIGN KEY (`internship_profile_id`) REFERENCES `internship_profile` (`internship_profile_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `internship_response_document`;
CREATE TABLE `internship_response_document` (
  `response_document_id` int(11) NOT NULL AUTO_INCREMENT,
  `internship_profile_id` int(11) NOT NULL,
  `original_filename` varchar(255) NOT NULL,
  `stored_filename` varchar(255) NOT NULL,
  `review_status` enum('pending','approved','revision_requested','not_accepted') NOT NULL DEFAULT 'pending',
  `review_note` text DEFAULT NULL,
  `reviewed_by` int(11) DEFAULT NULL,
  `uploaded_at` datetime NOT NULL DEFAULT current_timestamp(),
  `reviewed_at` datetime DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`response_document_id`),
  UNIQUE KEY `uq_internship_response_profile` (`internship_profile_id`),
  KEY `idx_internship_response_review` (`review_status`),
  KEY `fk_internship_response_reviewer` (`reviewed_by`),
  CONSTRAINT `fk_internship_response_profile` FOREIGN KEY (`internship_profile_id`) REFERENCES `internship_profile` (`internship_profile_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_internship_response_reviewer` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `internship_response_declaration`;
CREATE TABLE `internship_response_declaration` (
  `response_declaration_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `internship_profile_id` int(11) NOT NULL,
  `reported_result` enum('accepted','not_accepted','unknown') NOT NULL DEFAULT 'unknown',
  `evidence_state` enum('uploaded','no_document','awaiting_result') NOT NULL DEFAULT 'awaiting_result',
  `student_note` text DEFAULT NULL,
  `declared_by` int(11) NOT NULL,
  `declared_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`response_declaration_id`),
  UNIQUE KEY `uq_response_declaration_profile` (`internship_profile_id`),
  KEY `idx_response_declaration_followup` (`evidence_state`,`reported_result`),
  KEY `fk_response_declaration_user` (`declared_by`),
  CONSTRAINT `fk_response_declaration_profile` FOREIGN KEY (`internship_profile_id`) REFERENCES `internship_profile` (`internship_profile_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_response_declaration_user` FOREIGN KEY (`declared_by`) REFERENCES `users` (`user_id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `internship_response_no_document_review`;
CREATE TABLE IF NOT EXISTS internship_response_no_document_review (
    internship_profile_id INT NOT NULL PRIMARY KEY,
    review_status ENUM('approved','revision_requested','not_accepted') NOT NULL,
    review_note TEXT NOT NULL,
    declaration_updated_at TIMESTAMP NOT NULL,
    reviewed_by INT NOT NULL,
    reviewed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_response_no_document_review_profile
        FOREIGN KEY (internship_profile_id) REFERENCES internship_profile(internship_profile_id) ON DELETE CASCADE,
    CONSTRAINT fk_response_no_document_review_user
        FOREIGN KEY (reviewed_by) REFERENCES users(user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET FOREIGN_KEY_CHECKS = 1;
