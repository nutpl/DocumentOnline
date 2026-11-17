-- Consolidate document lifecycle and review audit records into one event table.
-- Run once after sequential_document_workflow.sql.

CREATE TABLE IF NOT EXISTS document_events (
    event_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    document_id INT NOT NULL,
    document_step_id BIGINT UNSIGNED NULL,
    actor_user_id INT NULL,
    event_type VARCHAR(50) NOT NULL,
    detail TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (event_id),
    KEY idx_document_event_document (document_id, created_at),
    KEY idx_document_event_step (document_step_id, created_at),
    KEY idx_document_event_actor (actor_user_id, created_at),
    KEY idx_document_event_type (event_type, created_at),
    CONSTRAINT fk_document_event_document
        FOREIGN KEY (document_id) REFERENCES documents(document_id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_document_event_step
        FOREIGN KEY (document_step_id) REFERENCES document_review_step_instances(document_step_id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_document_event_actor
        FOREIGN KEY (actor_user_id) REFERENCES users(user_id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO document_events
    (document_id, document_step_id, actor_user_id, event_type, detail, created_at)
SELECT document_id, document_step_id, actor_user_id, action_type, action_comment, created_at
FROM document_review_actions;

INSERT INTO document_events
    (document_id, document_step_id, actor_user_id, event_type, detail, created_at)
SELECT document_id, NULL, user_id,
       CASE
           WHEN action = 'teacher_reviewed' THEN 'approved'
           WHEN action = '' THEN 'saved'
           ELSE action
       END,
       detail, created_at
FROM document_history;

INSERT INTO document_events
    (document_id, document_step_id, actor_user_id, event_type, detail, created_at)
SELECT dr.document_id, NULL, dr.reviewer_user_id,
       CASE WHEN dr.review_result = 'teacher_reviewed' THEN 'approved' ELSE dr.review_result END,
       dr.review_comment, dr.reviewed_at
FROM document_reviews dr
WHERE NOT EXISTS (
    SELECT 1
    FROM document_events de
    WHERE de.document_id = dr.document_id
      AND de.actor_user_id = dr.reviewer_user_id
      AND de.event_type COLLATE utf8mb4_general_ci =
          (CASE WHEN dr.review_result = 'teacher_reviewed' THEN 'approved' ELSE dr.review_result END) COLLATE utf8mb4_general_ci
      AND ABS(TIMESTAMPDIFF(SECOND, de.created_at, dr.reviewed_at)) <= 2
);

DROP TABLE document_reviews;
DROP TABLE document_history;
DROP TABLE document_review_actions;
