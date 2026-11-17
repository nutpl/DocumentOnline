-- Core repair migration. Safe to run more than once.
CREATE TABLE IF NOT EXISTS template_fields (
    field_id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    template_id INT NOT NULL,
    field_name VARCHAR(80) NOT NULL,
    field_label VARCHAR(150) NOT NULL,
    field_type ENUM('text','textarea','date','number','checkbox') NOT NULL DEFAULT 'text',
    page_no INT NOT NULL DEFAULT 1,
    pos_x DECIMAL(10,2) NOT NULL,
    pos_y DECIMAL(10,2) NOT NULL,
    width DECIMAL(10,2) NOT NULL DEFAULT 180,
    height DECIMAL(10,2) NOT NULL DEFAULT 35,
    font_size DECIMAL(6,2) NOT NULL DEFAULT 12,
    required_field TINYINT(1) NOT NULL DEFAULT 0,
    default_value TEXT NULL,
    UNIQUE KEY unique_template_field_name (template_id, field_name),
    KEY idx_template_field (template_id, page_no),
    CONSTRAINT fk_template_fields_template FOREIGN KEY (template_id) REFERENCES document_template(template_id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO template_fields
    (field_id, template_id, field_name, field_label, field_type, page_no, pos_x, pos_y, width, height, font_size, required_field)
VALUES
    (1, 1, 'student_name', 'ชื่อ-นามสกุล', 'text', 1, 220, 350, 430, 30, 12, 1),
    (2, 1, 'student_number', 'รหัสนักศึกษา', 'text', 1, 570, 390, 220, 30, 12, 1),
    (3, 1, 'request_date', 'วันที่ยื่นคำร้อง', 'date', 1, 540, 180, 250, 30, 12, 1),
    (4, 1, 'request_detail', 'รายละเอียดคำร้อง', 'textarea', 1, 250, 590, 540, 130, 12, 1),
    (5, 1, 'education_level', 'ระดับการศึกษา', 'text', 1, 200, 425, 550, 30, 12, 1),
    (6, 1, 'subject', 'เรื่อง', 'text', 1, 210, 245, 580, 30, 12, 1),
    (7, 1, 'recipient', 'เรียน', 'text', 1, 210, 285, 580, 30, 12, 1),
    (8, 1, 'faculty', 'คณะ', 'text', 1, 185, 475, 280, 30, 12, 1),
    (9, 1, 'curriculum', 'หลักสูตร/สาขาวิชา', 'text', 1, 475, 475, 315, 30, 12, 1),
    (10, 1, 'telephone', 'เบอร์โทรศัพท์', 'text', 1, 580, 525, 210, 30, 12, 1),
    (11, 1, 'signature_name', 'ชื่อสำหรับลงนาม', 'text', 1, 540, 760, 250, 30, 12, 1);

UPDATE users
SET first_login = 1
WHERE password IS NULL OR password LIKE 'pending-pass%';
