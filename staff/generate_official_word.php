<?php
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
require_once __DIR__.'/../config/database.php';
require_once __DIR__.'/../helpers/official_internship_word_helper.php';
require_once __DIR__.'/../helpers/official_template_settings_helper.php';
require_once __DIR__.'/../helpers/company_response_export_helper.php';
require_once __DIR__.'/../helpers/alert_response_helper.php';

$officialDocumentContext = $officialDocumentContext ?? 'staff';
$isCoordinatorDownload = $officialDocumentContext === 'teacher_coordinator';
if ($isCoordinatorDownload) {
    require_once __DIR__.'/../middleware/teacher.php';
    require_once __DIR__.'/../helpers/teacher_coordinator_helper.php';
    $coordinator = teacherCoordinatorProfile($pdo, (int)$_SESSION['user_id']);
    if (!$coordinator) renderSweetAlertResponse('ไม่มีสิทธิ์ใช้งาน', 'หน้านี้สำหรับอาจารย์ประสานงานฝึกประสบการณ์วิชาชีพเท่านั้น', 'error', 'official_history.php', 403);
    $departmentId = (int)$coordinator['department_id'];
} else {
    require_once __DIR__.'/../middleware/staff.php';
    $staffStmt = $pdo->prepare('SELECT department_id FROM employee WHERE user_id=? AND employee_type IN ("staff","teacher") LIMIT 1');
    $staffStmt->execute([$_SESSION['user_id']]);
    $departmentId = (int)$staffStmt->fetchColumn();
    if (!$departmentId) renderSweetAlertResponse('ไม่มีสิทธิ์', 'บัญชีนี้ไม่มีสิทธิ์จัดการหนังสือของสาขา', 'error', 'official_history.php', 403);
}

$id = (int)($_GET['id'] ?? 0);
$requestedType = (string)($_GET['type'] ?? '');
$fontOption = strtolower((string)($_GET['font'] ?? 'psk')) === 'new' ? 'new' : 'psk';
if ($requestedType === 'response') {
    renderSweetAlertResponse('ดาวน์โหลดได้เฉพาะ PDF', 'แบบตอบรับสำหรับพิมพ์รองรับการดาวน์โหลดเป็น PDF เท่านั้น', 'info', 'official_history.php', 422);
}
if ($id < 1 || !in_array($requestedType, ['request', 'send'], true)
    || ($isCoordinatorDownload && !in_array($requestedType, ['request', 'send'], true))) {
    renderSweetAlertResponse('ข้อมูลไม่ถูกต้อง', 'ไม่พบข้อมูลหนังสือที่ต้องการดาวน์โหลด', 'error', 'official_history.php', 422);
}

$stmt = $pdo->prepare('SELECT DISTINCT od.* FROM internship_official_document od
    JOIN internship_official_student os ON os.official_document_id=od.official_document_id
    JOIN internship_profile ip ON ip.internship_profile_id=os.internship_profile_id
    JOIN student s ON s.student_id=ip.student_id JOIN group_class g ON g.group_id=s.group_id
    WHERE od.official_document_id=? AND g.department_id=?');
$stmt->execute([$id, $departmentId]);
$document = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$document || $document['document_type'] !== $requestedType) {
    renderSweetAlertResponse('ไม่พบหนังสือ', 'ไม่พบหนังสือที่เลือก หรือคุณไม่มีสิทธิ์เข้าถึง', 'error', 'official_history.php', 404);
}

$stmt = $pdo->prepare('SELECT ip.*,ic.academic_year,ic.semester,ic.start_date,ic.end_date,s.student_number,s.firstname,s.lastname,COALESCE(NULLIF(p.prefix_short,""),p.prefix_name) AS prefix_short,d.department_name
    FROM internship_official_student os JOIN internship_profile ip ON ip.internship_profile_id=os.internship_profile_id
    JOIN internship_calendar ic ON ic.calendar_id=ip.calendar_id JOIN student s ON s.student_id=ip.student_id
    LEFT JOIN prefixes p ON p.prefix_id=s.prefix_id JOIN group_class g ON g.group_id=s.group_id
    JOIN department d ON d.department_id=g.department_id WHERE os.official_document_id=? ORDER BY os.sequence_no');
$stmt->execute([$id]);
$students = $stmt->fetchAll(PDO::FETCH_ASSOC);
if (!$students) { renderSweetAlertResponse('ไม่พบรายชื่อนักศึกษา', 'หนังสือนี้ยังไม่มีรายชื่อนักศึกษาที่สามารถสร้างเอกสารได้', 'warning', 'official_history.php', 404); }
$document = officialTemplateApplySettings($pdo, $departmentId, $document);

$filename = officialInternshipCompanyDownloadFilename($document, 'docx', count($students));
$tempFile = tempnam(sys_get_temp_dir(), 'official-word-');
if ($tempFile === false) { renderSweetAlertResponse('สร้างไฟล์ไม่สำเร็จ', 'ระบบไม่สามารถเตรียมไฟล์ Word ได้ กรุณาลองใหม่อีกครั้ง', 'error', 'official_history.php', 500); }
$docxFile = $tempFile . '.docx';
@unlink($tempFile);
try {
    saveOfficialInternshipWordFile($document, $students, $requestedType, $docxFile, false, $fontOption);
    header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
    header('Content-Disposition: attachment; filename*=UTF-8\'\''.rawurlencode($filename));
    header('Content-Length: '.filesize($docxFile));
    readfile($docxFile);
} finally {
    @unlink($docxFile);
}
exit;
