-- ER diagram group only. Tables may repeat in other groups to show relationships.
-- Do not run this file together with the other group files as a database installation script.
SET FOREIGN_KEY_CHECKS = 0;

-- Supporting organization tables are repeated so this group imports on its
-- own and every employee foreign key can be resolved by an ER tool.
CREATE TABLE `faculty` (
  `faculty_id` int(11) NOT NULL AUTO_INCREMENT,
  `faculty_abbreviation` varchar(20) NOT NULL,
  `faculty_name` varchar(255) NOT NULL,
  PRIMARY KEY (`faculty_id`),
  UNIQUE KEY `faculty_abbreviation` (`faculty_abbreviation`)
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

CREATE TABLE `prefixes` (
  `prefix_id` int(11) NOT NULL AUTO_INCREMENT,
  `prefix_name` varchar(100) NOT NULL,
  `prefix_short` varchar(50) DEFAULT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`prefix_id`)
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

CREATE TABLE `positions` (
  `position_id` int(11) NOT NULL AUTO_INCREMENT,
  `position_name` varchar(255) NOT NULL,
  PRIMARY KEY (`position_id`),
  UNIQUE KEY `position_name` (`position_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `document_type` (
  `document_type_id` int(11) NOT NULL AUTO_INCREMENT,
  `document_code` varchar(50) NOT NULL,
  `document_name` varchar(255) NOT NULL,
  `form_file` varchar(100) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  PRIMARY KEY (`document_type_id`),
  UNIQUE KEY `document_code` (`document_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `document_template` (
  `template_id` int(11) NOT NULL AUTO_INCREMENT,
  `document_type_id` int(11) NOT NULL,
  `template_name` varchar(255) NOT NULL,
  `pdf_template` varchar(255) DEFAULT NULL,
  `docx_template` varchar(255) DEFAULT NULL,
  `original_file` varchar(500) NOT NULL,
  `preview_image` varchar(255) DEFAULT NULL,
  `total_pages` int(11) DEFAULT 1,
  `file_type` varchar(50) DEFAULT 'pdf',
  `status` enum('draft','active','inactive') NOT NULL DEFAULT 'draft',
  `imported_by` int(11) DEFAULT NULL,
  `imported_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`template_id`),
  KEY `fk_template_document_type` (`document_type_id`),
  KEY `fk_template_imported_by` (`imported_by`),
  CONSTRAINT `fk_template_document_type` FOREIGN KEY (`document_type_id`) REFERENCES `document_type` (`document_type_id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_template_imported_by` FOREIGN KEY (`imported_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `template_fields` (
  `field_id` int(11) NOT NULL AUTO_INCREMENT,
  `template_id` int(11) NOT NULL,
  `field_name` varchar(80) NOT NULL,
  `field_label` varchar(150) NOT NULL,
  `field_type` enum('text','textarea','date','number','checkbox') NOT NULL DEFAULT 'text',
  `page_no` int(11) NOT NULL DEFAULT 1,
  `pos_x` decimal(10,2) NOT NULL,
  `pos_y` decimal(10,2) NOT NULL,
  `width` decimal(10,2) NOT NULL DEFAULT 180.00,
  `height` decimal(10,2) NOT NULL DEFAULT 35.00,
  `font_size` decimal(6,2) NOT NULL DEFAULT 12.00,
  `required_field` tinyint(1) NOT NULL DEFAULT 0,
  `default_value` text DEFAULT NULL,
  PRIMARY KEY (`field_id`),
  UNIQUE KEY `unique_template_field_name` (`template_id`,`field_name`),
  KEY `idx_template_field` (`template_id`,`page_no`),
  CONSTRAINT `fk_template_fields_template` FOREIGN KEY (`template_id`) REFERENCES `document_template` (`template_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `review_workflows` (
  `workflow_id` int(11) NOT NULL AUTO_INCREMENT,
  `document_type_id` int(11) NOT NULL,
  `workflow_name` varchar(150) NOT NULL,
  `version_no` int(11) NOT NULL DEFAULT 1,
  `workflow_status` enum('draft','active','inactive') NOT NULL DEFAULT 'draft',
  `revision_return_policy` enum('same_step','first_step') NOT NULL DEFAULT 'same_step',
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`workflow_id`),
  UNIQUE KEY `uq_review_workflow_version` (`document_type_id`,`version_no`),
  KEY `idx_review_workflow_status` (`document_type_id`,`workflow_status`),
  KEY `fk_review_workflow_creator` (`created_by`),
  CONSTRAINT `fk_review_workflow_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_review_workflow_type` FOREIGN KEY (`document_type_id`) REFERENCES `document_type` (`document_type_id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `review_workflow_steps` (
  `workflow_step_id` int(11) NOT NULL AUTO_INCREMENT,
  `workflow_id` int(11) NOT NULL,
  `step_order` smallint(5) unsigned NOT NULL,
  `role_code` varchar(60) NOT NULL,
  `step_name` varchar(150) NOT NULL,
  `reviewer_source` enum('student_advisor','document_teacher','office_position','employee') NOT NULL,
  `reviewer_employee_id` int(11) DEFAULT NULL,
  `reviewer_position_id` int(11) DEFAULT NULL,
  `scope_type` enum('department','faculty','university') NOT NULL DEFAULT 'department',
  `is_required` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`workflow_step_id`),
  UNIQUE KEY `uq_review_workflow_step_order` (`workflow_id`,`step_order`),
  UNIQUE KEY `uq_review_workflow_role` (`workflow_id`,`role_code`),
  KEY `idx_review_step_employee` (`reviewer_employee_id`),
  KEY `idx_review_step_position` (`reviewer_position_id`),
  CONSTRAINT `fk_review_step_employee` FOREIGN KEY (`reviewer_employee_id`) REFERENCES `employee` (`employee_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_review_step_position` FOREIGN KEY (`reviewer_position_id`) REFERENCES `positions` (`position_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_review_step_workflow` FOREIGN KEY (`workflow_id`) REFERENCES `review_workflows` (`workflow_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
