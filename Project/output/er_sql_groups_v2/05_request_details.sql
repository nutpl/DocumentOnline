-- ER diagram group only. Tables may repeat in other groups to show relationships.
-- Do not run this file together with the other group files as a database installation script.
SET FOREIGN_KEY_CHECKS = 0;

-- Supporting master tables repeated for standalone ER import.
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

CREATE TABLE `r01_request` (
  `r01_id` int(11) NOT NULL AUTO_INCREMENT,
  `document_id` int(11) NOT NULL,
  `advisor_employee_id` int(11) NOT NULL,
  `subject` varchar(255) DEFAULT NULL,
  `recipient` varchar(255) DEFAULT NULL,
  `request_detail` text DEFAULT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`r01_id`),
  UNIQUE KEY `document_id` (`document_id`),
  KEY `fk_r01_employee` (`advisor_employee_id`),
  CONSTRAINT `fk_r01_document` FOREIGN KEY (`document_id`) REFERENCES `documents` (`document_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_r01_employee` FOREIGN KEY (`advisor_employee_id`) REFERENCES `employee` (`employee_id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `r12_request` (
  `r12_id` int(11) NOT NULL AUTO_INCREMENT,
  `document_id` int(11) NOT NULL,
  `advisor_employee_id` int(11) NOT NULL,
  `course_code` varchar(50) DEFAULT NULL,
  `course_name` varchar(255) DEFAULT NULL,
  `old_group` varchar(100) DEFAULT NULL,
  `new_group` varchar(100) DEFAULT NULL,
  `reason` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`r12_id`),
  UNIQUE KEY `document_id` (`document_id`),
  KEY `fk_r12_employee` (`advisor_employee_id`),
  CONSTRAINT `fk_r12_document` FOREIGN KEY (`document_id`) REFERENCES `documents` (`document_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_r12_employee` FOREIGN KEY (`advisor_employee_id`) REFERENCES `employee` (`employee_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `r12_request_courses` (
  `r12_course_id` int(11) NOT NULL AUTO_INCREMENT,
  `r12_id` int(11) NOT NULL,
  `item_no` tinyint(3) unsigned NOT NULL,
  `semester` varchar(50) DEFAULT NULL,
  `course_code` varchar(50) DEFAULT NULL,
  `course_name` varchar(255) DEFAULT NULL,
  `section` varchar(100) DEFAULT NULL,
  `old_group` varchar(100) DEFAULT NULL,
  `new_group` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`r12_course_id`),
  UNIQUE KEY `uq_r12_course_item` (`r12_id`,`item_no`),
  CONSTRAINT `fk_r12_course_request` FOREIGN KEY (`r12_id`) REFERENCES `r12_request` (`r12_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `r14_request` (
  `r14_id` int(11) NOT NULL AUTO_INCREMENT,
  `document_id` int(11) NOT NULL,
  `semester` varchar(20) NOT NULL,
  `academic_year` varchar(10) NOT NULL,
  `request_type` enum('less','more') NOT NULL,
  `enrolled_credits` decimal(5,2) NOT NULL,
  `cumulative_gpa` decimal(4,2) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`r14_id`),
  UNIQUE KEY `uq_r14_document` (`document_id`),
  CONSTRAINT `fk_r14_document` FOREIGN KEY (`document_id`) REFERENCES `documents` (`document_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `r18_request` (
  `r18_id` int(11) NOT NULL AUTO_INCREMENT,
  `document_id` int(11) NOT NULL,
  `course_code` varchar(50) DEFAULT NULL,
  `course_name` varchar(255) DEFAULT NULL,
  `credits` decimal(4,1) DEFAULT NULL,
  `semester` varchar(50) DEFAULT NULL,
  `academic_year` varchar(20) DEFAULT NULL,
  `teacher_employee_id` int(11) DEFAULT NULL,
  `reason` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`r18_id`),
  UNIQUE KEY `document_id` (`document_id`),
  KEY `fk_r18_teacher` (`teacher_employee_id`),
  CONSTRAINT `fk_r18_document` FOREIGN KEY (`document_id`) REFERENCES `documents` (`document_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_r18_teacher` FOREIGN KEY (`teacher_employee_id`) REFERENCES `employee` (`employee_id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `r24_request` (
  `r24_id` int(11) NOT NULL AUTO_INCREMENT,
  `document_id` int(11) NOT NULL,
  `leave_type` varchar(100) DEFAULT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `reason` text DEFAULT NULL,
  `course_code` varchar(50) DEFAULT NULL,
  `course_name` varchar(255) DEFAULT NULL,
  `teacher_employee_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`r24_id`),
  UNIQUE KEY `document_id` (`document_id`),
  KEY `fk_r24_teacher` (`teacher_employee_id`),
  CONSTRAINT `fk_r24_document` FOREIGN KEY (`document_id`) REFERENCES `documents` (`document_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_r24_teacher` FOREIGN KEY (`teacher_employee_id`) REFERENCES `employee` (`employee_id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
