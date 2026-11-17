<?php
session_start();
require_once '../middleware/student.php';
require_once '../config/database.php';
require_once '../helpers/csrf_helper.php';
require_once '../helpers/notification_helper.php';
require_once '../helpers/alert_response_helper.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_FILES['response_pdf'])) { header('Location: response_letter.php'); exit; }
verify_csrf_or_abort();
$file = $_FILES['response_pdf'];
if ($file['error'] !== UPLOAD_ERR_OK || $file['size'] > 10 * 1024 * 1024) { renderSweetAlertResponse('อัปโหลดไม่สำเร็จ', 'กรุณาเลือกไฟล์ PDF หรือ Word ที่มีขนาดไม่เกิน 10 MB', 'warning', 'response_letter.php', 422); }
$allowedResponseFiles = [
    'application/pdf' => 'pdf',
    'application/msword' => 'doc',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
];
$mimeType = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
$extension = strtolower(pathinfo((string)$file['name'], PATHINFO_EXTENSION));
if (!isset($allowedResponseFiles[$mimeType]) || $extension !== $allowedResponseFiles[$mimeType]) {
    renderSweetAlertResponse('ชนิดไฟล์ไม่ถูกต้อง', 'รองรับเฉพาะ PDF และ Word (.doc, .docx) เท่านั้น', 'warning', 'response_letter.php', 422);
}
$stmt = $pdo->prepare('SELECT ip.internship_profile_id,s.student_number,s.firstname,s.lastname,g.department_id,rd.stored_filename,rd.review_status
    FROM internship_profile ip JOIN student s ON s.student_id=ip.student_id JOIN group_class g ON g.group_id=s.group_id
    JOIN internship_application ia ON ia.internship_profile_id=ip.internship_profile_id AND ia.status="submitted"
    LEFT JOIN internship_response_document rd ON rd.internship_profile_id=ip.internship_profile_id WHERE s.user_id=? AND ip.request_status="active" ORDER BY ip.internship_profile_id DESC LIMIT 1');
$stmt->execute([$_SESSION['user_id']]); $record = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$record || !in_array($record['review_status'] ?? '', ['', 'revision_requested'], true)) { renderSweetAlertResponse('ยังอัปโหลดไม่ได้', 'หนังสือตอบรับยังไม่อยู่ในสถานะที่อัปโหลดได้', 'warning', 'response_letter.php', 409); }
$dir = __DIR__ . '/../uploads/internship_response'; if (!is_dir($dir) && !mkdir($dir, 0775, true)) { renderSweetAlertResponse('อัปโหลดไม่สำเร็จ', 'ระบบไม่สามารถเตรียมพื้นที่จัดเก็บไฟล์ได้', 'error', 'response_letter.php', 500); }
$name = 'response_' . preg_replace('/[^A-Za-z0-9_-]/', '_', $record['student_number']) . '_' . date('YmdHis') . '_' . bin2hex(random_bytes(4)) . '.' . $extension;
if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $name)) { renderSweetAlertResponse('อัปโหลดไม่สำเร็จ', 'ระบบไม่สามารถจัดเก็บไฟล์ได้ กรุณาลองใหม่อีกครั้ง', 'error', 'response_letter.php', 500); }
$pdo->beginTransaction();
try {
    $save = $pdo->prepare('INSERT INTO internship_response_document (internship_profile_id,original_filename,stored_filename,review_status,review_note,reviewed_by,uploaded_at,reviewed_at)
        VALUES (?,?,?,"pending",NULL,NULL,NOW(),NULL) ON DUPLICATE KEY UPDATE original_filename=VALUES(original_filename),stored_filename=VALUES(stored_filename),review_status="pending",review_note=NULL,reviewed_by=NULL,uploaded_at=NOW(),reviewed_at=NULL');
    $save->execute([(int)$record['internship_profile_id'], mb_substr((string)$file['name'], 0, 255), $name]);
    $declaration = $pdo->prepare('INSERT INTO internship_response_declaration
        (internship_profile_id,reported_result,evidence_state,student_note,declared_by)
        VALUES (?,"unknown","uploaded",NULL,?)
        ON DUPLICATE KEY UPDATE evidence_state="uploaded",declared_by=VALUES(declared_by)');
    $declaration->execute([(int)$record['internship_profile_id'], (int)$_SESSION['user_id']]);
    $tracking = $pdo->prepare('INSERT INTO internship_tracking (internship_profile_id,response_status,updated_by) VALUES (?,NULL,?) ON DUPLICATE KEY UPDATE response_status=NULL,updated_by=VALUES(updated_by)');
    $tracking->execute([(int)$record['internship_profile_id'], $_SESSION['user_id']]);
    notifyDepartmentStaffForResponseLetter($pdo, (int)$record['department_id'], (string)$record['student_number'], trim($record['firstname'].' '.$record['lastname']));
    $pdo->commit();
    if (!empty($record['stored_filename'])) { @unlink($dir . '/' . basename($record['stored_filename'])); }
    header('Location: response_letter.php?uploaded=1'); exit;
} catch (Throwable $e) { if ($pdo->inTransaction()) { $pdo->rollBack(); } @unlink($dir . '/' . $name); error_log('Response letter upload failed: '.$e->getMessage()); renderSweetAlertResponse('บันทึกไม่สำเร็จ', 'ระบบไม่สามารถบันทึกหนังสือตอบรับได้ กรุณาลองใหม่อีกครั้ง', 'error', 'response_letter.php', 500); }
