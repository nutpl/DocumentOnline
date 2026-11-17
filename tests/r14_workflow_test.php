<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/document_workflow_helper.php';

$pdo->beginTransaction();
try {
    $templateId = (int)$pdo->query(
        "SELECT t.template_id
         FROM document_template t
         JOIN document_type dt ON dt.document_type_id=t.document_type_id
         WHERE REPLACE(dt.document_code,'.','')='R14'
           AND dt.status='active' AND t.status='active'
         ORDER BY t.template_id DESC LIMIT 1"
    )->fetchColumn();
    if ($templateId < 1) {
        throw new RuntimeException('ไม่พบแม่แบบ R.14 ที่เปิดใช้งาน');
    }

    $student = $pdo->query(
        "SELECT s.student_id,s.user_id,g.advisor_employee_id
         FROM student s INNER JOIN group_class g ON g.group_id=s.group_id
         WHERE g.advisor_employee_id IS NOT NULL
         ORDER BY s.student_id LIMIT 1"
    )->fetch(PDO::FETCH_ASSOC);
    if (!$student) {
        throw new RuntimeException('ไม่พบนักศึกษาที่มีอาจารย์ที่ปรึกษาสำหรับทดสอบ');
    }

    $pdo->prepare("INSERT INTO documents(student_id,template_id,document_status) VALUES (?,?,'submitted')")
        ->execute([(int)$student['student_id'],$templateId]);
    $documentId = (int)$pdo->lastInsertId();
    $result = startOrResumeDocumentWorkflow($pdo,$documentId,(int)$student['user_id']);
    if (empty($result['managed'])) {
        throw new RuntimeException('R.14 ไม่เริ่ม Workflow');
    }

    $step = $pdo->query(
        'SELECT dsi.step_order,dsi.step_name,dsi.step_status,dsi.reviewer_employee_id
         FROM document_review_processes drp
         JOIN document_review_step_instances dsi ON dsi.process_id=drp.process_id
         WHERE drp.document_id=' . $documentId
    )->fetch(PDO::FETCH_ASSOC);
    if (!$step || (int)$step['step_order'] !== 1 || $step['step_status'] !== 'current'
        || (int)$step['reviewer_employee_id'] !== (int)$student['advisor_employee_id']) {
        throw new RuntimeException('R.14 ไม่ได้ส่งให้อาจารย์ที่ปรึกษาเป็นขั้นตอนเดียว');
    }

    $advisorUserIdStatement = $pdo->prepare('SELECT user_id FROM employee WHERE employee_id=? LIMIT 1');
    $advisorUserIdStatement->execute([(int)$student['advisor_employee_id']]);
    $advisorUserId = (int)$advisorUserIdStatement->fetchColumn();
    if ($advisorUserId < 1) {
        throw new RuntimeException('อาจารย์ที่ปรึกษาไม่มีบัญชีผู้ใช้งานสำหรับทดสอบ');
    }

    processDocumentWorkflowReview(
        $pdo,
        $documentId,
        $advisorUserId,
        'revision_requested',
        'กรุณาแก้ไขเอกสารทดสอบ'
    );
    $revisionNotificationCount = (int)$pdo->query(
        "SELECT COUNT(*) FROM notifications WHERE document_id={$documentId}
         AND recipient_user_id=" . (int)$student['user_id'] . " AND notification_type='revision_requested'"
    )->fetchColumn();
    if ($revisionNotificationCount !== 1) {
        throw new RuntimeException('นักศึกษาไม่ได้รับการแจ้งเตือนเมื่ออาจารย์ส่งกลับให้แก้ไข');
    }

    startOrResumeDocumentWorkflow($pdo,$documentId,(int)$student['user_id']);
    $finalResult = processDocumentWorkflowReview($pdo,$documentId,$advisorUserId,'approved','');
    if (empty($finalResult['completed'])) {
        throw new RuntimeException('R.14 ไม่เสร็จสิ้นหลังอาจารย์ที่ปรึกษาอนุมัติ');
    }
    $finalNotificationTypes = $pdo->query(
        "SELECT notification_type FROM notifications WHERE document_id={$documentId}
         AND recipient_user_id=" . (int)$student['user_id'] . "
         AND notification_type IN ('review_step_approved','document_approved')"
    )->fetchAll(PDO::FETCH_COLUMN);
    if ($finalNotificationTypes !== ['document_approved']) {
        throw new RuntimeException('การอนุมัติขั้นสุดท้ายแจ้งเตือนนักศึกษาซ้ำ');
    }
    $approvedNotificationMessage = (string)$pdo->query(
        "SELECT message FROM notifications WHERE document_id={$documentId}
         AND recipient_user_id=" . (int)$student['user_id'] . "
         AND notification_type='document_approved' LIMIT 1"
    )->fetchColumn();
    if (!str_contains($approvedNotificationMessage, 'ดาวน์โหลดและพิมพ์เอกสาร')) {
        throw new RuntimeException('ข้อความอนุมัติไม่ได้แนะนำให้นักศึกษาพิมพ์เอกสารไปลงนาม');
    }

    echo "r14_active_template=passed\n";
    echo "r14_advisor_only_workflow=passed\n";
    echo "r14_revision_notification=passed\n";
    echo "r14_final_approval_single_notification=passed\n";
    echo "r14_print_after_approval_notification=passed\n";
} finally {
    $pdo->rollBack();
    echo "transaction_rolled_back=1\n";
}
