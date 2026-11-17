<?php
session_start();
require_once '../middleware/student.php';
require_once '../config/database.php';
require_once '../helpers/csrf_helper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: history.php');
    exit;
}

verify_csrf_or_abort();
$documentId = (int) ($_POST['document_id'] ?? 0);

$stmt = $pdo->prepare('SELECT d.document_id,d.document_status,dt.document_name,dt.document_code,
        ip.internship_profile_id,ia.signed_file
    FROM documents d
    JOIN student s ON s.student_id=d.student_id
    JOIN document_template t ON t.template_id=d.template_id
    JOIN document_type dt ON dt.document_type_id=t.document_type_id
    LEFT JOIN internship_application ia ON ia.document_id=d.document_id
    LEFT JOIN internship_profile ip ON ip.internship_profile_id=ia.internship_profile_id
    WHERE d.document_id=? AND s.user_id=? LIMIT 1');
$stmt->execute([$documentId, $_SESSION['user_id']]);
$document = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$document) {
    $_SESSION['student_history_error'] = 'ไม่พบเอกสาร หรือคุณไม่มีสิทธิ์ลบเอกสารนี้';
    header('Location: history.php');
    exit;
}

if ($document['document_status'] !== 'draft') {
    $_SESSION['student_history_error'] = 'ลบได้เฉพาะเอกสารฉบับร่าง เอกสารที่ส่งแล้วจะเก็บไว้เป็นประวัติการดำเนินงาน';
    header('Location: history.php');
    exit;
}

if (!empty($document['internship_profile_id'])) {
    $usedStmt = $pdo->prepare('SELECT COUNT(*) FROM internship_official_student WHERE internship_profile_id=?');
    $usedStmt->execute([(int) $document['internship_profile_id']]);
    if ((int) $usedStmt->fetchColumn() > 0) {
        $_SESSION['student_history_error'] = 'ไม่สามารถลบเอกสารนี้ได้ เนื่องจากเจ้าหน้าที่นำข้อมูลไปสร้างหนังสือราชการแล้ว';
        header('Location: history.php');
        exit;
    }
}

try {
    $pdo->beginTransaction();
    $pdo->prepare('DELETE FROM documents WHERE document_id=?')->execute([$documentId]);
    if (!empty($document['internship_profile_id'])) {
        $pdo->prepare('DELETE FROM internship_profile WHERE internship_profile_id=?')->execute([(int) $document['internship_profile_id']]);
    }
    $pdo->commit();

    if (!empty($document['signed_file'])) {
        $base = realpath(__DIR__ . '/../uploads/internship_signed');
        $path = $base ? realpath($base . DIRECTORY_SEPARATOR . basename($document['signed_file'])) : false;
        if ($base && $path && str_starts_with($path, $base . DIRECTORY_SEPARATOR)) {
            @unlink($path);
        }
    }
    $_SESSION['student_history_success'] = 'ลบเอกสาร “' . $document['document_name'] . '” เรียบร้อยแล้ว';
} catch (Throwable $error) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $_SESSION['student_history_error'] = 'ไม่สามารถลบเอกสารได้ กรุณาลองใหม่อีกครั้ง';
}

header('Location: history.php');
exit;
