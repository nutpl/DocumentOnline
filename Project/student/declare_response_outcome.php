<?php
session_start();
require_once '../middleware/student.php';
require_once '../config/database.php';
require_once '../helpers/csrf_helper.php';
require_once '../helpers/alert_response_helper.php';
require_once '../helpers/notification_helper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: response_letter.php');
    exit;
}
verify_csrf_or_abort();

$outcome = (string)($_POST['reported_result'] ?? 'unknown');
if (!in_array($outcome, ['accepted', 'not_accepted', 'unknown'], true)) {
    renderSweetAlertResponse('ข้อมูลไม่ถูกต้อง', 'กรุณาเลือกสถานะที่บริษัทแจ้งให้ทราบ', 'warning', 'response_letter.php', 422);
}
$note = trim((string)($_POST['student_note'] ?? ''));
$recipientName = trim((string)($_POST['recipient_name'] ?? ''));
$recipientPosition = trim((string)($_POST['recipient_position'] ?? ''));
if ($note === '' || $recipientName === '' || $recipientPosition === '') {
    renderSweetAlertResponse('กรุณาระบุรายละเอียด', 'โปรดระบุช่องทางหรือรายละเอียดที่ทราบจากสถานประกอบการ เพื่อให้เจ้าหน้าที่ติดตามได้', 'warning', 'response_letter.php', 422);
}
if (mb_strlen($note) > 1000 || mb_strlen($recipientName) > 255 || mb_strlen($recipientPosition) > 180) {
    renderSweetAlertResponse('ข้อมูลยาวเกินกำหนด', 'กรุณาตรวจสอบเหตุผล ชื่อผู้รับ และตำแหน่งผู้รับอีกครั้ง', 'warning', 'response_letter.php', 422);
}

$profileStmt = $pdo->prepare('SELECT ip.internship_profile_id,s.student_number,s.firstname,s.lastname,g.department_id
    FROM internship_profile ip
    JOIN student s ON s.student_id=ip.student_id
    JOIN group_class g ON g.group_id=s.group_id
    JOIN internship_application ia ON ia.internship_profile_id=ip.internship_profile_id
    WHERE s.user_id=? AND ip.request_status="active" AND ia.status="submitted"
    ORDER BY ip.internship_profile_id DESC LIMIT 1');
$profileStmt->execute([$_SESSION['user_id']]);
$profile = $profileStmt->fetch(PDO::FETCH_ASSOC);
$profileId = (int)($profile['internship_profile_id'] ?? 0);
if ($profileId < 1) {
    renderSweetAlertResponse('ยังบันทึกสถานะไม่ได้', 'กรุณาส่งคำร้องฉบับลงนามครบให้เจ้าหน้าที่ก่อน', 'warning', 'response_letter.php', 409);
}

$pdo->beginTransaction();
try {
$statement = $pdo->prepare('INSERT INTO internship_response_declaration
    (internship_profile_id,reported_result,evidence_state,student_note,declared_by)
    VALUES (?, ?, ?, ?, ?)
    ON DUPLICATE KEY UPDATE
        reported_result=VALUES(reported_result),
        evidence_state=VALUES(evidence_state),
        student_note=VALUES(student_note),
        declared_by=VALUES(declared_by)');
$statement->execute([
    $profileId,
    $outcome,
    $outcome === 'unknown' ? 'awaiting_result' : 'no_document',
    $note !== '' ? $note : null,
    $_SESSION['user_id'],
]);
require_once '../helpers/response_no_document_review_helper.php';
if (responseNoDocumentReviewAvailable($pdo)) {
    $pdo->prepare('DELETE FROM internship_response_no_document_review WHERE internship_profile_id=?')->execute([$profileId]);
}
$pdo->prepare('UPDATE internship_profile SET recipient_name=?,recipient_position=? WHERE internship_profile_id=?')->execute([$recipientName,$recipientPosition,$profileId]);
notifyDepartmentStaffForResponseFollowup(
    $pdo,
    (int)$profile['department_id'],
    (string)$profile['student_number'],
    trim((string)$profile['firstname'] . ' ' . (string)$profile['lastname'])
);
$pdo->commit();
header('Location: response_letter.php?declared=1');
exit;
} catch (Throwable $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('Response no-document declaration failed: '.$error->getMessage());
    renderSweetAlertResponse('บันทึกไม่สำเร็จ','ระบบไม่สามารถส่งข้อมูลให้เจ้าหน้าที่ติดตามได้ กรุณาลองใหม่อีกครั้ง','error','response_letter.php',500);
}
