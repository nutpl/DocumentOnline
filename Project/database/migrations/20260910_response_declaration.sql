-- Manual follow-up when a company has no response letter.
-- This workflow does not classify, analyse, or send documents to AI.

CREATE TABLE IF NOT EXISTS internship_response_declaration (
    response_declaration_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    internship_profile_id INT NOT NULL,
    reported_result ENUM('accepted','not_accepted','unknown') NOT NULL DEFAULT 'unknown',
    evidence_state ENUM('uploaded','no_document','awaiting_result') NOT NULL DEFAULT 'awaiting_result',
    student_note TEXT NULL,
    declared_by INT NOT NULL,
    declared_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_response_declaration_profile (internship_profile_id),
    KEY idx_response_declaration_followup (evidence_state, reported_result),
    CONSTRAINT fk_response_declaration_profile FOREIGN KEY (internship_profile_id)
        REFERENCES internship_profile(internship_profile_id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_response_declaration_user FOREIGN KEY (declared_by)
        REFERENCES users(user_id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
