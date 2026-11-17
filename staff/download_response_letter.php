<?php
if (session_status() !== PHP_SESSION_ACTIVE) session_start(); require_once __DIR__.'/../config/database.php'; require_once __DIR__.'/../helpers/company_response_export_helper.php'; require_once __DIR__.'/../helpers/alert_response_helper.php';
$responseDownloadContext = $responseDownloadContext ?? 'staff';
$responseReturnPage = $responseDownloadContext === 'teacher_coordinator' ? '../teacher/coordinator_requests.php' : 'response_letters.php';
if ($responseDownloadContext === 'teacher_coordinator') {
    require_once __DIR__.'/../middleware/teacher.php'; require_once __DIR__.'/../helpers/teacher_coordinator_helper.php';
    $coordinator = teacherCoordinatorProfile($pdo, (int)$_SESSION['user_id']);
    if (!$coordinator) { renderSweetAlertResponse('ไม่มีสิทธิ์', 'คุณไม่มีสิทธิ์ดูหนังสือตอบรับฉบับนี้', 'error', $responseReturnPage, 403); }
    $departmentId = (int)$coordinator['department_id'];
} else {
    require_once __DIR__.'/../middleware/staff.php';
    $staff=$pdo->prepare('SELECT department_id FROM employee WHERE user_id=? AND employee_type IN ("staff","teacher") LIMIT 1');$staff->execute([$_SESSION['user_id']]);$departmentId=(int)$staff->fetchColumn();
}
$id=(int)($_GET['id']??0); $format=(string)($_GET['format']??'view');
$stmt=$pdo->prepare('SELECT rd.original_filename,rd.stored_filename,s.student_number,s.firstname,s.lastname,COALESCE(p.prefix_short,"") prefix_short FROM internship_response_document rd JOIN internship_profile ip ON ip.internship_profile_id=rd.internship_profile_id JOIN student s ON s.student_id=ip.student_id LEFT JOIN prefixes p ON p.prefix_id=s.prefix_id JOIN group_class g ON g.group_id=s.group_id WHERE rd.response_document_id=? AND g.department_id=? LIMIT 1');$stmt->execute([$id,$departmentId]);$file=$stmt->fetch(PDO::FETCH_ASSOC);$path=$file?realpath(__DIR__.'/../uploads/internship_response/'.basename($file['stored_filename'])):false;if(!$path||!is_file($path)){renderSweetAlertResponse('ไม่พบไฟล์', 'ไม่พบหนังสือตอบรับที่ต้องการ', 'error', $responseReturnPage, 404);}
$extension = strtolower(pathinfo((string)$file['original_filename'], PATHINFO_EXTENSION));
$studentName = trim((string)$file['prefix_short'].' '.(string)$file['firstname'].' '.(string)$file['lastname']);
$downloadFilename = 'หนังสือตอบรับ_'.trim((string)$file['student_number'].'_'.$studentName).'.'.$extension;
$responseMimeTypes = [
    'pdf' => 'application/pdf',
    'doc' => 'application/msword',
    'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
];
if (!isset($responseMimeTypes[$extension])) { renderSweetAlertResponse('ไฟล์ไม่รองรับ', 'รูปแบบไฟล์หนังสือตอบรับไม่รองรับ', 'error', $responseReturnPage, 422); }

// The response letter is evidence from the company.  Do not generate a new
// Word document from an uploaded PDF; staff can only access the original file.
if ($format === 'word' && $extension === 'pdf') {
    renderSweetAlertResponse('ไม่รองรับการสร้างไฟล์ Word', 'หนังสือตอบรับเปิดหรือดาวน์โหลดได้ตามไฟล์ต้นฉบับที่นักศึกษาอัปโหลดเท่านั้น', 'info', $responseReturnPage, 422);
}

if ($format === 'word') {
    if (in_array($extension, ['doc', 'docx'], true)) {
        header('Content-Type: '.$responseMimeTypes[$extension]);
        header('Content-Disposition: attachment; filename*=UTF-8\'\''.rawurlencode((string)$file['original_filename']));
        header('Content-Length: '.filesize($path));
        header('X-Content-Type-Options: nosniff');
        readfile($path);
        exit;
    }
    $tempFile = tempnam(sys_get_temp_dir(), 'response_word_');
    if ($tempFile === false) { renderSweetAlertResponse('สร้างไฟล์ไม่สำเร็จ', 'ระบบไม่สามารถเตรียมไฟล์ Word ได้ กรุณาลองใหม่อีกครั้ง', 'error', $responseReturnPage, 500); }
    $docxPath = $tempFile . '.docx'; @unlink($tempFile);
    try {
        createCompanyResponseWord($path, $docxPath);
        if (!is_file($docxPath)) throw new RuntimeException('ไม่สามารถสร้างไฟล์ Word ได้');
        $wordFilename = pathinfo((string)$file['original_filename'], PATHINFO_FILENAME) . '.docx';
        header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
        header('Content-Disposition: attachment; filename*=UTF-8\'\''.rawurlencode($wordFilename));
        header('Content-Length: '.filesize($docxPath));
        readfile($docxPath);
    } catch (Throwable $e) {
        error_log('Company response Word export failed: '.$e->getMessage());
        @unlink($docxPath);
        renderSweetAlertResponse('สร้างไฟล์ Word ไม่สำเร็จ', 'ระบบไม่สามารถแปลงหนังสือตอบรับต้นฉบับได้ กรุณาลองใหม่อีกครั้ง', 'error', $responseReturnPage, 500);
    } finally { @unlink($docxPath); }
    exit;
}

if ($format === 'pdf' && $extension !== 'pdf') {
    renderSweetAlertResponse('ยังไม่มีไฟล์ PDF', 'หนังสือตอบรับฉบับนี้อัปโหลดเป็นไฟล์ Word กรุณาดาวน์โหลดไฟล์ Word', 'info', $responseReturnPage, 422);
}
header('Content-Type: '.$responseMimeTypes[$extension]);
header('Content-Disposition: attachment; filename*=UTF-8\'\''.rawurlencode($downloadFilename));
header('X-Content-Type-Options: nosniff');
header('Content-Length: '.filesize($path));readfile($path);exit;
