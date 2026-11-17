-- Compact ER Group: Documents and review history
-- Full attributes from ER_SCHEMA_CURRENT.sql; diagram-only, do not execute as a migration.

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

CREATE TABLE `documents` (
  `document_id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL,
  `template_id` int(11) NOT NULL,
  `document_status` enum('draft','submitted','in_review','revision_requested','teacher_reviewed','approved','cancel') NOT NULL DEFAULT 'draft',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `submitted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`document_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
  FOREIGN KEY (`document_id`) REFERENCES `documents` (`document_id`)
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
  FOREIGN KEY (`process_id`) REFERENCES `document_review_processes` (`process_id`),
  FOREIGN KEY (`reviewer_user_id`) REFERENCES `users` (`user_id`)
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
  FOREIGN KEY (`document_id`) REFERENCES `documents` (`document_id`),
  FOREIGN KEY (`document_step_id`) REFERENCES `document_review_step_instances` (`document_step_id`),
  FOREIGN KEY (`actor_user_id`) REFERENCES `users` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

