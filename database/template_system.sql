-- Run this once against the `online_request_system` database.
-- It adds the data structures used by the PDF-template document workflow.

CREATE TABLE IF NOT EXISTS document_template (
    template_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    document_type_id INT NOT NULL,
    template_name VARCHAR(150) NOT NULL,
    pdf_template VARCHAR(255) NOT NULL,
    docx_template VARCHAR(255) NULL,
    original_file VARCHAR(255) NULL,
    status ENUM('draft','active','inactive') NOT NULL DEFAULT 'draft',
    imported_by INT NULL,
    imported_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_template_type_status (document_type_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS template_fields (
    field_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    template_id INT UNSIGNED NOT NULL,
    field_name VARCHAR(80) NOT NULL,
    field_label VARCHAR(150) NOT NULL,
    field_type ENUM('text','textarea','date','number','checkbox') NOT NULL DEFAULT 'text',
    page_no INT UNSIGNED NOT NULL DEFAULT 1,
    pos_x DECIMAL(10,2) NOT NULL,
    pos_y DECIMAL(10,2) NOT NULL,
    width DECIMAL(10,2) NOT NULL DEFAULT 180,
    height DECIMAL(10,2) NOT NULL DEFAULT 35,
    font_size DECIMAL(6,2) NOT NULL DEFAULT 12,
    required_field TINYINT(1) NOT NULL DEFAULT 0,
    default_value TEXT NULL,
    INDEX idx_template_field (template_id, page_no)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS documents (
    document_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    template_id INT UNSIGNED NOT NULL,
    document_status ENUM('draft','submitted','cancel') NOT NULL DEFAULT 'draft',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    submitted_at DATETIME NULL,
    INDEX idx_document_student (student_id, created_at),
    INDEX idx_document_template (template_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS document_field_values (
    value_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    document_id INT UNSIGNED NOT NULL,
    field_id INT UNSIGNED NOT NULL,
    field_value TEXT NULL,
    UNIQUE KEY unique_document_field (document_id, field_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
