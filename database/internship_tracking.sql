CREATE TABLE IF NOT EXISTS internship_tracking (
    internship_tracking_id INT AUTO_INCREMENT PRIMARY KEY,
    internship_profile_id INT NOT NULL,
    request_letter_status ENUM('sent','not_sent') NULL,
    response_status ENUM('sent','not_sent') NULL,
    application_status ENUM('applied','not_applied') NULL,
    send_letter_status ENUM('sent','not_sent') NULL,
    employment_status ENUM('pending','hired','not_hired') NOT NULL DEFAULT 'pending',
    notes TEXT NULL,
    updated_by INT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_internship_tracking_profile (internship_profile_id),
    KEY idx_internship_tracking_updated_by (updated_by),
    CONSTRAINT fk_internship_tracking_profile FOREIGN KEY (internship_profile_id)
        REFERENCES internship_profile(internship_profile_id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_internship_tracking_user FOREIGN KEY (updated_by)
        REFERENCES users(user_id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
