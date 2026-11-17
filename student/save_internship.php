<?php
session_start();
require_once '../middleware/student.php';
require_once '../config/database.php';
require_once '../helpers/activity_log_helper.php';
require_once '../helpers/csrf_helper.php';
require_once '../helpers/document_workflow_helper.php';
require_once '../helpers/alert_response_helper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: internship.php'); exit; }
verify_csrf_or_abort();
$requestedAction = (string)($_POST['action'] ?? 'draft');
$action = in_array($requestedAction, ['submit_review', 'prepare'], true) ? 'submit_review' : 'draft';
$returnFrom = in_array((string)($_POST['return_from'] ?? ''), ['history','internship','submissions','dashboard'], true)
    ? (string)$_POST['return_from']
    : 'internship';
$studentStmt = $pdo->prepare('SELECT student_id FROM student WHERE user_id=? LIMIT 1');
$studentStmt->execute([$_SESSION['user_id']]);
$studentId = (int)$studentStmt->fetchColumn();
if (!$studentId) { renderSweetAlertResponse('ไม่พบข้อมูลนักศึกษา', 'ไม่พบข้อมูลผู้ใช้งานนักศึกษาในระบบ', 'error', 'internship_form.php', 403); }

$required = ['student_phone','year_level','calendar_id','company_name','recipient_name','recipient_position','company_number','subdistrict','district','province','postal_code','company_phone','contact_name','contact_position','contact_phone'];
if ($action === 'submit_review') {
    foreach ($required as $name) {
        if (trim((string)($_POST[$name] ?? '')) === '') { renderSweetAlertResponse('กรอกข้อมูลไม่ครบ', 'กรุณากรอกข้อมูลที่จำเป็นให้ครบถ้วนก่อนส่งคำร้อง', 'warning', 'internship_form.php', 422); }
    }
}
$calendarId=(int)($_POST['calendar_id']??0);$calendar=[];
if($calendarId>0){$calendarStmt=$pdo->prepare('SELECT calendar_id,academic_year,semester,start_date,end_date FROM internship_calendar WHERE calendar_id=? AND status="active" LIMIT 1');$calendarStmt->execute([$calendarId]);$calendar=$calendarStmt->fetch(PDO::FETCH_ASSOC)?:[];if(!$calendar){renderSweetAlertResponse('ไม่พบปฏิทินการศึกษา','ไม่พบปีการศึกษาและภาคเรียนที่เลือก หรือรายการดังกล่าวไม่ได้เปิดใช้งาน','warning','internship_form.php',422);}}
if($action==='submit_review'&&!$calendar){renderSweetAlertResponse('ยังไม่สามารถส่งคำร้องได้','กรุณาเลือกปีการศึกษาและภาคเรียนก่อนส่งคำร้อง','warning','internship_form.php',422);}

$fields = ['calendar_id','student_phone','year_level','recipient_name','company_name','company_address','subdistrict','district','province','postal_code','company_phone','company_fax','contact_prefix','contact_name','contact_position','contact_phone','job_position','job_description','capacity_note','recipient_position','recipient_department','company_number','company_moo','company_building','company_floor','company_road','company_alley','company_email','contact_department','contact_email','qualification_department','qualification_preparation','qualification_gpa','qualification_company'];
$values = [];
foreach ($fields as $field) {
    if($field==='calendar_id'){$values[$field]=$calendar?(int)$calendar['calendar_id']:null;}
    elseif($field==='company_name'){$values[$field]=trim((string)($_POST[$field]??''));}
    else{$values[$field] = str_starts_with($field, 'qualification_') ? (isset($_POST[$field]) ? 1 : 0) : (trim((string)($_POST[$field] ?? '')) ?: null);}
}

