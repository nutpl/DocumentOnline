<?php
session_start();
require_once '../config/database.php';
require_once '../helpers/teacher_document_helper.php';
require_once '../helpers/alert_response_helper.php';
$role=(string)($_SESSION['role']??'');
if(!in_array($role,['student','staff','teacher'],true)){header('Location: ../auth/login.php');exit;}
$attachmentId=(int)($_GET['attachment']??0);
$stmt=$pdo->prepare('SELECT a.original_name,a.file_path,a.mime_type,a.file_size,d.document_id,d.document_status,s.user_id student_user_id,g.department_id,dt.document_code
    FROM document_attachments a JOIN documents d ON d.document_id=a.document_id JOIN student s ON s.student_id=d.student_id LEFT JOIN group_class g ON g.group_id=s.group_id
    JOIN document_template t ON t.template_id=d.template_id JOIN document_type dt ON dt.document_type_id=t.document_type_id WHERE a.attachment_id=? LIMIT 1');
$stmt->execute([$attachmentId]);$file=$stmt->fetch(PDO::FETCH_ASSOC);
if($file){
    $allowed=$role==='student'&&(int)$file['student_user_id']===(int)$_SESSION['user_id'];
    if($role==='staff'&&in_array($file['document_code'],['INTERNSHIP_INFO','INTERNSHIP_REQUEST','INTERNSHIP_SEND'],true)){
        $accessStmt=$pdo->prepare('SELECT 1 FROM employee WHERE user_id=? AND employee_type="staff" AND department_id=?');$accessStmt->execute([$_SESSION['user_id'],$file['department_id']]);$allowed=(bool)$accessStmt->fetchColumn();
    }
    if($role==='teacher'&&$file['document_status']!=='draft')$allowed=(bool)getTeacherDocumentForReview($pdo,(int)$_SESSION['user_id'],(int)$file['document_id']);
    if(!$allowed)$file=false;
}
if(!$file){renderSweetAlertResponse('ไม่พบไฟล์','ไม่พบไฟล์ที่ต้องการ หรือคุณไม่มีสิทธิ์ดาวน์โหลด','error',null,404);}
$base=realpath(__DIR__.'/../uploads/document_attachments');
$path=$base?realpath(__DIR__.'/../'.ltrim((string)$file['file_path'],'/\\')):false;
if(!$base||!$path||!str_starts_with($path,$base.DIRECTORY_SEPARATOR)||!is_file($path)){renderSweetAlertResponse('ไม่พบไฟล์','ไม่พบไฟล์แนบที่ต้องการดาวน์โหลด','error',null,404);}
$downloadName=str_replace(["\r","\n",'"'],['_','_',''],(string)$file['original_name']);
header('Content-Type: '.$file['mime_type']);
header('Content-Length: '.filesize($path));
header("Content-Disposition: attachment; filename*=UTF-8''".rawurlencode($downloadName));
header('X-Content-Type-Options: nosniff');
readfile($path);
