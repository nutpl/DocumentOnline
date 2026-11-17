-- Compact ER Group: Tracking, supervision and official documents
-- Full attributes from ER_SCHEMA_CURRENT.sql; diagram-only, do not execute as a migration.

-- A request official document plus its linked internship profiles supplies the data used to generate one response-form PDF per company.
-- This generation rule is application logic, not a database foreign key; no artificial ER relationship is added.

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
  UNIQUE KEY `employee_code` (`employee_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
  FOREIGN KEY (`previous_profile_id`) REFERENCES `internship_profile` (`internship_profile_id`)
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
  FOREIGN KEY (`internship_profile_id`) REFERENCES `internship_profile` (`internship_profile_id`)
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
  KEY `idx_internship_supervisor_employee` (`supervisor_employee_id`),
  FOREIGN KEY (`internship_profile_id`) REFERENCES `internship_profile` (`internship_profile_id`),
  FOREIGN KEY (`supervisor_employee_id`) REFERENCES `employee` (`employee_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
  KEY `idx_official_type_date` (`document_type`,`document_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `internship_official_student` (
  `official_document_id` int(11) NOT NULL,
  `internship_profile_id` int(11) NOT NULL,
  `sequence_no` smallint(6) NOT NULL,
  PRIMARY KEY (`official_document_id`,`internship_profile_id`),
  UNIQUE KEY `uq_official_sequence` (`official_document_id`,`sequence_no`),
  FOREIGN KEY (`official_document_id`) REFERENCES `internship_official_document` (`official_document_id`),
  FOREIGN KEY (`internship_profile_id`) REFERENCES `internship_profile` (`internship_profile_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

