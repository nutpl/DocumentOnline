-- หนังสือตอบรับจากสถานประกอบการ: นักศึกษาอัปโหลด และเจ้าหน้าที่ตรวจสอบ
CREATE TABLE IF NOT EXISTS internship_response_document (
    response_document_id INT AUTO_INCREMENT PRIMARY KEY,
    internship_profile_id INT NOT NULL,
    original_filename VARCHAR(255) NOT NULL,
    stored_filename VARCHAR(255) NOT NULL,
    review_status ENUM('pending','approved','revision_requested','not_accepted') NOT NULL DEFAULT 'pending',
    review_note TEXT NULL,
    reviewed_by INT NULL,
    uploaded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    reviewed_at DATETIME NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_internship_response_profile (internship_profile_id),
    KEY idx_internship_response_review (review_status),
    CONSTRAINT fk_internship_response_profile FOREIGN KEY (internship_profile_id)
        REFERENCES internship_profile(internship_profile_id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_internship_response_reviewer FOREIGN KEY (reviewed_by)
        REFERENCES users(user_id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
