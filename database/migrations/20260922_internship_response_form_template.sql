-- Registers the approved response-form PDF in the existing template system.
-- The database stores the template record and the trusted relative file path;
-- the PDF remains a filesystem asset so FPDI can preserve it byte-for-byte.

START TRANSACTION;

INSERT INTO document_type (document_code, document_name, form_file, description, status)
VALUES (
    'INTERNSHIP_RESPONSE_FORM',
    'แบบตอบรับนักศึกษาฝึกประสบการณ์วิชาชีพ',
    'response_letter_template.pdf',
    'แม่แบบ PDF แบบตอบรับสำหรับแนบหนังสือขอความอนุเคราะห์รับนักศึกษาฝึกประสบการณ์วิชาชีพ',
    'active'
)
ON DUPLICATE KEY UPDATE
    document_name=VALUES(document_name),
    form_file=VALUES(form_file),
    description=VALUES(description),
    status='active';

INSERT INTO document_template (
    document_type_id, template_name, pdf_template, docx_template,
    original_file, preview_image, total_pages, file_type, status, imported_by
)
SELECT
    dt.document_type_id,
    'แบบตอบรับนักศึกษาฝึกประสบการณ์วิชาชีพ',
    'templates/official_internship/response_letter_template.pdf',
    NULL,
    'แบบตอบรับ.pdf',
    NULL,
    2,
    'pdf',
    'active',
    NULL
FROM document_type dt
WHERE dt.document_code='INTERNSHIP_RESPONSE_FORM'
  AND NOT EXISTS (
      SELECT 1 FROM document_template existing_template
      WHERE existing_template.document_type_id=dt.document_type_id
        AND existing_template.pdf_template='templates/official_internship/response_letter_template.pdf'
  );

COMMIT;
