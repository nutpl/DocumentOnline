<?php
session_start();
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
require_once '../config/database.php';
require_once '../vendor/autoload.php';
require_once '../helpers/activity_log_helper.php';
require_once '../helpers/pdf_font_helper.php';
require_once '../helpers/document_role_name_helper.php';
require_once '../helpers/r01_layout_helper.php';
require_once '../helpers/r12_layout_helper.php';
require_once '../helpers/r14_layout_helper.php';
require_once '../helpers/r16_layout_helper.php';
require_once '../helpers/document_download_access_helper.php';
require_once '../helpers/internship_pdf_helper.php';

use setasign\Fpdi\Tcpdf\Fpdi;

$role = $_SESSION['role'] ?? '';
if (!in_array($role, ['student', 'teacher', 'staff'], true)) {
    header('Location: ../auth/login.php');
    exit;
}

function renderDownloadSweetAlert(string $title, string $message, string $role, int $statusCode = 404): never
{
    $fallback = match ($role) {
        'teacher' => '../teacher/requests.php',
        'staff' => '../staff/documents.php',
        default => 'internship_submissions.php',
    };
    $redirect = $fallback;
    $referer = (string)($_SERVER['HTTP_REFERER'] ?? '');
    $refererParts = $referer !== '' ? parse_url($referer) : false;
    if (is_array($refererParts)
        && isset($refererParts['host'])
        && strcasecmp((string)$refererParts['host'], (string)($_SERVER['HTTP_HOST'] ?? '')) === 0) {
        $redirect = $referer;
    }

    http_response_code($statusCode);
    header('Content-Type: text/html; charset=UTF-8');
    $safeTitle = json_encode($title, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    $safeMessage = json_encode($message, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    $safeRedirect = json_encode($redirect, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    echo '<!doctype html><html lang="th"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>แจ้งเตือน</title><script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script></head><body><script>';
    echo 'Swal.fire({icon:"warning",title:' . $safeTitle . ',text:' . $safeMessage . ',confirmButtonText:"กลับหน้ารายการ",confirmButtonColor:"#dc2626",allowOutsideClick:false}).then(function(){window.location.href=' . $safeRedirect . ';});';
    echo '</script></body></html>';
    exit;
}

$documentId = (int)($_GET['document'] ?? 0);

$baseSelect = '
    SELECT 
        d.template_id,
        d.document_status,
        dt.document_code,
        dt.document_name,
        t.pdf_template,
        s.firstname AS student_firstname,
        s.lastname AS student_lastname,
        pr.prefix_short,
        pr.prefix_name
    FROM documents d 
    JOIN student s ON s.student_id = d.student_id 
    LEFT JOIN prefixes pr ON pr.prefix_id = s.prefix_id
    LEFT JOIN group_class g ON g.group_id = s.group_id
    JOIN document_template t ON t.template_id = d.template_id 
    JOIN document_type dt ON dt.document_type_id = t.document_type_id';

if ($role === 'student') {
    $stmt = $pdo->prepare($baseSelect . ' WHERE d.document_id = ? AND s.user_id = ?');
    $stmt->execute([$documentId, $_SESSION['user_id']]);
} elseif ($role === 'teacher') {
    $employeeStmt = $pdo->prepare("SELECT employee_id,department_id FROM employee WHERE user_id=? AND employee_type='teacher'");
    $employeeStmt->execute([$_SESSION['user_id']]);
    $teacher = $employeeStmt->fetch(PDO::FETCH_ASSOC);
    if (!$teacher) {
        renderDownloadSweetAlert('ไม่มีสิทธิ์เปิดเอกสาร', 'ไม่พบสิทธิ์อาจารย์สำหรับเปิดเอกสารฉบับนี้', $role, 403);
    }
    $stmt = $pdo->prepare($baseSelect . '
        WHERE d.document_id = ? AND d.document_status IN ("submitted","in_review","approved") AND (
            EXISTS (SELECT 1 FROM document_review_processes drp JOIN document_review_step_instances dsi ON dsi.process_id=drp.process_id WHERE drp.document_id=d.document_id AND dsi.reviewer_user_id=?)
            OR (REPLACE(dt.document_code,".","") IN ("R01", "R12", "R14", "R18", "R24") AND g.advisor_employee_id = ?)
	            OR (d.document_status="approved" AND g.department_id=? AND EXISTS (
	                SELECT 1 FROM employee_position ep JOIN positions p ON p.position_id=ep.position_id
	                WHERE ep.employee_id=? AND (
	                    (REPLACE(dt.document_code,".","") IN ("R01","R12","R14") AND p.position_name="ประธานหลักสูตร")
	                    OR (REPLACE(dt.document_code,".","") IN ("R01","R14","R18","R24") AND p.position_name LIKE "หัวหน้าสาขา%")
	                    OR (REPLACE(dt.document_code,".","")="INTERNSHIP_INFO" AND p.position_name="อาจารย์ประสานงานฝึกประสบการณ์วิชาชีพ")
	                )
	            ))
	            OR (d.document_status="approved" AND (
	                (REPLACE(dt.document_code,".","")="R18" AND EXISTS (SELECT 1 FROM r18_request rr18 WHERE rr18.document_id=d.document_id AND rr18.teacher_employee_id=?))
	                OR (REPLACE(dt.document_code,".","")="R24" AND EXISTS (SELECT 1 FROM r24_request rr24 WHERE rr24.document_id=d.document_id AND rr24.teacher_employee_id=?))
	            ))
        )');
    $stmt->execute([
        $documentId,
        $_SESSION['user_id'],
	        $teacher['employee_id'],
	        $teacher['department_id'], $teacher['employee_id'], $teacher['employee_id'], $teacher['employee_id'],
    ]);
} else {
    $employeeStmt = $pdo->prepare("SELECT department_id FROM employee WHERE user_id=? AND employee_type='staff'");
    $employeeStmt->execute([$_SESSION['user_id']]);
    $departmentId = (int) $employeeStmt->fetchColumn();
    if (!$departmentId) {
        renderDownloadSweetAlert('ไม่มีสิทธิ์เปิดเอกสาร', 'ไม่พบสิทธิ์เจ้าหน้าที่สำหรับเปิดเอกสารฉบับนี้', $role, 403);
    }
    $stmt = $pdo->prepare($baseSelect . ' WHERE d.document_id=? AND d.document_status="submitted" AND g.department_id=? AND dt.document_code IN ("INTERNSHIP_REQUEST","INTERNSHIP_SEND")');
    $stmt->execute([$documentId, $departmentId]);
}
$document = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$document) {
    renderDownloadSweetAlert('ไม่พบเอกสาร', 'ไม่พบเอกสารที่ต้องการ หรือคุณไม่มีสิทธิ์เปิดเอกสาร', $role, 404);
}

if ($role === 'student') {
    $internshipStatus = null;
    if (str_replace('.', '', strtoupper((string)$document['document_code'])) === 'INTERNSHIP_INFO') {
        $applicationStatusStatement = $pdo->prepare('SELECT status FROM internship_application WHERE document_id = ? LIMIT 1');
        $applicationStatusStatement->execute([$documentId]);
        $internshipStatus = $applicationStatusStatement->fetchColumn() ?: null;
    }

    if (!studentCanDownloadDocument(
        (string)$document['document_code'],
        (string)$document['document_status'],
        $internshipStatus !== null ? (string)$internshipStatus : null
    )) {
        renderDownloadSweetAlert(
            'ยังไม่สามารถพิมพ์เอกสารได้',
            'นักศึกษาจะพิมพ์และอัปโหลดเอกสารได้หลังอาจารย์ที่ปรึกษาและอาจารย์ประสานงานอนุมัติครบแล้ว',
            $role,
            403
        );
    }
}

if (strtoupper((string) $document['document_code']) === 'INTERNSHIP_INFO') {
    $applicationStatement = $pdo->prepare(
        'SELECT ip.*,ia.request_date,ia.status AS application_status,ia.signed_file,
                doc.document_status,doc.document_id,s.student_number,s.firstname,s.lastname,
                p.prefix_short,d.department_name,f.faculty_name,
                CONCAT(COALESCE(ap.prefix_short,ap.prefix_name,""),ae.firstname," ",ae.lastname) AS advisor_name,
                (SELECT CONCAT(COALESCE(cp.prefix_short,cp.prefix_name,""),ce.firstname," ",ce.lastname)
                 FROM employee ce
                 LEFT JOIN prefixes cp ON cp.prefix_id=ce.prefix_id
                 JOIN employee_position cep ON cep.employee_id=ce.employee_id
                 JOIN positions cpos ON cpos.position_id=cep.position_id
                 WHERE ce.department_id=d.department_id
                   AND cpos.position_name="อาจารย์ประสานงานฝึกประสบการณ์วิชาชีพ"
                 ORDER BY ce.employee_id LIMIT 1) AS coordinator_name
         FROM internship_application ia
         JOIN documents doc ON doc.document_id=ia.document_id
         JOIN internship_profile ip ON ip.internship_profile_id=ia.internship_profile_id
         JOIN student s ON s.student_id=ip.student_id
         LEFT JOIN prefixes p ON p.prefix_id=s.prefix_id
         LEFT JOIN group_class g ON g.group_id=s.group_id
         LEFT JOIN employee ae ON ae.employee_id=g.advisor_employee_id
         LEFT JOIN prefixes ap ON ap.prefix_id=ae.prefix_id
         LEFT JOIN department d ON d.department_id=g.department_id
         LEFT JOIN faculty f ON f.faculty_id=d.faculty_id
         WHERE doc.document_id=? LIMIT 1'
    );
    $applicationStatement->execute([$documentId]);
    $application = $applicationStatement->fetch(PDO::FETCH_ASSOC);
    if (!$application) {
        renderDownloadSweetAlert(
            'ไม่พบข้อมูลคำร้องสหกิจ',
            'ไม่พบข้อมูลเอกสารที่ต้องการ กรุณากลับไปตรวจสอบรายการอีกครั้ง',
            $role
        );
    }

    // Teachers review the generated application. Students receive the same
    // generated PDF only after every online review step has been approved.
    if ($role === 'teacher' || (string)$application['application_status'] !== 'submitted') {
        logActivity($pdo, (int)$_SESSION['user_id'], 'document_downloaded', 'เปิดแบบคำร้องสหกิจเลขที่ #' . $documentId . ' ในบทบาท ' . $role);
        $internshipPdf = createInternshipApplicationPdf($application);
        $outputMode = $role === 'teacher' ? 'I' : 'D';
        $internshipPdf->Output('RMUTI_Pro-Ex_02-' . $application['student_number'] . '.pdf', $outputMode);
        exit;
    }

    $signedBase = realpath(__DIR__ . '/../uploads/internship_signed');
    $signedPath = $signedBase && !empty($application['signed_file'])
        ? realpath($signedBase . DIRECTORY_SEPARATOR . basename((string) $application['signed_file']))
        : false;
    if (!$signedBase || !$signedPath || !str_starts_with($signedPath, $signedBase . DIRECTORY_SEPARATOR)) {
        renderDownloadSweetAlert(
            'ไม่พบไฟล์ฉบับลงชื่อ',
            'ไม่พบไฟล์คำร้องสหกิจฉบับลงชื่อ กรุณาให้นักศึกษาอัปโหลดไฟล์ใหม่หรือติดต่อเจ้าหน้าที่',
            $role
        );
    }
    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="INTERNSHIP_INFO_' . preg_replace('/[^A-Za-z0-9_-]/', '_', (string) $application['student_number']) . '.pdf"');
    header('Content-Length: ' . filesize($signedPath));
    readfile($signedPath);
    exit;
}

$automaticRoleNames = getDocumentRolePrintNames($pdo, $documentId);
$normalizedDocumentCode = str_replace('.', '', strtoupper((string)$document['document_code']));
$r01Layout = $normalizedDocumentCode === 'R01' ? r01LayoutConfig() : [];
$r12Layout = $normalizedDocumentCode === 'R12' ? r12LayoutConfig() : [];
$r14Layout = $normalizedDocumentCode === 'R14' ? r14LayoutConfig() : [];
$r16Layout = $normalizedDocumentCode === 'R16' ? r16LayoutConfig() : [];

logActivity(
    $pdo,
    (int)$_SESSION['user_id'],
    'document_downloaded',
    'ดาวน์โหลด ' . $document['document_code'] . ' เลขที่ #' . $documentId . ' ในบทบาท ' . $role
);

$source = realpath('../uploads/pdf/' . $document['pdf_template']);
if (!$source || strtolower(pathinfo($source, PATHINFO_EXTENSION)) !== 'pdf') {
    renderDownloadSweetAlert('ไม่พบไฟล์ต้นฉบับ', 'ไม่พบไฟล์แม่แบบสำหรับสร้างเอกสารนี้ กรุณาติดต่อผู้ดูแลระบบ', $role, 404);
}

$stmt = $pdo->prepare('
    SELECT 
        f.*,
        v.field_value 
    FROM template_fields f 
    LEFT JOIN document_field_values v ON v.field_id = f.field_id AND v.document_id = ? 
    WHERE f.template_id = ? 
    ORDER BY f.page_no, f.pos_y
');
$stmt->execute([$documentId, $document['template_id']]);
$fields = $stmt->fetchAll(PDO::FETCH_ASSOC);
if ($normalizedDocumentCode === 'R24') {
    $guardianValues = [];
    foreach ($fields as $field) {
        $guardianValues[(string)$field['field_name']] = trim((string)($field['field_value'] ?? ''));
    }
    // Do not print legacy/autofilled guardian names on an otherwise blank
    // consent section. The name appears only when the guardian details have
    // actually been completed.
    if (($guardianValues['guardian_relationship'] ?? '') === ''
        && ($guardianValues['guardian_signature_name'] ?? '') === '') {
        foreach ($fields as &$field) {
            if ((string)$field['field_name'] === 'guardian_name') {
                $field['field_value'] = '';
                break;
            }
        }
        unset($field);
    }
}
if ($normalizedDocumentCode === 'R18') {
    $r18DownloadValues = [];
    foreach ($fields as $downloadField) {
        $r18DownloadValues[(string)$downloadField['field_name']] = $downloadField['field_value'];
    }
    $r18DownloadTotal = (is_numeric($r18DownloadValues['theory_credits'] ?? null) ? (float)$r18DownloadValues['theory_credits'] : 0.0)
        + (is_numeric($r18DownloadValues['practical_credits'] ?? null) ? (float)$r18DownloadValues['practical_credits'] : 0.0);
    foreach ($fields as &$field) {
        if (in_array((string)$field['field_name'], ['recipient_faculty', 'responsible_faculty'], true)) {
            $field['field_value'] = 'วิศวกรรมศาสตร์และเทคโนโลยี';
        }
        if ((string)$field['field_name'] === 'total_credits') {
            $field['field_value'] = rtrim(rtrim(number_format($r18DownloadTotal, 2, '.', ''), '0'), '.');
        }
    }
    unset($field);
}
if (in_array($normalizedDocumentCode, ['R14', 'R24'], true)) {
    foreach ($fields as &$field) {
        if ((string)$field['field_name'] === 'signature_name') {
            $field['field_value'] = trim((string)($document['student_firstname'] ?? '') . ' ' . (string)($document['student_lastname'] ?? ''));
        }
        if ($normalizedDocumentCode === 'R24' && in_array((string)$field['field_name'], ['advisor_print_name', 'department_head_print_name', 'instructor_print_name'], true)) {
            $field['field_value'] = '';
        }
    }
    unset($field);
}

$pdf = new Fpdi('P', 'mm', 'A4');
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);
$thaiFont = thSarabunPskFont();
$thaiBoldFont = thSarabunPskFont('B');
$thaiItalicFont = thSarabunPskFont('I');
$thaiBoldItalicFont = thSarabunPskFont('BI');
$pages = $pdf->setSourceFile($source);

function splitTextToWidths(Fpdi $pdf, string $text, array $widths): array
{
    preg_match_all('/\\X/u', $text, $matches);
    $graphemes = $matches[0] ?? [];
    $lines = [];
    $cursor = 0;

    foreach ($widths as $width) {
        $line = '';
        while ($cursor < count($graphemes)) {
            $next = $line . $graphemes[$cursor];
            if ($line !== '' && $pdf->GetStringWidth($next) > $width) {
                break;
            }
            $line = $next;
            $cursor++;
        }
        $lines[] = trim($line);
    }

    return $lines;
}

for ($page = 1; $page <= $pages; $page++) {
    $tpl = $pdf->importPage($page);
    $sourceSize = $pdf->getTemplateSize($tpl);
    
    $scaleX = 210 / (float)$sourceSize['width'];
    $scaleY = 297 / (float)$sourceSize['height'];
    
    $pdf->AddPage('P', 'A4');
    $pdf->useTemplate($tpl, 0, 0, 210, 297);

    foreach ($fields as $field) {
        $fieldName = (string)$field['field_name'];
        if (array_key_exists($fieldName, $automaticRoleNames)) {
            $field['field_value'] = $automaticRoleNames[$fieldName];
        }
        if ((int)$field['page_no'] !== $page || (string)($field['field_value'] ?? '') === '') {
            continue;
        }

        $isR01 = $normalizedDocumentCode === 'R01';
        $isR12 = $normalizedDocumentCode === 'R12';
        $isR14 = $normalizedDocumentCode === 'R14';
        $isR16 = $normalizedDocumentCode === 'R16';
        $isR18 = $normalizedDocumentCode === 'R18';
        $isR24 = $normalizedDocumentCode === 'R24';
        $useReviewedR12PreviewLayout = $isR12 && in_array($role, ['student', 'teacher'], true);
        $useReviewedR14PreviewLayout = $isR14;
        $useReviewedR16PreviewLayout = $isR16 && in_array($role, ['student', 'teacher'], true);
        // R18 uses one reviewed layout for every role so staff/admin downloads
        // match the same preview shown to students and teachers.
        $useReviewedR18PreviewLayout = $isR18;
        $useReviewedR24PreviewLayout = $isR24;
        // document_form.php moves reviewed R-document overlay layers down 3px.
        // R18 export is positioned 3pt above the previously reviewed position
        // after the latest 2pt downward adjustment.
        $useReviewedR01PreviewLayout = $isR01 && in_array($role, ['student', 'teacher'], true);
        $previewLayerOffsetY = $useReviewedR01PreviewLayout
            ? (float)$r01Layout['layer_offset_y']
            : ($useReviewedR18PreviewLayout
                ? -3.5
                : (($useReviewedR12PreviewLayout || $useReviewedR14PreviewLayout || $useReviewedR16PreviewLayout || $useReviewedR24PreviewLayout) ? 3.0 : 0.0));
        if (in_array((string)$field['field_name'], ['instructor_employee_id', 'teacher_employee_id'], true)) {
            continue;
        }
        if ($isR12 && preg_match('/_(5)$/', (string)$field['field_name'])) {
            continue;
        }

        if ($isR01) {
            $r01ExportPositions = $r01Layout['fields'];
            $r01ExportPositions['request_date'] = [$r01Layout['date']['day'][0], $r01Layout['date']['day'][1], 250];
            $r01ExportPositions['education_level'] = [$r01Layout['education_ticks']['undergraduate'][0], $r01Layout['education_ticks']['undergraduate'][1], 550];
            if (isset($r01ExportPositions[$field['field_name']])) {
                [$field['pos_x'], $field['pos_y'], $field['width']] = $r01ExportPositions[$field['field_name']];
            }
        }

        if ($isR12) {
            $r12ExportPositions = $r12Layout['main_fields'];
            $r12ExportPositions['request_date'] = [$r12Layout['date']['day'][0], $r12Layout['date']['day'][1], 250];
            $r12ExportPositions['education_level'] = [$r12Layout['education_x']['undergraduate'], $r12Layout['education_y'], 24];
            $r12ExportPositions['signature_name'] = $r12Layout['signature']['ink'];
            if (isset($r12ExportPositions[$field['field_name']])) {
                [$field['pos_x'], $field['pos_y'], $field['width']] = $r12ExportPositions[$field['field_name']];
            } elseif (preg_match('/^(semester|course_code|course_name|section|old_group|new_group)_([1-4])$/', (string)$field['field_name'], $r12Match)) {
                $field['pos_x'] = (float)$field['pos_x'] - $r12Layout['table_left_shift'][$r12Match[1]];
                $field['pos_y'] = (float)$field['pos_y'] + $r12Layout['table_y_shift'];
            }
        }

        if ($isR14 && in_array((string)$field['field_name'], ['signature_name', 'signer_name', 'student_signature_name'], true)) {
            [$field['pos_x'], $field['pos_y'], $field['width'], $field['height']] = $r14Layout['signature_pdf_print'];
        }

        if ($isR16 && in_array((string)$field['field_name'], ['faculty', 'curriculum'], true)) {
            $field['pos_x'] = (float)$field['pos_x'] + 8;
            $field['pos_y'] = (float)$field['pos_y'] + 6;
        }
        if ($isR16 && (string)$field['field_name'] === 'student_name') {
            $field['pos_x'] = (float)$field['pos_x'] + 16;
            $field['pos_y'] = (float)$field['pos_y'] + 6;
        }
        if ($isR16 && (string)$field['field_name'] === 'student_number') {
            $field['pos_x'] = (float)$field['pos_x'] + 8;
            $field['pos_y'] = (float)$field['pos_y'] + 6;
        }
        if ($isR16 && in_array((string)$field['field_name'], ['course_code', 'course_name', 'section', 'reason', 'current_enrollment_count', 'additional_seat_count'], true)) {
            $field['pos_x'] = (float)$field['pos_x'] + 16;
            if ((string)$field['field_name'] !== 'reason') {
                $field['pos_y'] = (float)$field['pos_y'] + 6;
            } else {
                $field['pos_y'] = (float)$field['pos_y'] + 16.8;
            }
        }
        if ($isR16 && in_array((string)$field['field_name'], ['signature_name', 'signer_name', 'student_signature_name'], true)) {
            $field['pos_x'] = (float)$field['pos_x'] - 25;
        }
        if ($isR16 && !in_array((string)$field['field_name'], ['signature_name', 'signer_name', 'student_signature_name'], true)) {
            $field['pos_y'] = (float)$field['pos_y'] - 3;
        }
        if ($isR18 && in_array((string)$field['field_name'], ['signature_name', 'signer_name', 'student_signature_name'], true)) {
            $field['pos_y'] = 560;
        }
        if ($isR24 && (string)$field['field_name'] === 'signature_name') {
            $field['pos_x'] = 469.5;
            // TCPDF's 16pt baseline sits lower than the browser preview.
            // Lift only the downloaded signature text 5pt above the line.
            $field['pos_y'] = 527.5;
        }
        if ($isR18 && (string)$field['field_name'] === 'student_count') {
            $field['pos_x'] = (float)$field['pos_x'] + 10;
            $field['pos_y'] = (float)$field['pos_y'] + 4;
        }
        if ($isR18 && in_array((string)$field['field_name'], ['request_semester', 'request_academic_year'], true)) {
            $field['pos_x'] = (float)$field['pos_x'] + 8.5;
        }
        if ($isR18 && (string)$field['field_name'] === 'student_number') {
            // Source overlay coordinates use 1.5px per PDF point.
            $field['pos_x'] = (float)$field['pos_x'] - 6.0;
        }
        if ($isR18 && (string)$field['field_name'] === 'faculty') {
            $field['pos_x'] = (float)$field['pos_x'] - 7.5;
        }
        
        $x = ((float)$field['pos_x'] / 1.5) * 25.4 / 72 * $scaleX;
        $yPixel = ($useReviewedR01PreviewLayout || $useReviewedR12PreviewLayout || $useReviewedR14PreviewLayout || $useReviewedR16PreviewLayout || $useReviewedR18PreviewLayout || $useReviewedR24PreviewLayout)
            ? (float)$field['pos_y'] + $previewLayerOffsetY
            : (float)$field['pos_y'] + (float)$field['font_size'];
        $y = ($yPixel / 1.5) * 25.4 / 72 * $scaleY;
        if ($isR01) {
            $r01FieldYOffset = match (true) {
                $fieldName === 'request_date' => (float)$r01Layout['pdf_date_offset_y_px'],
                in_array($fieldName, ['faculty', 'curriculum'], true) => (float)$r01Layout['pdf_faculty_curriculum_offset_y_px'],
                default => (float)$r01Layout['pdf_text_offset_y_px'],
            };
            $y += (($r01FieldYOffset / 1.5) * 25.4 / 72) * $scaleY;
        }
        $w = ((float)$field['width'] / 1.5) * 25.4 / 72 * $scaleX;
        // CSS preview sizes are pixels; regular overlay text must be converted to PDF points.
        $isR12TableField = $isR12 && preg_match('/^(semester|course_code|course_name|section|old_group|new_group)_[1-4]$/', (string)$field['field_name']);
        $isR16TableField = $isR16 && preg_match('/^(student_number|student_name|department|year_level|registration_status)_[1-6]$/', (string)$field['field_name']);
        $exportFontSize = $isR01
            ? (float)$r01Layout['font_pt']
            : ($isR12TableField
                ? (float)$r12Layout['table_font_pt']
                : ($isR12
                    ? (float)$r12Layout['font_pt']
                    : ($isR14
                        ? (float)$r14Layout['font_pt']
                        : ($isR16TableField
                        ? (float)$r16Layout['table_font_pt']
                        : ($isR16
                            ? (float)$r16Layout['font_pt']
                            : ($isR18
                                ? (in_array($fieldName, ['responsible_faculty', 'student_number'], true) ? 15.0 : 16.0)
                                : ($isR24 ? 16.0 : 11.0)))))));
        $overlayFont = ($isR01 || $isR12 || $isR14 || $isR16 || $isR18 || $isR24) ? $thaiBoldFont : $thaiFont;
        // Keep 16 pt R12 text above the dotted/table lines. The education
        // check mark is vector artwork and must retain its approved position.
        $isR16VectorMark = $isR16 && ($fieldName === 'education_level' || preg_match('/^registration_status_[1-6]$/', $fieldName));
        $overlayYOffset = match (true) {
            $isR12 && $fieldName !== 'education_level' => ((((float)$r12Layout['pdf_text_offset_y_px'] / 1.5) * 25.4 / 72) * $scaleY),
            $isR14 && !in_array($fieldName, ['education_level', 'request_type'], true) => ((((float)$r14Layout['pdf_text_offset_y_px'] / 1.5) * 25.4 / 72) * $scaleY),
            $isR16 && !$isR16VectorMark => ((((float)$r16Layout['pdf_text_offset_y_px'] / 1.5) * 25.4 / 72) * $scaleY),
            default => 0.0,
        };
        $y += $overlayYOffset;
        $r24AdditionalLiftMm = 4.0 * 25.4 / 72;
        if ($isR24 && !in_array($fieldName, ['education_level', 'guardian_relationship'], true)) {
            $y -= $r24AdditionalLiftMm;
        }
        
        $pdf->SetTextColor(30, 58, 138); // Blue ink color for official filled text

        if ($field['field_name'] === 'student_name') {
            $field['field_value'] = preg_replace('/^\s*(?:นาย|นางสาว|นาง)\s*/u', '', (string)$field['field_value']);
            $prefix = trim((string)($document['prefix_short'] ?: $document['prefix_name']));
            $prefixAliases = ['น.ส.' => 'นางสาว', 'น.ส' => 'นางสาว'];
            $prefix = $prefixAliases[$prefix] ?? $prefix;
            if ($isR16) {
                // Canonical R16 coordinates are stored in source PDF points.
                // Convert pt to the stretched A4 millimetres only at draw time.
                $r16SourceWidthPt = 612.0;
                $r16SourceHeightPt = 792.0;
                $r16RangesPt = [
                    'นาย' => [139.4, 154.0667],
                    'นาง' => [163.4, 177.4],
                    'นางสาว' => [186.0667, 213.4],
                ];
                $prefixRanges = [];
                foreach ($r16RangesPt as $prefixLabel => [$x1Pt, $x2Pt]) {
                    $prefixRanges[$prefixLabel] = [
                        ($x1Pt / $r16SourceWidthPt) * 210.0,
                        ($x2Pt / $r16SourceWidthPt) * 210.0,
                    ];
                }
            } else {
                $prefixRanges = $isR14
                    ? $r14Layout['prefix_ranges_mm']
                    : ($isR12
                    ? $r12Layout['prefix_ranges_mm']
                    : ($isR18
                        ? ['นาย'=>[36.3,41.4], 'นาง'=>[42.7,47.3], 'นางสาว'=>[48.6,58.0]]
                        : ['นาย'=>[44.3,49.7], 'นาง'=>[51.0,56.8], 'นางสาว'=>[58.0,67.7]]));
            }
            $prefixStrikeY = $isR16
                ? ((165.8667 / $r16SourceHeightPt) * 297.0)
                : match($normalizedDocumentCode){
                'R01'=>((($r01Layout['prefix_strikes']['นาย'][1] + $r01Layout['layer_offset_y']) / 1.5) * 25.4 / 72) * $scaleY,
                'R12'=>(float)$r12Layout['prefix_strike_y_mm'],
                'R14'=>(float)$r14Layout['prefix_strike_y_mm'],
                'R18'=>((((240.0 + $previewLayerOffsetY) / 1.5) * 25.4 / 72) * $scaleY) + (5.0 * 25.4 / 72),
                'R24'=>((((284.5 + $previewLayerOffsetY) / 1.5) * 25.4 / 72) * $scaleY),
                default=>$y+1.5,
            };
            $pdf->SetDrawColor(30,58,138);
            $pdf->SetLineWidth($isR16 ? ((1.3333 / $r16SourceHeightPt) * 297.0) : 0.6);
            foreach($prefixRanges as $prefixLabel=>[$strikeX1,$strikeX2]){
                if ($prefixLabel !== $prefix) {
                    $pdf->Line($strikeX1,$prefixStrikeY,$strikeX2,$prefixStrikeY);
                }
            }
        } elseif ($field['field_name'] === 'faculty') {
            $field['field_value'] = preg_replace('/^\s*คณะ\s*/u', '', (string)$field['field_value']);
        } elseif ($isR24 && $field['field_name'] === 'guardian_name') {
            if (preg_match('/^\s*(นาย|นางสาว|นาง|น\.ส\.)\s*/u', (string)$field['field_value'], $guardianPrefixMatch)) {
                $guardianPrefix = $guardianPrefixMatch[1] === 'น.ส.' ? 'นางสาว' : $guardianPrefixMatch[1];
                $field['field_value'] = preg_replace('/^\s*(?:นาย|นางสาว|นาง|น\.ส\.)\s*/u', '', (string)$field['field_value']);
                $guardianPrefixRanges = [
                    'นาย' => [123.5, 150.5],
                    'นาง' => [161.5, 186.5],
                    'นางสาว' => [196.5, 239.5],
                ];
                $guardianStrikeY = ((((650.5 + $previewLayerOffsetY) / 1.5) * 25.4 / 72) * $scaleY);
                $pdf->SetDrawColor(30, 58, 138);
                $pdf->SetLineWidth(0.6);
                foreach ($guardianPrefixRanges as $guardianLabel => [$guardianX1, $guardianX2]) {
                    if ($guardianLabel === $guardianPrefix) continue;
                    $strikeX1 = (($guardianX1 / 1.5) * 25.4 / 72) * $scaleX;
                    $strikeX2 = (($guardianX2 / 1.5) * 25.4 / 72) * $scaleX;
                    $pdf->Line($strikeX1, $guardianStrikeY, $strikeX2, $guardianStrikeY);
                }
            }
        } elseif ($isR12 && $field['field_name'] === 'recipient') {
            $field['field_value'] = preg_replace('/^\s*(?:เรียน\s*)?คณบดีคณะ\s*/u', '', (string)$field['field_value']);
        }

        if ($field['field_name'] === 'request_date') {
            $rawDate = $field['field_value'];
            $d = DateTime::createFromFormat('Y-m-d', $rawDate);
            if ($d) {
                $day = $d->format('j');
                $months_th = [
                    1 => 'มกราคม', 2 => 'กุมภาพันธ์', 3 => 'มีนาคม', 4 => 'เมษายน',
                    5 => 'พฤษภาคม', 6 => 'มิถุนายน', 7 => 'กรกฎาคม', 8 => 'สิงหาคม',
                    9 => 'กันยายน', 10 => 'ตุลาคม', 11 => 'พฤศจิกายน', 12 => 'ธันวาคม'
                ];
                $month = $months_th[(int)$d->format('n')] ?? '';
                $year = (string)((int)$d->format('Y') + 543);
                
                $pdf->SetFont($overlayFont, '', $exportFontSize);
                
                // Keep the exported PDF aligned with each reviewed on-screen preview.
                $dateDayX = $isR01 ? $r01Layout['date']['day'][0] : ($isR12 ? $r12Layout['date']['day'][0] : ($isR14 ? $r14Layout['date']['day'][0] : ($isR16 ? $r16Layout['date']['day'][0] : ($isR18 ? 490 : ($isR24 ? 485 : 570)))));
                $dateMonthX = $isR01 ? $r01Layout['date']['month'][0] : ($isR12 ? $r12Layout['date']['month'][0] : ($isR14 ? $r14Layout['date']['month'][0] : ($isR16 ? $r16Layout['date']['month'][0] : ($isR18 ? 562 : ($isR24 ? 590 : 655)))));
                $dateYearX = $isR01 ? $r01Layout['date']['year'][0] : ($isR12 ? $r12Layout['date']['year'][0] : ($isR14 ? $r14Layout['date']['year'][0] : ($isR16 ? $r16Layout['date']['year'][0] : ($isR18 ? 675 : ($isR24 ? 723 : 764)))));
                if ($useReviewedR12PreviewLayout) {
                $y = ((($r12Layout['date']['day'][1] + $previewLayerOffsetY) / 1.5) * 25.4 / 72) * $scaleY + $overlayYOffset;
                } elseif ($isR12) {
                    $y = (((180 + 13) / 1.5) * 25.4 / 72) * $scaleY + $overlayYOffset;
                }
                if ($isR18) {
                    $y = ((((132.0 + $previewLayerOffsetY) / 1.5) * 25.4 / 72) * $scaleY);
                }
                if ($isR14) {
                    $y = (((($r14Layout['date']['day'][1] + $previewLayerOffsetY) / 1.5) * 25.4 / 72) * $scaleY) + $overlayYOffset;
                }
                if ($isR24) {
                    $y = ((((188.5 + $previewLayerOffsetY) / 1.5) * 25.4 / 72) * $scaleY) - $r24AdditionalLiftMm;
                }
                $x_day = (($dateDayX / 1.5) * 25.4 / 72) * $scaleX;
                $pdf->Text($x_day, $y, $day);
                
                $x_month = (($dateMonthX / 1.5) * 25.4 / 72) * $scaleX;
                $pdf->Text($x_month, $y, $month);
                
                $x_year = (($dateYearX / 1.5) * 25.4 / 72) * $scaleX;
                $pdf->Text($x_year, $y, $year);
            } else {
                $pdf->SetFont($overlayFont, '', $exportFontSize);
                $pdf->Text($x, $y, $field['field_value']);
            }
        } 
        elseif ($field['field_name'] === 'education_level') {
            $val = $field['field_value'];
            $x_offsets = $isR14
                ? $r14Layout['education_x']
                : ($isR16
                ? ['certificate'=>224.4, 'diploma'=>316.8, 'undergraduate'=>412, 'master'=>534.7, 'doctor'=>658.0]
                : ($isR12
                ? $r12Layout['education_x']
                    : ($isR18
                        ? ['certificate'=>142.6, 'diploma'=>191.7, 'undergraduate'=>241.3, 'graduate'=>323.9, 'master'=>323.9, 'doctor'=>323.9]
                        : ($isR24
                            ? ['certificate'=>208, 'diploma'=>310.5, 'undergraduate'=>410.5, 'master'=>561.5, 'doctor'=>683]
                            : array_map(static fn($tick)=>(float)$tick[0], $r01Layout['education_ticks'])))));
            $target_x = $x_offsets[$val] ?? ($isR14 ? $r14Layout['education_x']['undergraduate'] : ($isR18 ? 241.3 : ($isR16 ? 412 : ($isR12 ? 420 : 432))));
            $x_tick = (($target_x / 1.5) * 25.4 / 72) * $scaleX;
            
            // Draw the check mark as vector lines. TH Sarabun PSK does not
            // contain the Unicode check-mark glyph and otherwise renders it
            // as an empty square in the downloaded PDF.
            $pdf->SetDrawColor(30, 58, 138);
            $pdf->SetLineWidth(0.35);
            if ($isR14) {
                $tickY = (((($r14Layout['education_y'] + $previewLayerOffsetY) / 1.5) * 25.4 / 72) * $scaleY) + 2.8;
            } elseif ($isR01 && isset($r01Layout['education_ticks'][$val])) {
                $tickTop = $r01Layout['education_ticks'][$val][1] + $r01Layout['layer_offset_y'];
                $tickY = (($tickTop / 1.5) * 25.4 / 72) * $scaleY + 1.9;
            } else {
                $tickY = $y + 2.8;
                if ($isR18) {
                    $tickY += 10.0 * 25.4 / 72;
                }
            }
            $pdf->Line($x_tick, $tickY - 1.05, $x_tick + 1.05, $tickY);
            $pdf->Line($x_tick + 1.05, $tickY, $x_tick + 3.0, $tickY - 2.55);
        } 
        elseif ($isR14 && $field['field_name'] === 'request_type') {
            $position = $r14Layout['request_type'][(string)$field['field_value']] ?? $r14Layout['request_type']['less'];
            $markX = (($position[0] / 1.5) * 25.4 / 72) * $scaleX;
            $markY = (((($position[1] + $previewLayerOffsetY) / 1.5) * 25.4 / 72) * $scaleY) + 2.8;
            $pdf->SetDrawColor(30, 58, 138);
            $pdf->SetLineWidth(0.35);
            $pdf->Line($markX, $markY - 1.05, $markX + 1.05, $markY);
            $pdf->Line($markX + 1.05, $markY, $markX + 3.0, $markY - 2.55);
        }
        elseif ($isR18 && $field['field_name'] === 'study_type') {
            $studyTypeX = ['normal' => 523.25, 'associate' => 579.25, 'special' => 640.25];
            $targetX = $studyTypeX[(string)$field['field_value']] ?? 523.25;
            $markX = (($targetX / 1.5) * 25.4 / 72) * $scaleX;
            // Keep the normal/associate/special mark 7pt below its original
            // export position after the latest requested 3pt upward move.
            $markY = ((((253.0 + $previewLayerOffsetY) / 1.5) * 25.4 / 72) * $scaleY) + 2.8 + (7.0 * 25.4 / 72);
            $pdf->SetDrawColor(30, 58, 138);
            $pdf->SetLineWidth(0.35);
            $pdf->Line($markX, $markY - 1.05, $markX + 1.05, $markY);
            $pdf->Line($markX + 1.05, $markY, $markX + 3.0, $markY - 2.55);
        }
        elseif ($isR24 && $field['field_name'] === 'leave_type') {
            $markX = ((255.0 / 1.5) * 25.4 / 72) * $scaleX;
            $markTop = (string)$field['field_value'] === 'personal' ? 421.5 : 361.5;
            $markY = ((($markTop + $previewLayerOffsetY) / 1.5) * 25.4 / 72) * $scaleY + 2.8 - $r24AdditionalLiftMm;
            $pdf->SetDrawColor(30, 58, 138);
            $pdf->SetLineWidth(0.35);
            $pdf->Line($markX, $markY - 1.05, $markX + 1.05, $markY);
            $pdf->Line($markX + 1.05, $markY, $markX + 3.0, $markY - 2.55);
        }
        elseif ($isR24 && $field['field_name'] === 'guardian_relationship') {
            $relationshipX = ['father' => 565.0, 'mother' => 622.0, 'other' => 685.0];
            $relationshipValue = ['บิดา'=>'father', 'มารดา'=>'mother', 'อื่น'=>'other', 'อื่นๆ'=>'other', 'อื่น ๆ'=>'other'][(string)$field['field_value']] ?? (string)$field['field_value'];
            $relationshipOtherText = '';
            if (str_starts_with($relationshipValue, 'other:')) {
                $relationshipOtherText = trim(substr($relationshipValue, 6));
                $relationshipValue = 'other';
            }
            $targetX = $relationshipX[$relationshipValue] ?? null;
            if ($targetX !== null) {
                $markX = (($targetX / 1.5) * 25.4 / 72) * $scaleX;
                $markY = ((((637.0 + $previewLayerOffsetY) / 1.5) * 25.4 / 72) * $scaleY) + 2.8;
                $pdf->SetDrawColor(30, 58, 138);
                $pdf->SetLineWidth(0.35);
                $pdf->Line($markX, $markY - 1.05, $markX + 1.05, $markY);
                $pdf->Line($markX + 1.05, $markY, $markX + 3.0, $markY - 2.55);
                if ($relationshipValue === 'other' && $relationshipOtherText !== '') {
                    $otherX = ((750.0 / 1.5) * 25.4 / 72) * $scaleX;
                    $otherY = ((((634.0 + $previewLayerOffsetY) / 1.5) * 25.4 / 72) * $scaleY);
                    $pdf->SetFont($overlayFont, '', $exportFontSize);
                    $pdf->Text($otherX, $otherY, $relationshipOtherText);
                }
            }
        }
        elseif ($isR24 && in_array((string)$field['field_name'], ['leave_from', 'leave_to'], true)) {
            $leaveDate = DateTime::createFromFormat('Y-m-d', (string)$field['field_value']);
            $leaveDateText = $leaveDate
                ? $leaveDate->format('d/m/') . ((int)$leaveDate->format('Y') + 543)
                : (string)$field['field_value'];
            $pdf->SetFont($overlayFont, '', $exportFontSize);
            $pdf->Text($x, $y, $leaveDateText);
        }
        elseif ($isR16 && preg_match('/^registration_status_[1-6]$/', (string)$field['field_name'])) {
            $statusX = ['normal' => 654, 'repeat' => 726, 'regrade' => 803];
            $targetX = $statusX[(string)$field['field_value']] ?? null;
            if ($targetX !== null) {
                $markX = (($targetX / 1.5) * 25.4 / 72) * $scaleX;
                $markY = $y + 2.8;
                $pdf->SetDrawColor(30, 58, 138);
                $pdf->SetLineWidth(0.35);
                $pdf->Line($markX, $markY - 1.05, $markX + 1.05, $markY);
                $pdf->Line($markX + 1.05, $markY, $markX + 3.0, $markY - 2.55);
            }
        }
        elseif ($field['field_name'] === 'request_detail') {
            $pdf->SetFont($overlayFont, '', $exportFontSize);
            if ($isR01) {
                $limits = array_column($r01Layout['request_lines'], 4);
                $requestLines = r01SplitTextByLimits((string)($field['field_value'] ?? ''), $limits);
                foreach ($r01Layout['request_lines'] as $lineIndex => $lineLayout) {
                    if (($requestLines[$lineIndex] ?? '') === '') continue;
                    $lineX = (($lineLayout[0] / 1.5) * 25.4 / 72) * $scaleX;
                    $lineY = ((($lineLayout[1] + $r01Layout['layer_offset_y'] + $r01Layout['pdf_text_offset_y_px']) / 1.5) * 25.4 / 72) * $scaleY;
                    $pdf->Text($lineX, $lineY, $requestLines[$lineIndex]);
                }
            } else {
                $x_rest = ((85 / 1.5) * 25.4 / 72) * $scaleX;
                $w_rest = ((740 / 1.5) * 25.4 / 72) * $scaleX;
                [$r1, $r2, $r3, $r4] = splitTextToWidths($pdf, (string)($field['field_value'] ?? ''), [$w, $w_rest, $w_rest, $w_rest]);
                foreach ([$r1,$r2,$r3,$r4] as $lineIndex=>$lineText) {
                    if ($lineText === '') continue;
                    $lineX = $lineIndex === 0 ? $x : $x_rest;
                    $lineY = $lineIndex === 0 ? $y : ((((float)$field['pos_y'] + (27 * $lineIndex) + (float)$field['font_size']) / 1.5) * 25.4 / 72 * $scaleY + $overlayYOffset);
                    $pdf->Text($lineX, $lineY, $lineText);
                }
            }
        } 
        elseif (in_array($field['field_name'], ['signature_name', 'signer_name', 'student_signature_name'], true)) {
            if ($isR14) {
                $signaturePrefix = trim((string)($document['prefix_short'] ?: $document['prefix_name']));
                $signaturePrefix = ['น.ส.' => 'นางสาว', 'น.ส' => 'นางสาว'][$signaturePrefix] ?? $signaturePrefix;
                $signatureName = preg_replace('/^\s*(?:นาย|นางสาว|นาง|น\.ส\.)\s*/u', '', trim((string)$field['field_value']));
                $signatureText = $signaturePrefix . $signatureName;
                $signatureFontSize = (float)$r14Layout['signature_font_pt'];
                $signaturePadding = (float)$r14Layout['signature_padding_mm'];

                [$signatureInkXPx, $signatureInkYPx, $signatureInkWPx] = $r14Layout['signature_pdf_ink'];
                $signatureInkX = (($signatureInkXPx / 1.5) * 25.4 / 72) * $scaleX;
                $signatureInkY = ((($signatureInkYPx + $previewLayerOffsetY) / 1.5) * 25.4 / 72) * $scaleY + $overlayYOffset;
                $signatureInkWidth = (($signatureInkWPx / 1.5) * 25.4 / 72) * $scaleX;
                $usableSignatureInkWidth = max(1.0, $signatureInkWidth - $signaturePadding);
                $signatureInkFontSize = (float)$r14Layout['signature_font_pt'];
                $pdf->SetFont($thaiBoldItalicFont, '', $signatureInkFontSize);
                $signatureInkTextWidth = $pdf->GetStringWidth($signatureName);
                $pdf->SetFontStretching($signatureInkTextWidth > $usableSignatureInkWidth
                    ? max(1.0, ($usableSignatureInkWidth / $signatureInkTextWidth) * 100)
                    : 100.0);
                $pdf->SetXY($signatureInkX + ($signaturePadding / 2), $signatureInkY);
                $pdf->Cell($usableSignatureInkWidth, 4, $signatureName, 0, 0, 'C', false, '', 1, false, 'T', 'M');
                $pdf->SetFontStretching(100.0);

                $usableSignatureWidth = max(1.0, $w - $signaturePadding);
                $pdf->SetFont($thaiBoldFont, '', $signatureFontSize);
                $signatureTextWidth = $pdf->GetStringWidth($signatureText);
                $pdf->SetFontStretching($signatureTextWidth > $usableSignatureWidth
                    ? max(1.0, ($usableSignatureWidth / $signatureTextWidth) * 100)
                    : 100.0);
                $pdf->SetXY($x + ($signaturePadding / 2), $y);
                $pdf->Cell($usableSignatureWidth, 4, $signatureText, 0, 0, 'C', false, '', 1, false, 'T', 'M');
                $pdf->SetFontStretching(100.0);
                continue;
            }
            // Print signature (ink) - slightly italicized blue ink
            $pdf->SetFont(($isR01 || $isR12 || $isR16 || $isR18 || $isR24) ? $thaiBoldItalicFont : $thaiItalicFont, '', $exportFontSize);
            // Center the upper signature on the same horizontal frame as the
            // parenthesized printed name below it.
            $signatureInkName = ($isR16 || $isR18 || $isR24)
                ? preg_replace('/^\s*(?:นาย|นางสาว|นาง|น\.ส\.)\s*/u', '', trim((string)$field['field_value']))
                : (string)$field['field_value'];
            $pdf->SetXY($x, $y);
            $pdf->Cell($w, 4, $signatureInkName, 0, 0, 'C', false, '', 1, false, 'T', 'M');
            
            // Print the name in parentheses with prefix, smaller and on one line.
            $signaturePrefix = trim((string)($document['prefix_short'] ?: $document['prefix_name']));
            $signatureName = preg_replace('/^\s*(?:นาย|นางสาว|นาง|น\.ส\.)\s*/u', '', trim((string)$field['field_value']));
            $printedName = $signaturePrefix . $signatureName;
            $printedNameFontSize = $isR01 ? (float)$r01Layout['printed_name_font_pt'] : ($isR12 ? (float)$r12Layout['font_pt'] : ($isR16 ? (float)$r16Layout['font_pt'] : (($isR18 || $isR24) ? 16.0 : min(11, $exportFontSize))));
            $pdf->SetFont(($isR12 || $isR16 || $isR18 || $isR24) ? $thaiBoldFont : $thaiFont, '', $printedNameFontSize);
            $printedTop = $isR01 ? $r01Layout['signature']['print'][1] : ($isR12 ? $r12Layout['signature']['print'][1] : ($isR16 ? $r16Layout['signature_print'][1] : ($isR18 ? 586 : ($isR24 ? 559.5 : (float)$field['pos_y'] + 38))));
            $printedYPixel = ($useReviewedR01PreviewLayout || $useReviewedR12PreviewLayout || $useReviewedR16PreviewLayout || $useReviewedR18PreviewLayout || $useReviewedR24PreviewLayout)
                ? $printedTop + $previewLayerOffsetY
                : $printedTop + (float)$field['font_size'];
            $y_print = ($printedYPixel / 1.5) * 25.4 / 72 * $scaleY + $overlayYOffset;
            if ($isR24) {
                $y_print -= $r24AdditionalLiftMm;
            }
            if ($isR01) {
                $y_print += (((float)$r01Layout['pdf_printed_name_offset_y_px'] / 1.5) * 25.4 / 72) * $scaleY;
                $printX = (($r01Layout['signature']['print'][0] / 1.5) * 25.4 / 72) * $scaleX;
                $printW = (($r01Layout['signature']['print'][2] / 1.5) * 25.4 / 72) * $scaleX;
                $pdf->SetXY($printX, $y_print);
                $pdf->Cell($printW, 4, $printedName, 0, 0, 'C', false, '', 1, false, 'T', 'M');
            } elseif ($isR16) {
                $printX = (($r16Layout['signature_print'][0] / 1.5) * 25.4 / 72) * $scaleX;
                $printW = (($r16Layout['signature_print'][2] / 1.5) * 25.4 / 72) * $scaleX;
                $fittedSignatureFontSize = (float)$r16Layout['font_pt'];
                $minimumSignatureFontSize = (float)$r16Layout['signature_min_font_pt'];
                $pdf->SetFont($thaiBoldFont, '', $fittedSignatureFontSize);
                while ($pdf->GetStringWidth($printedName) > ($printW - 1.5) && $fittedSignatureFontSize > $minimumSignatureFontSize) {
                    $fittedSignatureFontSize -= 0.25;
                    $pdf->SetFont($thaiBoldFont, '', $fittedSignatureFontSize);
                }
                $pdf->SetXY($printX, $y_print);
                $pdf->Cell($printW, 4, $printedName, 0, 0, 'C', false, '', 0, false, 'T', 'M');
            } else {
                $pdf->SetXY($x, ($useReviewedR12PreviewLayout || $useReviewedR16PreviewLayout || $useReviewedR18PreviewLayout || $useReviewedR24PreviewLayout) ? $y_print : $y_print - 3);
                $pdf->Cell($w, 4, $printedName, 0, 0, 'C', false, '', 1, false, 'T', 'M');
            }
        } 
        elseif ($isR12TableField) {
            // Match the R12 preview: keep every value inside its table cell,
            // center it, and shrink long values instead of crossing borders.
            $pdf->SetFont($overlayFont, '', $exportFontSize);
            $pdf->SetXY($x, $y);
            $pdf->Cell($w, 4, (string)$field['field_value'], 0, 0, 'C', false, '', 1, false, 'T', 'M');
        }
        elseif ($isR16 && preg_match('/^(student_number|student_name|department|year_level)_[1-6]$/', (string)$field['field_name'])) {
            // Keep names and student data inside their fixed table cells so a
            // different student's text length never appears to move the form.
            $pdf->SetFont($overlayFont, '', $exportFontSize);
            $pdf->SetXY($x, $y);
            $pdf->Cell($w, 4, (string)$field['field_value'], 0, 0, 'L', false, '', 1, false, 'T', 'M');
        }
        elseif ($isR12 && in_array((string)$field['field_name'], ['recipient', 'student_name', 'student_number', 'faculty', 'curriculum', 'telephone'], true)) {
            // Scalar R12 fields have fixed dotted-line areas in the template.
            // TCPDF::Text ignores field width, so use a stretching Cell to
            // prevent Faculty/Curriculum/Telephone from overlapping.
            $pdf->SetFont($overlayFont, '', $exportFontSize);
            $pdf->SetXY($x, $y);
            $pdf->Cell($w, 4, (string)$field['field_value'], 0, 0, 'L', false, '', 1, false, 'T', 'M');
        }
        elseif ($isR14 && in_array((string)$field['field_name'], ['recipient', 'student_name', 'student_number', 'telephone', 'faculty', 'curriculum', 'cumulative_gpa', 'semester', 'academic_year', 'enrolled_credits'], true)) {
            $pdf->SetFont($overlayFont, '', $exportFontSize);
            $pdf->SetXY($x, $y);
            $pdf->Cell($w, 4, (string)$field['field_value'], 0, 0, 'L', false, '', 1, false, 'T', 'M');
        }
        else {
            $pdf->SetFont($overlayFont, '', $exportFontSize);
            if ($field['field_type'] === 'textarea') {
                $textareaY = ($useReviewedR18PreviewLayout || $useReviewedR24PreviewLayout)
                    ? $y
                    : $y - ((float)$field['font_size'] / 1.5) * 25.4 / 72 * $scaleY;
                $pdf->SetXY($x, $textareaY);
                $pdf->MultiCell($w, 0, $field['field_value'], 0, 'L', false, 1);
            } else {
                $pdf->Text($x, $y, $field['field_value']);
            }
        }
    }
}

$downloadNames = [
    'R01' => 'R.01-คำร้องทั่วไป',
    'R12' => 'R.12-คำร้องขอแก้ไขกลุ่มวิชาตามโครงสร้างหลักสูตร',
    'R14' => 'R.14-คำร้องขอลงทะเบียนเรียนต่ำกว่า-เกินกว่าหน่วยกิตที่กำหนด',
    'R18' => 'R.18-คำร้องขอเปิดรายวิชาเรียน',
    'R24' => 'R.24-คำร้องขอลาป่วย-ลากิจ',
];
$documentCode = $normalizedDocumentCode;
$downloadBaseName = $downloadNames[$documentCode] ?? ((string)$document['document_code'] . '-' . $documentId);
$downloadBaseName = preg_replace('~[\\\\/:*?"<>|\x00-\x1F]~u', '-', $downloadBaseName);
$downloadBaseName = trim((string)$downloadBaseName, " .-");
$pdf->Output($downloadBaseName . '.pdf', 'D');
