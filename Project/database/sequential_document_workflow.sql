-- Sequential document-review workflow and current office-holder assignments.
-- Safe to run more than once. Existing documents and one-step review history
-- remain intact while the application is migrated to the new workflow.

USE online_request_system;

ALTER TABLE documents
    MODIFY document_status ENUM(
        'draft',
        'submitted',
        'in_review',
        'revision_requested',
        'approved',
        'cancel'
    ) NOT NULL DEFAULT 'draft';

CREATE TABLE IF NOT EXISTS review_workflows (
    workflow_id INT NOT NULL AUTO_INCREMENT,
    document_type_id INT NOT NULL,
    workflow_name VARCHAR(150) NOT NULL,
    version_no INT NOT NULL DEFAULT 1,
    workflow_status ENUM('draft','active','inactive') NOT NULL DEFAULT 'draft',
    revision_return_policy ENUM('same_step','first_step') NOT NULL DEFAULT 'same_step',
    created_by INT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (workflow_id),
    UNIQUE KEY uq_review_workflow_version (document_type_id, version_no),
    KEY idx_review_workflow_status (document_type_id, workflow_status),
    CONSTRAINT fk_review_workflow_type
        FOREIGN KEY (document_type_id) REFERENCES document_type(document_type_id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_review_workflow_creator
        FOREIGN KEY (created_by) REFERENCES users(user_id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS review_workflow_steps (
    workflow_step_id INT NOT NULL AUTO_INCREMENT,
    workflow_id INT NOT NULL,
    step_order SMALLINT UNSIGNED NOT NULL,
    role_code VARCHAR(60) NOT NULL,
    step_name VARCHAR(150) NOT NULL,
    reviewer_source ENUM(
        'student_advisor',
        'document_teacher',
        'office_position',
        'employee'
    ) NOT NULL,
    reviewer_employee_id INT NULL,
    reviewer_position_id INT NULL,
    scope_type ENUM('department','faculty','university') NOT NULL DEFAULT 'department',
    is_required TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (workflow_step_id),
    UNIQUE KEY uq_review_workflow_step_order (workflow_id, step_order),
    UNIQUE KEY uq_review_workflow_role (workflow_id, role_code),
    KEY idx_review_step_employee (reviewer_employee_id),
    KEY idx_review_step_position (reviewer_position_id),
    CONSTRAINT fk_review_step_workflow
        FOREIGN KEY (workflow_id) REFERENCES review_workflows(workflow_id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_review_step_employee
        FOREIGN KEY (reviewer_employee_id) REFERENCES employee(employee_id)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_review_step_position
        FOREIGN KEY (reviewer_position_id) REFERENCES positions(position_id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS document_review_processes (
    process_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    document_id INT NOT NULL,
    workflow_id INT NOT NULL,
    workflow_version_no INT NOT NULL,
    current_step_order SMALLINT UNSIGNED NULL,
    process_status ENUM(
        'pending',
        'in_review',
        'revision_requested',
        'approved',
        'cancelled'
    ) NOT NULL DEFAULT 'pending',
    revision_count INT UNSIGNED NOT NULL DEFAULT 0,
    started_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    completed_at DATETIME NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (process_id),
    UNIQUE KEY uq_document_review_process (document_id),
    KEY idx_review_process_workflow (workflow_id),
    KEY idx_review_process_status (process_status, current_step_order),
    CONSTRAINT fk_review_process_document
        FOREIGN KEY (document_id) REFERENCES documents(document_id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_review_process_workflow
        FOREIGN KEY (workflow_id) REFERENCES review_workflows(workflow_id)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS document_review_step_instances (
    document_step_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    process_id BIGINT UNSIGNED NOT NULL,
    source_workflow_step_id INT NULL,
    step_order SMALLINT UNSIGNED NOT NULL,
    role_code VARCHAR(60) NOT NULL,
    step_name VARCHAR(150) NOT NULL,
    reviewer_employee_id INT NULL,
    reviewer_user_id INT NULL,
    step_status ENUM(
        'waiting',
        'current',
        'revision_requested',
        'approved',
        'skipped'
    ) NOT NULL DEFAULT 'waiting',
    attempt_count INT UNSIGNED NOT NULL DEFAULT 0,
    activated_at DATETIME NULL,
    reviewed_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (document_step_id),
    UNIQUE KEY uq_document_review_step_order (process_id, step_order),
    KEY idx_document_step_reviewer (reviewer_user_id, step_status),
    KEY idx_document_step_employee (reviewer_employee_id),
    KEY idx_document_step_source (source_workflow_step_id),
    CONSTRAINT fk_document_step_process
        FOREIGN KEY (process_id) REFERENCES document_review_processes(process_id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_document_step_source
        FOREIGN KEY (source_workflow_step_id) REFERENCES review_workflow_steps(workflow_step_id)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_document_step_employee
        FOREIGN KEY (reviewer_employee_id) REFERENCES employee(employee_id)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_document_step_user
        FOREIGN KEY (reviewer_user_id) REFERENCES users(user_id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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

CREATE TABLE IF NOT EXISTS office_holders (
    office_holder_id INT NOT NULL AUTO_INCREMENT,
    position_id INT NOT NULL,
    employee_id INT NULL,
    holder_name VARCHAR(255) NOT NULL,
    department_id INT NULL,
    effective_from DATE NOT NULL,
    effective_to DATE NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    signature_title_line1 VARCHAR(255) NULL,
    signature_title_line2 VARCHAR(255) NULL,
    created_by INT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (office_holder_id),
    KEY idx_office_holder_lookup (position_id, department_id, is_active),
    KEY idx_office_holder_employee (employee_id, is_active),
    CONSTRAINT fk_office_holder_position
        FOREIGN KEY (position_id) REFERENCES positions(position_id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_office_holder_employee
        FOREIGN KEY (employee_id) REFERENCES employee(employee_id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_office_holder_department
        FOREIGN KEY (department_id) REFERENCES department(department_id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_office_holder_creator
        FOREIGN KEY (created_by) REFERENCES users(user_id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Compatibility for databases that created office_holders before holder_name
-- was separated from the optional system user account.
ALTER TABLE office_holders
    ADD COLUMN IF NOT EXISTS holder_name VARCHAR(255) NULL AFTER employee_id,
    MODIFY employee_id INT NULL;

-- Position master data only. Admin will enter the current holder's name later.
INSERT IGNORE INTO positions(position_name) VALUES
    ('คณบดี'),
    ('รองคณบดีฝ่ายวิชาการและวิจัย');
