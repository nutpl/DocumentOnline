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

CREATE TABLE `document_field_values` (
  `value_id` int(11) NOT NULL AUTO_INCREMENT,
  `document_id` int(11) NOT NULL,
  `field_id` int(11) NOT NULL,
  `field_value` text DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`value_id`),
  UNIQUE KEY `document_id` (`document_id`,`field_id`),
  KEY `fk_field_values_field` (`field_id`),
  CONSTRAINT `fk_field_values_document` FOREIGN KEY (`document_id`) REFERENCES `documents` (`document_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_field_values_field` FOREIGN KEY (`field_id`) REFERENCES `template_fields` (`field_id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `document_attachments` (
  `attachment_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `document_id` int(11) NOT NULL,
  `uploaded_by` int(11) NOT NULL,
  `original_name` varchar(255) NOT NULL,
  `stored_name` varchar(255) NOT NULL,
  `file_path` varchar(500) NOT NULL,
  `mime_type` varchar(100) NOT NULL,
  `file_size` bigint(20) unsigned NOT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`attachment_id`),
  KEY `idx_attachment_document` (`document_id`,`uploaded_at`),
  KEY `fk_attachment_user` (`uploaded_by`),
  CONSTRAINT `fk_attachment_document` FOREIGN KEY (`document_id`) REFERENCES `documents` (`document_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_attachment_user` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `document_review_processes` (
  `process_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `document_id` int(11) NOT NULL,
  `workflow_id` int(11) NOT NULL,
  `workflow_version_no` int(11) NOT NULL,
  `current_step_order` smallint(5) unsigned DEFAULT NULL,
  `process_status` enum('pending','in_review','revision_requested','approved','cancelled') NOT NULL DEFAULT 'pending',
  `revision_count` int(10) unsigned NOT NULL DEFAULT 0,
  `started_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `completed_at` datetime DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`process_id`),
  UNIQUE KEY `uq_document_review_process` (`document_id`),
  KEY `idx_review_process_workflow` (`workflow_id`),
  KEY `idx_review_process_status` (`process_status`,`current_step_order`),
  CONSTRAINT `fk_review_process_document` FOREIGN KEY (`document_id`) REFERENCES `documents` (`document_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_review_process_workflow` FOREIGN KEY (`workflow_id`) REFERENCES `review_workflows` (`workflow_id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `document_review_step_instances` (
  `document_step_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `process_id` bigint(20) unsigned NOT NULL,
  `source_workflow_step_id` int(11) DEFAULT NULL,
  `step_order` smallint(5) unsigned NOT NULL,
  `role_code` varchar(60) NOT NULL,
  `step_name` varchar(150) NOT NULL,
  `reviewer_employee_id` int(11) DEFAULT NULL,
  `reviewer_user_id` int(11) DEFAULT NULL,
  `step_status` enum('waiting','current','revision_requested','approved','skipped') NOT NULL DEFAULT 'waiting',
  `attempt_count` int(10) unsigned NOT NULL DEFAULT 0,
  `activated_at` datetime DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`document_step_id`),
  UNIQUE KEY `uq_document_review_step_order` (`process_id`,`step_order`),
  KEY `idx_document_step_reviewer` (`reviewer_user_id`,`step_status`),
  KEY `idx_document_step_employee` (`reviewer_employee_id`),
  KEY `idx_document_step_source` (`source_workflow_step_id`),
  CONSTRAINT `fk_document_step_employee` FOREIGN KEY (`reviewer_employee_id`) REFERENCES `employee` (`employee_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_document_step_process` FOREIGN KEY (`process_id`) REFERENCES `document_review_processes` (`process_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_document_step_source` FOREIGN KEY (`source_workflow_step_id`) REFERENCES `review_workflow_steps` (`workflow_step_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_document_step_user` FOREIGN KEY (`reviewer_user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `document_events` (
  `event_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `document_id` int(11) NOT NULL,
  `document_step_id` bigint(20) unsigned DEFAULT NULL,
  `actor_user_id` int(11) DEFAULT NULL,
  `event_type` varchar(50) NOT NULL,
  `detail` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`event_id`),
  KEY `idx_document_event_document` (`document_id`,`created_at`),
  KEY `idx_document_event_step` (`document_step_id`,`created_at`),
  KEY `idx_document_event_actor` (`actor_user_id`,`created_at`),
  KEY `idx_document_event_type` (`event_type`,`created_at`),
  CONSTRAINT `fk_document_event_actor` FOREIGN KEY (`actor_user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_document_event_document` FOREIGN KEY (`document_id`) REFERENCES `documents` (`document_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_document_event_step` FOREIGN KEY (`document_step_id`) REFERENCES `document_review_step_instances` (`document_step_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `notifications` (
  `notification_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `recipient_user_id` int(11) NOT NULL,
  `document_id` int(11) DEFAULT NULL,
  `notification_type` varchar(50) NOT NULL,
  `title` varchar(180) NOT NULL,
  `message` text NOT NULL,
  `target_url` varchar(500) DEFAULT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `read_at` datetime DEFAULT NULL,
  PRIMARY KEY (`notification_id`),
  KEY `idx_notification_recipient` (`recipient_user_id`,`is_read`,`created_at`),
  KEY `idx_notification_document` (`document_id`),
  CONSTRAINT `fk_notification_document` FOREIGN KEY (`document_id`) REFERENCES `documents` (`document_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_notification_user` FOREIGN KEY (`recipient_user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

SET FOREIGN_KEY_CHECKS = 1;
