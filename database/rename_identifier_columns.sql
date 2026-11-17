-- ปรับชื่อคอลัมน์ให้สื่อความหมายตรงกับข้อมูลที่เก็บ
-- สำรองฐานข้อมูลก่อนรัน และรันเพียงครั้งเดียวกับฐานข้อมูลเดิม

START TRANSACTION;

ALTER TABLE student
    CHANGE student_code student_number VARCHAR(50) NOT NULL;

ALTER TABLE department
    CHANGE department_code department_abbreviation VARCHAR(20) NOT NULL;

ALTER TABLE faculty
    CHANGE faculty_code faculty_abbreviation VARCHAR(20) NOT NULL;

-- เปลี่ยนชื่อดัชนี Unique ให้สื่อความหมายตรงกับคอลัมน์ใหม่
ALTER TABLE student
    DROP INDEX student_code,
    ADD UNIQUE KEY student_number (student_number);

ALTER TABLE department
    DROP INDEX department_code,
    ADD UNIQUE KEY department_abbreviation (department_abbreviation);

ALTER TABLE faculty
    DROP INDEX faculty_code,
    ADD UNIQUE KEY faculty_abbreviation (faculty_abbreviation);

-- student_prefix เคยเก็บส่วนประกอบซ้ำของชื่อกลุ่มเรียน
-- ระบบใช้ department_abbreviation + group_number สร้าง group_name แทน
ALTER TABLE group_class
    DROP COLUMN student_prefix;

-- เปลี่ยนชื่อคีย์ฟิลด์ในแม่แบบเอกสารให้สอดคล้องกับ student.student_number
UPDATE template_fields
SET field_name = REPLACE(field_name, 'student_code', 'student_number')
WHERE field_name LIKE '%student_code%';

COMMIT;
