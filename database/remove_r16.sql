-- Permanently remove R.16 after R.14 has replaced it.
-- Restore database/backups/before_remove_r16_20260821.sql if recovery is needed.

USE online_request_system;

SET @r16_type_id := (
    SELECT document_type_id
    FROM document_type
    WHERE REPLACE(UPPER(document_code), '.', '') = 'R16'
    LIMIT 1
);

DELETE d
FROM documents d
JOIN document_template t ON t.template_id = d.template_id
WHERE t.document_type_id = @r16_type_id;

DELETE rw
FROM review_workflows rw
WHERE rw.document_type_id = @r16_type_id;

DELETE t
FROM document_template t
WHERE t.document_type_id = @r16_type_id;

DROP TABLE IF EXISTS r16_request_students;
DROP TABLE IF EXISTS r16_request;

DELETE FROM document_type
WHERE document_type_id = @r16_type_id;
