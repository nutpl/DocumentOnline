-- Staff verification for cases where a company does not issue a response letter.
-- It never represents an uploaded document.
CREATE TABLE IF NOT EXISTS internship_response_no_document_review (
    internship_profile_id INT NOT NULL PRIMARY KEY,
    review_status ENUM('approved','revision_requested','not_accepted') NOT NULL,
    review_note TEXT NOT NULL,
    declaration_updated_at TIMESTAMP NOT NULL,
    reviewed_by INT NOT NULL,
    reviewed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_response_no_document_review_profile
        FOREIGN KEY (internship_profile_id) REFERENCES internship_profile(internship_profile_id) ON DELETE CASCADE,
    CONSTRAINT fk_response_no_document_review_user
        FOREIGN KEY (reviewed_by) REFERENCES users(user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
