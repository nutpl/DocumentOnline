<?php
declare(strict_types=1);
session_start();
require_once __DIR__.'/../middleware/staff.php';
require_once __DIR__.'/../config/database.php';
require_once __DIR__.'/../helpers/csrf_helper.php';
require_once __DIR__.'/../helpers/notification_helper.php';

function responseDeleteReturn(string $result): never
{
    header('Location: response_letters.php?response_delete='.rawurlencode($result));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') responseDeleteReturn('invalid');
verify_csrf_or_abort();
$responseId=(int)($_POST['response_document_id']??0);
$staff=$pdo->prepare('SELECT department_id FROM employee WHERE user_id=? AND employee_type IN ("staff","teacher") LIMIT 1');
$staff->execute([$_SESSION['user_id']]); $departmentId=(int)$staff->fetchColumn();
if ($responseId<1 || $departmentId<1) responseDeleteReturn('invalid');

$item=$pdo->prepare('SELECT rd.response_document_id,rd.internship_profile_id,rd.stored_filename,s.user_id,s.student_number,s.firstname,s.lastname,
    EXISTS (SELECT 1 FROM internship_official_student ios JOIN internship_official_document od ON od.official_document_id=ios.official_document_id WHERE ios.internship_profile_id=rd.internship_profile_id AND od.document_type="send") AS has_send_letter
    FROM internship_response_document rd JOIN internship_profile ip ON ip.internship_profile_id=rd.internship_profile_id JOIN student s ON s.student_id=ip.student_id JOIN group_class g ON g.group_id=s.group_id
    WHERE rd.response_document_id=? AND g.department_id=? LIMIT 1');
$item->execute([$responseId,$departmentId]); $row=$item->fetch(PDO::FETCH_ASSOC);
if (!$row) responseDeleteReturn('not_found');
if ((int)$row['has_send_letter']===1) responseDeleteReturn('send_exists');

$pdo->beginTransaction();
try {
    $pdo->prepare('DELETE FROM internship_response_document WHERE response_document_id=?')->execute([$responseId]);
    $pdo->prepare('INSERT INTO internship_tracking (internship_profile_id,response_status,application_status,updated_by) VALUES (?,NULL,NULL,?) ON DUPLICATE KEY UPDATE response_status=NULL,application_status=NULL,updated_by=VALUES(updated_by)')->execute([(int)$row['internship_profile_id'],$_SESSION['user_id']]);
    createNotification($pdo,(int)$row['user_id'],null,'internship_response_deleted','กรุณาอัปโหลดใบตอบรับใหม่','เจ้าหน้าที่ลบใบตอบรับเดิมเพื่อให้คุณอัปโหลดไฟล์ฉบับใหม่ได้','/Project/student/response_letter.php');
    $pdo->commit();
} catch (Throwable $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('Delete response letter failed: '.$error->getMessage());
    responseDeleteReturn('failed');
}
$file=__DIR__.'/../uploads/internship_response/'.basename((string)$row['stored_filename']);
if (is_file($file) && !@unlink($file)) error_log('Response file delete failed: '.$file);
responseDeleteReturn('deleted');
