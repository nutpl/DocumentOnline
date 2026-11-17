<?php
declare(strict_types=1);

session_start();
require_once __DIR__ . '/../middleware/staff.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/csrf_helper.php';
require_once __DIR__ . '/../helpers/notification_helper.php';
require_once __DIR__ . '/../helpers/document_workflow_helper.php';
require_once __DIR__ . '/../helpers/activity_log_helper.php';

function signedInternshipDeleteReturn(string $result): never
{
    header('Location: documents.php?internship_delete=' . rawurlencode($result));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') signedInternshipDeleteReturn('invalid');
verify_csrf_or_abort();

$documentId = (int) ($_POST['document_id'] ?? 0);
$staffStmt = $pdo->prepare('SELECT department_id FROM employee WHERE user_id=? AND employee_type IN ("staff","teacher") LIMIT 1');
$staffStmt->execute([$_SESSION['user_id']]);
$departmentId = (int) $staffStmt->fetchColumn();
if ($documentId < 1 || $departmentId < 1) signedInternshipDeleteReturn('invalid');

$pdo->beginTransaction();
$documentStmt = $pdo->prepare('SELECT ia.internship_application_id,ia.internship_profile_id,ia.signed_file,s.user_id,s.student_number,s.firstname,s.lastname,
    EXISTS (
        SELECT 1 FROM internship_official_student ios
        JOIN internship_official_document iod ON iod.official_document_id=ios.official_document_id
        WHERE ios.internship_profile_id=ia.internship_profile_id
    ) AS has_official_document
    FROM internship_application ia
    JOIN documents d ON d.document_id=ia.document_id
    JOIN document_template t ON t.template_id=d.template_id
    JOIN document_type dt ON dt.document_type_id=t.document_type_id
    JOIN internship_profile ip ON ip.internship_profile_id=ia.internship_profile_id
    JOIN student s ON s.student_id=ip.student_id
    JOIN group_class g ON g.group_id=s.group_id
    WHERE ia.document_id=? AND ia.status="submitted" AND dt.document_code="INTERNSHIP_INFO" AND g.department_id=?
    LIMIT 1 FOR UPDATE');
$documentStmt->execute([$documentId, $departmentId]);
$document = $documentStmt->fetch(PDO::FETCH_ASSOC);
if (!$document) { $pdo->rollBack(); signedInternshipDeleteReturn('not_found'); }
if ((int) $document['has_official_document'] === 1) { $pdo->rollBack(); signedInternshipDeleteReturn('official_exists'); }

try {
    // A replacement signed request is a new version of the request.  It must
    // therefore be submitted to both reviewers again before it can be signed.
    $pdo->prepare('UPDATE internship_application
        SET signed_file=NULL,status="draft",submitted_at=NULL
        WHERE internship_application_id=? AND status="submitted"')
        ->execute([(int) $document['internship_application_id']]);
    if ((int) $pdo->query('SELECT ROW_COUNT()')->fetchColumn() !== 1) {
        throw new RuntimeException('รายการนี้ถูกดำเนินการโดยผู้ใช้อื่นแล้ว');
    }
    $pdo->prepare('UPDATE documents SET document_status="revision_requested",updated_at=NOW() WHERE document_id=?')
        ->execute([$documentId]);
    resetDocumentWorkflowForFullReview($pdo, $documentId);
    $pdo->prepare('INSERT INTO internship_tracking (internship_profile_id,application_status,updated_by)
        VALUES (?,NULL,?)
        ON DUPLICATE KEY UPDATE application_status=NULL,updated_by=VALUES(updated_by)')
        ->execute([(int) $document['internship_profile_id'], $_SESSION['user_id']]);
    $pdo->prepare('INSERT INTO document_events (document_id,actor_user_id,event_type,detail)
        VALUES (?, ?, "signed_file_deleted", "เจ้าหน้าที่ส่งคำร้องกลับให้แก้ไขและยื่นให้อาจารย์พิจารณาใหม่ก่อนลงนาม")')
        ->execute([$documentId, $_SESSION['user_id']]);
    logActivity($pdo, (int) $_SESSION['user_id'], 'internship_signed_returned', 'ส่งคำร้องสหกิจของ ' . $document['student_number'] . ' กลับให้แก้ไข');
    createNotification(
        $pdo,
        (int) $document['user_id'],
        $documentId,
        'internship_signed_file_deleted',
        'กรุณาแก้ไขและยื่นใบคำร้องสหกิจใหม่',
        'เจ้าหน้าที่ส่งคำร้องกลับแล้ว กรุณาตรวจสอบ/แก้ไขข้อมูลและยื่นให้อาจารย์ที่ปรึกษาและอาจารย์ประสานงานพิจารณาใหม่',
        '/Project/student/internship_form.php'
    );
    $pdo->commit();
} catch (Throwable $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('Delete signed internship file failed: ' . $error->getMessage());
    signedInternshipDeleteReturn('failed');
}

$uploadDir = realpath(__DIR__ . '/../uploads/internship_signed');
$filename = basename((string) $document['signed_file']);
if ($uploadDir && $filename !== '') {
    $filePath = $uploadDir . DIRECTORY_SEPARATOR . $filename;
    if (is_file($filePath) && !@unlink($filePath)) {
        error_log('Signed internship upload delete failed: ' . $filePath);
    }
}

signedInternshipDeleteReturn('deleted');
