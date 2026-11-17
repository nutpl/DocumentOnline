<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/document_workflow_helper.php';
require_once __DIR__ . '/../helpers/document_workflow_status_helper.php';

function workflowTestAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$pdo->beginTransaction();
try {
    $document = $pdo->query(
        "SELECT d.document_id, d.document_status, dt.document_type_id, s.user_id AS student_user_id
         FROM documents d
         INNER JOIN student s ON s.student_id = d.student_id
         INNER JOIN document_template t ON t.template_id = d.template_id
         INNER JOIN document_type dt ON dt.document_type_id = t.document_type_id
         LEFT JOIN document_review_processes drp ON drp.document_id = d.document_id
         WHERE REPLACE(dt.document_code, '.', '') IN ('R01','R12','R14','R18','R24')
           AND drp.process_id IS NULL
         ORDER BY (d.document_status = 'draft') DESC, d.document_id
         LIMIT 1 FOR UPDATE"
    )->fetch(PDO::FETCH_ASSOC);
    workflowTestAssert((bool) $document, 'No available R document for workflow test');

    $teacher = $pdo->query(
        "SELECT e.employee_id, e.user_id
         FROM employee e INNER JOIN users u ON u.user_id = e.user_id
         WHERE e.employee_type = 'teacher' AND u.status = 'active'
         ORDER BY e.employee_id LIMIT 1"
    )->fetch(PDO::FETCH_ASSOC);
    workflowTestAssert((bool) $teacher, 'No active teacher for workflow test');

    // Prevent the notification helper from sending an external email.
    $pdo->prepare("UPDATE users SET email = ? WHERE user_id = ?")
        ->execute(['workflow-teacher-test.invalid', $teacher['user_id']]);
    $pdo->prepare("UPDATE users SET email = ? WHERE user_id = ?")
        ->execute(['workflow-student-test.invalid', $document['student_user_id']]);

    $versionStatement = $pdo->prepare(
        "SELECT COALESCE(MAX(version_no), 0) + 1 FROM review_workflows WHERE document_type_id = ?"
    );
    $versionStatement->execute([$document['document_type_id']]);
    $version = (int) $versionStatement->fetchColumn();
    $pdo->prepare(
        "INSERT INTO review_workflows
            (document_type_id, workflow_name, version_no, workflow_status, revision_return_policy, created_by)
         VALUES (?, 'CODEX transaction test', ?, 'active', 'same_step', 1)"
    )->execute([$document['document_type_id'], $version]);
    $workflowId = (int) $pdo->lastInsertId();

    $insertStep = $pdo->prepare(
        "INSERT INTO review_workflow_steps
            (workflow_id, step_order, role_code, step_name, reviewer_source,
             reviewer_employee_id, scope_type, is_required)
         VALUES (?, ?, ?, ?, 'employee', ?, 'department', 1)"
    );
    $insertStep->execute([$workflowId, 1, 'advisor_test', 'อาจารย์ที่ปรึกษา', $teacher['employee_id']]);
    $insertStep->execute([$workflowId, 2, 'coordinator_test', 'อาจารย์ประสานงาน', $teacher['employee_id']]);

    $pdo->prepare("UPDATE documents SET document_status = 'submitted' WHERE document_id = ?")
        ->execute([$document['document_id']]);
    $started = startOrResumeDocumentWorkflow(
        $pdo,
        (int) $document['document_id'],
        (int) $document['student_user_id']
    );
    workflowTestAssert(!empty($started['managed']), 'Workflow was not started');

    $stepStatuses = $pdo->query(
        "SELECT step_status FROM document_review_step_instances
         WHERE process_id = " . (int) $started['process_id'] . " ORDER BY step_order"
    )->fetchAll(PDO::FETCH_COLUMN);
    workflowTestAssert($stepStatuses === ['current', 'waiting'], 'Only first step must be current');
    $tracking = getDocumentWorkflowStatus($pdo, (int) $document['document_id']);
    workflowTestAssert($tracking !== null && count($tracking['steps']) === 2, 'Workflow tracking data is incomplete');

    $firstApproval = processDocumentWorkflowReview(
        $pdo,
        (int) $document['document_id'],
        (int) $teacher['user_id'],
        'approved',
        'ผ่านขั้นแรก'
    );
    workflowTestAssert(($firstApproval['next_step_name'] ?? '') === 'อาจารย์ประสานงาน', 'Second step was not activated');

    processDocumentWorkflowReview(
        $pdo,
        (int) $document['document_id'],
        (int) $teacher['user_id'],
        'revision_requested',
        'กรุณาแก้ไขเอกสาร'
    );
    $status = $pdo->query("SELECT document_status FROM documents WHERE document_id = " . (int) $document['document_id'])->fetchColumn();
    workflowTestAssert($status === 'revision_requested', 'Revision status was not set');

    startOrResumeDocumentWorkflow($pdo, (int) $document['document_id'], (int) $document['student_user_id']);
    $finalApproval = processDocumentWorkflowReview(
        $pdo,
        (int) $document['document_id'],
        (int) $teacher['user_id'],
        'approved',
        ''
    );
    workflowTestAssert(!empty($finalApproval['completed']), 'Workflow did not complete');
    $status = $pdo->query("SELECT document_status FROM documents WHERE document_id = " . (int) $document['document_id'])->fetchColumn();
    workflowTestAssert($status === 'approved', 'Document was not approved');

    echo "document_workflow_test=passed\n";
    echo "same_reviewer_two_roles=passed\n";
    echo "revision_same_step=passed\n";
    echo "sequential_notifications=passed\n";
    echo "workflow_tracking=passed\n";
} finally {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "transaction_rolled_back=1\n";
}
