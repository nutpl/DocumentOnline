<?php
if(session_status()!==PHP_SESSION_ACTIVE)session_start();require_once __DIR__.'/../config/database.php';require_once __DIR__.'/../helpers/official_internship_word_helper.php';require_once __DIR__.'/../helpers/official_template_settings_helper.php';require_once __DIR__.'/../helpers/official_internship_pdf_helper.php';require_once __DIR__.'/../helpers/official_response_form_pdf_helper.php';require_once __DIR__.'/../helpers/word_to_pdf_converter_helper.php';require_once __DIR__.'/../helpers/alert_response_helper.php';
$officialDocumentContext=$officialDocumentContext??'staff';$isCoordinatorDownload=$officialDocumentContext==='teacher_coordinator';if($isCoordinatorDownload){require_once __DIR__.'/../middleware/teacher.php';require_once __DIR__.'/../helpers/teacher_coordinator_helper.php';$coordinator=teacherCoordinatorProfile($pdo,(int)$_SESSION['user_id']);if(!$coordinator){renderSweetAlertResponse('ไม่มีสิทธิ์ใช้งาน','หน้านี้สำหรับอาจารย์ประสานงานฝึกประสบการณ์วิชาชีพเท่านั้น','error','official_history.php',403);}$departmentId=(int)$coordinator['department_id'];}else{require_once __DIR__.'/../middleware/staff.php';$staffStmt=$pdo->prepare('SELECT department_id FROM employee WHERE user_id=? AND employee_type IN ("staff","teacher") LIMIT 1');$staffStmt->execute([$_SESSION['user_id']]);$departmentId=(int)$staffStmt->fetchColumn();if(!$departmentId){renderSweetAlertResponse('ไม่มีสิทธิ์','บัญชีนี้ไม่มีสิทธิ์จัดการหนังสือของสาขา','error','official_history.php',403);}}
$id=(int)($_GET['id']??0);$requestedType=(string)($_GET['type']??'');if($id<1||!in_array($requestedType,['request','send','response'],true)||($isCoordinatorDownload&&!in_array($requestedType,['request','send'],true))){renderSweetAlertResponse('ข้อมูลไม่ถูกต้อง','ไม่พบข้อมูลหนังสือที่ต้องการดาวน์โหลด','error','official_history.php',422);}
$stmt=$pdo->prepare('SELECT DISTINCT od.* FROM internship_official_document od JOIN internship_official_student os ON os.official_document_id=od.official_document_id JOIN internship_profile ip ON ip.internship_profile_id=os.internship_profile_id JOIN student s ON s.student_id=ip.student_id JOIN group_class g ON g.group_id=s.group_id WHERE od.official_document_id=? AND g.department_id=?');$stmt->execute([$id,$departmentId]);$document=$stmt->fetch(PDO::FETCH_ASSOC);if(!$document||($requestedType!=='response'&&$document['document_type']!==$requestedType)||($requestedType==='response'&&$document['document_type']!=='request')){renderSweetAlertResponse('ไม่พบหนังสือ','ไม่พบหนังสือที่เลือก หรือคุณไม่มีสิทธิ์เข้าถึง','error','official_history.php',404);}
$stmt=$pdo->prepare('SELECT ip.*,ic.academic_year,ic.semester,ic.start_date,ic.end_date,s.student_number,s.firstname,s.lastname,COALESCE(NULLIF(p.prefix_short,""),p.prefix_name) AS prefix_short,d.department_name FROM internship_official_student os JOIN internship_profile ip ON ip.internship_profile_id=os.internship_profile_id JOIN internship_calendar ic ON ic.calendar_id=ip.calendar_id JOIN student s ON s.student_id=ip.student_id LEFT JOIN prefixes p ON p.prefix_id=s.prefix_id JOIN group_class g ON g.group_id=s.group_id JOIN department d ON d.department_id=g.department_id WHERE os.official_document_id=? ORDER BY os.sequence_no');$stmt->execute([$id]);$students=$stmt->fetchAll(PDO::FETCH_ASSOC);if(!$students){renderSweetAlertResponse('ไม่พบรายชื่อนักศึกษา','หนังสือนี้ยังไม่มีรายชื่อนักศึกษาที่สามารถสร้างเอกสารได้','warning','official_history.php',404);}
$document=officialTemplateApplySettings($pdo,$departmentId,$document);
$filename=officialInternshipCompanyDownloadFilename($document, 'pdf', count($students));
if($requestedType==='response'){
    try{
        $pdfData=createOfficialResponseFormPdf($pdo,$document,$students)->Output('', 'S');
        header('Content-Type: application/pdf');header('Content-Disposition: attachment; filename*=UTF-8\'\''.rawurlencode($filename));header('Content-Length: '.strlen($pdfData));header('X-Content-Type-Options: nosniff');echo $pdfData;
    }catch(Throwable $error){error_log('Official response form PDF export failed: '.$error->getMessage());renderSweetAlertResponse('สร้างไฟล์ PDF ไม่สำเร็จ','ระบบไม่สามารถสร้างแบบตอบรับสำหรับพิมพ์ได้ กรุณาลองใหม่อีกครั้ง','error','official_history.php',500);}
    exit;
}
try{
    if(PHP_OS_FAMILY==='Windows'){
        // XAMPP has Microsoft Word available.  Export the unchanged approved
        // Word template through Word COM so its PDF is visually identical to
        // the Word download on this machine.
        $tempBase=tempnam(sys_get_temp_dir(),'official-word-pdf-');
        if($tempBase===false){throw new RuntimeException('ระบบไม่สามารถเตรียมไฟล์เอกสารได้');}
        $docxFile=$tempBase.'.docx';$pdfFile=$tempBase.'.pdf';@unlink($tempBase);
        // Convert the exact same DOCX that the Word button downloads.
        saveOfficialInternshipWordFile($document,$students,$requestedType,$docxFile,false);
        convertWordDocumentToPdf($docxFile,$pdfFile);
        $pdfData=@file_get_contents($pdfFile);
    }else{
        // LibreOffice needs its conversion-specific template on Server. The
        // normal Word template makes it substitute font/VML metrics and can
        // expand one A4 letter into two pages.
        $tempBase=tempnam(sys_get_temp_dir(),'official-word-pdf-');
        if($tempBase===false){throw new RuntimeException('ระบบไม่สามารถเตรียมไฟล์เอกสารได้');}
        $docxFile=$tempBase.'.docx';$pdfFile=$tempBase.'.pdf';@unlink($tempBase);
        // Keep the source DOCX identical to the Word download here as well.
        saveOfficialInternshipWordFile($document,$students,$requestedType,$docxFile,false);
        convertWordDocumentToPdf($docxFile,$pdfFile);
        $pdfData=@file_get_contents($pdfFile);
    }
    if($pdfData===''||$pdfData===false){throw new RuntimeException('ไม่สามารถสร้างข้อมูล PDF ได้');}
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename*=UTF-8\'\''.rawurlencode($filename));
    header('Content-Length: '.strlen($pdfData));
    header('X-Content-Type-Options: nosniff');
    echo $pdfData;
}catch(Throwable $error){
    error_log('Official server PDF export failed: '.$error->getMessage());
    renderSweetAlertResponse('สร้างไฟล์ PDF ไม่สำเร็จ','ระบบไม่สามารถสร้างเอกสารได้ กรุณาลองใหม่อีกครั้ง','error','official_history.php',500);
}finally{
    if(isset($docxFile))@unlink($docxFile);
    if(isset($pdfFile))@unlink($pdfFile);
}
