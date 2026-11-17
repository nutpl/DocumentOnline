<?php
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
require_once __DIR__ . '/../middleware/staff.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/csrf_helper.php';
require_once __DIR__ . '/../helpers/alert_response_helper.php';

verify_csrf_or_abort();
$format = (string)($_POST['format'] ?? '');
$ids = array_values(array_unique(array_filter(array_map('intval', $_POST['official_document_ids'] ?? []))));
if (!in_array($format, ['pdf', 'word'], true) || !$ids) {
    renderSweetAlertResponse('ยังไม่ได้เลือกเอกสาร', 'กรุณาเลือกเอกสารอย่างน้อยหนึ่งรายการก่อนดาวน์โหลดไฟล์รวม', 'warning', 'official_history.php', 422);
}

$staffStmt = $pdo->prepare('SELECT department_id FROM employee WHERE user_id=? AND employee_type IN ("staff","teacher") LIMIT 1');
$staffStmt->execute([$_SESSION['user_id']]);
$departmentId = (int)$staffStmt->fetchColumn();
if (!$departmentId) {
    renderSweetAlertResponse('ไม่มีสิทธิ์ใช้งาน', 'บัญชีนี้ไม่มีสิทธิ์จัดการหนังสือของสาขา', 'error', 'official_history.php', 403);
}

$marks = implode(',', array_fill(0, count($ids), '?'));
$stmt = $pdo->prepare("SELECT DISTINCT od.official_document_id, od.document_type, od.document_number
    FROM internship_official_document od
    JOIN internship_official_student os ON os.official_document_id=od.official_document_id
    JOIN internship_profile ip ON ip.internship_profile_id=os.internship_profile_id
    JOIN student s ON s.student_id=ip.student_id
    JOIN group_class g ON g.group_id=s.group_id
    WHERE od.official_document_id IN ($marks) AND g.department_id=?");
$stmt->execute([...$ids, $departmentId]);
$documents = $stmt->fetchAll(PDO::FETCH_ASSOC);
if (count($documents) !== count($ids)) {
    renderSweetAlertResponse('ไม่สามารถรวมเอกสารได้', 'มีเอกสารบางรายการที่ไม่มีสิทธิ์เข้าถึง', 'error', 'official_history.php', 403);
}

$types = array_values(array_unique(array_column($documents, 'document_type')));
$numbers = array_values(array_unique(array_column($documents, 'document_number')));
if (count($types) !== 1 || count($numbers) !== 1) {
    renderSweetAlertResponse('เลือกเอกสารคนละชุด', 'การดาวน์โหลดไฟล์รวมต้องเลือกเอกสารประเภทเดียวกันและเลขหนังสือเดียวกัน', 'warning', 'official_history.php', 422);
}

$_SESSION['official_batch_download'] = [
    'ids' => $ids,
    'type' => $types[0],
    'department_id' => $departmentId,
    'created_at' => time(),
];
header('Location: generate_official_batch.php?format=' . rawurlencode($format) . '&type=' . rawurlencode($types[0]));
exit;
