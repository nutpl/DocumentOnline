-- เก็บคำร้องสหกิจเดิมไว้เป็นประวัติ และอนุญาตให้นักศึกษามีคำร้องใหม่ได้
ALTER TABLE internship_profile
    DROP INDEX uq_internship_profile_student,
    ADD COLUMN request_status ENUM('active','closed') NOT NULL DEFAULT 'active' AFTER student_id,
    ADD COLUMN request_reason ENUM('initial','not_accepted','relocation') NOT NULL DEFAULT 'initial' AFTER request_status,
    ADD COLUMN previous_profile_id INT NULL AFTER request_reason,
    ADD COLUMN closed_reason VARCHAR(255) NULL AFTER request_status,
    ADD COLUMN closed_at DATETIME NULL AFTER closed_reason,
    ADD KEY idx_internship_profile_student_status (student_id, request_status),
    ADD KEY idx_internship_profile_previous (previous_profile_id),
    ADD CONSTRAINT fk_internship_profile_previous FOREIGN KEY (previous_profile_id)
        REFERENCES internship_profile(internship_profile_id) ON DELETE SET NULL ON UPDATE CASCADE;