$pdo->beginTransaction();
try {
    $profileStmt = $pdo->prepare('SELECT internship_profile_id FROM internship_profile WHERE student_id=? AND request_status="active" ORDER BY internship_profile_id DESC LIMIT 1 FOR UPDATE');
    $profileStmt->execute([$studentId]);
    $profileId = (int)$profileStmt->fetchColumn();
    if ($profileId) {
        $set = implode(',', array_map(static fn($f) => "$f=?", $fields));
        $pdo->prepare("UPDATE internship_profile SET $set WHERE internship_profile_id=?")->execute([...array_values($values), $profileId]);
    } else {
        $columns = implode(',', $fields); $marks = implode(',', array_fill(0, count($fields), '?'));
        $pdo->prepare("INSERT INTO internship_profile (student_id,$columns) VALUES (?,$marks)")->execute([$studentId, ...array_values($values)]);
        $profileId = (int)$pdo->lastInsertId();
    }

    $templateStmt = $pdo->query('SELECT t.template_id FROM document_template t JOIN document_type dt ON dt.document_type_id=t.document_type_id WHERE dt.document_code="INTERNSHIP_INFO" AND t.status="active" ORDER BY t.template_id DESC LIMIT 1');
    $templateId = (int)$templateStmt->fetchColumn();
    if (!$templateId) { throw new RuntimeException('ไม่พบแม่แบบ RMUTI_Pro-Ex_02'); }

    $appStmt = $pdo->prepare('SELECT ia.internship_application_id,ia.document_id,ia.status,d.document_status
        FROM internship_application ia JOIN documents d ON d.document_id=ia.document_id
        WHERE ia.internship_profile_id=? FOR UPDATE');
    $appStmt->execute([$profileId]);
    $app = $appStmt->fetch(PDO::FETCH_ASSOC);
    if (in_array((string)($app['status'] ?? ''), ['in_review','waiting_signature','submitted'], true)) {
        throw new RuntimeException('เอกสารอยู่ระหว่างดำเนินการและไม่สามารถแก้ไขข้อมูลได้');
    }
    $status = $action === 'submit_review' ? 'in_review' : 'draft';
    if ($app) {
        $documentId = (int)$app['document_id'];
        $pdo->prepare('UPDATE internship_application
            SET request_date=?,contact_status=?,delivery_method=?,status=?,signed_file=NULL,submitted_at=NULL
            WHERE internship_application_id=?')
            ->execute([$_POST['request_date'] ?: date('Y-m-d'),$_POST['contact_status'] ?? 'not_contacted',$_POST['delivery_method'] ?? 'faculty',$status,$app['internship_application_id']]);
    } else {
        $pdo->prepare('INSERT INTO documents (student_id,template_id,document_status) VALUES (?,? ,"draft")')->execute([$studentId,$templateId]);
        $documentId = (int)$pdo->lastInsertId();
        $pdo->prepare('INSERT INTO internship_application (internship_profile_id,document_id,request_date,contact_status,delivery_method,status) VALUES (?,?,?,?,?,?)')->execute([$profileId,$documentId,$_POST['request_date'] ?: date('Y-m-d'),$_POST['contact_status'] ?? 'not_contacted',$_POST['delivery_method'] ?? 'faculty',$status]);
    }
    if ($action === 'submit_review') {
        $pdo->prepare('UPDATE documents SET document_status="submitted",submitted_at=NOW(),updated_at=NOW() WHERE document_id=?')->execute([$documentId]);
        $pdo->prepare('INSERT INTO document_events (document_id,actor_user_id,event_type,detail) VALUES (?,?,"submitted","ส่งคำร้องสหกิจให้อาจารย์ที่ปรึกษาพิจารณา")')->execute([$documentId,$_SESSION['user_id']]);
        $workflow = startOrResumeDocumentWorkflow($pdo,$documentId,(int)$_SESSION['user_id']);
        if (empty($workflow['managed'])) {
            throw new RuntimeException('ยังไม่ได้กำหนด Workflow อาจารย์ที่ปรึกษาสำหรับคำร้องสหกิจ');
        }
    } else {
        $draftDocumentStatus = (($app['document_status'] ?? '') === 'revision_requested') ? 'revision_requested' : 'draft';
        $pdo->prepare('UPDATE documents SET document_status=?,updated_at=NOW() WHERE document_id=?')->execute([$draftDocumentStatus,$documentId]);
        $pdo->prepare('INSERT INTO document_events (document_id,actor_user_id,event_type,detail) VALUES (?,?,"saved","บันทึกแบบร่างข้อมูลสหกิจ")')->execute([$documentId,$_SESSION['user_id']]);
    }
    $pdo->commit();
    logActivity($pdo,(int)$_SESSION['user_id'],$action === 'submit_review' ? 'internship_submitted_for_review' : 'internship_draft_saved',($action === 'submit_review' ? 'ส่งให้อาจารย์ที่ปรึกษาพิจารณา' : 'บันทึกฉบับร่าง') . ' RMUTI_Pro-Ex_02 เลขที่ #' . $documentId);
    header('Location: internship_form.php?' . ($action === 'submit_review' ? 'review_submitted=1' : 'saved=1') . '&from=' . rawurlencode($returnFrom));
} catch (Throwable $e) {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    error_log('Save internship failed: ' . $e->getMessage());
    renderSweetAlertResponse('บันทึกไม่สำเร็จ', 'ระบบไม่สามารถบันทึกข้อมูลได้ กรุณาลองใหม่อีกครั้ง', 'error', 'internship_form.php', 500);
}
