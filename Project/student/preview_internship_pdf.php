<?php
session_start();
require_once '../middleware/student.php';
require_once '../config/database.php';
require_once '../helpers/internship_pdf_helper.php';
require_once '../helpers/csrf_helper.php';
require_once '../helpers/alert_response_helper.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_or_abort();
}

$stmt = $pdo->prepare('SELECT ip.*,ic.academic_year,ic.semester,ic.start_date,ic.end_date,COALESCE(ia.request_date,CURDATE()) AS request_date,s.student_number,s.firstname,s.lastname,p.prefix_short,d.department_name,f.faculty_name,
    CONCAT(COALESCE(ap.prefix_short,ap.prefix_name,""),ae.firstname," ",ae.lastname) AS advisor_name,
    (SELECT CONCAT(COALESCE(cp.prefix_short,cp.prefix_name,""),ce.firstname," ",ce.lastname) FROM employee ce LEFT JOIN prefixes cp ON cp.prefix_id=ce.prefix_id JOIN employee_position cep ON cep.employee_id=ce.employee_id JOIN positions cpos ON cpos.position_id=cep.position_id WHERE ce.department_id=d.department_id AND cpos.position_name="อาจารย์ประสานงานฝึกประสบการณ์วิชาชีพ" ORDER BY ce.employee_id LIMIT 1) AS coordinator_name
    FROM student s
    LEFT JOIN internship_profile ip ON ip.student_id=s.student_id AND ip.request_status="active"
    LEFT JOIN internship_calendar ic ON ic.calendar_id=ip.calendar_id
    LEFT JOIN internship_application ia ON ia.internship_profile_id=ip.internship_profile_id
    LEFT JOIN prefixes p ON p.prefix_id=s.prefix_id
    LEFT JOIN group_class g ON g.group_id=s.group_id LEFT JOIN employee ae ON ae.employee_id=g.advisor_employee_id
    LEFT JOIN prefixes ap ON ap.prefix_id=ae.prefix_id LEFT JOIN department d ON d.department_id=g.department_id
    LEFT JOIN faculty f ON f.faculty_id=d.faculty_id WHERE s.user_id=? LIMIT 1');
$stmt->execute([$_SESSION['user_id']]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$row) { renderSweetAlertResponse('ไม่พบข้อมูลนักศึกษา', 'ไม่สามารถสร้างตัวอย่างเอกสารได้ กรุณาลองเข้าสู่ระบบใหม่', 'error', 'internship_form.php', 404); }

// Preview values currently typed in the form without saving them to the database.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $previewFields = [
        'request_date','academic_year','semester','start_date','end_date','student_phone','year_level',
        'recipient_name','recipient_position','recipient_department','company_name','company_number','company_moo',
        'company_building','company_floor','company_road','company_alley','subdistrict','district','province',
        'postal_code','company_phone','company_fax','company_email','contact_prefix','contact_name',
        'contact_position','contact_department','contact_phone','contact_email','job_position','job_description',
        'capacity_note'
    ];
    foreach ($previewFields as $field) {
        if (array_key_exists($field, $_POST)) {
            $row[$field] = trim((string)$_POST[$field]);
        }
    }
    foreach (['qualification_department','qualification_preparation','qualification_gpa','qualification_company'] as $field) {
        $row[$field] = isset($_POST[$field]) ? 1 : 0;
    }
}

$pdf = createInternshipApplicationPdf($row);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $content = $pdf->Output('RMUTI_Pro-Ex_02-preview.pdf', 'S');
    header('Content-Type: application/pdf');
    header('Content-Length: ' . strlen($content));
    echo $content;
    exit;
}
$pdf->Output('RMUTI_Pro-Ex_02-preview.pdf', 'I');
