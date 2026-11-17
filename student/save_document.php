<?php

session_start();
require_once '../config/database.php';
require_once '../helpers/document_autofill_helper.php';
require_once '../helpers/activity_log_helper.php';
require_once '../helpers/csrf_helper.php';
require_once '../helpers/notification_helper.php';
require_once '../helpers/document_workflow_helper.php';
require_once '../helpers/alert_response_helper.php';

if (($_SESSION['role'] ?? '') !== 'student' || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../auth/login.php');
    exit;
}

verify_csrf_or_abort();

$documentId = (int) ($_POST['document_id'] ?? 0);
$action = $_POST['action'] ?? 'draft';
$values = $_POST['field'] ?? [];
$studentPrefixId = (int)($_POST['student_prefix_id'] ?? 0);
$deleteAttachmentIds=array_values(array_filter(array_map('intval',(array)($_POST['delete_attachment_ids']??[]))));
$uploadedAttachmentPaths=[];
$attachmentUploads=[];
if(isset($_FILES['attachments'])&&is_array($_FILES['attachments']['name']??null)){
    $allowedMimes=['application/pdf'=>'pdf','image/jpeg'=>'jpg','image/png'=>'png'];
    $finfo=new finfo(FILEINFO_MIME_TYPE);
    foreach($_FILES['attachments']['name'] as $index=>$originalName){
        $error=(int)($_FILES['attachments']['error'][$index]??UPLOAD_ERR_NO_FILE);
        if($error===UPLOAD_ERR_NO_FILE)continue;
        if($error!==UPLOAD_ERR_OK){renderSweetAlertResponse('อัปโหลดเอกสารประกอบไม่สำเร็จ','กรุณาลองเลือกไฟล์ใหม่อีกครั้ง','warning','document_form.php?document=' . $documentId,422);}
        $size=(int)($_FILES['attachments']['size'][$index]??0);
        $tmp=(string)($_FILES['attachments']['tmp_name'][$index]??'');
        if($size<=0||$size>10*1024*1024){renderSweetAlertResponse('ขนาดไฟล์เกินกำหนด','เอกสารประกอบต้องมีขนาดไม่เกิน 10 MB ต่อไฟล์','warning','document_form.php?document=' . $documentId,422);}
        $mime=(string)$finfo->file($tmp);
        if(!isset($allowedMimes[$mime])){renderSweetAlertResponse('ชนิดไฟล์ไม่ถูกต้อง','รองรับเอกสารประกอบเฉพาะ PDF, JPG และ PNG','warning','document_form.php?document=' . $documentId,422);}
        $safeOriginal=mb_substr(basename((string)$originalName),0,255);
        $storedName=bin2hex(random_bytes(16)).'.'.$allowedMimes[$mime];
        $attachmentUploads[]=['tmp'=>$tmp,'original'=>$safeOriginal,'stored'=>$storedName,'mime'=>$mime,'size'=>$size];
    }
}

