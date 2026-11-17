<?php

session_start();

require_once '../config/database.php';
require_once '../helpers/activity_log_helper.php';
require_once '../helpers/alert_response_helper.php';

if (($_SESSION['role'] ?? '') !== 'student') {
    header('Location: ../auth/login.php');
    exit;
}

$documentTypeId = filter_input(INPUT_GET, 'type', FILTER_VALIDATE_INT);

if (!$documentTypeId) {
    renderSweetAlertResponse('ไม่พบประเภทเอกสาร', 'ไม่พบประเภทเอกสารที่ต้องการสร้าง', 'error', 'dashboard.php', 404);
}

$templateStmt = $pdo->prepare('
    SELECT t.template_id, d.document_code, d.document_name
    FROM document_template t
    JOIN document_type d ON d.document_type_id = t.document_type_id
    WHERE t.document_type_id = ?
      AND t.status = "active"
      AND d.status = "active"
      AND t.pdf_template IS NOT NULL
      AND t.pdf_template <> ""
    ORDER BY t.imported_at DESC, t.template_id DESC
    LIMIT 1
');
$templateStmt->execute([$documentTypeId]);
$template = $templateStmt->fetch(PDO::FETCH_ASSOC);

if (!$template) {
    renderSweetAlertResponse('แม่แบบเอกสารไม่พร้อมใช้งาน', 'เอกสารนี้ยังไม่มีแม่แบบที่พร้อมใช้งาน', 'warning', 'dashboard.php', 422);
}

$studentStmt = $pdo->prepare('SELECT student_id FROM student WHERE user_id = ? LIMIT 1');
$studentStmt->execute([$_SESSION['user_id']]);
$studentId = (int) $studentStmt->fetchColumn();

if (!$studentId) {
    renderSweetAlertResponse('ไม่พบข้อมูลนักศึกษา', 'ไม่สามารถค้นหาข้อมูลนักศึกษาของบัญชีนี้ได้', 'error', 'dashboard.php', 403);
}

$draftStmt = $pdo->prepare('
    SELECT document_id
    FROM documents
    WHERE student_id = ? AND template_id = ? AND document_status = "draft"
    ORDER BY updated_at DESC, document_id DESC
    LIMIT 1
');
$draftStmt->execute([$studentId, $template['template_id']]);
$documentId = (int) $draftStmt->fetchColumn();

if (!$documentId) {
    $createStmt = $pdo->prepare('INSERT INTO documents (student_id, template_id, document_status) VALUES (?, ?, "draft")');
    $createStmt->execute([$studentId, $template['template_id']]);
    $documentId = (int) $pdo->lastInsertId();
    $historyStmt = $pdo->prepare("INSERT INTO document_events (document_id, actor_user_id, event_type, detail) VALUES (?, ?, 'created', ?)");
    $historyStmt->execute([$documentId, $_SESSION['user_id'], 'สร้างแบบร่างเอกสาร']);
    logActivity($pdo, (int)$_SESSION['user_id'], 'document_created', 'สร้างเอกสาร ' . $template['document_code'] . ' เลขที่ #' . $documentId);
}

header('Location: document_form.php?document=' . $documentId);
exit;
