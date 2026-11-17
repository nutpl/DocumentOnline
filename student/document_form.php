<?php
session_start();
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
require_once '../config/database.php';
require_once '../helpers/document_autofill_helper.php';
require_once '../helpers/document_role_name_helper.php';
require_once '../helpers/r01_layout_helper.php';
require_once '../helpers/r12_layout_helper.php';
require_once '../helpers/r14_layout_helper.php';
require_once '../helpers/r16_layout_helper.php';
require_once '../helpers/csrf_helper.php';
require_once '../helpers/alert_response_helper.php';
require_once '../helpers/theme_helper.php';

if (($_SESSION['role'] ?? '') !== 'student') {
    header('Location: ../auth/login.php');
    exit;
}

$documentFormError = (string)($_SESSION['document_form_error'] ?? '');
unset($_SESSION['document_form_error']);

$documentId = (int)($_GET['document'] ?? 0);
$backPage = (string)($_GET['from'] ?? '') === 'history' ? 'history.php' : 'documents.php';

// Get document details
$stmt = $pdo->prepare('
    SELECT 
        d.document_id,
        d.template_id,
        d.document_status,
        dt.document_code,
        dt.document_name,
        t.pdf_template 
    FROM documents d 
    JOIN student s ON s.student_id = d.student_id 
    JOIN document_template t ON t.template_id = d.template_id 
    JOIN document_type dt ON dt.document_type_id = t.document_type_id 
    WHERE d.document_id = ? AND s.user_id = ?
');
$stmt->execute([$documentId, $_SESSION['user_id']]);
$document = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$document) {
    renderSweetAlertResponse('ไม่พบเอกสาร', 'ไม่พบเอกสารที่ต้องการ หรือคุณไม่มีสิทธิ์เปิดเอกสารนี้', 'error', $backPage, 404);
}

$canEditDocument = in_array((string)$document['document_status'], ['draft', 'revision_requested'], true);
if (!$canEditDocument) {
    if ((string)$document['document_status'] === 'approved') {
        header('Location: download_document.php?document=' . $documentId);
    } else {
        header('Location: document_status.php?document=' . $documentId);
    }
    exit;
}
$revisionComment='';
if($document['document_status']==='revision_requested'){
    $revisionStmt=$pdo->prepare('SELECT detail FROM document_events WHERE document_id=? AND event_type="revision_requested" ORDER BY created_at DESC,event_id DESC LIMIT 1');
    $revisionStmt->execute([$documentId]);
    $revisionComment=trim((string)$revisionStmt->fetchColumn());
}
$attachmentStmt=$pdo->prepare('SELECT attachment_id,original_name,mime_type,file_size FROM document_attachments WHERE document_id=? ORDER BY uploaded_at');
$attachmentStmt->execute([$documentId]);
$documentAttachments=$attachmentStmt->fetchAll(PDO::FETCH_ASSOC);

