-- ทำความสะอาดโครงสร้างที่ไม่ถูกใช้งานแล้ว
-- internship_submission ไม่มีข้อมูลและไม่มีโค้ดเรียกใช้
-- document_template.version ไม่ได้ใช้หลังระบบกำหนดแม่แบบใช้งานเพียงฉบับเดียว

START TRANSACTION;

DROP TABLE IF EXISTS internship_submission;

ALTER TABLE document_template
    DROP COLUMN version;

ALTER TABLE group_class
    ADD CONSTRAINT uq_group_class_name UNIQUE (group_name);

COMMIT;
