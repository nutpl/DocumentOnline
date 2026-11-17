-- Compact ER Group: Internship application and response
-- Full attributes from ER_SCHEMA_CURRENT.sql; diagram-only, do not execute as a migration.

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
  UNIQUE KEY `student_number` (`student_number`)
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
  UNIQUE KEY `uq_internship_calendar_term` (`academic_year`,`semester`)
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
  FOREIGN KEY (`student_id`) REFERENCES `student` (`student_id`),
  FOREIGN KEY (`calendar_id`) REFERENCES `internship_calendar` (`calendar_id`),
  FOREIGN KEY (`previous_profile_id`) REFERENCES `internship_profile` (`internship_profile_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
  FOREIGN KEY (`internship_profile_id`) REFERENCES `internship_profile` (`internship_profile_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
  FOREIGN KEY (`internship_profile_id`) REFERENCES `internship_profile` (`internship_profile_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
  FOREIGN KEY (`internship_profile_id`) REFERENCES `internship_profile` (`internship_profile_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

