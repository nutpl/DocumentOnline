CREATE TABLE IF NOT EXISTS internship_calendar (
    calendar_id INT AUTO_INCREMENT PRIMARY KEY,
    academic_year SMALLINT NOT NULL,
    semester TINYINT NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_by INT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_internship_calendar_term (academic_year, semester),
    CONSTRAINT fk_internship_calendar_user FOREIGN KEY (created_by) REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE internship_profile
    ADD COLUMN IF NOT EXISTS calendar_id INT NULL AFTER student_id,
    ADD INDEX IF NOT EXISTS idx_internship_profile_calendar (calendar_id);

SET @calendar_fk_exists = (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='internship_profile' AND CONSTRAINT_NAME='fk_internship_profile_calendar');
SET @calendar_fk_sql = IF(@calendar_fk_exists=0,'ALTER TABLE internship_profile ADD CONSTRAINT fk_internship_profile_calendar FOREIGN KEY (calendar_id) REFERENCES internship_calendar(calendar_id) ON DELETE SET NULL','SELECT 1');
PREPARE calendar_fk_stmt FROM @calendar_fk_sql;
EXECUTE calendar_fk_stmt;
DEALLOCATE PREPARE calendar_fk_stmt;

INSERT IGNORE INTO internship_calendar (academic_year,semester,start_date,end_date,status)
VALUES
    (2569,1,'2026-06-15','2026-10-02','active'),
    (2569,2,'2026-11-02','2027-02-19','active'),
    (2569,3,'2027-03-22','2027-07-09','active');

UPDATE internship_profile ip
JOIN internship_calendar ic ON ic.academic_year=ip.academic_year AND ic.semester=ip.semester
SET ip.calendar_id=ic.calendar_id,ip.start_date=ic.start_date,ip.end_date=ic.end_date
WHERE ip.calendar_id IS NULL;