// Get student's detailed profile for auto-filling
$stmt = $pdo->prepare('
    SELECT 
        s.student_id,
        s.student_number,
        s.firstname,
        s.lastname,
        s.prefix_id,
        pr.prefix_name,
        pr.prefix_short,
        g.group_name,
        g.department_id,
        d.department_name,
        f.faculty_name
    FROM student s
    LEFT JOIN prefixes pr ON pr.prefix_id = s.prefix_id
    LEFT JOIN group_class g ON g.group_id = s.group_id
    LEFT JOIN department d ON d.department_id = g.department_id
    LEFT JOIN faculty f ON f.faculty_id = d.faculty_id
    WHERE s.user_id = ?
    LIMIT 1
');
$stmt->execute([$_SESSION['user_id']]);
$studentFullProfile = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
$prefixesStmt = $pdo->query("SELECT prefix_id, prefix_name, prefix_short FROM prefixes WHERE prefix_name IN ('นาย','นาง','นางสาว') OR prefix_short IN ('นาย','นาง','นางสาว','น.ส.','น.ส') ORDER BY prefix_id");
$studentPrefixes = $prefixesStmt->fetchAll(PDO::FETCH_ASSOC);

// Get template fields and values
$stmt = $pdo->prepare('
    SELECT 
        f.*, 
        v.field_value 
    FROM template_fields f 
    LEFT JOIN document_field_values v ON v.field_id = f.field_id AND v.document_id = ? 
    WHERE f.template_id = ? 
    ORDER BY f.field_id
');
$stmt->execute([$documentId, $document['template_id']]);
$fields = $stmt->fetchAll(PDO::FETCH_ASSOC);

$studentProfile = getDocumentStudentProfile($pdo, (int)$_SESSION['user_id']);
$accessDate = $_SESSION['login_date'] ?? date('Y-m-d');
$teacherStmt = $pdo->query("SELECT e.employee_id,e.firstname,e.lastname,e.department_id,d.department_name
    FROM employee e LEFT JOIN department d ON d.department_id=e.department_id
    WHERE e.employee_type='teacher'
    ORDER BY (e.department_id IS NULL),d.department_name,e.firstname,e.lastname");
$teachersForDocument = $teacherStmt->fetchAll(PDO::FETCH_ASSOC);
$studentDepartmentId = (int)($studentFullProfile['department_id'] ?? 0);
$teachersSameDepartment = array_values(array_filter($teachersForDocument,static fn($teacher)=>(int)$teacher['department_id']===$studentDepartmentId));
$teachersOtherDepartment = array_values(array_filter($teachersForDocument,static fn($teacher)=>(int)$teacher['department_id']!==$studentDepartmentId));
$departmentStmt = $pdo->query('SELECT department_id, department_name FROM department ORDER BY department_name');
$departmentsForDocument = $departmentStmt->fetchAll(PDO::FETCH_ASSOC);

// Map field values and metadata
$fieldValues = [];
$fieldIds = [];
$fieldLabels = [];
$fieldRequired = [];
$fieldType = [];
$normalizedDocumentCode = str_replace('.', '', strtoupper((string)$document['document_code']));

foreach ($fields as $f) {
    $fieldName = $f['field_name'];
    $automaticValue = getAutomaticDocumentFieldValue($f, $studentProfile, $accessDate);

    // R16 starts with one student only. Labels such as "ชื่อ-นามสกุล" and
    // "รหัสนักศึกษา" normally trigger autofill, so explicitly suppress it
    // for the optional table rows until the user expands and fills them.
    if ($normalizedDocumentCode === 'R16'
        && preg_match('/^(student_number|student_name|department|year_level|registration_status)_([2-6])$/', $fieldName)) {
        $automaticValue = null;
    }
    // Guardian details in R24 belong to the student's parent/guardian and
    // must never inherit the student's name from label-based autofill.
    if ($normalizedDocumentCode === 'R24'
        && in_array($fieldName, ['guardian_name', 'guardian_relationship', 'guardian_signature_name'], true)) {
        $automaticValue = null;
    }
    
    // Auto fill defaults if value is empty
    if ($fieldName === 'student_name') {
        $automaticValue = trim(($studentFullProfile['prefix_short'] ?? $studentFullProfile['prefix_name'] ?? '') . ' ' . ($studentFullProfile['firstname'] ?? '') . ' ' . ($studentFullProfile['lastname'] ?? ''));
    } elseif ($fieldName === 'student_number') {
        $automaticValue = $studentFullProfile['student_number'] ?? '';
    } elseif ($fieldName === 'faculty') {
        $automaticValue = preg_replace('/^\s*คณะ\s*/u', '', (string)($studentFullProfile['faculty_name'] ?? ''));
    } elseif ($fieldName === 'curriculum') {
        $automaticValue = $studentFullProfile['department_name'] ?? '';
    } elseif ($normalizedDocumentCode === 'R24' && $fieldName === 'signature_name') {
        $automaticValue = trim((string)($studentFullProfile['firstname'] ?? '') . ' ' . (string)($studentFullProfile['lastname'] ?? ''));
    }
    
    $val = $f['field_value'] !== null ? $f['field_value'] : ($automaticValue !== null ? $automaticValue : ($f['default_value'] ?? ''));
    if ($normalizedDocumentCode === 'R24' && $fieldName === 'guardian_name') {
        $legacyStudentNames = array_filter([
            trim((string)($studentFullProfile['firstname'] ?? '') . ' ' . (string)($studentFullProfile['lastname'] ?? '')),
            trim((string)($studentFullProfile['prefix_short'] ?? '') . ' ' . (string)($studentFullProfile['firstname'] ?? '') . ' ' . (string)($studentFullProfile['lastname'] ?? '')),
            trim((string)($studentFullProfile['prefix_name'] ?? '') . ' ' . (string)($studentFullProfile['firstname'] ?? '') . ' ' . (string)($studentFullProfile['lastname'] ?? '')),
        ]);
        if (in_array(trim((string)$val), $legacyStudentNames, true)) {
            $val = '';
        }
    }
    if ($fieldName === 'student_name') {
        $val = preg_replace('/^\s*(?:นาย|นางสาว|นาง)\s*/u', '', (string)$val);
    } elseif ($fieldName === 'faculty') {
        $val = preg_replace('/^\s*คณะ\s*/u', '', (string)$val);
    } elseif ($normalizedDocumentCode === 'R12' && $fieldName === 'recipient') {
        $val = preg_replace('/^\s*(?:เรียน\s*)?คณบดีคณะ\s*/u', '', (string)$val);
    }
    
    $fieldValues[$fieldName] = $val;
    $fieldIds[$fieldName] = $f['field_id'];
    $fieldLabels[$fieldName] = $f['field_label'];
    $fieldRequired[$fieldName] = $f['required_field'];
    $fieldType[$fieldName] = $f['field_type'];
}

// Older R.24 drafts could retain a name that was accidentally copied into
// the guardian field. A guardian section is considered intentionally filled
// only when there is supporting guardian information as well.
if ($normalizedDocumentCode === 'R24'
    && trim((string)($fieldValues['guardian_relationship'] ?? '')) === ''
    && trim((string)($fieldValues['guardian_signature_name'] ?? '')) === '') {
    $fieldValues['guardian_name'] = '';
}

$automaticRoleNames = getDocumentRolePrintNames($pdo, $documentId);
foreach ($automaticRoleNames as $roleFieldName => $rolePrintName) {
    if (array_key_exists($roleFieldName, $fieldValues)) {
        $fieldValues[$roleFieldName] = $rolePrintName;
    }
}
$automaticRoleFieldNames = [
    'advisor_print_name', 'instructor_print_name', 'department_head_print_name', 'dean_print_name',
    'advisor_print_name_p2', 'student_department_head_print_name_p2',
    'responsible_department_head_print_name_p2', 'student_dean_print_name_p2',
    'instructor_print_name_p2', 'responsible_dean_print_name_p2',
];

// Treat document codes with and without dots as the same code throughout the system.
$isCustomR01 = ($normalizedDocumentCode === 'R01');
$r01Layout = $isCustomR01 ? r01LayoutConfig() : [];
$isCustomR12 = ($normalizedDocumentCode === 'R12');
$r12Layout = $isCustomR12 ? r12LayoutConfig() : [];
$isCustomR14 = ($normalizedDocumentCode === 'R14');
$r14Layout = $isCustomR14 ? r14LayoutConfig() : [];
$isCustomR16 = ($normalizedDocumentCode === 'R16');
$r16Layout = $isCustomR16 ? r16LayoutConfig() : [];
$isCustomR18 = ($normalizedDocumentCode === 'R18');
$isCustomR24 = ($normalizedDocumentCode === 'R24');
$displayDocumentCode = preg_replace('/^R(\d+)$/i', 'R.$1', (string)$document['document_code']);

// The approval panel on R24 is completed outside the student submission.
// Keep all system-generated names in that panel blank.
if ($isCustomR24) {
    if (array_key_exists('signature_name', $fieldValues)) {
        $fieldValues['signature_name'] = trim((string)($studentFullProfile['firstname'] ?? '') . ' ' . (string)($studentFullProfile['lastname'] ?? ''));
    }
    foreach (['advisor_print_name', 'department_head_print_name', 'instructor_print_name'] as $r24ApprovalField) {
        if (array_key_exists($r24ApprovalField, $fieldValues)) {
            $fieldValues[$r24ApprovalField] = '';
        }
    }
}

// R18 is a faculty-specific form; these faculty lines must always use the
// official faculty name and should not depend on a student's saved profile.
if ($isCustomR18) {
    foreach (['recipient_faculty', 'responsible_faculty'] as $r18FacultyField) {
        if (array_key_exists($r18FacultyField, $fieldValues)) {
            $fieldValues[$r18FacultyField] = 'วิศวกรรมศาสตร์และเทคโนโลยี';
        }
    }
    $r18TheoryCredits = is_numeric($fieldValues['theory_credits'] ?? null) ? (float)$fieldValues['theory_credits'] : 0.0;
    $r18PracticalCredits = is_numeric($fieldValues['practical_credits'] ?? null) ? (float)$fieldValues['practical_credits'] : 0.0;
    $fieldValues['total_credits'] = rtrim(rtrim(number_format($r18TheoryCredits + $r18PracticalCredits, 2, '.', ''), '0'), '.');
}

// Parse request_date to Day, Month (Thai), Year
$rawDate = $fieldValues['request_date'] ?? $accessDate;
$day = '';
$month = '';
$year = '';
if ($rawDate) {
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
    } else {
        $parts = explode('-', $rawDate);
        if (count($parts) === 3) {
            $day = $parts[2];
            $month = $parts[1];
            $year = $parts[0];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <link rel="icon" type="image/png" href="/Project/assets/images/fet-logo-transparent.png?v=20260815-3">

  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>กรอกคำร้อง <?= htmlspecialchars($document['document_code']) ?> | คณะวิศวกรรมศาสตร์และเทคโนโลยี มทร.อีสาน</title>

  <!-- Google Fonts: Prompt & Sarabun -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&family=Sarabun:ital,wght@0,300;0,400;0,500;0,600;0,700;0,800;1,400&display=swap" rel="stylesheet">

  <!-- FontAwesome Icons -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

  <!-- Tailwind CSS -->
  <script src="https://cdn.tailwindcss.com"></script>
  <?php renderThemeAssets(); ?>
  <script>
    tailwind.config = {
      theme: {
        extend: {
          fontFamily: {
            sans: ['TH Sarabun PSK', 'Tahoma', 'sans-serif'],
            print: ['TH Sarabun PSK', 'Tahoma', 'sans-serif'],
          },
          colors: {
            brand: {
              red: '#b91c1c',
              redHover: '#991b1b',
              dark: '#09090b',
              sidebar: '#18181b',
            }
          }
        }
      }
    }
  </script>

  <style>
    /* Enlarge entered R-document text without changing check-mark sizing. */
    .r-document-fields .val-line { font-size: 14px !important; }
    .r-document-fields #p-sig-name-print,
    .r-document-fields #r12-signature-print { font-size: 12px !important; }
    <?php if ($isCustomR01): ?>
    .r01-document-fields .val-line {
      font-size: <?= (float)$r01Layout['font_px'] ?>px !important;
      line-height: 1 !important;
      transform: translateY(<?= (float)$r01Layout['pdf_text_offset_y_px'] ?>px);
    }
    .r01-document-fields .r01-date-value {
      transform: translateY(<?= (float)$r01Layout['pdf_date_offset_y_px'] ?>px);
    }
    .r01-document-fields .r01-field-faculty,
    .r01-document-fields .r01-field-curriculum {
      transform: translateY(<?= (float)$r01Layout['pdf_faculty_curriculum_offset_y_px'] ?>px);
    }
    .r01-document-fields #p-sig-name-print {
      font-size: <?= (float)$r01Layout['printed_name_font_px'] ?>px !important;
      transform: translateY(<?= (float)$r01Layout['pdf_printed_name_offset_y_px'] ?>px);
    }
    <?php endif; ?>
    .r12-document-fields .r12-main-value,
    .r12-document-fields .r12-date-value,
    .r12-document-fields .r12-signature-ink,
    .r12-document-fields #r12-signature-print { font-size: <?= $isCustomR12 ? (float)$r12Layout['font_px'] : 16 ?>px !important; line-height: 1 !important; font-weight: <?= $isCustomR12 ? (int)$r12Layout['font_weight'] : 700 ?> !important; transform: translateY(<?= $isCustomR12 ? (float)$r12Layout['preview_text_offset_y_px'] : 0 ?>px); }
    .r12-document-fields .r12-table-value { font-size: <?= $isCustomR12 ? (float)$r12Layout['table_font_px'] : 14 ?>px !important; line-height: 1 !important; font-weight: <?= $isCustomR12 ? (int)$r12Layout['font_weight'] : 700 ?> !important; transform: translateY(<?= $isCustomR12 ? (float)$r12Layout['preview_table_offset_y_px'] : -7 ?>px); }
    .r12-document-fields .r12-signature-ink { font-style: italic; }
    .r16-document-fields .r16-main-value,
    .r16-document-fields .r16-date-value,
    .r16-document-fields .r16-signature-ink,
    .r16-document-fields #r16-signature-print { font-size: <?= $isCustomR16 ? (float)$r16Layout['font_px'] : 21.333 ?>px !important; line-height: 1 !important; font-weight: <?= $isCustomR16 ? (int)$r16Layout['font_weight'] : 700 ?> !important; transform: translateY(<?= $isCustomR16 ? (float)$r16Layout['preview_text_offset_y_px'] : 0 ?>px); }
    .r16-document-fields .r16-table-value { overflow: hidden; white-space: nowrap; text-overflow: ellipsis; font-size: <?= $isCustomR16 ? (float)$r16Layout['table_font_px'] : 21.333 ?>px !important; line-height: 1 !important; font-weight: <?= $isCustomR16 ? (int)$r16Layout['font_weight'] : 700 ?> !important; transform: translateY(<?= $isCustomR16 ? (float)$r16Layout['preview_table_offset_y_px'] : -7 ?>px); }
    .r16-document-fields .r16-signature-ink { font-style: italic; }
    .r16-document-fields #r16-signature-print { overflow: hidden; white-space: nowrap; box-sizing: border-box; padding-inline: 6px; }
    .r16-document-fields .r16-mark { font-size: 15px !important; line-height: 1 !important; }
    .r14-document-fields [id^="p-field-"],
    .r14-document-fields #r14-date-day,
    .r14-document-fields #r14-date-month,
    .r14-document-fields #r14-date-year {
      overflow: hidden;
      white-space: nowrap;
      text-overflow: clip;
      font-size: <?= $isCustomR14 ? (float)$r14Layout['font_px'] : 21.333 ?>px !important;
      line-height: 1 !important;
    }
    .r14-document-fields .r14-signature-ink,
    .r14-document-fields .r14-signature-print {
      font-size: <?= $isCustomR14 ? (float)$r14Layout['signature_font_px'] : 16 ?>px !important;
      font-weight: 700 !important;
      line-height: 1 !important;
      box-sizing: border-box;
      padding-inline: <?= $isCustomR14 ? (float)$r14Layout['signature_padding_px'] : 8 ?>px;
      overflow: hidden;
      white-space: nowrap;
      text-overflow: clip;
    }
    .r18-document-fields .val-line {
      font-size: 21.333px !important;
      line-height: 1 !important;
      font-weight: 700 !important;
    }
    .r24-document-fields .val-line {
      font-size: 21.333px !important;
      line-height: 1 !important;
      font-weight: 700 !important;
    }
    .r16-form-fields { gap: .65rem !important; }
    #document-form .r16-field-card { padding: .7rem .8rem; }
    #document-form .r16-field-card input,
    #document-form .r16-field-card select { padding: .58rem .72rem !important; }
    .r16-student-group > summary::-webkit-details-marker { display: none; }
    @font-face {
      font-family: 'TH Sarabun PSK';
      src: url('../assets/fonts/thsarabunpsk/THSarabun.ttf') format('truetype');
      font-weight: 400;
      font-style: normal;
      font-display: swap;
    }
    @font-face {
      font-family: 'TH Sarabun PSK';
      src: url('../assets/fonts/thsarabunpsk/THSarabun-Bold.ttf') format('truetype');
      font-weight: 700;
      font-style: normal;
      font-display: swap;
    }
    /* Dotted line input values style */
    .val-line {
      font-family: 'TH Sarabun PSK', sans-serif;
      font-weight: 700;
      color: #1e3a8a; /* Deep blue color for filled text to resemble ink */
      white-space: normal;
      word-break: break-word;
      overflow-wrap: anywhere;
    }

    #document-form > div:not(:last-child) {
      padding: 1.25rem;
      border: 1px solid #e5e7eb;
      border-radius: 1rem;
      background: #ffffff;
      box-shadow: 0 1px 3px rgba(15, 23, 42, .06);
      transition: border-color .2s ease, box-shadow .2s ease, transform .2s ease;
    }

    #document-form > div:not(:last-child):focus-within {
      border-color: #ef4444;
      box-shadow: 0 0 0 3px rgba(239, 68, 68, .1), 0 8px 18px rgba(15, 23, 42, .08);
      transform: translateY(-1px);
    }

    #document-form > .space-y-4 > div {
      padding: 1.25rem;
      border: 1px solid #e5e7eb;
      border-radius: 1rem;
      background: #ffffff;
    }

    #document-form label {
      display: flex;
      align-items: center;
      gap: .4rem;
      margin-bottom: .45rem;
      color: #1e293b;
      font-size: 1rem;
      font-weight: 600;
      line-height: 1.35;
    }

    #document-form label::before {
      content: '';
      width: .35rem;
      height: .35rem;
      border-radius: 999px;
      background: #ef4444;
      box-shadow: 0 0 0 3px rgba(239, 68, 68, .12);
    }

    #document-form input:not([type="hidden"]),
    #document-form select,
    #document-form textarea {
      border-color: #d1d5db !important;
      background-color: #ffffff !important;
      border-radius: .75rem !important;
      padding: .7rem .85rem !important;
      color: #111827 !important;
      font-size: 1.35rem;
      line-height: 1.45;
      transition: border-color .2s ease, box-shadow .2s ease, background-color .2s ease;
    }

    /* Form utilities used by older document templates must keep readable contrast. */
    #document-form .text-zinc-400,
    #document-form .text-slate-400,
    #document-form .text-slate-500,
    #document-form .text-gray-400,
    #document-form .text-gray-500 { color: #475569 !important; }

    #document-form .text-slate-700,
    #document-form .text-slate-800,
    #document-form .text-slate-900 { color: #172033 !important; }

    #document-form input:disabled,
    #document-form textarea:disabled,
    #document-form select:disabled {
      color: #334155 !important;
      background-color: #f1f5f9 !important;
      border-color: #cbd5e1 !important;
      opacity: 1;
    }

    #document-form input:not([type="hidden"]):focus,
    #document-form select:focus,
    #document-form textarea:focus {
      border-color: #ef4444 !important;
      box-shadow: 0 0 0 3px rgba(239, 68, 68, .12);
      background-color: #ffffff !important;
    }

    #document-form textarea {
      min-height: 7rem;
      line-height: 1.5;
      resize: vertical;
    }

    #document-form input[type="file"]::file-selector-button {
      margin-right: .75rem;
      border: 0;
      border-radius: .55rem;
      background: #fee2e2;
      padding: .5rem .7rem;
      color: #b91c1c;
      font: inherit;
      font-weight: 700;
      cursor: pointer;
    }

    /* Keep the working panel comfortably readable without changing the
       scaled A4 document preview. */
    body > header .text-sm { font-size: 1.2rem !important; }
    body > header .text-xs { font-size: 1.05rem !important; }
    .paper-height-panel > div:first-child h2 { font-size: 1.55rem !important; }
    .document-form-scroll > div > h2 { font-size: 1.7rem !important; }
    .document-form-scroll > div > p { font-size: 1.15rem; line-height: 1.5; }

    /* The preview metadata is supporting information, but it still needs to
       be readable at the same distance as the form panel. */
    .document-preview-scroll > section > div > p.text-xs,
    .document-preview-scroll > section > span.text-xs {
      font-size: 1.1rem !important;
      line-height: 1.35;
    }

    .document-preview-scroll > section > span.text-xs {
      padding: .65rem 1.25rem !important;
    }

    #document-form button {
      min-height: 3.25rem;
      transition: transform .2s ease, box-shadow .2s ease, filter .2s ease;
    }

    #document-form button:hover {
      transform: translateY(-2px);
      filter: brightness(1.06);
    }

    /* Bottom actions use the same comfortable touch target and visual weight
       as the internship form's fixed action bar. */
    #document-form > div:last-child > button {
      min-height: 4rem;
      padding: .85rem 1.5rem !important;
      font-size: 1.25rem !important;
      line-height: 1.2;
    }

    #document-form > div:last-child > button i {
      font-size: 1.2rem;
    }

    #document-form input::placeholder,
    #document-form textarea::placeholder { color: #64748b; opacity: 1; }

    #document-form input:invalid:not(:placeholder-shown),
    #document-form select:invalid { border-color: #dc2626 !important; }

    #document-form input:invalid:not(:placeholder-shown):focus,
    #document-form select:invalid:focus {
      box-shadow: 0 0 0 3px rgba(220, 38, 38, .15) !important;
    }

    #document-form .fallback-input,
    #document-form .r16-field-card input,
    #document-form .r16-field-card select { min-height: 3rem; }

    @media (max-width: 639px) {
      #document-form > div:not(:last-child),
      #document-form > .space-y-4 > div { padding: .85rem; }
      #document-form label { font-size: .95rem; }
      #document-form input:not([type="hidden"]),
      #document-form select,
      #document-form input:not([type="hidden"]),
      #document-form select,
      #document-form textarea { font-size: 1.3rem; }
      #document-form textarea { min-height: 6.5rem; }
    }

    @media (min-width: 1024px) {
      html, body { height: 100%; overflow: hidden; }
      .document-workspace { height: calc(100vh - 6rem); overflow: hidden; }
      .paper-height-panel { height: 100%; min-height: 0; }
      .document-form-scroll {
        min-height: 0;
        overflow-y: auto;
        overscroll-behavior: contain;
        scrollbar-gutter: stable;
      }
      .document-preview-scroll {
        height: 100%;
        overflow: auto;
        overscroll-behavior: contain;
      }
    }
  </style>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body class="min-h-screen bg-slate-100 font-sans text-slate-800">

  <header class="sticky top-0 z-30 flex h-16 items-center justify-between border-b border-slate-200 bg-white/95 px-4 shadow-sm backdrop-blur sm:px-6">
    <a href="<?= htmlspecialchars($backPage) ?>" aria-label="ย้อนกลับ" class="inline-flex items-center gap-2 text-sm font-semibold text-slate-600 transition hover:text-red-700"><i class="fa-solid fa-arrow-left"></i><span></span></a>
    <div class="min-w-0 text-right"><p class="truncate text-sm font-bold text-slate-900"><?= htmlspecialchars($displayDocumentCode) ?> — <?= htmlspecialchars($document['document_name']) ?></p><p class="text-xs text-slate-500">กรอกข้อมูลและตรวจสอบตัวอย่างเอกสาร</p></div>
  </header>

  <div class="document-workspace mx-auto flex w-full max-w-[1500px] flex-col-reverse items-start gap-6 p-4 lg:flex-row-reverse lg:p-6">

  <!-- RIGHT PANEL: FORM INPUTS -->
  <aside class="paper-height-panel flex w-full flex-shrink-0 flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm lg:w-[520px]">
    <!-- Header Controls -->
    <div class="flex items-center justify-between border-b border-slate-200 bg-slate-50 px-6 py-6">
      <div><p class="text-sm font-bold uppercase tracking-wide text-red-600">แบบคำร้อง <?= htmlspecialchars($document['document_code']) ?></p><h2 class="mt-1 text-xl font-bold text-slate-900">ข้อมูลสำหรับกรอกเอกสาร</h2></div>
      <span class="inline-flex h-12 w-12 items-center justify-center rounded-xl bg-red-100 text-lg text-red-700"><i class="fa-solid fa-file-pen"></i></span>
    </div>

    <!-- Inputs Content -->
    <div class="document-form-scroll flex-grow space-y-6 p-6 sm:p-7">
      <?php if($revisionComment!==''): ?>
      <div class="rounded-xl border border-red-200 bg-red-50 p-4 text-red-900">
        <p class="font-bold"><i class="fa-solid fa-rotate-left mr-2"></i>เจ้าหน้าที่ขอให้แก้ไขเอกสาร</p>
        <p class="mt-2 text-sm leading-6"><?= htmlspecialchars($revisionComment) ?></p>
      </div>
      <?php endif; ?>
      <div>
        <h2 class="mb-1 text-2xl font-bold text-slate-900">กรอกคำร้อง <?= htmlspecialchars($document['document_code']) ?></h2>
        <p class="text-base leading-6 text-slate-500">ข้อมูลที่กรอกจะแสดงบนตัวอย่างกระดาษแบบเรียลไทม์ ช่องที่มีจุดสีแดงเป็นข้อมูลที่ควรตรวจสอบให้ครบถ้วน</p>
      </div>

      <form action="save_document.php" method="post" enctype="multipart/form-data" id="document-form" class="space-y-4" novalidate>
        <?= csrf_field() ?>
        <input type="hidden" name="document_id" value="<?= $documentId ?>">
        <input type="hidden" name="action" id="form-action" value="draft">

        <?php if ($isCustomR01): ?>
          <!-- Custom Layout for R01 -->
          
          <!-- Hidden inputs for mapping to save_document.php -->
          <input type="hidden" name="field[<?= $fieldIds['request_date'] ?>]" id="real-request-date" value="<?= htmlspecialchars($rawDate) ?>">

          <!-- Date Group -->
          <div class="grid grid-cols-3 gap-2">
            <div>
              <label class="block text-xs font-semibold text-zinc-400 mb-1">วันที่</label>
              <input type="text" id="in-date" class="w-full bg-zinc-900 border border-zinc-700 rounded-lg px-3 py-2 text-white focus:outline-none focus:border-brand-red text-sm" placeholder="24" value="<?= htmlspecialchars($day) ?>">
            </div>
            <div>
              <label class="block text-xs font-semibold text-zinc-400 mb-1">เดือน</label>
              <input type="text" id="in-month" class="w-full bg-zinc-900 border border-zinc-700 rounded-lg px-3 py-2 text-white focus:outline-none focus:border-brand-red text-sm" placeholder="มิถุนายน" value="<?= htmlspecialchars($month) ?>">
            </div>
            <div>
              <label class="block text-xs font-semibold text-zinc-400 mb-1">พ.ศ.</label>
              <input type="text" id="in-year" class="w-full bg-zinc-900 border border-zinc-700 rounded-lg px-3 py-2 text-white focus:outline-none focus:border-brand-red text-sm" placeholder="2568" value="<?= htmlspecialchars($year) ?>">
            </div>
          </div>

          <!-- Subject & To -->
          <div>
            <label class="block text-xs font-semibold text-zinc-400 mb-1"><?= htmlspecialchars($fieldLabels['subject'] ?? 'เรื่อง (Title)') ?></label>
            <input type="text" name="field[<?= $fieldIds['subject'] ?>]" id="in-subject" class="w-full bg-zinc-900 border border-zinc-700 rounded-lg px-3 py-2 text-white focus:outline-none focus:border-brand-red text-sm" placeholder="ขอฝึกงานวิชาชีพในสถานประกอบการ" value="<?= htmlspecialchars($fieldValues['subject']) ?>" <?= ($fieldRequired['subject'] ?? false) ? 'required' : '' ?>>
          </div>
          <div>
            <label class="block text-xs font-semibold text-zinc-400 mb-1"><?= htmlspecialchars($fieldLabels['recipient'] ?? 'เรียน (To)') ?></label>
            <input type="text" name="field[<?= $fieldIds['recipient'] ?>]" id="in-to" class="w-full bg-zinc-900 border border-zinc-700 rounded-lg px-3 py-2 text-white focus:outline-none focus:border-brand-red text-sm" placeholder="คณบดีคณะวิศวกรรมศาสตร์และเทคโนโลยี" value="<?= htmlspecialchars($fieldValues['recipient']) ?>" <?= ($fieldRequired['recipient'] ?? false) ? 'required' : '' ?>>
          </div>

          <!-- Name & Student ID -->
          <div>
            <label class="block text-xs font-semibold text-zinc-400 mb-1">คำนำหน้า</label>
            <select name="student_prefix_id" id="student-prefix-select" class="w-full bg-zinc-900 border border-zinc-700 rounded-lg px-3 py-2 text-white focus:outline-none focus:border-brand-red text-sm">
              <?php foreach ($studentPrefixes as $prefixOption): ?>
                <option value="<?= (int)$prefixOption['prefix_id'] ?>" data-prefix="<?= htmlspecialchars($prefixOption['prefix_short'] ?: $prefixOption['prefix_name']) ?>" <?= (int)$studentFullProfile['prefix_id'] === (int)$prefixOption['prefix_id'] ? 'selected' : '' ?>><?= htmlspecialchars($prefixOption['prefix_name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label class="block text-xs font-semibold text-zinc-400 mb-1"><?= htmlspecialchars($fieldLabels['student_name'] ?? 'ชื่อ-นามสกุล (ข้าพเจ้า)') ?></label>
            <input type="text" name="field[<?= $fieldIds['student_name'] ?>]" id="in-name" class="w-full bg-zinc-900 border border-zinc-700 rounded-lg px-3 py-2 text-white focus:outline-none focus:border-brand-red text-sm" placeholder="นาย สมศักดิ์ รักเรียน" value="<?= htmlspecialchars($fieldValues['student_name']) ?>" <?= ($fieldRequired['student_name'] ?? false) ? 'required' : '' ?>>
          </div>
          <div>
            <label class="block text-xs font-semibold text-zinc-400 mb-1"><?= htmlspecialchars($fieldLabels['student_number'] ?? 'รหัสประจำตัวนักศึกษา') ?></label>
            <input type="text" name="field[<?= $fieldIds['student_number'] ?>]" id="in-student-id" class="w-full bg-zinc-900 border border-zinc-700 rounded-lg px-3 py-2 text-white focus:outline-none focus:border-brand-red text-sm" placeholder="368105123456-7" value="<?= htmlspecialchars($fieldValues['student_number']) ?>" <?= ($fieldRequired['student_number'] ?? false) ? 'required' : '' ?>>
          </div>

          <!-- Study Level dropdown -->
          <div>
            <label class="block text-xs font-semibold text-zinc-400 mb-1"><?= htmlspecialchars($fieldLabels['education_level'] ?? 'ระดับการศึกษา (Level of study)') ?></label>
            <select name="field[<?= $fieldIds['education_level'] ?>]" id="in-level" class="w-full bg-zinc-900 border border-zinc-700 rounded-lg px-3 py-2 text-white focus:outline-none focus:border-brand-red text-sm">
              <option value="undergraduate" <?= ($fieldValues['education_level'] === 'undergraduate') ? 'selected' : '' ?>>ปริญญาตรี (Undergraduate)</option>
              <option value="certificate" <?= ($fieldValues['education_level'] === 'certificate') ? 'selected' : '' ?>>ปวช. (Certificate)</option>
              <option value="diploma" <?= ($fieldValues['education_level'] === 'diploma') ? 'selected' : '' ?>>ปวส. (Diploma)</option>
              <option value="master" <?= ($fieldValues['education_level'] === 'master') ? 'selected' : '' ?>>ปริญญาโท (Master's Degree)</option>
              <option value="doctor" <?= ($fieldValues['education_level'] === 'doctor') ? 'selected' : '' ?>>ปริญญาเอก (Doctoral Degree)</option>
            </select>
          </div>

          <!-- Faculty, Curriculum, Telephone -->
          <div>
            <label class="block text-xs font-semibold text-zinc-400 mb-1"><?= htmlspecialchars($fieldLabels['faculty'] ?? 'คณะ (Faculty)') ?></label>
            <input type="text" name="field[<?= $fieldIds['faculty'] ?>]" id="in-faculty" class="w-full bg-zinc-900 border border-zinc-700 rounded-lg px-3 py-2 text-white focus:outline-none focus:border-brand-red text-sm" placeholder="วิศวกรรมศาสตร์และเทคโนโลยี" value="<?= htmlspecialchars($fieldValues['faculty']) ?>" <?= ($fieldRequired['faculty'] ?? false) ? 'required' : '' ?>>
          </div>
          <div>
            <label class="block text-xs font-semibold text-zinc-400 mb-1"><?= htmlspecialchars($fieldLabels['curriculum'] ?? 'หลักสูตร/สาขาวิชา (Curriculum)') ?></label>
            <input type="text" name="field[<?= $fieldIds['curriculum'] ?>]" id="in-curriculum" class="w-full bg-zinc-900 border border-zinc-700 rounded-lg px-3 py-2 text-white focus:outline-none focus:border-brand-red text-sm" placeholder="วศ.บ. วิศวกรรมคอมพิวเตอร์" value="<?= htmlspecialchars($fieldValues['curriculum']) ?>" <?= ($fieldRequired['curriculum'] ?? false) ? 'required' : '' ?>>
          </div>
          <div>
            <label class="block text-xs font-semibold text-zinc-400 mb-1"><?= htmlspecialchars($fieldLabels['telephone'] ?? 'เบอร์โทรศัพท์ (Telephone)') ?></label>
            <input type="text" name="field[<?= $fieldIds['telephone'] ?>]" id="in-phone" class="w-full bg-zinc-900 border border-zinc-700 rounded-lg px-3 py-2 text-white focus:outline-none focus:border-brand-red text-sm" placeholder="089-123-4567" value="<?= htmlspecialchars($fieldValues['telephone']) ?>" <?= ($fieldRequired['telephone'] ?? false) ? 'required' : '' ?>>
          </div>

          <!-- Request detail -->
          <div>
            <label class="block text-xs font-semibold text-zinc-400 mb-1"><?= htmlspecialchars($fieldLabels['request_detail'] ?? 'มีความประสงค์ (I would like to)') ?></label>
            <textarea name="field[<?= $fieldIds['request_detail'] ?>]" id="in-request" rows="5" class="w-full bg-zinc-900 border border-zinc-700 rounded-lg px-3 py-2 text-white focus:outline-none focus:border-brand-red text-sm" placeholder="เนื่องด้วยข้าพเจ้ามีความต้องการยื่นเรื่องขอออกหนังสือส่งตัวฝึกประสบการณ์วิชาชีพ ณ บริษัท ก้าวหน้าเทคโนโลยี จำกัด ตั้งแต่วันที่ 1 พฤศจิกายน 2568 ถึงวันที่ 28 กุมภาพันธ์ 2569..." <?= ($fieldRequired['request_detail'] ?? false) ? 'required' : '' ?>><?= htmlspecialchars($fieldValues['request_detail']) ?></textarea>
          </div>

          <!-- Signature Name -->
          <div>
            <label class="block text-xs font-semibold text-zinc-400 mb-1"><?= htmlspecialchars($fieldLabels['signature_name'] ?? 'ชื่อสำหรับการลงชื่อ (นักศึกษา)') ?></label>
            <input type="text" name="field[<?= $fieldIds['signature_name'] ?>]" id="in-sig-name" class="w-full bg-zinc-900 border border-zinc-700 rounded-lg px-3 py-2 text-white focus:outline-none focus:border-brand-red text-sm" placeholder="สมศักดิ์ รักเรียน" value="<?= htmlspecialchars($fieldValues['signature_name']) ?>" <?= ($fieldRequired['signature_name'] ?? false) ? 'required' : '' ?>>
          </div>

        <?php elseif ($isCustomR12): ?>
          <input type="hidden" name="field[<?= $fieldIds['student_name'] ?>]" value="<?= htmlspecialchars($fieldValues['student_name']) ?>">
          <input type="hidden" name="field[<?= $fieldIds['student_number'] ?>]" value="<?= htmlspecialchars($fieldValues['student_number']) ?>">
          <input type="hidden" name="field[<?= $fieldIds['faculty'] ?>]" value="<?= htmlspecialchars($fieldValues['faculty']) ?>">
          <input type="hidden" name="field[<?= $fieldIds['curriculum'] ?>]" value="<?= htmlspecialchars($fieldValues['curriculum']) ?>">

          <section class="space-y-4">
            <div class="flex items-center gap-3"><span class="inline-flex h-9 w-9 items-center justify-center rounded-lg bg-red-100 text-red-700"><i class="fa-solid fa-user-graduate"></i></span><div><h3 class="font-bold text-slate-900">ข้อมูลนักศึกษา</h3><p class="text-xs text-slate-500">ข้อมูลจากบัญชีนักศึกษา</p></div></div>
            <div class="grid gap-3 sm:grid-cols-2">
              <div><label>คำนำหน้า</label><select name="student_prefix_id" id="student-prefix-select" class="w-full rounded-lg border"><?php foreach ($studentPrefixes as $prefixOption): ?><option value="<?= (int)$prefixOption['prefix_id'] ?>" data-prefix="<?= htmlspecialchars($prefixOption['prefix_short'] ?: $prefixOption['prefix_name']) ?>" <?= (int)$studentFullProfile['prefix_id'] === (int)$prefixOption['prefix_id'] ? 'selected' : '' ?>><?= htmlspecialchars($prefixOption['prefix_name']) ?></option><?php endforeach; ?></select></div>
              <div><label>ชื่อ-นามสกุล</label><input value="<?= htmlspecialchars($fieldValues['student_name']) ?>" disabled class="w-full rounded-lg border bg-slate-100"></div>
              <div><label>รหัสนักศึกษา</label><input value="<?= htmlspecialchars($fieldValues['student_number']) ?>" disabled class="w-full rounded-lg border bg-slate-100"></div>
              <div><label>คณะ</label><input value="<?= htmlspecialchars($fieldValues['faculty']) ?>" disabled class="w-full rounded-lg border bg-slate-100"></div>
              <div><label>หลักสูตร/สาขาวิชา</label><input value="<?= htmlspecialchars($fieldValues['curriculum']) ?>" disabled class="w-full rounded-lg border bg-slate-100"></div>
            </div>
            <div><label><?= htmlspecialchars($fieldLabels['telephone'] ?? 'เบอร์โทรศัพท์') ?></label><input type="text" name="field[<?= $fieldIds['telephone'] ?>]" id="fallback-telephone" data-fid="<?= $fieldIds['telephone'] ?>" value="<?= htmlspecialchars($fieldValues['telephone']) ?>" required class="fallback-input w-full rounded-lg border"></div>
            <div class="grid gap-3 sm:grid-cols-2">
              <div><label><?= htmlspecialchars($fieldLabels['request_date'] ?? 'วันที่ยื่นคำร้อง') ?></label><input type="date" name="field[<?= $fieldIds['request_date'] ?>]" id="fallback-request_date" data-fid="<?= $fieldIds['request_date'] ?>" value="<?= htmlspecialchars($fieldValues['request_date']) ?>" required class="fallback-input w-full rounded-lg border"></div>
              <div><label><?= htmlspecialchars($fieldLabels['education_level'] ?? 'ระดับการศึกษา') ?></label><select name="field[<?= $fieldIds['education_level'] ?>]" id="fallback-education_level" data-fid="<?= $fieldIds['education_level'] ?>" class="fallback-input w-full rounded-lg border"><option value="certificate" <?= $fieldValues['education_level']==='certificate'?'selected':'' ?>>ปวช.</option><option value="diploma" <?= $fieldValues['education_level']==='diploma'?'selected':'' ?>>ปวส.</option><option value="undergraduate" <?= $fieldValues['education_level']==='undergraduate'?'selected':'' ?>>ปริญญาตรี</option><option value="master" <?= $fieldValues['education_level']==='master'?'selected':'' ?>>ปริญญาโท</option><option value="doctor" <?= $fieldValues['education_level']==='doctor'?'selected':'' ?>>ปริญญาเอก</option></select></div>
            </div>
            <div><label><?= htmlspecialchars($fieldLabels['recipient'] ?? 'เรียน') ?></label><input type="text" name="field[<?= $fieldIds['recipient'] ?>]" id="fallback-recipient" data-fid="<?= $fieldIds['recipient'] ?>" value="<?= htmlspecialchars($fieldValues['recipient']) ?>" required class="fallback-input w-full rounded-lg border"></div>
          </section>

          <section class="space-y-4">
            <div class="flex items-center gap-3"><span class="inline-flex h-9 w-9 items-center justify-center rounded-lg bg-blue-100 text-blue-700"><i class="fa-solid fa-book-open"></i></span><div><h3 class="font-bold text-slate-900">รายวิชาที่ขอแก้ไขกลุ่ม</h3><p class="text-xs text-slate-500">กรอกได้สูงสุด 4 รายวิชา — กดรายการเพิ่มเติมเมื่อต้องการกรอก</p></div></div>
            <?php for ($rowNo=1; $rowNo<=4; $rowNo++):
              $rowHasValue = false;
              foreach (['semester','course_code','course_name','section','old_group','new_group'] as $checkName) {
                if (trim((string)($fieldValues[$checkName.'_'.$rowNo] ?? '')) !== '') { $rowHasValue = true; break; }
              }
            ?>
              <?php if ($rowNo > 1): ?>
              <details class="group rounded-xl border border-slate-200 bg-slate-50" <?= $rowHasValue ? 'open' : '' ?>>
                <summary class="flex cursor-pointer list-none items-center justify-between px-4 py-3 text-sm font-bold text-slate-700 hover:bg-slate-100">
                  <span>รายการที่ <?= $rowNo ?></span>
                  <span class="text-xs font-medium text-blue-600 group-open:hidden"><i class="fa-solid fa-plus mr-1"></i>เพิ่มรายการ</span>
                  <span class="hidden text-xs font-medium text-slate-500 group-open:inline"><i class="fa-solid fa-chevron-up mr-1"></i>ซ่อน</span>
                </summary>
                <div class="border-t border-slate-200 p-3">
              <?php else: ?>
              <div class="rounded-xl border border-slate-200 bg-slate-50 p-3">
                <p class="mb-3 text-sm font-bold text-slate-700">รายการที่ 1</p>
              <?php endif; ?>
                <div class="grid gap-3 sm:grid-cols-2">
                <?php foreach (['semester'=>'ภาคการศึกษา','course_code'=>'รหัสวิชา','course_name'=>'ชื่อวิชา','section'=>'กลุ่มเรียนที่ลงทะเบียน','old_group'=>'กลุ่มวิชาเดิม','new_group'=>'กลุ่มวิชาใหม่'] as $baseName=>$label): $fname=$baseName.'_'.$rowNo; ?>
                  <div class="<?= $baseName==='course_name'?'sm:col-span-2':'' ?>"><label><?= $label ?></label><input type="text" name="field[<?= $fieldIds[$fname] ?>]" id="fallback-<?= $fname ?>" data-fid="<?= $fieldIds[$fname] ?>" value="<?= htmlspecialchars($fieldValues[$fname]) ?>" class="fallback-input w-full rounded-lg border"></div>
                <?php endforeach; ?>
                </div>
              <?php if ($rowNo > 1): ?>
                </div>
              </details>
              <?php else: ?>
              </div>
              <?php endif; ?>
            <?php endfor; ?>
          </section>

          <section><label><?= htmlspecialchars($fieldLabels['signature_name'] ?? 'ชื่อสำหรับลงนาม') ?></label><input type="text" name="field[<?= $fieldIds['signature_name'] ?>]" id="fallback-signature_name" data-fid="<?= $fieldIds['signature_name'] ?>" value="<?= htmlspecialchars($fieldValues['signature_name']) ?>" required class="fallback-input w-full rounded-lg border"></section>

        <?php else: ?>
          <!-- Generic Fallback Form for other templates -->
          <div id="generic-form-fields" class="space-y-4<?= $isCustomR16 ? ' r16-form-fields' : '' ?>">
            <?php if ($isCustomR14 || $isCustomR16 || $isCustomR18): ?>
              <div<?= $isCustomR16 ? ' class="r16-field-card"' : '' ?>>
                <label class="block text-xs font-semibold text-zinc-400 mb-1">คำนำหน้า</label>
                <select name="student_prefix_id" id="student-prefix-select" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-800 focus:border-red-500 focus:outline-none focus:ring-2 focus:ring-red-100">
                  <?php foreach ($studentPrefixes as $prefixOption): ?>
                    <option value="<?= (int)$prefixOption['prefix_id'] ?>" data-prefix="<?= htmlspecialchars($prefixOption['prefix_short'] ?: $prefixOption['prefix_name']) ?>" <?= (int)$studentFullProfile['prefix_id'] === (int)$prefixOption['prefix_id'] ? 'selected' : '' ?>><?= htmlspecialchars($prefixOption['prefix_name']) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
            <?php endif; ?>
            <?php if ($isCustomR18): ?>
              <section class="rounded-xl border border-slate-200 bg-white p-3">
                <h3 class="mb-3 font-bold text-slate-800">รายชื่อนักศึกษาแนบคำร้อง</h3>
                <div class="space-y-2">
                <?php for($attachmentRow=1;$attachmentRow<=25;$attachmentRow++): ?>
                  <details class="rounded-lg border border-slate-200" <?= $attachmentRow===1?'open':'' ?>>
                    <summary class="cursor-pointer px-3 py-2 font-semibold text-slate-700">ลำดับที่ <?=$attachmentRow?></summary>
                    <div class="grid gap-3 border-t border-slate-200 p-3 sm:grid-cols-2">
                    <?php foreach(['student_number'=>'รหัสนักศึกษา','student_name'=>'ชื่อ - สกุล','group'=>'กลุ่มเรียน','gpa'=>'เกรดเฉลี่ย','expected_semester'=>'ภาคการศึกษาที่คาดว่าจะสำเร็จ'] as $attachmentKey=>$attachmentLabel):$attachmentName='attachment_'.$attachmentKey.'_'.$attachmentRow;if(!isset($fieldIds[$attachmentName]))continue;?>
                      <label class="<?=$attachmentKey==='student_name'?'sm:col-span-2':''?>"><span class="mb-1 block text-xs font-semibold text-slate-500"><?=$attachmentLabel?></span><input type="text" name="field[<?=$fieldIds[$attachmentName]?>]" id="fallback-<?=$attachmentName?>" data-fid="<?=$fieldIds[$attachmentName]?>" value="<?=htmlspecialchars($fieldValues[$attachmentName]??'')?>" class="fallback-input w-full rounded-lg border border-slate-300 px-3 py-2"></label>
                    <?php endforeach;?>
                    </div>
                  </details>
                <?php endfor;?>
                </div>
              </section>
            <?php endif; ?>
            <?php foreach ($fields as $field): 
              $fid = $field['field_id'];
              $fname = $field['field_name'];
              if ($isCustomR18 && preg_match('/^attachment_(student_number|student_name|group|gpa|expected_semester)_\d+$/',$fname)) continue;
              if (in_array($fname, $automaticRoleFieldNames, true)) continue;
              $flabel = $field['field_label'];
              $ftype = $field['field_type'];
              $freq = $field['required_field'];
              $fval = $fieldValues[$fname] ?? '';
            ?>
              <?php
              $fieldContainerAttributes = '';
              if ($isCustomR16) {
                  $fieldContainerAttributes = ' class="r16-field-card" data-r16-field="' . htmlspecialchars($fname) . '"';
              }
              if ($isCustomR24 && in_array($fname, ['sick_reason', 'personal_reason'], true)) {
                  $fieldContainerAttributes = ' class="r24-reason-field" data-r24-reason="' . ($fname === 'sick_reason' ? 'sick' : 'personal') . '"';
              }
              ?>
              <div<?= $fieldContainerAttributes ?>>
                <label class="block text-xs font-semibold text-zinc-400 mb-1"><?= htmlspecialchars($flabel) ?></label>
                <?php if ($isCustomR18 && in_array($fname, ['recipient_faculty', 'responsible_faculty'], true)): ?>
                  <input type="text" name="field[<?= $fid ?>]" id="fallback-<?= $fname ?>" data-fid="<?= $fid ?>" class="fallback-input w-full cursor-not-allowed rounded-lg border border-slate-200 bg-slate-100 px-3 py-2 text-sm font-semibold text-slate-700" value="วิศวกรรมศาสตร์และเทคโนโลยี" readonly>
                <?php elseif ($isCustomR18 && $fname === 'responsible_department'): ?>
                  <select name="field[<?= $fid ?>]" id="fallback-<?= $fname ?>" data-fid="<?= $fid ?>" class="fallback-input w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-800 focus:border-red-500 focus:outline-none focus:ring-2 focus:ring-red-100" <?= $freq ? 'required' : '' ?>>
                    <option value="">เลือกสาขาวิชาที่รับผิดชอบ</option>
                    <?php foreach ($departmentsForDocument as $departmentOption): ?>
                      <option value="<?= htmlspecialchars($departmentOption['department_name']) ?>" <?= (string)$fval === (string)$departmentOption['department_name'] ? 'selected' : '' ?>><?= htmlspecialchars($departmentOption['department_name']) ?></option>
                    <?php endforeach; ?>
                  </select>
                <?php elseif ($isCustomR14 && $fname === 'request_type'): ?>
                  <select name="field[<?= $fid ?>]" id="fallback-<?= $fname ?>" data-fid="<?= $fid ?>" class="fallback-input w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-800" <?= $freq ? 'required' : '' ?>>
                    <option value="less" <?= $fval === 'less' ? 'selected' : '' ?>>ลงทะเบียนเรียนต่ำกว่าหน่วยกิตที่กำหนด</option>
                    <option value="more" <?= $fval === 'more' ? 'selected' : '' ?>>ลงทะเบียนเรียนเกินกว่าหน่วยกิตที่กำหนด</option>
                  </select>
                <?php elseif ($isCustomR14 && $fname === 'semester'): ?>
                  <select name="field[<?= $fid ?>]" id="fallback-<?= $fname ?>" data-fid="<?= $fid ?>" class="fallback-input w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-800" <?= $freq ? 'required' : '' ?>>
                    <option value="">เลือกภาคการศึกษา</option>
                    <?php foreach (['1','2','3'] as $semesterOption): ?><option value="<?= $semesterOption ?>" <?= (string)$fval === $semesterOption ? 'selected' : '' ?>><?= $semesterOption ?></option><?php endforeach; ?>
                  </select>
                <?php elseif ($isCustomR14 && $fname === 'signature_name'): ?>
                  <input type="text" name="field[<?= $fid ?>]" id="fallback-<?= $fname ?>" data-fid="<?= $fid ?>" class="fallback-input w-full cursor-not-allowed rounded-lg border border-slate-200 bg-slate-100 px-3 py-2 text-sm font-semibold text-slate-700" value="<?= htmlspecialchars(trim((string)($studentFullProfile['firstname'] ?? '') . ' ' . (string)($studentFullProfile['lastname'] ?? ''))) ?>" readonly>
                <?php elseif ($isCustomR14 && $fname === 'cumulative_gpa'): ?>
                  <input type="number" min="0" max="4" step="0.01" inputmode="decimal" placeholder="เช่น 3.25" name="field[<?= $fid ?>]" id="fallback-<?= $fname ?>" data-fid="<?= $fid ?>" class="fallback-input w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" value="<?= htmlspecialchars($fval) ?>" <?= $freq ? 'required' : '' ?>>
                <?php elseif ($isCustomR24 && $fname === 'leave_type'): ?>
                  <select name="field[<?= $fid ?>]" id="fallback-<?= $fname ?>" data-fid="<?= $fid ?>" class="fallback-input w-full rounded-lg border" <?= $freq ? 'required' : '' ?>><option value="sick" <?= $fval==='sick'?'selected':'' ?>>ลาป่วย</option><option value="personal" <?= $fval==='personal'?'selected':'' ?>>ลากิจ</option></select>
                <?php elseif ($isCustomR24 && $fname === 'student_name'): ?>
                  <div class="grid grid-cols-[9rem_1fr] gap-2">
                    <select name="student_prefix_id" id="student-prefix-select" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm" required>
                      <?php foreach ($studentPrefixes as $prefixOption): ?>
                        <option value="<?= (int)$prefixOption['prefix_id'] ?>" data-prefix="<?= htmlspecialchars($prefixOption['prefix_short'] ?: $prefixOption['prefix_name']) ?>" <?= (int)$studentFullProfile['prefix_id'] === (int)$prefixOption['prefix_id'] ? 'selected' : '' ?>><?= htmlspecialchars($prefixOption['prefix_name']) ?></option>
                      <?php endforeach; ?>
                    </select>
                    <input type="text" name="field[<?= $fid ?>]" id="fallback-<?= $fname ?>" data-fid="<?= $fid ?>" class="fallback-input w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" value="<?= htmlspecialchars($fval) ?>" <?= $freq ? 'required' : '' ?>>
                  </div>
                <?php elseif ($isCustomR24 && $fname === 'guardian_relationship'): ?>
                  <?php
                  $fval = ['บิดา'=>'father', 'มารดา'=>'mother', 'อื่น'=>'other', 'อื่นๆ'=>'other', 'อื่น ๆ'=>'other'][$fval] ?? $fval;
                  $relationshipOtherText = str_starts_with((string)$fval, 'other:') ? trim(substr((string)$fval, 6)) : '';
                  $relationshipChoice = $relationshipOtherText !== '' || $fval === 'other' ? 'other' : $fval;
                  ?>
                  <input type="hidden" name="field[<?= $fid ?>]" id="fallback-<?= $fname ?>" data-fid="<?= $fid ?>" class="fallback-input" value="<?= htmlspecialchars($fval) ?>">
                  <select id="guardian-relationship-choice" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm" <?= $freq ? 'required' : '' ?>>
                    <option value="">เลือกความเกี่ยวข้อง</option>
                    <option value="father" <?= $relationshipChoice === 'father' ? 'selected' : '' ?>>บิดา</option>
                    <option value="mother" <?= $relationshipChoice === 'mother' ? 'selected' : '' ?>>มารดา</option>
                    <option value="other" <?= $relationshipChoice === 'other' ? 'selected' : '' ?>>อื่น ๆ</option>
                  </select>
                  <input type="text" id="guardian-relationship-other" class="mt-2 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm <?= $relationshipChoice === 'other' ? '' : 'hidden' ?>" value="<?= htmlspecialchars($relationshipOtherText) ?>" placeholder="ระบุความเกี่ยวข้อง เช่น ป้า">
                <?php elseif ($isCustomR24 && $fname === 'signature_name'): ?>
                  <input type="text" name="field[<?= $fid ?>]" id="fallback-<?= $fname ?>" data-fid="<?= $fid ?>" class="fallback-input w-full cursor-not-allowed rounded-lg border border-slate-200 bg-slate-100 px-3 py-2 text-sm font-semibold text-slate-700" value="<?= htmlspecialchars(trim((string)($studentFullProfile['firstname'] ?? '') . ' ' . (string)($studentFullProfile['lastname'] ?? ''))) ?>" readonly>
                <?php elseif ($isCustomR24 && $fname === 'guardian_name'): ?>
                  <?php
                  $guardianPrefixValue = '';
                  $guardianPlainName = trim((string)$fval);
                  if (preg_match('/^\s*(นาย|นางสาว|นาง|น\.ส\.)\s*/u', $guardianPlainName, $guardianFormMatch)) {
                      $guardianPrefixValue = $guardianFormMatch[1] === 'น.ส.' ? 'นางสาว' : $guardianFormMatch[1];
                      $guardianPlainName = preg_replace('/^\s*(?:นาย|นางสาว|นาง|น\.ส\.)\s*/u', '', $guardianPlainName);
                  }
                  ?>
                  <input type="hidden" name="field[<?= $fid ?>]" id="fallback-guardian_name" data-fid="<?= $fid ?>" value="<?= htmlspecialchars(trim($guardianPrefixValue . ' ' . $guardianPlainName)) ?>">
                  <div class="grid grid-cols-[9rem_1fr] gap-2">
                    <select id="guardian-prefix" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm" <?= $freq ? 'required' : '' ?>>
                      <option value="">เลือกคำนำหน้า</option>
                      <option value="นาย" <?= $guardianPrefixValue === 'นาย' ? 'selected' : '' ?>>นาย</option>
                      <option value="นาง" <?= $guardianPrefixValue === 'นาง' ? 'selected' : '' ?>>นาง</option>
                      <option value="นางสาว" <?= $guardianPrefixValue === 'นางสาว' ? 'selected' : '' ?>>นางสาว</option>
                    </select>
                    <input type="text" id="guardian-plain-name" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" value="<?= htmlspecialchars($guardianPlainName) ?>" placeholder="ชื่อ–นามสกุลผู้ปกครอง" <?= $freq ? 'required' : '' ?>>
                  </div>
                <?php elseif ($isCustomR18 && $fname === 'study_type'): ?>
                  <select name="field[<?= $fid ?>]" id="fallback-<?= $fname ?>" data-fid="<?= $fid ?>" class="fallback-input w-full rounded-lg border" <?= $freq ? 'required' : '' ?>><option value="normal" <?= $fval==='normal'?'selected':'' ?>>ภาคปกติ</option><option value="associate" <?= $fval==='associate'?'selected':'' ?>>ภาคสมทบ</option><option value="special" <?= $fval==='special'?'selected':'' ?>>ภาคพิเศษ</option></select>
                <?php elseif ($isCustomR16 && preg_match('/^registration_status_[1-6]$/', $fname)): ?>
                  <select name="field[<?= $fid ?>]" id="fallback-<?= $fname ?>" data-fid="<?= $fid ?>" class="w-full bg-zinc-900 border border-zinc-700 rounded-lg px-3 py-2 text-white focus:outline-none focus:border-brand-red text-sm fallback-input" <?= $freq ? 'required' : '' ?>>
                    <option value="">เลือกสถานะการลงทะเบียน</option>
                    <option value="normal" <?= $fval === 'normal' ? 'selected' : '' ?>>ปกติ</option>
                    <option value="repeat" <?= $fval === 'repeat' ? 'selected' : '' ?>>ลงซ้ำ</option>
                    <option value="regrade" <?= $fval === 'regrade' ? 'selected' : '' ?>>รีเกรด</option>
                  </select>
                <?php elseif (in_array($fname, ['instructor_employee_id', 'teacher_employee_id'], true)): ?>
                  <select name="field[<?= $fid ?>]" id="fallback-<?= $fname ?>" data-fid="<?= $fid ?>" class="w-full bg-zinc-900 border border-zinc-700 rounded-lg px-3 py-2 text-white focus:outline-none focus:border-brand-red text-sm fallback-input" <?= $freq ? 'required' : '' ?>>
                    <option value="">เลือกอาจารย์ผู้สอน</option>
                    <optgroup label="กรณีที่ 1 — สาขาเดียวกับนักศึกษา">
                    <?php foreach ($teachersSameDepartment as $teacherOption): ?>
                      <option value="<?= (int)$teacherOption['employee_id'] ?>" data-department="<?=htmlspecialchars($teacherOption['department_name']??'')?>" <?= (string)$fval===(string)$teacherOption['employee_id']?'selected':'' ?>><?=htmlspecialchars($teacherOption['firstname'].' '.$teacherOption['lastname'])?></option>
                    <?php endforeach;?>
                    </optgroup>
                    <optgroup label="กรณีที่ 2 — ต่างสาขาจากนักศึกษา">
                    <?php foreach ($teachersOtherDepartment as $teacherOption): ?>
                      <option value="<?= (int)$teacherOption['employee_id'] ?>" data-department="<?=htmlspecialchars($teacherOption['department_name']??'')?>" <?= (string)$fval===(string)$teacherOption['employee_id']?'selected':'' ?>><?=htmlspecialchars(($teacherOption['department_name']?:'ไม่ระบุสาขา').' — '.$teacherOption['firstname'].' '.$teacherOption['lastname'])?></option>
                    <?php endforeach;?>
                    </optgroup>
                  </select>
                <?php elseif ($fname === 'education_level'): ?>
                  <select name="field[<?= $fid ?>]" id="fallback-<?= $fname ?>" data-fid="<?= $fid ?>" class="w-full bg-zinc-900 border border-zinc-700 rounded-lg px-3 py-2 text-white focus:outline-none focus:border-brand-red text-sm fallback-input" <?= $freq ? 'required' : '' ?>>
                    <option value="certificate" <?= $fval === 'certificate' ? 'selected' : '' ?>>ปวช.</option>
                    <option value="diploma" <?= $fval === 'diploma' ? 'selected' : '' ?>>ปวส.</option>
                    <option value="undergraduate" <?= $fval === 'undergraduate' ? 'selected' : '' ?>>ปริญญาตรี</option>
                    <?php if ($isCustomR18): ?>
                      <option value="graduate" <?= in_array($fval, ['graduate', 'master', 'doctor'], true) ? 'selected' : '' ?>>บัณฑิตศึกษา</option>
                    <?php else: ?>
                      <option value="master" <?= $fval === 'master' ? 'selected' : '' ?>>ปริญญาโท</option>
                      <option value="doctor" <?= $fval === 'doctor' ? 'selected' : '' ?>>ปริญญาเอก</option>
                    <?php endif; ?>
                  </select>
                <?php elseif ($ftype === 'textarea'): ?>
                  <textarea name="field[<?= $fid ?>]" id="fallback-<?= $fname ?>" data-fid="<?= $fid ?>" rows="4" class="w-full bg-zinc-900 border border-zinc-700 rounded-lg px-3 py-2 text-white focus:outline-none focus:border-brand-red text-sm fallback-input" <?= $freq ? 'required' : '' ?>><?= htmlspecialchars($fval) ?></textarea>
                <?php elseif ($ftype === 'checkbox'): ?>
                  <input type="text" name="field[<?= $fid ?>]" id="fallback-<?= $fname ?>" data-fid="<?= $fid ?>" class="w-full bg-zinc-900 border border-zinc-700 rounded-lg px-3 py-2 text-white focus:outline-none focus:border-brand-red text-sm fallback-input" placeholder="กรอก ✓ เพื่อเลือก" value="<?= htmlspecialchars($fval) ?>">
                <?php elseif ($ftype === 'date'): ?>
                  <input type="date" name="field[<?= $fid ?>]" id="fallback-<?= $fname ?>" data-fid="<?= $fid ?>" class="w-full bg-zinc-900 border border-zinc-700 rounded-lg px-3 py-2 text-white focus:outline-none focus:border-brand-red text-sm fallback-input" value="<?= htmlspecialchars($fval) ?>" <?= $freq ? 'required' : '' ?>>
                <?php elseif ($isCustomR18 && $fname === 'total_credits'): ?>
                  <input type="number" name="field[<?= $fid ?>]" id="fallback-<?= $fname ?>" data-fid="<?= $fid ?>" class="fallback-input w-full cursor-not-allowed rounded-lg border border-slate-200 bg-slate-100 px-3 py-2 text-sm font-semibold text-slate-700" value="<?= htmlspecialchars($fval) ?>" readonly>
                <?php elseif ($ftype === 'number'): ?>
                  <input type="number" name="field[<?= $fid ?>]" id="fallback-<?= $fname ?>" data-fid="<?= $fid ?>" class="w-full bg-zinc-900 border border-zinc-700 rounded-lg px-3 py-2 text-white focus:outline-none focus:border-brand-red text-sm fallback-input" value="<?= htmlspecialchars($fval) ?>" <?= $freq ? 'required' : '' ?>>
                <?php else: ?>
                  <input type="text" name="field[<?= $fid ?>]" id="fallback-<?= $fname ?>" data-fid="<?= $fid ?>" class="w-full bg-zinc-900 border border-zinc-700 rounded-lg px-3 py-2 text-white focus:outline-none focus:border-brand-red text-sm fallback-input" value="<?= htmlspecialchars($fval) ?>" <?= $freq ? 'required' : '' ?>>
                <?php endif; ?>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>

        <section class="rounded-xl border border-slate-200 bg-slate-50 p-4">
          <h3 class="font-bold text-slate-900"><i class="fa-solid fa-paperclip mr-2 text-red-600"></i>เอกสารประกอบคำร้อง</h3>
          <p class="mt-1 text-xs text-slate-500">รองรับ PDF, JPG และ PNG ขนาดไม่เกิน 10 MB ต่อไฟล์</p>
          <?php if($documentAttachments): ?>
          <div class="mt-3 space-y-2">
            <?php foreach($documentAttachments as $attachment): ?>
            <label class="flex items-center justify-between gap-3 rounded-lg border border-slate-200 bg-white p-3 text-sm">
              <span class="min-w-0 truncate"><i class="fa-regular fa-file mr-2 text-slate-400"></i><?= htmlspecialchars($attachment['original_name']) ?> <span class="text-xs text-slate-400">(<?= number_format($attachment['file_size']/1024,1) ?> KB)</span></span>
              <span class="shrink-0 text-xs font-bold text-red-700"><input type="checkbox" name="delete_attachment_ids[]" value="<?= (int)$attachment['attachment_id'] ?>" class="mr-1">ลบ</span>
            </label>
            <?php endforeach; ?>
          </div>
          <?php endif; ?>
          <input type="file" name="attachments[]" multiple accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png" class="mt-3 block w-full rounded-lg border border-slate-300 bg-white p-2 text-sm">
        </section>

        <!-- Form submit actions -->
        <div class="sticky bottom-4 z-20 flex flex-col-reverse gap-3 rounded-2xl border border-slate-200 bg-white/95 p-4 shadow-lg backdrop-blur sm:flex-row sm:justify-end">
          <button type="button" onclick="submitForm('draft')" class="rounded-xl border border-slate-300 bg-white px-5 py-3 text-sm font-bold text-slate-700 transition-all duration-200 hover:bg-slate-50">
            <i class="fa-regular fa-floppy-disk mr-2"></i>บันทึกแบบร่าง
          </button>
          <button type="button" onclick="submitForm('ready')" class="rounded-xl bg-red-700 px-5 py-3 text-sm font-bold text-white shadow-sm transition-all duration-200 hover:bg-red-800">
            <i class="fa-solid fa-paper-plane mr-2"></i>ส่งให้อาจารย์ที่ปรึกษาตรวจ
          </button>
        </div>
      </form>
    </div>
  </aside>

  <!-- LEFT PANEL: LIVE PDF CANVAS PREVIEW -->
  <main class="document-preview-scroll flex min-w-0 w-full flex-grow flex-col items-center">
    <div class="w-full shrink-0 overflow-hidden rounded-2xl border border-slate-200 bg-slate-200 p-3 shadow-sm sm:p-5">
    <div id="pdf-wrap" class="relative mx-auto w-full select-none overflow-hidden border border-slate-300 bg-white shadow-xl">
      <canvas id="pdf-canvas" class="block h-auto w-full"></canvas>
      <div id="fields" class="absolute inset-0 pointer-events-none<?= ($isCustomR01 || $isCustomR12 || $isCustomR14 || $isCustomR16 || $isCustomR18 || $isCustomR24) ? ' r-document-fields' : '' ?><?= $isCustomR01 ? ' r01-document-fields' : '' ?><?= $isCustomR12 ? ' r12-document-fields' : '' ?><?= $isCustomR14 ? ' r14-document-fields' : '' ?><?= $isCustomR16 ? ' r16-document-fields' : '' ?><?= $isCustomR18 ? ' r18-document-fields' : '' ?><?= $isCustomR24 ? ' r24-document-fields' : '' ?>"<?= ($isCustomR01 || $isCustomR12 || $isCustomR14 || $isCustomR16 || $isCustomR18 || $isCustomR24) ? ' style="transform:translateY(3px)"' : '' ?>>
        
        <?php if ($isCustomR01): ?>
          <?php
          $previewPrefix = trim((string)($studentFullProfile['prefix_short'] ?? $studentFullProfile['prefix_name'] ?? ''));
          $previewPrefix = ['น.ส.'=>'นางสาว', 'น.ส'=>'นางสาว'][$previewPrefix] ?? $previewPrefix;
          $r01 = $r01Layout;
          ?>
          <?php $strike=$r01['prefix_strikes']['นาย']; ?><div id="prefix-strike-mr" class="absolute bg-blue-900" style="left:<?= $strike[0] ?>px;top:<?= $strike[1] ?>px;width:<?= $strike[2] ?>px;height:<?= $strike[3] ?>px;display:<?= $previewPrefix !== 'นาย' ? 'block' : 'none' ?>"></div>
          <?php $strike=$r01['prefix_strikes']['นาง']; ?><div id="prefix-strike-mrs" class="absolute bg-blue-900" style="left:<?= $strike[0] ?>px;top:<?= $strike[1] ?>px;width:<?= $strike[2] ?>px;height:<?= $strike[3] ?>px;display:<?= $previewPrefix !== 'นาง' ? 'block' : 'none' ?>"></div>
          <?php $strike=$r01['prefix_strikes']['นางสาว']; ?><div id="prefix-strike-miss" class="absolute bg-blue-900" style="left:<?= $strike[0] ?>px;top:<?= $strike[1] ?>px;width:<?= $strike[2] ?>px;height:<?= $strike[3] ?>px;display:<?= $previewPrefix !== 'นางสาว' ? 'block' : 'none' ?>"></div>
          <!-- Custom positioned text overlays for R01 -->
          <!-- Date splits -->
          <div id="p-date-day" class="r01-date-value absolute val-line select-none" style="left:<?= $r01['date']['day'][0] ?>px;top:<?= $r01['date']['day'][1] ?>px;"><?= htmlspecialchars($day) ?></div>
          <div id="p-date-month" class="r01-date-value absolute val-line select-none" style="left:<?= $r01['date']['month'][0] ?>px;top:<?= $r01['date']['month'][1] ?>px;"><?= htmlspecialchars($month) ?></div>
          <div id="p-date-year" class="r01-date-value absolute val-line select-none" style="left:<?= $r01['date']['year'][0] ?>px;top:<?= $r01['date']['year'][1] ?>px;"><?= htmlspecialchars($year) ?></div>

          <!-- Level checkboxes ticks -->
          <?php $lvl = $fieldValues['education_level'] ?? 'undergraduate'; ?>
          <?php foreach ($r01['education_ticks'] as $levelKey=>$tick): ?><div id="chk-<?= $levelKey ?>-tick" class="absolute font-bold text-blue-900 pointer-events-none" style="left:<?= $tick[0] ?>px;top:<?= $tick[1] ?>px;font-size:13px;display:<?= ($lvl === $levelKey) ? 'block' : 'none' ?>;">✓</div><?php endforeach; ?>

          <!-- Multi-line request text -->
          <?php $req_detail_field = $fields[3]; // request_detail index ?>
          <?php foreach ($r01['request_lines'] as $lineIndex=>$line): ?><div id="p-request-<?= $lineIndex+1 ?>" class="absolute val-line select-none flex items-start" style="left:<?= $line[0] ?>px;top:<?= $line[1] ?>px;width:<?= $line[2] ?>px;height:<?= $line[3] ?>px;"></div><?php endforeach; ?>

          <!-- Double signature boxes -->
          <div id="p-sig-name-ink" class="absolute val-line select-none flex items-start justify-center italic" style="left:<?= $r01['signature']['ink'][0] ?>px;top:<?= $r01['signature']['ink'][1] ?>px;width:<?= $r01['signature']['ink'][2] ?>px;height:<?= $r01['signature']['ink'][3] ?>px;"><?= htmlspecialchars($fieldValues['signature_name']) ?></div>
          <div id="p-sig-name-print" class="absolute val-line select-none flex items-start justify-center" style="left:<?= $r01['signature']['print'][0] ?>px;top:<?= $r01['signature']['print'][1] ?>px;width:<?= $r01['signature']['print'][2] ?>px;height:<?= $r01['signature']['print'][3] ?>px;white-space:nowrap;"><?= htmlspecialchars(trim(($studentFullProfile['prefix_short'] ?? $studentFullProfile['prefix_name'] ?? '')) . preg_replace('/^\s*(?:นาย|นางสาว|นาง|น\.ส\.)\s*/u', '', trim((string)$fieldValues['signature_name']))) ?></div>

          <!-- Remaining standard fields -->
          <?php
          // พิกัดเฉพาะ R.01 อ้างอิงเส้นประบน PDF ที่เรนเดอร์ด้วย scale 1.5
          $r01PreviewPositions = $r01['fields'];
          ?>
          <?php foreach ($fields as $field): 
            $fname = $field['field_name'];
            $fval = $fieldValues[$fname] ?? '';
            if (in_array($fname, ['request_date', 'education_level', 'request_detail', 'signature_name'])) continue;
            $previewPosition = $r01PreviewPositions[$fname] ?? [(float)$field['pos_x'], (float)$field['pos_y'], (float)$field['width'], (float)$field['height']];
            $style = 'left:' . $previewPosition[0] . 'px;top:' . $previewPosition[1] . 'px;width:' . $previewPosition[2] . 'px;height:' . $previewPosition[3] . 'px;line-height:1.2;';
          ?>
            <div id="p-field-<?= $field['field_id'] ?>" class="r01-field-<?= htmlspecialchars($fname) ?> absolute val-line select-none font-bold text-blue-900 text-xs flex items-start" style="<?= $style ?>">
              <?= htmlspecialchars($fval) ?>
            </div>
          <?php endforeach; ?>

        <?php elseif ($isCustomR12): ?>
          <?php
          $previewPrefix = trim((string)($studentFullProfile['prefix_short'] ?? $studentFullProfile['prefix_name'] ?? ''));
          $previewPrefix = ['น.ส.'=>'นางสาว', 'น.ส'=>'นางสาว'][$previewPrefix] ?? $previewPrefix;
          $r12 = $r12Layout;
          ?>
          <!-- R12 prefix positions measured from its PDF (not copied from R01). -->
          <?php $strike=$r12['prefix_strikes']['นาย']; ?><div id="prefix-strike-mr" class="absolute bg-blue-900" style="left:<?= $strike[0] ?>px;top:<?= $strike[1] ?>px;width:<?= $strike[2] ?>px;height:<?= $strike[3] ?>px;display:<?= $previewPrefix !== 'นาย' ? 'block' : 'none' ?>"></div>
          <?php $strike=$r12['prefix_strikes']['นาง']; ?><div id="prefix-strike-mrs" class="absolute bg-blue-900" style="left:<?= $strike[0] ?>px;top:<?= $strike[1] ?>px;width:<?= $strike[2] ?>px;height:<?= $strike[3] ?>px;display:<?= $previewPrefix !== 'นาง' ? 'block' : 'none' ?>"></div>
          <?php $strike=$r12['prefix_strikes']['นางสาว']; ?><div id="prefix-strike-miss" class="absolute bg-blue-900" style="left:<?= $strike[0] ?>px;top:<?= $strike[1] ?>px;width:<?= $strike[2] ?>px;height:<?= $strike[3] ?>px;display:<?= $previewPrefix !== 'นางสาว' ? 'block' : 'none' ?>"></div>
          <div id="r12-date-day" class="r12-date-value absolute val-line font-bold text-blue-900" style="left:<?= $r12['date']['day'][0] ?>px;top:<?= $r12['date']['day'][1] ?>px"><?= htmlspecialchars($day) ?></div>
          <div id="r12-date-month" class="r12-date-value absolute val-line font-bold text-blue-900" style="left:<?= $r12['date']['month'][0] ?>px;top:<?= $r12['date']['month'][1] ?>px"><?= htmlspecialchars($month) ?></div>
          <div id="r12-date-year" class="r12-date-value absolute val-line font-bold text-blue-900" style="left:<?= $r12['date']['year'][0] ?>px;top:<?= $r12['date']['year'][1] ?>px"><?= htmlspecialchars($year) ?></div>

          <?php
          $r12MainPositions = $r12['main_fields'];
          foreach ($r12MainPositions as $fname=>$position):
          ?>
            <div id="p-field-<?= $fieldIds[$fname] ?>" class="r12-main-value absolute val-line font-bold text-blue-900" style="left:<?= $position[0] ?>px;top:<?= $position[1] ?>px;width:<?= $position[2] ?>px;height:<?= $position[3] ?>px;font-size:15px;line-height:1.2"><?= htmlspecialchars($fieldValues[$fname]) ?></div>
          <?php endforeach; ?>

          <?php $r12Level=$fieldValues['education_level'] ?: 'undergraduate'; $r12LevelX=$r12['education_x']; ?>
          <div id="p-field-<?= $fieldIds['education_level'] ?>" class="absolute font-bold text-blue-900" style="left:<?= $r12LevelX[$r12Level] ?? $r12LevelX['undergraduate'] ?>px;top:<?= $r12['education_y'] ?>px;width:24px;height:24px;font-size:17px">✓</div>

          <?php $r12TableLeftShift=$r12['table_left_shift']; ?>
          <?php for ($rowNo=1; $rowNo<=4; $rowNo++): foreach (['semester','course_code','course_name','section','old_group','new_group'] as $baseName): $fname=$baseName.'_'.$rowNo; $fieldIndex=null; foreach ($fields as $candidate) { if ($candidate['field_name']===$fname) { $fieldIndex=$candidate; break; } } if (!$fieldIndex) continue; ?>
            <div id="p-field-<?= $fieldIds[$fname] ?>" class="r12-table-value absolute val-line text-center font-bold text-blue-900" style="left:<?= (float)$fieldIndex['pos_x'] - $r12TableLeftShift[$baseName] ?>px;top:<?= (float)$fieldIndex['pos_y'] + $r12['table_y_shift'] ?>px;width:<?= (float)$fieldIndex['width'] ?>px;height:<?= (float)$fieldIndex['height'] ?>px"><?= htmlspecialchars($fieldValues[$fname]) ?></div>
          <?php endforeach; endfor; ?>

          <div id="p-field-<?= $fieldIds['signature_name'] ?>" class="r12-signature-ink absolute val-line text-center text-blue-900" style="left:<?= $r12['signature']['ink'][0] ?>px;top:<?= $r12['signature']['ink'][1] ?>px;width:<?= $r12['signature']['ink'][2] ?>px;height:<?= $r12['signature']['ink'][3] ?>px;white-space:nowrap"><?= htmlspecialchars($fieldValues['signature_name']) ?></div>
          <div id="r12-signature-print" class="absolute val-line text-center font-bold text-blue-900" style="left:<?= $r12['signature']['print'][0] ?>px;top:<?= $r12['signature']['print'][1] ?>px;width:<?= $r12['signature']['print'][2] ?>px;height:<?= $r12['signature']['print'][3] ?>px;white-space:nowrap"><?= htmlspecialchars(trim(($studentFullProfile['prefix_short'] ?? $studentFullProfile['prefix_name'] ?? '')) . preg_replace('/^\s*(?:นาย|นางสาว|นาง|น\.ส\.)\s*/u', '', trim((string)$fieldValues['signature_name']))) ?></div>

        <?php else: ?>
          <!-- Generic fallback positioned overlays for other templates -->
          <?php if ($isCustomR14): ?>
            <?php
            $r14PreviewPrefix = trim((string)($studentFullProfile['prefix_short'] ?? $studentFullProfile['prefix_name'] ?? ''));
            $r14PreviewPrefix = ['น.ส.' => 'นางสาว', 'น.ส' => 'นางสาว'][$r14PreviewPrefix] ?? $r14PreviewPrefix;
            $r14SignatureValue = (string)($fieldValues['signature_name'] ?? $fieldValues['signer_name'] ?? $fieldValues['student_signature_name'] ?? '');
            $r14SignatureName = preg_replace('/^\s*(?:นาย|นางสาว|นาง|น\.ส\.)\s*/u', '', trim($r14SignatureValue));
            ?>
            <?php foreach ($r14Layout['prefix_strikes'] as $prefixLabel => $strike): ?>
              <div id="prefix-strike-<?= $prefixLabel === 'นาย' ? 'mr' : ($prefixLabel === 'นาง' ? 'mrs' : 'miss') ?>" class="absolute bg-blue-900" style="left:<?= $strike[0] ?>px;top:<?= $strike[1] ?>px;width:<?= $strike[2] ?>px;height:<?= $strike[3] ?>px;display:<?= $r14PreviewPrefix !== $prefixLabel ? 'block' : 'none' ?>"></div>
            <?php endforeach; ?>
            <div id="r14-date-day" class="absolute val-line font-bold text-blue-900" style="left:<?= $r14Layout['date']['day'][0] ?>px;top:<?= $r14Layout['date']['day'][1] ?>px;font-size:<?= $r14Layout['font_px'] ?>px"><?= htmlspecialchars($day) ?></div>
            <div id="r14-date-month" class="absolute val-line font-bold text-blue-900" style="left:<?= $r14Layout['date']['month'][0] ?>px;top:<?= $r14Layout['date']['month'][1] ?>px;font-size:<?= $r14Layout['font_px'] ?>px"><?= htmlspecialchars($month) ?></div>
            <div id="r14-date-year" class="absolute val-line font-bold text-blue-900" style="left:<?= $r14Layout['date']['year'][0] ?>px;top:<?= $r14Layout['date']['year'][1] ?>px;font-size:<?= $r14Layout['font_px'] ?>px"><?= htmlspecialchars($year) ?></div>
            <div id="r14-signature-ink" class="r14-signature-ink absolute val-line select-none text-center font-bold italic text-blue-900" style="left:<?= $r14Layout['signature_preview_ink'][0] ?>px;top:<?= $r14Layout['signature_preview_ink'][1] ?>px;width:<?= $r14Layout['signature_preview_ink'][2] ?>px;height:<?= $r14Layout['signature_preview_ink'][3] ?>px"><?= htmlspecialchars($r14SignatureName) ?></div>
          <?php endif; ?>
          <?php if ($isCustomR16): ?>
            <?php $r16PreviewPrefix = ['น.ส.'=>'นางสาว', 'น.ส'=>'นางสาว'][trim((string)($studentFullProfile['prefix_short'] ?? $studentFullProfile['prefix_name'] ?? ''))] ?? trim((string)($studentFullProfile['prefix_short'] ?? $studentFullProfile['prefix_name'] ?? '')); ?>
            <!-- Keep the approved R16 prefix strikes in one canonical unit (pt). -->
            <div id="prefix-strike-mr" class="absolute bg-blue-900" style="left:156.825pt;top:184.35pt;width:16.5pt;height:1.5pt;display:<?= $r16PreviewPrefix !== 'นาย' ? 'block' : 'none' ?>"></div>
            <div id="prefix-strike-mrs" class="absolute bg-blue-900" style="left:183.825pt;top:184.35pt;width:15.75pt;height:1.5pt;display:<?= $r16PreviewPrefix !== 'นาง' ? 'block' : 'none' ?>"></div>
            <div id="prefix-strike-miss" class="absolute bg-blue-900" style="left:209.325pt;top:184.35pt;width:30.75pt;height:1.5pt;display:<?= $r16PreviewPrefix !== 'นางสาว' ? 'block' : 'none' ?>"></div>
            <div id="r16-date-day" class="r16-date-value absolute val-line font-bold text-blue-900" style="left:<?= $r16Layout['date']['day'][0] ?>px;top:<?= $r16Layout['date']['day'][1] ?>px"><?= htmlspecialchars($day) ?></div>
            <div id="r16-date-month" class="r16-date-value absolute val-line font-bold text-blue-900" style="left:<?= $r16Layout['date']['month'][0] ?>px;top:<?= $r16Layout['date']['month'][1] ?>px"><?= htmlspecialchars($month) ?></div>
            <div id="r16-date-year" class="r16-date-value absolute val-line font-bold text-blue-900" style="left:<?= $r16Layout['date']['year'][0] ?>px;top:<?= $r16Layout['date']['year'][1] ?>px"><?= htmlspecialchars($year) ?></div>
          <?php endif; ?>
          <?php if ($isCustomR18): ?>
            <!-- R18 uses the same reviewed Thai date format as R01/R12/R16. -->
            <?php
            $r18PreviewPrefix = trim((string)($studentFullProfile['prefix_short'] ?? $studentFullProfile['prefix_name'] ?? ''));
            $r18PreviewPrefix = ['น.ส.' => 'นางสาว', 'น.ส' => 'นางสาว'][$r18PreviewPrefix] ?? $r18PreviewPrefix;
            ?>
            <div id="prefix-strike-mr" class="absolute bg-blue-900" style="left:154.5px;top:240px;width:21.5px;height:2px;display:<?= $r18PreviewPrefix !== 'นาย' ? 'block' : 'none' ?>"></div>
            <div id="prefix-strike-mrs" class="absolute bg-blue-900" style="left:181.4px;top:240px;width:19.9px;height:2px;display:<?= $r18PreviewPrefix !== 'นาง' ? 'block' : 'none' ?>"></div>
            <div id="prefix-strike-miss" class="absolute bg-blue-900" style="left:206.7px;top:240px;width:40.1px;height:2px;display:<?= $r18PreviewPrefix !== 'นางสาว' ? 'block' : 'none' ?>"></div>
            <div id="r18-date-day" class="absolute val-line font-bold text-blue-900" style="left:490px;top:132px;font-size:14px"><?= htmlspecialchars($day) ?></div>
            <div id="r18-date-month" class="absolute val-line font-bold text-blue-900" style="left:562px;top:132px;font-size:14px"><?= htmlspecialchars($month) ?></div>
            <div id="r18-date-year" class="absolute val-line font-bold text-blue-900" style="left:675px;top:132px;font-size:14px"><?= htmlspecialchars($year) ?></div>
          <?php endif; ?>
          <?php if ($isCustomR24): ?>
            <!-- R24 date follows the shared Thai day/month/B.E. format. -->
            <?php
            $r24StudentPrefix = trim((string)($studentFullProfile['prefix_short'] ?? $studentFullProfile['prefix_name'] ?? ''));
            $r24StudentPrefix = ['น.ส.' => 'นางสาว', 'น.ส' => 'นางสาว'][$r24StudentPrefix] ?? $r24StudentPrefix;
            ?>
            <div id="prefix-strike-mr" class="absolute bg-blue-900" style="left:188px;top:284.5px;width:23px;height:2px;display:<?= $r24StudentPrefix !== 'นาย' ? 'block' : 'none' ?>"></div>
            <div id="prefix-strike-mrs" class="absolute bg-blue-900" style="left:217px;top:284.5px;width:24px;height:2px;display:<?= $r24StudentPrefix !== 'นาง' ? 'block' : 'none' ?>"></div>
            <div id="prefix-strike-miss" class="absolute bg-blue-900" style="left:247px;top:284.5px;width:41px;height:2px;display:<?= $r24StudentPrefix !== 'นางสาว' ? 'block' : 'none' ?>"></div>
            <div id="r24-date-day" class="absolute val-line font-bold text-blue-900" style="left:485px;top:188.5px"><?= htmlspecialchars($day) ?></div>
            <div id="r24-date-month" class="absolute val-line font-bold text-blue-900" style="left:590px;top:188.5px"><?= htmlspecialchars($month) ?></div>
            <div id="r24-date-year" class="absolute val-line font-bold text-blue-900" style="left:723px;top:188.5px"><?= htmlspecialchars($year) ?></div>
          <?php endif; ?>
          <?php foreach ($fields as $field): 
            $fname = $field['field_name'];
            $fval = $fieldValues[$fname] ?? '';
            if ((int)$field['page_no'] !== 1) continue;
            if (in_array($fname, ['instructor_employee_id', 'teacher_employee_id'], true)) continue;
            if (($isCustomR14 || $isCustomR16 || $isCustomR18 || $isCustomR24) && $fname === 'request_date') continue;
            $r24GuardianPrefix = '';
            if ($isCustomR24 && $fname === 'guardian_name') {
              if (preg_match('/^\s*(นาย|นางสาว|นาง|น\.ส\.)\s*/u', (string)$fval, $guardianPrefixMatch)) {
                $r24GuardianPrefix = $guardianPrefixMatch[1] === 'น.ส.' ? 'นางสาว' : $guardianPrefixMatch[1];
                $fval = preg_replace('/^\s*(?:นาย|นางสาว|นาง|น\.ส\.)\s*/u', '', (string)$fval);
              }
            }
            $isSignatureField = in_array($fname, ['signature_name', 'signer_name', 'student_signature_name'], true);
            if ($isCustomR14 && $isSignatureField) {
              $field['pos_x'] = $r14Layout['signature_preview_print'][0];
              $field['pos_y'] = $r14Layout['signature_preview_print'][1];
              $field['width'] = $r14Layout['signature_preview_print'][2];
              $field['height'] = $r14Layout['signature_preview_print'][3];
              $signaturePrefix = trim((string)($studentFullProfile['prefix_short'] ?? $studentFullProfile['prefix_name'] ?? ''));
              $signaturePrefix = ['น.ส.' => 'นางสาว', 'น.ส' => 'นางสาว'][$signaturePrefix] ?? $signaturePrefix;
              $fval = $signaturePrefix . preg_replace('/^\s*(?:นาย|นางสาว|นาง|น\.ส\.)\s*/u', '', trim((string)$fval));
            }
            if ($isCustomR24 && $fname === 'signature_name') {
              $field['pos_x'] = 469.5;
              $field['pos_y'] = 527.5;
              $fval = preg_replace('/^\s*(?:นาย|นางสาว|นาง|น\.ส\.)\s*/u', '', trim((string)$fval));
            }
            if ($isCustomR16 && !$isSignatureField) {
              $field['pos_y'] = (float)$field['pos_y'] - 3;
            }
            if ($isSignatureField && !$isCustomR14 && !$isCustomR16 && !$isCustomR18 && !$isCustomR24) {
              $fval = trim(($studentFullProfile['prefix_short'] ?? $studentFullProfile['prefix_name'] ?? '') . ' ' . $fval);
            }
            if ($isCustomR18 && $isSignatureField) {
              $fval = preg_replace('/^\s*(?:นาย|นางสาว|นาง|น\.ส\.)\s*/u', '', trim((string)$fval));
              $field['pos_y'] = 560;
            }
            if ($isCustomR18 && $fname === 'student_count') {
              $field['pos_x'] = (float)$field['pos_x'] + 10;
              $field['pos_y'] = (float)$field['pos_y'] + 4;
            }
            if ($isCustomR18 && in_array($fname, ['request_semester', 'request_academic_year'], true)) {
              $field['pos_x'] = (float)$field['pos_x'] + 8.5;
            }
            $isEducationLevel = $fname === 'education_level';
            if ($isEducationLevel) {
              $educationX = $isCustomR14
                ? $r14Layout['education_x']
                : ($isCustomR16
                ? ['certificate' => 224.4, 'diploma' => 316.8, 'undergraduate' => 412, 'master' => 534.7, 'doctor' => 658.0]
                : ($isCustomR18
                  ? ['certificate' => 142.6, 'diploma' => 191.7, 'undergraduate' => 241.3, 'graduate' => 323.9, 'master' => 323.9, 'doctor' => 323.9]
                  : ($isCustomR24
                    ? ['certificate' => 208, 'diploma' => 310.5, 'undergraduate' => 410.5, 'master' => 561.5, 'doctor' => 683]
                    : ['certificate' => 300, 'diploma' => 420, 'undergraduate' => 428, 'master' => 555, 'doctor' => 708])));
              $field['pos_x'] = $educationX[$fval] ?? ($isCustomR14 ? $r14Layout['education_x']['undergraduate'] : ($isCustomR18 ? 241.3 : 428));
              if ($isCustomR14) $field['pos_y'] = $r14Layout['education_y'];
              $field['width'] = 24;
              $fval = '✓';
            }
            if ($isCustomR14 && $fname === 'request_type') {
              $requestTypePosition = $r14Layout['request_type'][$fval] ?? $r14Layout['request_type']['less'];
              $field['pos_x'] = $requestTypePosition[0];
              $field['pos_y'] = $requestTypePosition[1];
              $field['width'] = 24;
              $field['height'] = 24;
              $fval = '✓';
            }
            if ($isCustomR24 && $fname === 'leave_type') {
              $field['pos_x'] = 255;
              $field['pos_y'] = $fval === 'personal' ? 421.5 : 361.5;
              $field['width'] = 24;
              $fval = '✓';
            }
            if ($isCustomR24 && $fname === 'guardian_relationship') {
              $relationshipOtherPreview = '';
              if (str_starts_with((string)$fval, 'other:')) {
                $relationshipOtherPreview = trim(substr((string)$fval, 6));
                $fval = 'other';
              }
              $relationshipX = ['father' => 565, 'mother' => 622, 'other' => 685];
              $field['pos_x'] = $relationshipX[$fval] ?? 565;
              $field['pos_y'] = 637;
              $field['width'] = 24;
              $fval = $fval !== '' ? '✓' : '';
            }
            if ($isCustomR24 && in_array($fname, ['leave_from', 'leave_to'], true) && $fval !== '') {
              $leaveDate = DateTime::createFromFormat('Y-m-d', (string)$fval);
              if ($leaveDate) {
                $fval = $leaveDate->format('d/m/') . ((int)$leaveDate->format('Y') + 543);
              }
            }
            if ($isCustomR18 && $fname === 'study_type') {
              $studyTypeX = ['normal' => 523.25, 'associate' => 579.25, 'special' => 640.25];
              $field['pos_x'] = $studyTypeX[$fval] ?? 523.25;
              $field['pos_y'] = 253;
              $field['width'] = 24;
              $fval = '✓';
            }
            $isR16TableField = $isCustomR16 && preg_match('/^(student_number|student_name|department|year_level|registration_status)_[1-6]$/', $fname);
            $isR16Status = $isCustomR16 && preg_match('/^registration_status_[1-6]$/', $fname);
            if ($isCustomR16 && in_array($fname, ['faculty', 'curriculum'], true)) {
              $field['pos_x'] = (float)$field['pos_x'] + 8;
              $field['pos_y'] = (float)$field['pos_y'] + 6;
            }
            if ($isCustomR16 && $fname === 'student_name') {
              $field['pos_x'] = (float)$field['pos_x'] + 16;
              $field['pos_y'] = (float)$field['pos_y'] + 6;
            }
            if ($isCustomR16 && $fname === 'student_number') {
              $field['pos_x'] = (float)$field['pos_x'] + 8;
              $field['pos_y'] = (float)$field['pos_y'] + 6;
            }
            if ($isCustomR16 && in_array($fname, ['course_code', 'course_name', 'section', 'reason', 'current_enrollment_count', 'additional_seat_count'], true)) {
              $field['pos_x'] = (float)$field['pos_x'] + 16;
              if ($fname !== 'reason') {
                $field['pos_y'] = (float)$field['pos_y'] + 6;
              } else {
                $field['pos_y'] = (float)$field['pos_y'] + 16.8 + (float)$r16Layout['preview_reason_offset_y_px'];
              }
            }
            if ($isCustomR16 && $isSignatureField) {
              $field['pos_x'] = (float)$field['pos_x'] - 25;
            }
            if ($isR16Status) {
              $statusX = ['normal' => 654, 'repeat' => 726, 'regrade' => 803];
              $field['pos_x'] = $statusX[$fval] ?? 654;
              $field['width'] = 20;
              $fval = $fval !== '' ? '✓' : '';
            }
            $fallbackFontSize = $isR16TableField ? 12 : ($isSignatureField ? 12 : (float)$field['font_size']);
            $signatureStyle = $isSignatureField ? 'white-space:nowrap;text-align:center;justify-content:center;' : '';
            $style = 'left:' . (float)$field['pos_x'] . 'px;top:' . (float)$field['pos_y'] . 'px;width:' . (float)$field['width'] . 'px;height:' . (float)$field['height'] . 'px;font-size:' . $fallbackFontSize . 'px;line-height:1.2;' . $signatureStyle;
            $r16Class = ($isR16Status || $isEducationLevel)
              ? ' r16-mark'
              : ($isR16TableField ? ' r16-table-value' : ($isSignatureField ? ' r16-signature-ink' : ' r16-main-value'));
            $r14Class = ($isCustomR14 && $isSignatureField) ? ' r14-signature-print' : '';
          ?>
            <?php if ($isCustomR24 && $fname === 'guardian_name'): ?>
              <div id="guardian-prefix-strike-mr" class="absolute bg-blue-900" style="left:123.5px;top:650.5px;width:27px;height:2px;display:<?= $r24GuardianPrefix !== 'นาย' ? 'block' : 'none' ?>"></div>
              <div id="guardian-prefix-strike-mrs" class="absolute bg-blue-900" style="left:161.5px;top:650.5px;width:25px;height:2px;display:<?= $r24GuardianPrefix !== 'นาง' ? 'block' : 'none' ?>"></div>
              <div id="guardian-prefix-strike-miss" class="absolute bg-blue-900" style="left:196.5px;top:650.5px;width:43px;height:2px;display:<?= $r24GuardianPrefix !== 'นางสาว' ? 'block' : 'none' ?>"></div>
            <?php endif; ?>
            <?php if ($isCustomR24 && $fname === 'guardian_relationship'): ?>
              <div id="r24-relationship-other-preview" class="absolute val-line font-bold text-blue-900" style="left:750px;top:634px;width:150px;height:24px"><?= htmlspecialchars($relationshipOtherPreview ?? '') ?></div>
            <?php endif; ?>
            <div id="p-field-<?= $field['field_id'] ?>" class="absolute val-line select-none font-bold text-blue-900 text-xs flex items-start<?= $r16Class ?><?= $r14Class ?>" style="<?= $style ?>">
              <?= htmlspecialchars($fval) ?>
            </div>
          <?php endforeach; ?>
          <?php if ($isCustomR24): ?>
            <?php $r24SignatureName = trim((string)($studentFullProfile['firstname'] ?? '') . ' ' . (string)($studentFullProfile['lastname'] ?? '')); ?>
            <div id="r24-signature-print" class="absolute val-line text-center font-bold text-blue-900" style="left:469.5px;top:562.5px;width:260px;height:24px;white-space:nowrap"><?= htmlspecialchars($r24StudentPrefix . $r24SignatureName) ?></div>
          <?php endif; ?>
          <?php if ($isCustomR18): ?>
            <?php $r18SignatureName = preg_replace('/^\s*(?:นาย|นางสาว|นาง|น\.ส\.)\s*/u', '', trim((string)($fieldValues['signature_name'] ?? ''))); ?>
            <div id="r18-signature-print" class="absolute val-line text-center font-bold text-blue-900" style="left:380.6px;top:586px;width:260px;height:24px;font-size:12px;white-space:nowrap"><?= htmlspecialchars($r18PreviewPrefix . $r18SignatureName) ?></div>
          <?php endif; ?>
          <?php if ($isCustomR16): ?>
            <?php $r16SignatureName = preg_replace('/^\s*(?:นาย|นางสาว|นาง|น\.ส\.)\s*/u', '', trim((string)($fieldValues['signature_name'] ?? ''))); ?>
            <div id="r16-signature-print" class="absolute val-line text-center font-bold text-blue-900" style="left:<?= $r16Layout['signature_print'][0] ?>px;top:<?= $r16Layout['signature_print'][1] ?>px;width:<?= $r16Layout['signature_print'][2] ?>px;height:<?= $r16Layout['signature_print'][3] ?>px;white-space:nowrap"><?= htmlspecialchars($r16PreviewPrefix . $r16SignatureName) ?></div>
          <?php endif; ?>
        <?php endif; ?>

      </div>
    </div>

    <?php if ($isCustomR18): ?>
      <?php for ($previewPage = 2; $previewPage <= 3; $previewPage++): ?>
        <div id="pdf-wrap-<?= $previewPage ?>" class="relative mx-auto mt-6 w-full shrink-0 select-none overflow-hidden border border-slate-300 bg-white shadow-xl">
          <canvas id="pdf-canvas-<?= $previewPage ?>" class="block h-auto w-full"></canvas>
          <div class="r-document-fields r18-document-fields pointer-events-none absolute inset-0" style="transform:translateY(3px)">
            <?php foreach ($fields as $field): ?>
              <?php
              if ((int)$field['page_no'] !== $previewPage) continue;
              $pageFieldName = (string)$field['field_name'];
              if (in_array($pageFieldName, ['instructor_employee_id', 'teacher_employee_id'], true)) continue;
              $pageFieldValue = $fieldValues[$pageFieldName] ?? '';
              $pageFieldStyle = 'left:' . (float)$field['pos_x'] . 'px;top:' . (float)$field['pos_y'] . 'px;width:' . (float)$field['width'] . 'px;height:' . (float)$field['height'] . 'px;font-size:' . (float)$field['font_size'] . 'px;line-height:1.2;';
              ?>
              <div id="p-field-<?= (int)$field['field_id'] ?>" class="val-line absolute flex select-none items-start font-bold text-blue-900" style="<?= $pageFieldStyle ?>">
                <?= htmlspecialchars($pageFieldValue) ?>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endfor; ?>
    <?php endif; ?>
    </div>
  </main>

  </div>

  <!-- PDF.js library -->
  <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>

  <!-- Real-time updates script -->
  <script>
    pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';

    // Render PDF onto Canvas
    const pdfUrl = '../uploads/pdf/<?= rawurlencode($document['pdf_template']) ?>';
    const loadingTask = pdfjsLib.getDocument(pdfUrl);

    loadingTask.promise.then(async function(pdf) {
      const previewPageCount = <?= $isCustomR18 ? 'Math.min(3, pdf.numPages)' : '1' ?>;
      for (let pageNumber = 1; pageNumber <= previewPageCount; pageNumber++) {
        const page = await pdf.getPage(pageNumber);
        // Keep the source resolution used by the document-coordinate data.
        // CSS then fits the A4 preview to its available panel width.
        const scale = 1.5;
        const viewport = page.getViewport({ scale: scale });
        const canvas = document.getElementById(pageNumber === 1 ? 'pdf-canvas' : `pdf-canvas-${pageNumber}`);
        if (!canvas) continue;
        const context = canvas.getContext('2d');
        
        canvas.width = viewport.width;
        canvas.height = viewport.height;
        
        const wrap = document.getElementById(pageNumber === 1 ? 'pdf-wrap' : `pdf-wrap-${pageNumber}`);
        wrap.style.width = '100%';
        wrap.style.aspectRatio = `${viewport.width} / ${viewport.height}`;
        canvas.style.width = '100%';
        canvas.style.height = 'auto';
        canvas.style.display = 'block';

        // Field coordinates are recorded at scale 1.5.  Resize their layer
        // to the same responsive width as the canvas so values stay aligned.
        requestAnimationFrame(() => {
          const overlayScale = wrap.getBoundingClientRect().width / viewport.width;
          wrap.querySelectorAll('.absolute.inset-0').forEach((overlay) => {
            const translateY = parseFloat((overlay.style.transform || '').match(/translateY\(([-.\d]+)px\)/)?.[1] || '0');
            overlay.style.transform = `translateY(${translateY * overlayScale}px) scale(${overlayScale})`;
            overlay.style.transformOrigin = 'top left';
          });
        });
        
        const renderContext = {
          canvasContext: context,
          viewport: viewport
        };
        
        await page.render(renderContext).promise;
      }
    });

    const studentPrefixSelect = document.getElementById('student-prefix-select');
    function selectedStudentPrefix() {
      if (!studentPrefixSelect) return '';
      const option = studentPrefixSelect.options[studentPrefixSelect.selectedIndex];
      const prefix = (option?.dataset.prefix || option?.textContent || '').trim();
      return (prefix === 'น.ส.' || prefix === 'น.ส') ? 'นางสาว' : prefix;
    }
    <?php if ($isCustomR14 && isset($fieldIds['signature_name'])): ?>
    function fitR14SignaturePrint() {
      const elements = [
        document.getElementById('r14-signature-ink'),
        document.getElementById('p-field-<?= (int)$fieldIds['signature_name'] ?>')
      ].filter(Boolean);
      elements.forEach(element => {
        element.style.setProperty('font-size', '<?= (float)$r14Layout['signature_font_px'] ?>px', 'important');
        element.style.transform = 'none';
        element.style.transformOrigin = 'center top';
        const range = document.createRange();
        range.selectNodeContents(element);
        const textWidth = range.getBoundingClientRect().width;
        const style = getComputedStyle(element);
        const availableWidth = element.clientWidth
          - parseFloat(style.paddingLeft || 0)
          - parseFloat(style.paddingRight || 0);
        if (textWidth > availableWidth && textWidth > 0) {
          element.style.transform = `scaleX(${availableWidth / textWidth})`;
        }
      });
    }
    <?php endif; ?>
    function updatePrefixPreview() {
      const prefix = selectedStudentPrefix();
      const strikes = {
        'นาย': document.getElementById('prefix-strike-mr'),
        'นาง': document.getElementById('prefix-strike-mrs'),
        'นางสาว': document.getElementById('prefix-strike-miss')
      };
      Object.entries(strikes).forEach(([label, element]) => {
        if (element) element.style.display = label === prefix ? 'none' : 'block';
      });
      const r18SignaturePrint = document.getElementById('r18-signature-print');
      const r18SignatureInput = document.getElementById('fallback-signature_name');
      if (r18SignaturePrint && r18SignatureInput) {
        const signatureName = r18SignatureInput.value.replace(/^\s*(?:นาย|นางสาว|นาง|น\.ส\.)\s*/u, '');
        r18SignaturePrint.innerText = `${prefix}${signatureName}`.trim();
      }
      const signatureInput = document.getElementById('in-sig-name') || document.getElementById('fallback-signature_name');
      const signatureName = (signatureInput?.value || '').replace(/^\s*(?:นาย|นางสาว|นาง|น\.ส\.)\s*/u, '');
      const printedName = document.getElementById('p-sig-name-print') || document.getElementById('r12-signature-print') || document.getElementById('r16-signature-print');
      if (printedName) printedName.innerText = `${prefix}${signatureName}`.trim();
      const r24SignaturePrint = document.getElementById('r24-signature-print');
       if (r24SignaturePrint) r24SignaturePrint.innerText = `${prefix}${signatureName}`.trim();
      <?php if ($isCustomR14 && isset($fieldIds['signature_name'])): ?>
      const r14SignaturePrint = document.getElementById('p-field-<?= (int)$fieldIds['signature_name'] ?>');
      const r14SignatureInk = document.getElementById('r14-signature-ink');
      if (r14SignatureInk) r14SignatureInk.innerText = signatureName;
      if (r14SignaturePrint) {
        r14SignaturePrint.innerText = `${prefix}${signatureName}`.trim();
        requestAnimationFrame(fitR14SignaturePrint);
      }
      <?php endif; ?>
    }
    if (studentPrefixSelect) {
      studentPrefixSelect.addEventListener('change', updatePrefixPreview);
      updatePrefixPreview();
    }

    <?php if ($isCustomR01): ?>
      // R01 specific real-time bindings
      const inDate = document.getElementById('in-date');
      const inMonth = document.getElementById('in-month');
      const inYear = document.getElementById('in-year');
      const inSubject = document.getElementById('in-subject');
      const inTo = document.getElementById('in-to');
      const inName = document.getElementById('in-name');
      const inStudentId = document.getElementById('in-student-id');
      const inLevel = document.getElementById('in-level');
      const inFaculty = document.getElementById('in-faculty');
      const inCurriculum = document.getElementById('in-curriculum');
      const inPhone = document.getElementById('in-phone');
      const inRequest = document.getElementById('in-request');
      const inSigName = document.getElementById('in-sig-name');
      const realRequestDate = document.getElementById('real-request-date');

      // Preview bindings mapping
      function bindText(inputEl, previewId) {
        if (!inputEl) return;
        const el = document.getElementById(previewId);
        if (el) {
          inputEl.addEventListener('input', () => {
            el.innerText = inputEl.value;
          });
        }
      }

      bindText(inSubject, 'p-field-<?= $fieldIds['subject'] ?? 0 ?>');
      bindText(inTo, 'p-field-<?= $fieldIds['recipient'] ?? 0 ?>');
      bindText(inName, 'p-field-<?= $fieldIds['student_name'] ?? 0 ?>');
      bindText(inStudentId, 'p-field-<?= $fieldIds['student_number'] ?? 0 ?>');
      bindText(inFaculty, 'p-field-<?= $fieldIds['faculty'] ?? 0 ?>');
      bindText(inCurriculum, 'p-field-<?= $fieldIds['curriculum'] ?? 0 ?>');
      bindText(inPhone, 'p-field-<?= $fieldIds['telephone'] ?? 0 ?>');

      // Date changes update
      function updateDatePreview() {
        document.getElementById('p-date-day').innerText = inDate.value;
        document.getElementById('p-date-month').innerText = inMonth.value;
        document.getElementById('p-date-year').innerText = inYear.value;

        // Update real YYYY-MM-DD hidden input
        const dVal = inDate.value.trim();
        const mName = inMonth.value.trim();
        const yVal = inYear.value.trim();
        
        const months_th = {
          'มกราคม': '01', 'กุมภาพันธ์': '02', 'มีนาคม': '03', 'เมษายน': '04',
          'พฤษภาคม': '05', 'มิถุนายน': '06', 'กรกฎาคม': '07', 'สิงหาคม': '08',
          'กันยายน': '09', 'ตุลาคม': '10', 'พฤศจิกายน': '11', 'ธันวาคม': '12'
        };
        
        let mVal = months_th[mName] || '01';
        let yEng = parseInt(yVal) - 543;
        if (isNaN(yEng)) yEng = new Date().getFullYear();
        let dStr = dVal.padStart(2, '0');
        if (isNaN(parseInt(dStr))) dStr = '01';
        
        realRequestDate.value = `${yEng}-${mVal}-${dStr}`;
      }

      if (inDate) inDate.addEventListener('input', updateDatePreview);
      if (inMonth) inMonth.addEventListener('input', updateDatePreview);
      if (inYear) inYear.addEventListener('input', updateDatePreview);

      // Level checkmark display
      const checkIds = ['chk-certificate-tick', 'chk-diploma-tick', 'chk-undergraduate-tick', 'chk-master-tick', 'chk-doctor-tick'];
      function updateLevelTicks() {
        if (!inLevel) return;
        const selected = inLevel.value;
        checkIds.forEach(id => {
          document.getElementById(id).style.display = 'none';
        });
        let targetId = 'chk-undergraduate-tick';
        if (selected === 'certificate') targetId = 'chk-certificate-tick';
        else if (selected === 'diploma') targetId = 'chk-diploma-tick';
        else if (selected === 'master') targetId = 'chk-master-tick';
        else if (selected === 'doctor') targetId = 'chk-doctor-tick';
        
        const targetEl = document.getElementById(targetId);
        if (targetEl) targetEl.style.display = 'block';
      }
      if (inLevel) {
        inLevel.addEventListener('change', updateLevelTicks);
        updateLevelTicks();
      }

      // Multi-line request text splitting
      function updateRequestDetailPreview() {
        if (!inRequest) return;
        const characters = Array.from(inRequest.value || '');
        const limits = [46, 62, 62, 62];
        let offset = 0;
        limits.forEach((limit, index) => {
          document.getElementById(`p-request-${index + 1}`).innerText = characters.slice(offset, offset + limit).join('');
          offset += limit;
        });
      }
      if (inRequest) {
        inRequest.addEventListener('input', updateRequestDetailPreview);
        updateRequestDetailPreview();
      }

      // Signature Name preview
      if (inSigName) {
        inSigName.addEventListener('input', () => {
          document.getElementById('p-sig-name-ink').innerText = inSigName.value;
          const signatureName = inSigName.value.replace(/^\s*(?:นาย|นางสาว|นาง|น\.ส\.)\s*/u, '');
          document.getElementById('p-sig-name-print').innerText = `${selectedStudentPrefix()}${signatureName}`.trim();
        });
      }

    <?php else: ?>
      // Fallback binding for generic templates
      <?php if ($isCustomR16): ?>
      const r16TableBaseFontPx = <?= json_encode((float)$r16Layout['table_font_px']) ?>;
      function fitR16TableValue(element) {
        if (!element?.classList.contains('r16-table-value')) return;
        let size = r16TableBaseFontPx;
        element.style.setProperty('font-size', `${size}px`, 'important');
        while (element.scrollWidth > element.clientWidth && size > 9) {
          size -= 0.5;
          element.style.setProperty('font-size', `${size}px`, 'important');
        }
      }
      const fitAllR16TableValues = () => document.querySelectorAll('.r16-table-value').forEach(fitR16TableValue);
      function fitR16SignaturePrint() {
        const element = document.getElementById('r16-signature-print');
        if (!element) return;
        let size = <?= json_encode((float)$r16Layout['font_px']) ?>;
        const minimumSize = <?= json_encode((float)$r16Layout['signature_min_font_px']) ?>;
        element.style.setProperty('font-size', `${size}px`, 'important');
        while (element.scrollWidth > element.clientWidth && size > minimumSize) {
          size -= 0.25;
          element.style.setProperty('font-size', `${size}px`, 'important');
        }
      }
      requestAnimationFrame(() => {
        fitAllR16TableValues();
        fitR16SignaturePrint();
      });
      <?php endif; ?>
      const fallbackInputs = document.querySelectorAll('.fallback-input');
      const r24LeaveTypeInput = document.getElementById('fallback-leave_type');
      const syncR24LeaveReasonFields = () => {
        if (!r24LeaveTypeInput) return;
        const leaveType = r24LeaveTypeInput.value === 'personal' ? 'personal' : 'sick';
        document.querySelectorAll('[data-r24-reason]').forEach(container => {
          const isSelectedReason = container.getAttribute('data-r24-reason') === leaveType;
          container.classList.toggle('hidden', !isSelectedReason);
          container.querySelectorAll('input, textarea, select').forEach(input => {
            input.disabled = !isSelectedReason;
          });
        });
      };
      r24LeaveTypeInput?.addEventListener('change', syncR24LeaveReasonFields);
      syncR24LeaveReasonFields();
      fallbackInputs.forEach(input => {
        const fid = input.getAttribute('data-fid');
        const pEl = document.getElementById('p-field-' + fid);
        if (pEl) {
          input.addEventListener('input', () => {
            if (input.id === 'fallback-education_level') {
              const educationX = <?= $isCustomR14 ? json_encode($r14Layout['education_x']) : ($isCustomR16 ? "{certificate:224.4,diploma:316.8,undergraduate:412,master:534.7,doctor:658.0}" : ($isCustomR18 ? "{certificate:142.6,diploma:191.7,undergraduate:241.3,graduate:323.9,master:323.9,doctor:323.9}" : ($isCustomR24 ? "{certificate:208,diploma:310.5,undergraduate:410.5,master:561.5,doctor:683}" : "{certificate:200,diploma:312,undergraduate:420,master:567,doctor:715}"))) ?>;
              pEl.style.left = (educationX[input.value] || <?= $isCustomR14 ? json_encode($r14Layout['education_x']['undergraduate']) : ($isCustomR16 ? '412' : ($isCustomR18 ? '241.3' : ($isCustomR24 ? '410.5' : '420'))) ?>) + 'px';
              pEl.innerText = '✓';
            } else if (input.id === 'fallback-request_type') {
              const requestTypePosition = <?= json_encode($isCustomR14 ? $r14Layout['request_type'] : [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
              const position = requestTypePosition[input.value] || requestTypePosition.less;
              if (position) {
                pEl.style.left = `${position[0]}px`;
                pEl.style.top = `${position[1]}px`;
              }
              pEl.innerText = input.value ? '✓' : '';
            } else if (input.id === 'fallback-study_type') {
              const studyTypeX = {normal:523.25,associate:579.25,special:640.25};
              pEl.style.left = (studyTypeX[input.value] || 523.25) + 'px';
              pEl.innerText = '✓';
            } else if (input.id === 'fallback-leave_type') {
              const leaveTypePosition = {
                sick: {left:255, top:361.5},
                personal: {left:255, top:421.5}
              };
              const position = leaveTypePosition[input.value] || leaveTypePosition.sick;
              pEl.style.left = `${position.left}px`;
              pEl.style.top = `${position.top}px`;
              pEl.innerText = input.value ? '✓' : '';
            } else if (input.id === 'fallback-guardian_relationship') {
              const relationshipX = {father:565,mother:622,other:685};
              const relationshipValue = input.value.startsWith('other:') ? 'other' : input.value;
              pEl.style.left = `${relationshipX[relationshipValue] || 565}px`;
              pEl.style.top = '637px';
              pEl.innerText = relationshipValue ? '✓' : '';
            } else if (input.id === 'fallback-leave_from' || input.id === 'fallback-leave_to') {
              if (!input.value) {
                pEl.innerText = '';
              } else {
                const leaveDate = new Date(input.value + 'T00:00:00');
                pEl.innerText = Number.isNaN(leaveDate.getTime())
                  ? input.value
                  : `${String(leaveDate.getDate()).padStart(2, '0')}/${String(leaveDate.getMonth() + 1).padStart(2, '0')}/${leaveDate.getFullYear() + 543}`;
              }
            } else if (input.id.startsWith('fallback-registration_status_')) {
              const statusX = {normal:654,repeat:726,regrade:803};
              pEl.style.left = (statusX[input.value] || 654) + 'px';
              pEl.innerText = input.value ? '✓' : '';
            } else if (input.id === 'fallback-signature_name') {
              const r12SignaturePrint = document.getElementById('r12-signature-print');
              const r16SignaturePrint = document.getElementById('r16-signature-print');
              const r14SignatureInk = document.getElementById('r14-signature-ink');
              if (r14SignatureInk) {
                const signatureName = input.value.replace(/^\s*(?:นาย|นางสาว|นาง|น\.ส\.)\s*/u, '');
                r14SignatureInk.innerText = signatureName;
                pEl.innerText = `${selectedStudentPrefix()}${signatureName}`.trim();
              } else if (r12SignaturePrint) {
                pEl.innerText = input.value;
                const signatureName = input.value.replace(/^\s*(?:นาย|นางสาว|นาง|น\.ส\.)\s*/u, '');
                r12SignaturePrint.innerText = `${selectedStudentPrefix()}${signatureName}`.trim();
              } else if (r16SignaturePrint) {
                const signatureName = input.value.replace(/^\s*(?:นาย|นางสาว|นาง|น\.ส\.)\s*/u, '');
                pEl.innerText = signatureName;
                r16SignaturePrint.innerText = <?= json_encode($r16PreviewPrefix ?? '', JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?> + signatureName;
              } else if (document.getElementById('r18-signature-print')) {
                const signatureName = input.value.replace(/^\s*(?:นาย|นางสาว|นาง|น\.ส\.)\s*/u, '');
                pEl.innerText = signatureName;
                document.getElementById('r18-signature-print').innerText = `${selectedStudentPrefix()}${signatureName}`.trim();
              } else {
                pEl.innerText = input.value;
              }
            } else {
              pEl.innerText = input.value;
            }
            <?php if ($isCustomR16): ?>
            requestAnimationFrame(() => {
              fitR16TableValue(pEl);
              fitR16SignaturePrint();
            });
            <?php endif; ?>
            <?php if ($isCustomR14 && isset($fieldIds['signature_name'])): ?>
            requestAnimationFrame(fitR14SignaturePrint);
            <?php endif; ?>
          });
        }
      });
      <?php if ($isCustomR14 && isset($fieldIds['signature_name'])): ?>
      requestAnimationFrame(fitR14SignaturePrint);
      if (document.fonts?.ready) {
        document.fonts.ready.then(() => requestAnimationFrame(fitR14SignaturePrint));
      }
      <?php endif; ?>
      <?php if ($isCustomR18): ?>
      const r18InstructorInput = document.getElementById('fallback-instructor_employee_id');
      const r18ResponsibleDepartmentInput = document.getElementById('fallback-responsible_department');
      function updateR18ResponsibleDepartment() {
        if (!r18InstructorInput || !r18ResponsibleDepartmentInput) return;
        const department = r18InstructorInput.selectedOptions[0]?.dataset.department || '';
        if (!department) return;
        r18ResponsibleDepartmentInput.value = department;
        r18ResponsibleDepartmentInput.dispatchEvent(new Event('input', {bubbles:true}));
        r18ResponsibleDepartmentInput.dispatchEvent(new Event('change', {bubbles:true}));
      }
      r18InstructorInput?.addEventListener('change', updateR18ResponsibleDepartment);
      const r18TheoryCreditsInput = document.getElementById('fallback-theory_credits');
      const r18PracticalCreditsInput = document.getElementById('fallback-practical_credits');
      const r18TotalCreditsInput = document.getElementById('fallback-total_credits');
      function updateR18TotalCredits() {
        if (!r18TotalCreditsInput) return;
        const theory = Number.parseFloat(r18TheoryCreditsInput?.value || '0') || 0;
        const practical = Number.parseFloat(r18PracticalCreditsInput?.value || '0') || 0;
        const total = Number((theory + practical).toFixed(2));
        r18TotalCreditsInput.value = String(total);
        const totalPreview = document.getElementById('p-field-' + r18TotalCreditsInput.dataset.fid);
        if (totalPreview) totalPreview.innerText = String(total);
      }
      r18TheoryCreditsInput?.addEventListener('input', updateR18TotalCredits);
      r18PracticalCreditsInput?.addEventListener('input', updateR18TotalCredits);
      updateR18TotalCredits();
      <?php endif; ?>
      const r12DateInput = document.getElementById('fallback-request_date');
      if (r12DateInput) {
        r12DateInput.addEventListener('input', () => {
          const value = new Date(r12DateInput.value + 'T00:00:00');
          if (Number.isNaN(value.getTime())) return;
          const months = ['มกราคม','กุมภาพันธ์','มีนาคม','เมษายน','พฤษภาคม','มิถุนายน','กรกฎาคม','สิงหาคม','กันยายน','ตุลาคม','พฤศจิกายน','ธันวาคม'];
          const dayEl = document.getElementById('r12-date-day');
          const monthEl = document.getElementById('r12-date-month');
          const yearEl = document.getElementById('r12-date-year');
          const r14DayEl = document.getElementById('r14-date-day');
          const r14MonthEl = document.getElementById('r14-date-month');
          const r14YearEl = document.getElementById('r14-date-year');
          const r16DayEl = document.getElementById('r16-date-day');
          const r16MonthEl = document.getElementById('r16-date-month');
          const r16YearEl = document.getElementById('r16-date-year');
          const r18DayEl = document.getElementById('r18-date-day');
          const r18MonthEl = document.getElementById('r18-date-month');
          const r18YearEl = document.getElementById('r18-date-year');
          const r24DayEl = document.getElementById('r24-date-day');
          const r24MonthEl = document.getElementById('r24-date-month');
          const r24YearEl = document.getElementById('r24-date-year');
          if (dayEl || r14DayEl || r16DayEl || r18DayEl || r24DayEl) (dayEl || r14DayEl || r16DayEl || r18DayEl || r24DayEl).innerText = value.getDate();
          if (monthEl || r14MonthEl || r16MonthEl || r18MonthEl || r24MonthEl) (monthEl || r14MonthEl || r16MonthEl || r18MonthEl || r24MonthEl).innerText = months[value.getMonth()];
          if (yearEl || r14YearEl || r16YearEl || r18YearEl || r24YearEl) (yearEl || r14YearEl || r16YearEl || r18YearEl || r24YearEl).innerText = value.getFullYear() + 543;
        });
      }
      const guardianPrefixInput = document.getElementById('guardian-prefix');
      const guardianNameInput = document.getElementById('guardian-plain-name');
      const guardianCombinedInput = document.getElementById('fallback-guardian_name');
      const syncGuardianName = () => {
        if (!guardianCombinedInput) return;
        guardianCombinedInput.value = [guardianPrefixInput?.value || '', guardianNameInput?.value.trim() || ''].filter(Boolean).join(' ');
        const previewName = document.getElementById('p-field-' + guardianCombinedInput.dataset.fid);
        if (previewName) previewName.innerText = guardianNameInput?.value.trim() || '';
        const guardianStrikes = {
          'นาย': document.getElementById('guardian-prefix-strike-mr'),
          'นาง': document.getElementById('guardian-prefix-strike-mrs'),
          'นางสาว': document.getElementById('guardian-prefix-strike-miss')
        };
        Object.entries(guardianStrikes).forEach(([label, strike]) => {
          if (strike) strike.style.display = label === guardianPrefixInput?.value ? 'none' : 'block';
        });
      };
      guardianPrefixInput?.addEventListener('change', syncGuardianName);
      guardianNameInput?.addEventListener('input', syncGuardianName);
      syncGuardianName();
      const relationshipChoiceInput = document.getElementById('guardian-relationship-choice');
      const relationshipOtherInput = document.getElementById('guardian-relationship-other');
      const relationshipCombinedInput = document.getElementById('fallback-guardian_relationship');
      const syncGuardianRelationship = () => {
        if (!relationshipCombinedInput || !relationshipChoiceInput) return;
        const isOther = relationshipChoiceInput.value === 'other';
        relationshipOtherInput?.classList.toggle('hidden', !isOther);
        relationshipOtherInput?.toggleAttribute('required', isOther);
        const otherText = relationshipOtherInput?.value.trim() || '';
        relationshipCombinedInput.value = isOther ? `other:${otherText}` : relationshipChoiceInput.value;
        relationshipCombinedInput.dispatchEvent(new Event('input', {bubbles:true}));
        const otherPreview = document.getElementById('r24-relationship-other-preview');
        if (otherPreview) otherPreview.innerText = isOther ? otherText : '';
      };
      relationshipChoiceInput?.addEventListener('change', syncGuardianRelationship);
      relationshipOtherInput?.addEventListener('input', syncGuardianRelationship);
      syncGuardianRelationship();
    <?php endif; ?>

    <?php if ($isCustomR16): ?>
    // Fold the six repeated student rows so the R16 input panel stays compact.
    (() => {
      const container = document.getElementById('generic-form-fields');
      if (!container) return;

      for (let row = 1; row <= 6; row++) {
        const cards = [...container.querySelectorAll(`[data-r16-field$="_${row}"]`)]
          .filter(card => /^(student_number|student_name|department|year_level|registration_status)_/.test(card.dataset.r16Field || ''));
        if (!cards.length) continue;

        const hasValue = cards.some(card => {
          const control = card.querySelector('input, select, textarea');
          return control && String(control.value || '').trim() !== '';
        });
        const details = document.createElement('details');
        details.className = 'r16-student-group group rounded-xl border border-slate-200 bg-slate-50';
        // Start with only the first student row expanded. Rows 2-6 remain
        // folded until the student explicitly chooses to enter them.
        details.open = row === 1 || hasValue;
        details.innerHTML = `
          <summary class="flex cursor-pointer list-none items-center justify-between rounded-xl px-4 py-3 text-sm font-bold text-slate-700 hover:bg-slate-100">
            <span><i class="fa-solid fa-user-graduate mr-2 text-red-600"></i>รายชื่อนักศึกษา ${row}</span>
            <span class="text-xs font-medium text-red-600 group-open:hidden"><i class="fa-solid fa-chevron-down mr-1"></i>ขยาย</span>
            <span class="hidden text-xs font-medium text-slate-500 group-open:inline"><i class="fa-solid fa-chevron-up mr-1"></i>พับ</span>
          </summary>
          <div class="r16-row-fields grid gap-2 border-t border-slate-200 p-3 sm:grid-cols-2"></div>`;
        container.insertBefore(details, cards[0]);
        const rowFields = details.querySelector('.r16-row-fields');
        cards.forEach(card => rowFields.appendChild(card));

        const setRowEnabled = () => {
          rowFields.querySelectorAll('input, select, textarea').forEach(control => {
            control.disabled = row > 1 && !details.open;
          });
        };
        details.addEventListener('toggle', setRowEnabled);
        setRowEnabled();
      }
    })();
    <?php endif; ?>

    function submitForm(status) {
      const form = document.getElementById('document-form');
      const missingFields = status === 'ready'
        ? [...form.querySelectorAll('[required]')].filter((field) => !field.checkValidity())
        : [];
      if (missingFields.length) {
        const labels = [...new Set(missingFields.map((field) => {
          const label = field.closest('div')?.querySelector('label');
          return label?.textContent.trim() || field.name;
        }))];
        Swal.fire({
          icon: 'warning',
          title: 'กรอกข้อมูลไม่ครบถ้วน',
          text: 'กรุณากรอกข้อมูลที่จำเป็นให้ครบถ้วน: ' + labels.join(', '),
          confirmButtonText: 'ตกลง',
          confirmButtonColor: '#dc2626'
        }).then(() => missingFields[0].focus());
        return;
      }
      document.getElementById('form-action').value = status;
      form.submit();
    }

    <?php if ($documentFormError !== ''): ?>
    Swal.fire({
      icon: 'warning',
      title: 'กรอกข้อมูลไม่ครบถ้วน',
      text: <?= json_encode($documentFormError, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>,
      confirmButtonText: 'ตกลง',
      confirmButtonColor: '#dc2626'
    });
    <?php endif; ?>

    <?php if (isset($_GET['saved'])): ?>
    Swal.fire({
      icon: 'success',
      title: 'บันทึกสำเร็จ',
      text: 'บันทึกเอกสารฉบับร่างเรียบร้อยแล้ว',
      confirmButtonText: 'ตกลง',
      confirmButtonColor: '#16a34a'
    });
    <?php endif; ?>
  </script>
</body>
</html>
