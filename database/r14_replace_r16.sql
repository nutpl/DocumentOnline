-- Replace R.16 in the active system with R.14 while preserving historical
-- R.16 documents and their detail rows for audit/download purposes.

USE online_request_system;

UPDATE document_type
SET status = 'inactive'
WHERE REPLACE(UPPER(document_code), '.', '') = 'R16';

UPDATE document_template t
JOIN document_type dt ON dt.document_type_id = t.document_type_id
SET t.status = 'inactive'
WHERE REPLACE(UPPER(dt.document_code), '.', '') = 'R16';

INSERT INTO document_type(document_code, document_name, description, status)
VALUES (
    'R.14',
    'คำร้องขอลงทะเบียนเรียนต่ำกว่า/เกินกว่าหน่วยกิตที่กำหนด',
    'ใช้ยื่นคำร้องเมื่อต้องการลงทะเบียนเรียนต่ำกว่าหรือเกินกว่าจำนวนหน่วยกิตที่กำหนด',
    'active'
)
ON DUPLICATE KEY UPDATE
    document_name = VALUES(document_name),
    description = VALUES(description),
    status = 'active';

SET @r14_type_id := (
    SELECT document_type_id
    FROM document_type
    WHERE REPLACE(UPPER(document_code), '.', '') = 'R14'
    LIMIT 1
);

INSERT INTO document_template(
    document_type_id, template_name, pdf_template, original_file,
    total_pages, file_type, status
)
SELECT
    @r14_type_id,
    'R.14 คำร้องขอลงทะเบียนเรียนต่ำกว่า/เกินกว่าหน่วยกิตที่กำหนด',
    'R14_official.pdf',
    'R.14.pdf',
    1,
    'pdf',
    'active'
WHERE NOT EXISTS (
    SELECT 1 FROM document_template
    WHERE document_type_id = @r14_type_id AND pdf_template = 'R14_official.pdf'
);

UPDATE document_template
SET status = 'active',
    template_name = 'R.14 คำร้องขอลงทะเบียนเรียนต่ำกว่า/เกินกว่าหน่วยกิตที่กำหนด',
    original_file = 'R.14.pdf',
    total_pages = 1,
    file_type = 'pdf'
WHERE document_type_id = @r14_type_id
  AND pdf_template = 'R14_official.pdf';

SET @r14_template_id := (
    SELECT template_id
    FROM document_template
    WHERE document_type_id = @r14_type_id
      AND pdf_template = 'R14_official.pdf'
    ORDER BY template_id DESC
    LIMIT 1
);

INSERT INTO template_fields
    (template_id, field_name, field_label, field_type, page_no, pos_x, pos_y, width, height, font_size, required_field, default_value)
VALUES
    (@r14_template_id, 'request_date', 'วันที่ยื่นคำร้อง', 'date', 1, 520, 176, 270, 24, 11, 1, NULL),
    (@r14_template_id, 'recipient', 'เรียน คณบดีคณะ', 'text', 1, 186, 203, 285, 24, 11, 1, 'วิศวกรรมศาสตร์และเทคโนโลยี'),
    (@r14_template_id, 'student_name', 'ชื่อ-นามสกุล', 'text', 1, 307, 290, 180, 24, 11, 1, NULL),
    (@r14_template_id, 'student_number', 'รหัสนักศึกษา', 'text', 1, 580, 290, 130, 24, 11, 1, NULL),
    (@r14_template_id, 'telephone', 'โทรศัพท์', 'text', 1, 745, 290, 105, 24, 11, 1, NULL),
    (@r14_template_id, 'education_level', 'ระดับการศึกษา', 'text', 1, 427, 323, 24, 24, 11, 1, 'undergraduate'),
    (@r14_template_id, 'faculty', 'คณะ', 'text', 1, 110, 357, 240, 24, 11, 1, NULL),
    (@r14_template_id, 'curriculum', 'หลักสูตร', 'text', 1, 416, 357, 210, 24, 11, 1, NULL),
    (@r14_template_id, 'cumulative_gpa', 'เกรดเฉลี่ยสะสม', 'number', 1, 713, 357, 110, 24, 11, 1, NULL),
    (@r14_template_id, 'semester', 'ภาคการศึกษาที่', 'text', 1, 360, 392, 80, 24, 11, 1, NULL),
    (@r14_template_id, 'academic_year', 'ปีการศึกษา', 'number', 1, 491, 392, 110, 24, 11, 1, NULL),
    (@r14_template_id, 'request_type', 'ประเภทการลงทะเบียน', 'text', 1, 233, 426, 24, 24, 11, 1, 'less'),
    (@r14_template_id, 'enrolled_credits', 'จำนวนหน่วยกิตลงทะเบียนเรียน', 'number', 1, 650, 447, 90, 24, 11, 1, NULL),
    (@r14_template_id, 'signature_name', 'ชื่อสำหรับพิมพ์ใต้ลายเซ็น', 'text', 1, 525, 570, 210, 24, 11, 1, NULL)
ON DUPLICATE KEY UPDATE
    field_label = VALUES(field_label),
    field_type = VALUES(field_type),
    page_no = VALUES(page_no),
    pos_x = VALUES(pos_x),
    pos_y = VALUES(pos_y),
    width = VALUES(width),
    height = VALUES(height),
    font_size = VALUES(font_size),
    required_field = VALUES(required_field),
    default_value = VALUES(default_value);

CREATE TABLE IF NOT EXISTS r14_request (
    r14_id INT NOT NULL AUTO_INCREMENT,
    document_id INT NOT NULL,
    semester VARCHAR(20) NOT NULL,
    academic_year VARCHAR(10) NOT NULL,
    request_type ENUM('less','more') NOT NULL,
    enrolled_credits DECIMAL(5,2) NOT NULL,
    cumulative_gpa DECIMAL(4,2) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (r14_id),
    UNIQUE KEY uq_r14_document (document_id),
    CONSTRAINT fk_r14_document
        FOREIGN KEY (document_id) REFERENCES documents(document_id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO review_workflows(
    document_type_id, workflow_name, version_no, workflow_status,
    revision_return_policy, created_by
)
SELECT
    @r14_type_id,
    'R.14 - อาจารย์ที่ปรึกษาพิจารณา',
    1,
    'active',
    'same_step',
    NULL
WHERE NOT EXISTS (
    SELECT 1 FROM review_workflows
    WHERE document_type_id = @r14_type_id AND version_no = 1
);

UPDATE review_workflows
SET workflow_status = 'active',
    workflow_name = 'R.14 - อาจารย์ที่ปรึกษาพิจารณา'
WHERE document_type_id = @r14_type_id AND version_no = 1;

SET @r14_workflow_id := (
    SELECT workflow_id FROM review_workflows
    WHERE document_type_id = @r14_type_id AND version_no = 1
    LIMIT 1
);

INSERT INTO review_workflow_steps(
    workflow_id, step_order, role_code, step_name, reviewer_source,
    reviewer_employee_id, reviewer_position_id, scope_type, is_required
)
VALUES (
    @r14_workflow_id, 1, 'advisor', 'อาจารย์ที่ปรึกษา', 'student_advisor',
    NULL, NULL, 'department', 1
)
ON DUPLICATE KEY UPDATE
    step_name = VALUES(step_name),
    reviewer_source = VALUES(reviewer_source),
    reviewer_employee_id = NULL,
    reviewer_position_id = NULL,
    scope_type = 'department',
    is_required = 1;
