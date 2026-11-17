CREATE TABLE IF NOT EXISTS internship_official_document (
    official_document_id INT AUTO_INCREMENT PRIMARY KEY,
    document_type ENUM('request','send') NOT NULL,
    company_name VARCHAR(255) NOT NULL,
    document_number VARCHAR(100) NOT NULL,
    document_date DATE NOT NULL,
    recipient_name VARCHAR(255) NOT NULL,
    reference_text VARCHAR(255) NULL,
    response_due_date DATE NULL,
    signatory_name VARCHAR(255) NOT NULL,
    signatory_title_line1 VARCHAR(255) NOT NULL,
    signatory_title_line2 VARCHAR(255) NULL,
    parent_official_document_id INT NULL,
    is_supplemental TINYINT(1) NOT NULL DEFAULT 0,
    created_by INT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_official_type_date (document_type, document_date),
    INDEX idx_official_parent (parent_official_document_id),
    CONSTRAINT fk_official_created_by FOREIGN KEY (created_by) REFERENCES users(user_id)
        ON UPDATE CASCADE,
    CONSTRAINT fk_official_parent FOREIGN KEY (parent_official_document_id)
        REFERENCES internship_official_document(official_document_id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS internship_official_student (
    official_document_id INT NOT NULL,
    internship_profile_id INT NOT NULL,
    sequence_no SMALLINT NOT NULL,
    PRIMARY KEY (official_document_id, internship_profile_id),
    UNIQUE KEY uq_official_sequence (official_document_id, sequence_no),
    CONSTRAINT fk_official_student_document FOREIGN KEY (official_document_id)
        REFERENCES internship_official_document(official_document_id) ON DELETE CASCADE,
    CONSTRAINT fk_official_student_profile FOREIGN KEY (internship_profile_id)
        REFERENCES internship_profile(internship_profile_id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