$stmt = $pdo->prepare('SELECT d.template_id,d.document_status,dt.document_code,g.advisor_employee_id
    FROM documents d
    JOIN student s ON s.student_id=d.student_id
    LEFT JOIN group_class g ON g.group_id=s.group_id
    JOIN document_template t ON t.template_id=d.template_id
    JOIN document_type dt ON dt.document_type_id=t.document_type_id
    WHERE d.document_id=? AND s.user_id=?');
$stmt->execute([$documentId, $_SESSION['user_id']]);
$documentMeta = $stmt->fetch(PDO::FETCH_ASSOC);
$templateId = (int)($documentMeta['template_id'] ?? 0);
$documentCode = str_replace('.', '', strtoupper((string)($documentMeta['document_code'] ?? '')));
if (!$templateId) {
    renderSweetAlertResponse('ไม่มีสิทธิ์แก้ไขเอกสาร', 'ไม่พบเอกสารที่อยู่ในความรับผิดชอบของคุณ', 'error', 'history.php', 403);
}
$previousStatus = (string)($documentMeta['document_status'] ?? '');
if (!in_array($previousStatus, ['draft', 'revision_requested'], true)) {
    renderSweetAlertResponse('แก้ไขเอกสารไม่ได้', 'เอกสารนี้ถูกส่งแล้วและไม่สามารถแก้ไขได้', 'warning', 'history.php', 409);
}

$stmt = $pdo->prepare('SELECT field_id,field_name,field_label,field_type,required_field FROM template_fields WHERE template_id=?');
$stmt->execute([$templateId]);
$fieldDefs = $stmt->fetchAll(PDO::FETCH_ASSOC);
if (!$fieldDefs) {
    renderSweetAlertResponse('แม่แบบเอกสารไม่พร้อมใช้งาน', 'แม่แบบเอกสารยังไม่มีช่องกรอกข้อมูล กรุณาติดต่อผู้ดูแลระบบ', 'error', 'history.php', 422);
}

if ($documentCode === 'R18') {
    $r18FieldIds = [];
    foreach ($fieldDefs as $def) {
        $r18FieldIds[(string)$def['field_name']] = (int)$def['field_id'];
    }
    $theoryValue = $values[$r18FieldIds['theory_credits'] ?? 0] ?? 0;
    $practicalValue = $values[$r18FieldIds['practical_credits'] ?? 0] ?? 0;
    $totalValue = (is_numeric($theoryValue) ? (float)$theoryValue : 0.0)
        + (is_numeric($practicalValue) ? (float)$practicalValue : 0.0);
    if (isset($r18FieldIds['total_credits'])) {
        $values[$r18FieldIds['total_credits']] = rtrim(rtrim(number_format($totalValue, 2, '.', ''), '0'), '.');
    }
    $instructorId=(int)($values[$r18FieldIds['instructor_employee_id']??0]??0);
    if($instructorId>0 && isset($r18FieldIds['responsible_department'])){
        $instructorDepartment=$pdo->prepare('SELECT d.department_name FROM employee e JOIN department d ON d.department_id=e.department_id WHERE e.employee_id=? AND e.employee_type="teacher" LIMIT 1');
        $instructorDepartment->execute([$instructorId]);
        $departmentName=trim((string)$instructorDepartment->fetchColumn());
        if($departmentName!=='')$values[$r18FieldIds['responsible_department']]=$departmentName;
    }
}

if ($documentCode === 'R14') {
    $r14FieldIds = [];
    foreach ($fieldDefs as $def) {
        $r14FieldIds[(string)$def['field_name']] = (int)$def['field_id'];
    }

    $gpaFieldId = $r14FieldIds['cumulative_gpa'] ?? 0;
    $gpaInput = trim((string)($values[$gpaFieldId] ?? ''));
    if ($gpaInput !== '') {
        // R.14 accepts a cumulative GPA from 0.00 to 4.00 with at most 2 decimal places.
        if (!preg_match('/^(?:[0-3](?:\.\d{1,2})?|4(?:\.0{1,2})?)$/', $gpaInput)) {
            $_SESSION['document_form_error'] = 'เกรดเฉลี่ยสะสมต้องเป็นตัวเลขระหว่าง 0.00–4.00 และมีทศนิยมได้ไม่เกิน 2 ตำแหน่ง';
            header('Location: document_form.php?document=' . $documentId);
            exit;
        }
        $values[$gpaFieldId] = number_format((float)$gpaInput, 2, '.', '');
    }
}

$studentProfile = getDocumentStudentProfile($pdo, (int) $_SESSION['user_id']);
$accessDate = $_SESSION['login_date'] ?? date('Y-m-d');
  $resolveValue = static function (array $def) use ($values, $studentProfile, $accessDate, $documentCode): string {
      if (in_array($documentCode, ['R14', 'R24'], true) && (string)$def['field_name'] === 'signature_name') {
          return trim((string)($studentProfile['firstname'] ?? '') . ' ' . (string)($studentProfile['lastname'] ?? ''));
      }
      if ($documentCode === 'R24'
          && in_array((string)$def['field_name'], ['advisor_print_name', 'department_head_print_name', 'instructor_print_name'], true)) {
          return '';
      }
      if ($documentCode === 'R18'
        && in_array((string)$def['field_name'], ['recipient_faculty', 'responsible_faculty'], true)) {
        return 'วิศวกรรมศาสตร์และเทคโนโลยี';
    }
    $submitted = trim((string) ($values[$def['field_id']] ?? ''));
    if ($documentCode === 'R12' && (string)$def['field_name'] === 'recipient') {
        $submitted = preg_replace('/^\s*(?:เรียน\s*)?คณบดีคณะ\s*/u', '', $submitted);
    }
    if ($submitted !== '') {
        return $submitted;
    }
    return trim((string) (getAutomaticDocumentFieldValue($def, $studentProfile, $accessDate) ?? ''));
};

$missingFields = [];
if ($action === 'ready') {
    foreach ($fieldDefs as $def) {
        if ($def['required_field'] && $resolveValue($def) === '') {
            $missingFields[] = $def['field_label'] ?: $def['field_name'];
        }
    }
}
if ($missingFields) {
    $_SESSION['document_form_error'] = 'กรุณากรอกข้อมูลที่จำเป็นให้ครบถ้วน: ' . implode(', ', $missingFields);
    header('Location: document_form.php?document=' . $documentId);
    exit;
}

$pdo->beginTransaction();
try {
    if ($studentPrefixId > 0) {
        $prefixStmt = $pdo->prepare("SELECT prefix_id FROM prefixes WHERE prefix_id=? AND (prefix_name IN ('นาย','นาง','นางสาว') OR prefix_short IN ('นาย','นาง','นางสาว','น.ส.','น.ส'))");
        $prefixStmt->execute([$studentPrefixId]);
        if ($prefixStmt->fetchColumn()) {
            $updatePrefix = $pdo->prepare('UPDATE student SET prefix_id=? WHERE user_id=?');
            $updatePrefix->execute([$studentPrefixId, (int)$_SESSION['user_id']]);
        }
    }

    $findValue = $pdo->prepare('SELECT value_id FROM document_field_values WHERE document_id=? AND field_id=? LIMIT 1');
    $insertValue = $pdo->prepare('INSERT INTO document_field_values (document_id,field_id,field_value) VALUES (?,?,?)');
    $updateValue = $pdo->prepare('UPDATE document_field_values SET field_value=? WHERE value_id=?');
    foreach ($fieldDefs as $def) {
        $value = $resolveValue($def);
        $findValue->execute([$documentId, $def['field_id']]);
        $valueId = $findValue->fetchColumn();
        if ($valueId) {
            $updateValue->execute([$value, $valueId]);
        } else {
            $insertValue->execute([$documentId, $def['field_id'], $value]);
        }
    }

    $resolvedByName = [];
    foreach ($fieldDefs as $def) {
        $resolvedByName[$def['field_name']] = $resolveValue($def);
    }
    $advisorId = (int)($documentMeta['advisor_employee_id'] ?? 0);

    if ($documentCode === 'R01' && $advisorId > 0) {
        $stmt = $pdo->prepare('INSERT INTO r01_request (document_id,advisor_employee_id,subject,recipient,request_detail,phone)
            VALUES (?,?,?,?,?,?) ON DUPLICATE KEY UPDATE advisor_employee_id=VALUES(advisor_employee_id),subject=VALUES(subject),recipient=VALUES(recipient),request_detail=VALUES(request_detail),phone=VALUES(phone)');
        $stmt->execute([$documentId,$advisorId,$resolvedByName['subject'] ?? '',$resolvedByName['recipient'] ?? '',$resolvedByName['request_detail'] ?? '',$resolvedByName['telephone'] ?? '']);
    }

    if ($documentCode === 'R12' && $advisorId > 0) {
        $stmt = $pdo->prepare('INSERT INTO r12_request (document_id,advisor_employee_id,course_code,course_name,old_group,new_group,reason)
            VALUES (?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE advisor_employee_id=VALUES(advisor_employee_id),course_code=VALUES(course_code),course_name=VALUES(course_name),old_group=VALUES(old_group),new_group=VALUES(new_group),reason=VALUES(reason)');
        $stmt->execute([$documentId,$advisorId,$resolvedByName['course_code_1'] ?? '',$resolvedByName['course_name_1'] ?? '',$resolvedByName['old_group_1'] ?? '',$resolvedByName['new_group_1'] ?? '',$resolvedByName['reason'] ?? '']);
        $r12Id = (int)$pdo->query('SELECT r12_id FROM r12_request WHERE document_id=' . $documentId)->fetchColumn();
        $pdo->prepare('DELETE FROM r12_request_courses WHERE r12_id=?')->execute([$r12Id]);
        $insertCourse = $pdo->prepare('INSERT INTO r12_request_courses (r12_id,item_no,semester,course_code,course_name,section,old_group,new_group) VALUES (?,?,?,?,?,?,?,?)');
        for ($itemNo = 1; $itemNo <= 4; $itemNo++) {
            $courseValues = [
                $resolvedByName['semester_'.$itemNo] ?? '', $resolvedByName['course_code_'.$itemNo] ?? '',
                $resolvedByName['course_name_'.$itemNo] ?? '', $resolvedByName['section_'.$itemNo] ?? '',
                $resolvedByName['old_group_'.$itemNo] ?? '', $resolvedByName['new_group_'.$itemNo] ?? '',
            ];
            if (implode('', $courseValues) !== '') {
                $insertCourse->execute([$r12Id,$itemNo,...$courseValues]);
            }
        }
    }

    if ($documentCode === 'R14') {
        $requestType = (string)($resolvedByName['request_type'] ?? '');
        if (!in_array($requestType, ['less', 'more'], true)) {
            throw new RuntimeException('กรุณาเลือกประเภทการลงทะเบียนของคำร้อง R.14');
        }
        $semester = trim((string)($resolvedByName['semester'] ?? ''));
        $academicYear = trim((string)($resolvedByName['academic_year'] ?? ''));
        $enrolledCredits = (float)($resolvedByName['enrolled_credits'] ?? 0);
        $cumulativeGpaInput = trim((string)($resolvedByName['cumulative_gpa'] ?? ''));
        $cumulativeGpa = is_numeric($cumulativeGpaInput) ? (float)$cumulativeGpaInput : -1;
        if ($semester === '' || $academicYear === '' || $enrolledCredits <= 0 || $cumulativeGpaInput === '' || $cumulativeGpa < 0 || $cumulativeGpa > 4) {
            throw new RuntimeException('ข้อมูลภาคการศึกษา ปีการศึกษา หน่วยกิต หรือเกรดเฉลี่ยสะสมของคำร้อง R.14 ไม่ถูกต้อง');
        }
        $stmt = $pdo->prepare('INSERT INTO r14_request
            (document_id,semester,academic_year,request_type,enrolled_credits,cumulative_gpa)
            VALUES (?,?,?,?,?,?)
            ON DUPLICATE KEY UPDATE semester=VALUES(semester),academic_year=VALUES(academic_year),
                request_type=VALUES(request_type),enrolled_credits=VALUES(enrolled_credits),
                cumulative_gpa=VALUES(cumulative_gpa)');
        $stmt->execute([$documentId,$semester,$academicYear,$requestType,$enrolledCredits,$cumulativeGpa]);
    }

    $status = $action === 'ready' ? 'submitted' : ($previousStatus === 'revision_requested' ? 'revision_requested' : 'draft');
    $stmt = $pdo->prepare('UPDATE documents
        SET document_status=?, submitted_at=CASE WHEN ?="submitted" THEN NOW() ELSE NULL END
        WHERE document_id=?');
    $stmt->execute([$status, $status, $documentId]);

    $isResubmission = $status === 'submitted' && $previousStatus === 'revision_requested';
    $historyAction = $status === 'submitted' ? ($isResubmission ? 'resubmitted' : 'submitted') : 'saved';
    $historyDetail = $status === 'submitted'
        ? ($isResubmission ? 'แก้ไขและส่งเอกสารใหม่' : 'ส่งเอกสารเพื่อดำเนินการ')
        : 'บันทึกแบบร่างเอกสาร';
    $stmt = $pdo->prepare('INSERT INTO document_events (document_id,actor_user_id,event_type,detail) VALUES (?,?,?,?)');
    $stmt->execute([$documentId, $_SESSION['user_id'], $historyAction, $historyDetail]);
    $deletedAttachmentPaths=[];
    if($deleteAttachmentIds){
        $placeholders=implode(',',array_fill(0,count($deleteAttachmentIds),'?'));
        $attachmentParams=array_merge([$documentId],$deleteAttachmentIds);
        $findAttachments=$pdo->prepare('SELECT attachment_id,file_path FROM document_attachments WHERE document_id=? AND attachment_id IN ('.$placeholders.')');
        $findAttachments->execute($attachmentParams);
        $deletedAttachmentPaths=$findAttachments->fetchAll(PDO::FETCH_COLUMN,1);
        $deleteAttachments=$pdo->prepare('DELETE FROM document_attachments WHERE document_id=? AND attachment_id IN ('.$placeholders.')');
        $deleteAttachments->execute($attachmentParams);
    }
    if($attachmentUploads){
        $uploadDirectory=__DIR__.'/../uploads/document_attachments';
        if(!is_dir($uploadDirectory)&&!mkdir($uploadDirectory,0775,true)&&!is_dir($uploadDirectory))throw new RuntimeException('สร้างโฟลเดอร์เอกสารแนบไม่สำเร็จ');
        $insertAttachment=$pdo->prepare('INSERT INTO document_attachments(document_id,uploaded_by,original_name,stored_name,file_path,mime_type,file_size) VALUES(?,?,?,?,?,?,?)');
        foreach($attachmentUploads as $upload){
            $destination=$uploadDirectory.DIRECTORY_SEPARATOR.$upload['stored'];
            if(!move_uploaded_file($upload['tmp'],$destination))throw new RuntimeException('ย้ายไฟล์เอกสารแนบไม่สำเร็จ');
            $uploadedAttachmentPaths[]=$destination;
            $insertAttachment->execute([$documentId,$_SESSION['user_id'],$upload['original'],$upload['stored'],'uploads/document_attachments/'.$upload['stored'],$upload['mime'],$upload['size']]);
        }
    }
    if ($status === 'submitted') {
        $workflowResult = startOrResumeDocumentWorkflow(
            $pdo,
            $documentId,
            (int) $_SESSION['user_id']
        );
        if (empty($workflowResult['managed'])) {
            throw new RuntimeException('ยังไม่ได้กำหนด Workflow อาจารย์ที่ปรึกษาสำหรับเอกสารประเภทนี้');
        }
    }
    $pdo->commit();
    foreach($deletedAttachmentPaths??[] as $relativePath){
        $absolute=realpath(__DIR__.'/../'.ltrim((string)$relativePath,'/\\'));
        $base=realpath(__DIR__.'/../uploads/document_attachments');
        if($absolute&&$base&&str_starts_with($absolute,$base.DIRECTORY_SEPARATOR))@unlink($absolute);
    }

    logActivity(
        $pdo,
        (int)$_SESSION['user_id'],
        $status === 'submitted' ? 'document_submitted' : 'document_draft_saved',
        ($status === 'submitted' ? 'ยืนยันส่ง' : 'บันทึกฉบับร่าง') . ' ' . $documentCode . ' เลขที่ #' . $documentId
    );

    if ($status === 'submitted') {
        header('Location: history.php?submitted=1');
        exit;
    }
    header('Location: document_form.php?document=' . $documentId . '&saved=1');
} catch (Throwable $e) {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    foreach($uploadedAttachmentPaths as $uploadedPath){if(is_file($uploadedPath))@unlink($uploadedPath);}
    error_log('Document save failed: '.$e->getMessage());
    renderSweetAlertResponse('บันทึกเอกสารไม่สำเร็จ', 'ระบบไม่สามารถบันทึกเอกสารได้ กรุณาลองใหม่อีกครั้ง', 'error', 'document_form.php?document=' . $documentId, 500);
}
