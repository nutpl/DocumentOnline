-- Document review, revision, attachment, and in-app notification workflow.
-- Run once against the `online_request_system` database.

ALTER TABLE documents
    MODIFY document_status ENUM(
        'draft',
        'submitted',
        'in_review',
        'revision_requested',
        'approved',
        'cancel'
    ) NOT NULL DEFAULT 'draft';

CREATE TABLE IF NOT EXISTS document_attachments (
    attachment_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    document_id INT NOT NULL,
    uploaded_by INT NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    stored_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    mime_type VARCHAR(100) NOT NULL,
    file_size BIGINT UNSIGNED NOT NULL,
    uploaded_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_attachment_document (document_id, uploaded_at),
    CONSTRAINT fk_attachment_document FOREIGN KEY (document_id)
        REFERENCES documents(document_id) ON DELETE CASCADE,
    CONSTRAINT fk_attachment_user FOREIGN KEY (uploaded_by)
        REFERENCES users(user_id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS notifications (
    notification_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    recipient_user_id INT NOT NULL,
    document_id INT NULL,
    notification_type VARCHAR(50) NOT NULL,
    title VARCHAR(180) NOT NULL,
    message TEXT NOT NULL,
    target_url VARCHAR(500) NULL,
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    read_at DATETIME NULL,
    INDEX idx_notification_recipient (recipient_user_id, is_read, created_at),
    INDEX idx_notification_document (document_id),
    CONSTRAINT fk_notification_user FOREIGN KEY (recipient_user_id)
        REFERENCES users(user_id) ON DELETE CASCADE,
    CONSTRAINT fk_notification_document FOREIGN KEY (document_id)
        REFERENCES documents(document_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
