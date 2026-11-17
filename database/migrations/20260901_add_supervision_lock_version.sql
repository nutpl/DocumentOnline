-- ป้องกันการบันทึกแผนนิเทศทับกันโดยไม่ทราบข้อมูลล่าสุด
ALTER TABLE internship_supervision
    ADD COLUMN lock_version INT NOT NULL DEFAULT 1 AFTER updated_by;
