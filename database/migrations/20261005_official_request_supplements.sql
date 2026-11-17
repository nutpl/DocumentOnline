-- Allows a late student at the same company to receive a separate,
-- traceable supplementary request letter without altering the original.
ALTER TABLE internship_official_document
    ADD COLUMN parent_official_document_id INT NULL AFTER signatory_title_line2,
    ADD COLUMN is_supplemental TINYINT(1) NOT NULL DEFAULT 0 AFTER parent_official_document_id,
    ADD INDEX idx_official_parent (parent_official_document_id),
    ADD CONSTRAINT fk_official_parent
        FOREIGN KEY (parent_official_document_id)
        REFERENCES internship_official_document(official_document_id)
        ON DELETE RESTRICT;
