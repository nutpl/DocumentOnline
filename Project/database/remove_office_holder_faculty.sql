-- office_holders ใช้ department_id เป็นขอบเขตของตำแหน่งเพียงคอลัมน์เดียว
-- ย้ายข้อมูลเดิมระดับคณะไปยังสาขาแรกในคณะเดียวกันก่อนลบ faculty_id

START TRANSACTION;

UPDATE office_holders oh
INNER JOIN (
    SELECT faculty_id, MIN(department_id) AS department_id
    FROM department
    GROUP BY faculty_id
) d ON d.faculty_id = oh.faculty_id
SET oh.department_id = d.department_id
WHERE oh.department_id IS NULL;

ALTER TABLE office_holders
    DROP FOREIGN KEY fk_office_holder_faculty,
    DROP INDEX fk_office_holder_faculty,
    DROP INDEX idx_office_holder_lookup,
    DROP COLUMN faculty_id,
    MODIFY department_id INT NOT NULL,
    ADD KEY idx_office_holder_lookup (position_id, department_id, is_active);

COMMIT;
