<?php
session_start();
require_once '../middleware/teacher.php';
require_once '../config/database.php';
require_once '../helpers/csrf_helper.php';
require_once '../helpers/teacher_document_helper.php';
require_once '../helpers/notification_helper.php';
require_once '../helpers/activity_log_helper.php';
require_once '../helpers/document_workflow_helper.php';
require_once '../helpers/alert_response_helper.php';
if($_SERVER['REQUEST_METHOD']!=='POST'){header('Location: dashboard.php');exit;}
verify_csrf_or_abort();
$documentId=(int)($_POST['document_id']??0);$result=(string)($_POST['review_result']??'');$comment=trim((string)($_POST['review_comment']??''));
$returnPage=(($_POST['return_to']??'')==='coordinator')?'coordinator_requests.php':'requests.php';
if(!in_array($result,['approved','revision_requested'],true)){renderSweetAlertResponse('ผลการพิจารณาไม่ถูกต้อง','กรุณาเลือกผลการพิจารณาใหม่อีกครั้ง','warning',$returnPage,422);}
if($result==='revision_requested'&&$comment===''){renderSweetAlertResponse('กรุณาระบุรายละเอียด','โปรดบอกจุดที่ต้องแก้ไขเพื่อให้นักศึกษาดำเนินการได้ถูกต้อง','warning',$returnPage,422);}
$document=getTeacherDocumentForReview($pdo,(int)$_SESSION['user_id'],$documentId);
if(!$document){renderSweetAlertResponse('ไม่มีสิทธิ์ตรวจเอกสาร','ไม่พบเอกสารที่อยู่ในความรับผิดชอบของคุณ','error',$returnPage,403);}
if(!in_array($document['document_status'],['submitted','in_review'],true)){header('Location: '.$returnPage.'?notice=not-pending');exit;}
$pdo->beginTransaction();
try{
 $workflowReview=workflowReviewForUser($pdo,$documentId,(int)$_SESSION['user_id']);
 if(!$workflowReview){throw new RuntimeException('เอกสารไม่ได้อยู่ใน Workflow ที่คุณมีสิทธิ์พิจารณา');}
 $workflowResult=processDocumentWorkflowReview(
    $pdo,
    $documentId,
    (int)$_SESSION['user_id'],
    $result==='revision_requested'?'revision_requested':'approved',
    $comment
 );
 if($result==='revision_requested'){
    $detail='ส่งกลับให้นักศึกษาแก้ไขในขั้น '.$workflowReview['step_name'].': '.$comment;
 }elseif(!empty($workflowResult['completed'])){
    $detail='อนุมัติขั้น '.$workflowReview['step_name'].' และเอกสารผ่านการพิจารณาครบทุกขั้นตอน';
 }else{
    $detail='อนุมัติขั้น '.$workflowReview['step_name'].' และส่งต่อไปยังขั้น '.$workflowResult['next_step_name'];
 }
 $pdo->commit();logActivity($pdo,(int)$_SESSION['user_id'],$result,$detail.' เอกสาร #'.$documentId);
}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();error_log($e->getMessage());renderSweetAlertResponse('บันทึกผลไม่สำเร็จ','ระบบไม่สามารถบันทึกผลการพิจารณาได้ กรุณาลองใหม่อีกครั้ง','error',$returnPage,500);}
header('Location: '.$returnPage.'?reviewed=1');
