-- Per-department values that staff may change without altering the approved
-- Word layout or the automatic student/company data fields.
CREATE TABLE IF NOT EXISTS internship_official_template_setting (
    department_id INT NOT NULL,
    setting_key VARCHAR(80) NOT NULL,
    setting_value TEXT NOT NULL,
    updated_by INT NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (department_id, setting_key),
    CONSTRAINT fk_official_template_setting_department
        FOREIGN KEY (department_id) REFERENCES department(department_id)
        ON DELETE CASCADE,
    CONSTRAINT fk_official_template_setting_user
        FOREIGN KEY (updated_by) REFERENCES users(user_id)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
