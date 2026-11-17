<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/document_workflow_helper.php';

$statement = $pdo->query(
    "SELECT d.document_id, s.user_id AS student_user_id, dt.document_code
     FROM documents d
     INNER JOIN student s ON s.student_id=d.student_id
     INNER JOIN document_template t ON t.template_id=d.template_id
     INNER JOIN document_type dt ON dt.document_type_id=t.document_type_id
     LEFT JOIN document_review_processes drp ON drp.document_id=d.document_id
     WHERE drp.process_id IS NULL
       AND d.document_status IN ('submitted','in_review')
       AND REPLACE(UPPER(dt.document_code),'.','') IN ('R01','R12','R14','R18','R24','INTERNSHIP_INFO')
     ORDER BY d.document_id"
);

$migrated = [];
$failed = [];
foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $documentId = (int) $row['document_id'];
    try {
        $pdo->beginTransaction();
        $result = startOrResumeDocumentWorkflow($pdo, $documentId, (int) $row['student_user_id']);
        if (empty($result['managed'])) {
            throw new RuntimeException('ไม่พบ Workflow ที่เปิดใช้งาน');
        }
        $pdo->commit();
        $migrated[] = $documentId;
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $failed[$documentId] = $exception->getMessage();
    }
}

$pdo->exec(
    "DELETE FROM notifications
     WHERE notification_type='document_submitted'
       AND is_read=0"
);

$pdo->exec("UPDATE documents SET document_status='approved' WHERE document_status='teacher_reviewed'");
$pdo->exec(
    "ALTER TABLE documents MODIFY document_status
     ENUM('draft','submitted','in_review','revision_requested','approved','cancel')
     NOT NULL DEFAULT 'draft'"
);

echo json_encode(
    ['migrated_document_ids' => $migrated, 'failed' => $failed],
    JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT
) . PHP_EOL;
