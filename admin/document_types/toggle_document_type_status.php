<?php

require_once __DIR__ . '/../../middleware/admin.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/csrf_helper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

verify_csrf_or_abort();

$documentTypeId = (int) ($_POST['document_type_id'] ?? 0);
$status = (string) ($_POST['status'] ?? '');
if ($documentTypeId < 1 || !in_array($status, ['active', 'inactive'], true)) {
    $_SESSION['admin_error'] = 'ข้อมูลประเภทเอกสารไม่ถูกต้อง';
    header('Location: index.php');
    exit;
}

$exists = $pdo->prepare('SELECT COUNT(*) FROM document_type WHERE document_type_id = ?');
$exists->execute([$documentTypeId]);

if (!(int) $exists->fetchColumn()) {
    $_SESSION['admin_error'] = 'ไม่พบประเภทเอกสารที่ต้องการ';
} else {
    $statement = $pdo->prepare('UPDATE document_type SET status = ? WHERE document_type_id = ?');
    $statement->execute([$status, $documentTypeId]);
    $_SESSION['admin_success'] = $status === 'active' ? 'เปิดใช้งานประเภทเอกสารแล้ว' : 'ปิดใช้งานประเภทเอกสารแล้ว';
}

header('Location: index.php');
exit;
