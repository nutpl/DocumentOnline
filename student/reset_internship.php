<?php
session_start();
require_once '../middleware/student.php';
require_once '../config/database.php';
require_once '../helpers/csrf_helper.php';
require_once '../helpers/alert_response_helper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: internship_form.php'); exit; }
verify_csrf_or_abort();
$stmt=$pdo->prepare('SELECT ip.internship_profile_id,ia.document_id FROM internship_profile ip JOIN internship_application ia ON ia.internship_profile_id=ip.internship_profile_id JOIN student s ON s.student_id=ip.student_id WHERE s.user_id=? AND ip.request_status="active" ORDER BY ip.internship_profile_id DESC LIMIT 1');
$stmt->execute([$_SESSION['user_id']]);$record=$stmt->fetch(PDO::FETCH_ASSOC);
if(!$record){header('Location: internship_form.php');exit;}
$pdo->beginTransaction();
try{
    $pdo->prepare('UPDATE internship_profile SET request_status="closed",closed_reason="นักศึกษาเริ่มคำร้องใหม่",closed_at=NOW() WHERE internship_profile_id=?')->execute([$record['internship_profile_id']]);
    $pdo->prepare('INSERT INTO document_events(document_id,actor_user_id,event_type,detail) VALUES (?,? ,"restart","เก็บคำร้องเดิมเป็นประวัติและเริ่มคำร้องใหม่")')->execute([$record['document_id'],$_SESSION['user_id']]);
    $pdo->commit();
    header('Location: internship_form.php?reset=1');
}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();error_log('Reset internship failed: '.$e->getMessage());renderSweetAlertResponse('เริ่มคำร้องใหม่ไม่สำเร็จ','ระบบไม่สามารถเริ่มคำร้องใหม่ได้ กรุณาลองใหม่อีกครั้ง','error','internship_form.php',500);}
