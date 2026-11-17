-- ใช้กับฐานข้อมูลที่นำ migration keep_internship_request_history เข้าแล้วก่อนหน้า
ALTER TABLE internship_profile
    ADD COLUMN request_reason ENUM('initial','not_accepted','relocation') NOT NULL DEFAULT 'initial' AFTER request_status,
    ADD COLUMN previous_profile_id INT NULL AFTER request_reason,
    ADD KEY idx_internship_profile_previous (previous_profile_id),
    ADD CONSTRAINT fk_internship_profile_previous FOREIGN KEY (previous_profile_id)
        REFERENCES internship_profile(internship_profile_id) ON DELETE SET NULL ON UPDATE CASCADE;
