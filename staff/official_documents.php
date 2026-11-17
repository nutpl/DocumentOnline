<?php
session_start();
require_once '../middleware/staff.php';
require_once '../config/database.php';
require_once '../helpers/office_holder_helper.php';
require_once '../helpers/alert_response_helper.php';
require_once '../helpers/csrf_helper.php';
require_once '../helpers/official_send_eligibility_helper.php';
require_once '../helpers/official_document_number_helper.php';

$staffStmt = $pdo->prepare('SELECT employee_id,department_id,firstname,lastname FROM employee WHERE user_id=? AND employee_type IN ("staff","teacher") LIMIT 1');
$staffStmt->execute([$_SESSION['user_id']]);
$staff = $staffStmt->fetch(PDO::FETCH_ASSOC);
if (!$staff) { renderSweetAlertResponse('ไม่มีสิทธิ์ใช้งาน', 'ไม่พบสิทธิ์เจ้าหน้าที่สาขาสำหรับจัดทำหนังสือ', 'error', 'dashboard.php', 403); }
$currentSignatory = currentInternshipSignatory($pdo);
$currentSignatory ??= [
    'name' => 'รองศาสตราจารย์ ดร.รตินันท์ เหลือมพล',
    'title_line1' => 'รองคณบดีฝ่ายวิชาการและวิจัย ปฏิบัติราชการแทน',
    'title_line2' => 'คณบดีคณะวิศวกรรมศาสตร์และเทคโนโลยี',
];
$pageType = ($_GET['type'] ?? '') === 'send' ? 'send' : 'request';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_or_abort();
    $profileIds = array_values(array_unique(array_filter(array_map('intval', $_POST['profile_ids'] ?? []))));
    $type = $pageType;
    $required = ['document_date', 'document_number_suffix'];
    foreach ($required as $field) {
        if (trim((string)($_POST[$field] ?? '')) === '') { renderSweetAlertResponse('กรอกข้อมูลไม่ครบ', 'กรุณากรอกข้อมูลหนังสือให้ครบถ้วน', 'warning', 'official_documents.php?type=' . $type, 422); }
    }
    // เจ้าหน้าที่เป็นผู้กำหนดเลขท้ายหนังสือของแต่ละฉบับเอง โดยระบบ
    // คงคำนำหน้าเลขหนังสือของคณะไว้ให้เป็นรูปแบบเดียวกัน.
    $documentNumberSuffix = trim((string)($_POST['document_number_suffix'] ?? ''));
    $documentNumber = officialFormatDocumentNumber('อว ๐๖๕๗.๑๗๐๐/' . $documentNumberSuffix);
    // ผู้ดูแลระบบกำหนดค่าเริ่มต้นของผู้ลงนาม แต่เจ้าหน้าที่สามารถ
    // ระบุผู้ปฏิบัติราชการแทนสำหรับหนังสือฉบับที่กำลังสร้างได้ โดยไม่
    // เปลี่ยนข้อมูลผู้ดำรงตำแหน่งกลางหรือเอกสารฉบับที่ออกไปแล้ว.
    $documentSignatory = [
        'name' => trim((string)($_POST['signatory_name'] ?? $currentSignatory['name'])),
        'title_line1' => trim((string)($_POST['signatory_title_line1'] ?? $currentSignatory['title_line1'])),
        'title_line2' => trim((string)($_POST['signatory_title_line2'] ?? $currentSignatory['title_line2'])),
    ];
    foreach (['name' => 'ชื่อผู้ลงนาม', 'title_line1' => 'ตำแหน่งผู้ลงนามบรรทัดที่ 1'] as $key => $label) {
        if ($documentSignatory[$key] === '' || mb_strlen($documentSignatory[$key]) > 255) {
            renderSweetAlertResponse('ข้อมูลผู้ลงนามไม่ครบ', 'กรุณาระบุ' . $label . 'ให้ถูกต้องก่อนสร้างหนังสือ', 'warning', 'official_documents.php?type=' . $type, 422);
        }
    }
    if (mb_strlen($documentSignatory['title_line2']) > 255) {
        renderSweetAlertResponse('ข้อมูลผู้ลงนามไม่ถูกต้อง', 'ตำแหน่งหรือหน่วยงานเพิ่มเติมยาวเกินกำหนด', 'warning', 'official_documents.php?type=' . $type, 422);
    }
    if (!$profileIds) { renderSweetAlertResponse('ยังไม่ได้เลือกนักศึกษา', 'กรุณาเลือกนักศึกษาอย่างน้อย 1 คน', 'warning', 'official_documents.php?type=' . $type, 422); }
    if ($type === 'request' && empty($_POST['response_due_date'])) { renderSweetAlertResponse('กรุณาระบุวันกำหนดตอบกลับ', 'โปรดเลือกวันกำหนดตอบกลับก่อนสร้างหนังสือขอความอนุเคราะห์', 'warning', 'official_documents.php?type=request', 422); }
    $marks = implode(',', array_fill(0, count($profileIds), '?'));
    $sendRequirement = '';
    $sendRequirementWhere = $type === 'send' ? ' AND '.officialSendReadySql($pdo) : " AND ia.status='submitted'";
    $directSql=officialDirectSendSql($pdo);
    $check = $pdo->prepare("SELECT ip.internship_profile_id,ip.company_name,ip.recipient_name,ip.recipient_position,ip.calendar_id,($directSql) AS direct_import,
        EXISTS (SELECT 1 FROM internship_official_student request_os JOIN internship_official_document request_od ON request_od.official_document_id=request_os.official_document_id WHERE request_os.internship_profile_id=ip.internship_profile_id AND request_od.document_type='request') AS request_letter_created,
        EXISTS (SELECT 1 FROM internship_official_student sent_os JOIN internship_official_document sent_od ON sent_od.official_document_id=sent_os.official_document_id WHERE sent_os.internship_profile_id=ip.internship_profile_id AND sent_od.document_type='send') AS send_letter_created
        FROM internship_profile ip
        LEFT JOIN internship_application ia ON ia.internship_profile_id=ip.internship_profile_id
        JOIN student s ON s.student_id=ip.student_id JOIN group_class g ON g.group_id=s.group_id
        $sendRequirement WHERE g.department_id=? AND ip.request_status='active' AND ip.internship_profile_id IN ($marks) $sendRequirementWhere ORDER BY ip.internship_profile_id");
    $check->execute([$staff['department_id'], ...$profileIds]);
    $selected = $check->fetchAll(PDO::FETCH_ASSOC);
    if (count($selected) !== count($profileIds)) { renderSweetAlertResponse('ไม่สามารถสร้างหนังสือได้', 'มีรายการที่ไม่มีสิทธิ์ หรือยังไม่ได้ส่งเอกสารฉบับลงนาม', 'warning', 'official_documents.php?type=' . $type, 403); }
    if ($type === 'request' && array_filter($selected, static fn($row) => (bool)$row['request_letter_created'])) {
        renderSweetAlertResponse('มีรายชื่ออยู่ในหนังสือเดิมแล้ว', 'เลือกได้เฉพาะนักศึกษาที่ยังไม่อยู่ในหนังสือขอความอนุเคราะห์ของบริษัทนั้น', 'warning', 'official_documents.php?type=request', 422);
    }
    if ($type === 'send' && array_filter($selected, static fn($row) => (bool)$row['send_letter_created'])) {
        renderSweetAlertResponse('มีใบขอส่งตัวสร้างไว้แล้ว', 'เอกสารของนักศึกษาที่เลือกถูกสร้างไว้ในระบบแล้ว หากต้องการสร้างฉบับใหม่ โปรดลบรายการเดิมจากหน้าประวัติเอกสารก่อน', 'warning', 'official_documents.php?type=send', 422);
    }
    if ($type === 'request' && array_filter($selected, static fn($row) => (bool)$row['request_letter_created'])) {
        renderSweetAlertResponse('มีหนังสือขอความอนุเคราะห์สร้างไว้แล้ว', 'เอกสารของนักศึกษาที่เลือกถูกสร้างไว้ในระบบแล้ว หากต้องการสร้างฉบับใหม่ โปรดลบรายการเดิมจากหน้าประวัติเอกสารก่อน', 'warning', 'official_documents.php?type=request', 422);
    }
    // หนึ่งบริษัทคือหนึ่งฉบับหนังสือ แต่การกดสร้างครั้งเดียวสามารถเลือก
    // ได้หลายบริษัท ระบบจึงสร้างแต่ละฉบับแยกกันแล้วรวมไฟล์ดาวน์โหลดให้.
    $selectedByCompany = [];
    foreach ($selected as $row) {
        $companyName = trim((string)$row['company_name']);
        if ($companyName === '') {
            renderSweetAlertResponse('ข้อมูลสถานประกอบการไม่ครบ', 'ไม่พบชื่อสถานประกอบการของนักศึกษาที่เลือก', 'warning', 'official_documents.php?type=' . $type, 422);
        }
        $selectedByCompany[$companyName][] = $row;
    }
    foreach ($selectedByCompany as $companyName => $companyStudents) {
        if (count($companyStudents) > 5) {
            renderSweetAlertResponse('เลือกรายชื่อมากเกินไป', 'บริษัท ' . $companyName . ' มีรายชื่อเกิน 5 คน หนังสือหนึ่งฉบับรองรับได้สูงสุด 5 คน', 'warning', 'official_documents.php?type=' . $type, 422);
        }
        if (count(array_unique(array_column($companyStudents, 'calendar_id'))) !== 1) {
            renderSweetAlertResponse('ช่วงฝึกงานต่างกัน', 'บริษัท ' . $companyName . ' มีนักศึกษาคนละช่วงฝึกงาน กรุณาแยกออกหนังสือ', 'warning', 'official_documents.php?type=' . $type, 422);
        }
        $recipientName = trim(implode(' ', array_filter([
            $companyStudents[0]['recipient_name'] ?? '',
            $companyStudents[0]['recipient_position'] ?? '',
            $companyName,
        ])));
        if ($recipientName === '') {
            renderSweetAlertResponse('ข้อมูลผู้รับไม่ครบ', 'ไม่พบข้อมูลผู้รับหนังสือของบริษัท ' . $companyName, 'warning', 'official_documents.php?type=' . $type, 422);
        }
    }
    // A late student for the same company receives a new supplementary
    // request letter. Keep the first document immutable and link every later
    // letter back to its original request for a complete audit trail.
    $requestParents = [];
    if ($type === 'request') {
        $companyNames = array_keys($selectedByCompany);
        $companyMarks = implode(',', array_fill(0, count($companyNames), '?'));
        $existingRequest = $pdo->prepare("SELECT od.company_name,
                MIN(COALESCE(od.parent_official_document_id, od.official_document_id)) AS root_official_document_id
            FROM internship_official_document od
            JOIN internship_official_student os ON os.official_document_id=od.official_document_id
            JOIN internship_profile ip ON ip.internship_profile_id=os.internship_profile_id
            JOIN student s ON s.student_id=ip.student_id
            JOIN group_class g ON g.group_id=s.group_id
            WHERE od.document_type='request' AND g.department_id=? AND od.company_name IN ($companyMarks)
            GROUP BY od.company_name");
        $existingRequest->execute([$staff['department_id'], ...$companyNames]);
        foreach ($existingRequest->fetchAll(PDO::FETCH_ASSOC) as $row) $requestParents[$row['company_name']] = (int)$row['root_official_document_id'];
    }
    // ใบส่งตัวอ้างถึงแบบตอบรับเสมอ และไม่รับค่าจากฟอร์ม เพื่อให้รูปแบบ
    // หนังสือทุกฉบับเป็นมาตรฐานเดียวกัน.  ผู้รับดึงจากข้อมูลที่นักศึกษาแจ้ง
    // ไว้ในโปรไฟล์ ส่วนชื่อบริษัทใช้ข้อมูลสถานประกอบการของรายการที่เลือก.
    $referenceText = $type === 'send' ? 'แบบตอบรับนักศึกษา จำนวน ๒ แผ่น' : null;

    $pdo->beginTransaction();
    try {
        $insert = $pdo->prepare('INSERT INTO internship_official_document
            (document_type,company_name,document_number,document_date,recipient_name,reference_text,response_due_date,signatory_name,signatory_title_line1,signatory_title_line2,parent_official_document_id,is_supplemental,created_by)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)');
        $link = $pdo->prepare('INSERT INTO internship_official_student (official_document_id,internship_profile_id,sequence_no) VALUES (?,?,?)');
        $tracking = $pdo->prepare('INSERT INTO internship_tracking (internship_profile_id,request_letter_status,send_letter_status,updated_by)
            VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE request_letter_status=COALESCE(VALUES(request_letter_status),request_letter_status),send_letter_status=COALESCE(VALUES(send_letter_status),send_letter_status),updated_by=VALUES(updated_by)');
        $officialIds = [];
        foreach ($selectedByCompany as $companyName => $companyStudents) {
            $recipientName = trim(implode(' ', array_filter([
                $companyStudents[0]['recipient_name'] ?? '',
                $companyStudents[0]['recipient_position'] ?? '',
                $companyName,
            ])));
            // เลขหนังสือเดียวกันเป็นความตั้งใจของผู้ใช้งานสำหรับชุดที่สร้างพร้อมกัน.
            $parentOfficialId = $type === 'request' ? ($requestParents[$companyName] ?? null) : null;
            $insert->execute([
                $type,
                $companyName,
                $documentNumber,
                $_POST['document_date'],
                $recipientName,
                $referenceText,
                ($_POST['response_due_date'] ?? '') ?: null,
                $documentSignatory['name'],
                $documentSignatory['title_line1'],
                $documentSignatory['title_line2'] ?: null,
                $parentOfficialId,
                $parentOfficialId !== null ? 1 : 0,
                $_SESSION['user_id'],
            ]);
            $officialId = (int)$pdo->lastInsertId();
            $officialIds[] = $officialId;
            foreach ($companyStudents as $i => $student) {
                $profileId = (int)$student['internship_profile_id'];
                $link->execute([$officialId, $profileId, $i + 1]);
                $tracking->execute([$profileId, $type === 'request' ? 'sent' : null, $type === 'send' ? 'sent' : null, $_SESSION['user_id']]);
            }
        }
        $pdo->commit();
        // Use the same export route for one or many companies. This prevents
        // the combined-selection screen from falling back to an individual file.
        $_SESSION['official_batch_download'] = [
            'ids' => $officialIds,
            'type' => $type,
            'department_id' => (int)$staff['department_id'],
            'created_at' => time(),
        ];
        header('Location: official_documents.php?created_batch=1&type=' . $type);
        exit;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        error_log('Create official document failed: ' . $e->getMessage());
        renderSweetAlertResponse('สร้างหนังสือไม่สำเร็จ', 'ระบบไม่สามารถสร้างชุดหนังสือได้ กรุณาลองใหม่อีกครั้ง', 'error', 'official_documents.php?type=' . $type, 500);
    }
}

$sendListRequirement = '';
$sendListWhere = $pageType === 'send' ? ' AND '.officialSendReadySql($pdo) : ' AND ia.status="submitted"';
$studentStmt = $pdo->prepare('SELECT ip.internship_profile_id,ip.company_name,ip.recipient_name,ip.recipient_position,ic.academic_year,ic.semester,ic.start_date,ic.end_date,s.student_number,s.firstname,s.lastname,p.prefix_short,('.officialDirectSendSql($pdo).') AS direct_import,
    EXISTS (SELECT 1 FROM internship_official_student request_os JOIN internship_official_document request_od ON request_od.official_document_id=request_os.official_document_id WHERE request_os.internship_profile_id=ip.internship_profile_id AND request_od.document_type="request") AS request_letter_created,
    EXISTS (SELECT 1 FROM internship_official_student sent_os JOIN internship_official_document sent_od ON sent_od.official_document_id=sent_os.official_document_id WHERE sent_os.internship_profile_id=ip.internship_profile_id AND sent_od.document_type="send") AS send_letter_created
    FROM internship_profile ip LEFT JOIN internship_application ia ON ia.internship_profile_id=ip.internship_profile_id
    JOIN internship_calendar ic ON ic.calendar_id=ip.calendar_id
    JOIN student s ON s.student_id=ip.student_id LEFT JOIN prefixes p ON p.prefix_id=s.prefix_id
    JOIN group_class g ON g.group_id=s.group_id '.$sendListRequirement.' WHERE ip.request_status="active" AND g.department_id=?'.$sendListWhere.'
    ORDER BY ip.company_name,s.student_number');
$studentStmt->execute([$staff['department_id']]);
$students = $studentStmt->fetchAll(PDO::FETCH_ASSOC);
$groups = [];
foreach ($students as $student) { $groups[$student['company_name']][] = $student; }
$officialCompanyStates = [];
$createdColumn = $pageType === 'request' ? 'request_letter_created' : 'send_letter_created';
foreach ($groups as $companyName => $companyStudents) {
    $createdProfileIds = array_map('intval', array_column(array_filter($companyStudents, static fn($student) => (bool)$student[$createdColumn]), 'internship_profile_id'));
    $officialCompanyStates[$companyName] = [
        'created_count' => count($createdProfileIds),
        'total_count' => count($companyStudents),
        'created_profile_ids' => $createdProfileIds,
    ];
}
$pendingCompanyGroups = [];
$createdCompanyGroups = [];
foreach ($groups as $companyName => $companyStudents) {
    $companyState = $officialCompanyStates[$companyName] ?? ['created_count' => 0];
    // Keep a company selectable when only some students already have a
    // request. The remaining student can then create a supplementary letter.
    if ((int)$companyState['created_count'] >= count($companyStudents)) {
        $createdCompanyGroups[$companyName] = $companyStudents;
    } else {
        $pendingCompanyGroups[$companyName] = $companyStudents;
    }
}
$companyGroupSections = [
    [
        'key' => 'pending',
        'title' => 'ยังไม่ได้สร้างเอกสาร',
        'description' => 'เลือกบริษัทในส่วนนี้เพื่อจัดทำเอกสาร',
        'icon' => 'fa-file-circle-plus',
        'classes' => 'border-red-200 bg-red-50 text-red-800',
        'groups' => $pendingCompanyGroups,
    ],
    [
        'key' => 'created',
        'title' => 'สร้างเอกสารแล้ว',
        'description' => 'หากต้องการสร้างฉบับใหม่ ให้ลบรายการเดิมจากหน้าประวัติเอกสารก่อน',
        'icon' => 'fa-circle-check',
        'classes' => 'border-emerald-200 bg-emerald-50 text-emerald-800',
        'groups' => $createdCompanyGroups,
    ],
];

$initialType=$pageType;
$createdBatch = isset($_GET['created_batch'])
    && is_array($_SESSION['official_batch_download'] ?? null)
    && ($_SESSION['official_batch_download']['type'] ?? null) === $initialType
    && (int)($_SESSION['official_batch_download']['department_id'] ?? 0) === (int)$staff['department_id']
    && (time() - (int)($_SESSION['official_batch_download']['created_at'] ?? 0)) < 7200;
$createdBatchCount = $createdBatch ? count($_SESSION['official_batch_download']['ids'] ?? []) : 0;
$staff_name=trim($staff['firstname'].' '.$staff['lastname']); $pageHeading=$initialType==='send'?'จัดทำใบขอส่งตัว':'จัดทำหนังสือขอความอนุเคราะห์'; $document_count=count($students);
function h($v): string { return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8'); }
?>
<!doctype html><html lang="th"><head>
    <link rel="icon" type="image/png" href="/Project/assets/images/fet-logo-transparent.png?v=20260815-3">
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>จัดทำหนังสือราชการสหกิจ</title><script src="https://cdn.tailwindcss.com"></script><link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css"><style>
.company-group{transition:border-color .18s ease,box-shadow .18s ease,background-color .18s ease}.company-group.is-selected{border-color:#86efac;background:#f0fdf4;box-shadow:0 8px 20px rgba(22,163,74,.10)}.company-group.is-selected>.company-group__header{border-color:#bbf7d0!important;background:#dcfce7!important}.company-group.is-selected .company-group__eyebrow,.company-group.is-selected .company-group__count{color:#15803d!important}.company-group.is-selected .company-group__count{background:#fff!important}.company-group.is-selected .company-group__student-list{border-color:#dcfce7}.company-group.is-selected .company-group__student-list label:hover{background:#ecfdf5!important}.company-group.is-selected .student-checkbox{color:#16a34a!important;accent-color:#16a34a}
</style></head>
<body class="bg-slate-100 text-slate-900"><?php include 'sidebar.php'; ?><div class="min-h-screen lg:ml-72"><?php include 'navbar.php'; ?><main class="p-4 sm:p-6 lg:p-8">
<section class="management-hero"><span class="management-hero__icon"><i class="fa-solid fa-file-signature"></i></span><div class="min-w-0 flex-1"><p class="management-hero__eyebrow">เอกสารทางการสหกิจ</p><h1><?= $initialType==='send'?'จัดทำใบขอส่งตัวนักศึกษา':'จัดทำหนังสือขอความอนุเคราะห์รับนักศึกษา' ?></h1><p>เลือกได้หลายสถานประกอบการ ระบบจะแยกหนังสือเป็นรายบริษัทและรวมเป็นไฟล์เดียว</p></div></section>
<?php if ($initialType === 'send' && (int) ($_GET['imported'] ?? 0) > 0): ?><div class="mt-5 rounded-2xl border border-emerald-200 bg-emerald-50 px-5 py-4 text-emerald-900"><i class="fa-solid fa-circle-check mr-2"></i><strong>นำเข้าข้อมูลสำเร็จ <?= (int) $_GET['imported'] ?> รายการ</strong><span class="ml-1 text-sm">เลือกรายชื่อด้านล่างเพื่อสร้างใบส่งตัวได้ทันที</span></div><?php endif; ?>
<?php if(isset($_GET['created'])): ?><div hidden data-success-alert data-success-title="สร้างหนังสือสำเร็จ" data-success-text="สร้างชุดหนังสือเรียบร้อยแล้ว" data-success-confirm="ดาวน์โหลด PDF" data-success-url="generate_official_pdf.php?id=<?= (int)$_GET['created'] ?>&amp;type=<?= h($initialType) ?>" data-success-secondary-confirm="ดาวน์โหลด Word" data-success-secondary-url="generate_official_word.php?id=<?= (int)$_GET['created'] ?>&amp;type=<?= h($initialType) ?>&amp;font=psk" data-success-clean-param="created"></div><?php endif; ?>
<?php if($createdBatch): ?><div hidden data-success-alert data-success-title="สร้างหนังสือสำเร็จ" data-success-text="สร้างหนังสือ <?= (int)$createdBatchCount ?> บริษัทแล้ว ดาวน์โหลดเป็นไฟล์เดียว<?= $createdBatchCount > 1 ? 'หลายหน้า' : '' ?>ได้ทันที" data-success-confirm="ดาวน์โหลด PDF รวม" data-success-url="generate_official_batch.php?format=pdf&amp;type=<?= h($initialType) ?>" data-success-secondary-confirm="ดาวน์โหลด Word รวม" data-success-secondary-url="generate_official_batch.php?format=word&amp;type=<?= h($initialType) ?>&amp;font=psk" data-success-clean-param="created_batch"></div><?php endif; ?>
<?php if($createdBatch && $initialType==='request'): ?><section class="mt-5 flex flex-col gap-3 rounded-2xl border border-emerald-200 bg-emerald-50 p-5 shadow-sm sm:flex-row sm:items-center sm:justify-between"><div class="flex items-start gap-3"><span class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-emerald-100 text-emerald-700"><i class="fa-solid fa-file-signature"></i></span><div><h2 class="font-bold text-emerald-950">แบบตอบรับสำหรับแนบกับหนังสือ</h2><p class="mt-1 text-sm text-emerald-800">ไฟล์แยกจากหนังสือขอความอนุเคราะห์ มีรายชื่อนักศึกษา สาขา และช่วงฝึกงานของแต่ละบริษัทครบแล้ว</p></div></div><a href="generate_official_batch.php?format=pdf&amp;type=response" class="inline-flex shrink-0 items-center justify-center rounded-xl bg-red-700 px-4 py-2.5 text-sm font-bold text-white shadow-sm transition hover:bg-red-800"><i class="fa-solid fa-file-pdf mr-2"></i>พิมพ์แบบตอบรับ PDF</a></section><?php endif; ?>
<form method="post" class="mt-5 space-y-5" data-confirm-form data-confirm-title="ยืนยันการสร้างหนังสือราชการ?" data-confirm-text="ตรวจสอบเลขหนังสือ ผู้รับ และรายชื่อนักศึกษาก่อนยืนยัน">
<?=csrf_field()?>
<section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm"><button type="button" data-section-toggle aria-expanded="true" aria-controls="official-section-1" class="flex w-full items-center justify-between gap-3 border-b border-slate-100 bg-slate-50 px-5 py-4 text-left transition hover:bg-slate-100"><span class="flex items-center gap-3"><span class="inline-flex h-9 w-9 items-center justify-center rounded-xl bg-red-50 font-bold text-red-700">1</span><span><span class="block font-bold">ข้อมูลหนังสือ</span><span class="block text-sm font-normal text-slate-500">กำหนดประเภท วันที่ และผู้รับหนังสือ</span></span></span><i data-section-toggle-icon class="fa-solid fa-chevron-up text-slate-400"></i></button><div id="official-section-1" class="p-5"><div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
<input type="hidden" name="document_type" value="<?= h($initialType) ?>"><div><span class="mb-1 block text-sm">ประเภทหนังสือ</span><div class="flex min-h-10 items-center rounded border bg-slate-50 px-3 font-semibold text-slate-700"><i class="fa-solid <?= $initialType==='send'?'fa-paper-plane':'fa-file-circle-plus' ?> mr-2 text-red-600"></i><?= $initialType==='send'?'ใบขอส่งตัวนักศึกษา':'ขอความอนุเคราะห์รับนักศึกษา' ?></div></div>
<label><span class="mb-1 block text-sm">เลขหนังสือ *</span><span class="flex overflow-hidden rounded border border-slate-300 bg-white focus-within:border-red-500 focus-within:ring-4 focus-within:ring-red-100"><span class="flex items-center whitespace-nowrap border-r border-slate-300 bg-slate-50 px-3 text-slate-700">อว ๐๖๕๗.๑๗๐๐/</span><input name="document_number_suffix" value="" required maxlength="50" aria-label="เลขท้ายหนังสือ" class="min-w-0 flex-1 border-0 bg-white px-3 py-2 font-semibold text-slate-900 outline-none" placeholder="กรอกเลขลำดับ เช่น 460"></span><small class="mt-1 block text-slate-500">กรอกเลขลำดับเองในแต่ละภาคการศึกษา ระบบจะแสดงเป็น ว.๔๖๐ อัตโนมัติ</small></label>
<label><span class="mb-1 block text-sm">วันที่หนังสือ *</span><input type="date" name="document_date" value="<?= date('Y-m-d') ?>" required class="w-full rounded border px-3 py-2"></label>
<label><span class="mb-1 block text-sm">เรียน</span><input id="recipient-preview" readonly placeholder="เลือกนักศึกษาเพื่อแสดงผู้รับหนังสือ" class="w-full rounded border bg-gray-100 px-3 py-2 text-gray-600"></label>
<label id="reference-field" class="<?= $initialType==='send'?'':'hidden' ?>"><span class="mb-1 block text-sm">อ้างถึง</span><input id="reference-text" value="แบบตอบรับนักศึกษา จำนวน ๒ แผ่น" readonly class="w-full rounded border bg-gray-100 px-3 py-2 text-gray-700"></label>
<?php if($initialType==='request'): ?><label><span class="mb-1 block text-sm">กำหนดตอบกลับ *</span><input type="date" name="response_due_date" data-swal-required data-swal-title="กรุณาระบุวันกำหนดตอบกลับ" data-swal-text="กรุณาเลือกวันกำหนดตอบกลับก่อนสร้างหนังสือ" class="w-full rounded border px-3 py-2"><small class="mt-1 block text-gray-500">แสดงในหนังสือขอความอนุเคราะห์</small></label><?php endif; ?>
</div></div></section>
<section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
    <button type="button" data-section-toggle aria-expanded="false" aria-controls="official-section-2" class="flex w-full items-center justify-between gap-3 bg-slate-50 px-5 py-4 text-left transition hover:bg-slate-100"><span class="flex min-w-0 items-center gap-3"><span class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-red-50 font-bold text-red-700">2</span><span><span class="block text-lg font-bold">ผู้ลงนามในหนังสือ</span><span class="mt-1 block text-sm font-normal text-slate-500">ใช้ค่าจากผู้ดูแลระบบเป็นค่าเริ่มต้น และแก้ไขเฉพาะหนังสือชุดนี้ได้</span></span></span><i data-section-toggle-icon class="fa-solid fa-chevron-down text-slate-400"></i></button>
    <div id="official-section-2" hidden class="p-5"><div class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900"><i class="fa-solid fa-circle-info mr-2"></i>กรณีผู้ลงนามหลักไม่อยู่ราชการ เจ้าหน้าที่สามารถระบุผู้ปฏิบัติราชการแทนได้ ข้อมูลนี้มีผลเฉพาะเอกสารที่กำลังสร้างและไม่เปลี่ยนค่ากลางของระบบ</div><div class="mt-4 grid gap-4 border-t border-slate-100 pt-4 md:grid-cols-3">
        <label><span class="mb-1 block text-sm font-medium text-slate-700">ชื่อผู้ลงนาม *</span><input type="text" name="signatory_name" required maxlength="255" value="<?= h($currentSignatory['name']) ?>" class="w-full rounded border border-slate-300 px-3 py-2 focus:border-red-500 focus:outline-none focus:ring-4 focus:ring-red-100"></label>
        <label><span class="mb-1 block text-sm font-medium text-slate-700">ตำแหน่งบรรทัดที่ 1 *</span><input type="text" name="signatory_title_line1" required maxlength="255" value="<?= h($currentSignatory['title_line1']) ?>" class="w-full rounded border border-slate-300 px-3 py-2 focus:border-red-500 focus:outline-none focus:ring-4 focus:ring-red-100"></label>
        <label><span class="mb-1 block text-sm font-medium text-slate-700">ตำแหน่งหรือหน่วยงานเพิ่มเติม</span><input type="text" name="signatory_title_line2" maxlength="255" value="<?= h($currentSignatory['title_line2']) ?>" class="w-full rounded border border-slate-300 px-3 py-2 focus:border-red-500 focus:outline-none focus:ring-4 focus:ring-red-100"></label>
    </div></div>
</section>
<section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm"><button type="button" data-section-toggle aria-expanded="false" aria-controls="official-section-3" class="flex w-full items-center justify-between gap-3 border-b border-slate-100 bg-slate-50 px-5 py-4 text-left transition hover:bg-slate-100"><span class="flex min-w-0 items-center gap-3"><span class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-red-50 font-bold text-red-700">3</span><span><span class="block text-lg font-bold">เลือกสถานประกอบการ</span><span class="mt-1 block text-sm font-normal text-slate-500">เลือกได้หลายบริษัท บริษัทละไม่เกิน 5 คน และยกเลิกรายชื่อนักศึกษารายบุคคลได้เมื่อจำเป็น</span></span></span><i data-section-toggle-icon class="fa-solid fa-chevron-down shrink-0 text-slate-400"></i></button><div id="official-section-3" hidden><div class="sticky top-0 z-20 flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 bg-slate-50 px-5 py-4 shadow-sm"><label class="flex cursor-pointer items-center gap-3 text-sm font-bold text-slate-700"><input id="official-select-all" type="checkbox" class="h-5 w-5 rounded border-slate-300 text-emerald-700 focus:ring-emerald-500"><span>เลือกทั้งหมดที่ยังไม่ได้สร้าง</span></label><div class="flex flex-wrap items-center gap-2"><div class="flex flex-wrap gap-2 text-xs font-bold"><span class="rounded-full bg-red-100 px-3 py-1.5 text-red-700">รอสร้าง <?= count($pendingCompanyGroups) ?> บริษัท</span><span class="rounded-full bg-emerald-100 px-3 py-1.5 text-emerald-700">สร้างแล้ว <?= count($createdCompanyGroups) ?> บริษัท</span></div><button type="submit" class="inline-flex min-h-10 items-center justify-center rounded-xl bg-red-700 px-5 py-2.5 text-sm font-bold text-white shadow-sm transition hover:bg-red-800 focus:outline-none focus:ring-4 focus:ring-red-200"><i class="fa-solid <?= $initialType==='send'?'fa-paper-plane':'fa-file-circle-plus' ?> mr-2"></i><?= $initialType==='send'?'สร้างใบขอส่งตัว':'สร้างหนังสือขอความอนุเคราะห์' ?></button></div></div><div class="space-y-6 p-5">
<?php foreach($companyGroupSections as $section): ?>
    <?php if(!$section['groups']) continue; ?>
    <section class="overflow-hidden rounded-xl border <?= h($section['classes']) ?>">
        <div class="flex flex-wrap items-center justify-between gap-3 px-4 py-3"><div class="flex items-center gap-2"><i class="fa-solid <?= h($section['icon']) ?>"></i><div><h3 class="font-bold"><?= h($section['title']) ?></h3><p class="text-xs font-medium opacity-80"><?= h($section['description']) ?></p></div></div><span class="rounded-full bg-white/80 px-3 py-1 text-xs font-bold"><?= count($section['groups']) ?> บริษัท</span></div>
        <div class="space-y-3 border-t border-current/10 p-3">
<?php foreach($section['groups'] as $company=>$companyStudents): ?>
    <?php $companyState=$officialCompanyStates[$company] ?? ['created_count'=>0,'total_count'=>count($companyStudents)]; $companyCreated=(int)$companyState['created_count']>=count($companyStudents); $companySupplement=$initialType==='request' && (int)$companyState['created_count']>0 && !$companyCreated; ?>
    <div class="company-group overflow-hidden rounded-xl border <?= $companyCreated?'border-emerald-300 bg-emerald-50/40':($companySupplement?'border-amber-300 bg-amber-50/40':'border-slate-200') ?>" data-company-group="<?= h($company) ?>" data-document-created="<?= $companyCreated?'true':'false' ?>">
        <div class="company-group__header flex items-center justify-between gap-3 border-b px-4 py-3 <?= $companyCreated?'border-emerald-200 bg-emerald-100':($companySupplement?'border-amber-200 bg-amber-100':'border-red-100 bg-red-50') ?>">
            <label class="flex min-w-0 items-center gap-3 <?= $companyCreated?'cursor-not-allowed':'cursor-pointer' ?>">
                <input type="checkbox" class="company-select-checkbox h-5 w-5 shrink-0 rounded border-slate-300 <?= $companyCreated?'text-emerald-700 focus:ring-emerald-500':($companySupplement?'text-amber-700 focus:ring-amber-500':'text-red-700 focus:ring-red-500') ?>" data-company="<?= h($company) ?>" aria-label="เลือกนักศึกษาทั้งหมดของ <?= h($company) ?>" <?= $companyCreated?'disabled':'' ?>>
                <span class="min-w-0"><span class="company-group__eyebrow block text-xs font-bold <?= $companyCreated?'text-emerald-700':($companySupplement?'text-amber-700':'text-red-600') ?>"><?= $companyCreated?'สร้างหนังสือแล้ว':($companySupplement?'สร้างหนังสือเพิ่มเติมได้':'สถานประกอบการ') ?></span><strong class="block truncate text-slate-900"><?= h($company) ?></strong></span>
            </label>
            <span class="company-group__count shrink-0 rounded-full bg-white px-3 py-1 text-sm font-bold <?= $companyCreated?'text-emerald-700':'text-red-700' ?>"><?= $companyCreated?'สร้างแล้ว':' '.count($companyStudents).' คน' ?></span>
        </div>
        <div class="company-group__student-list divide-y divide-slate-100">
            <?php foreach($companyStudents as $student): ?>
                <?php $recipientPreview=trim(implode(' ',array_filter([$student['recipient_name']??'',$student['recipient_position']??'',$company]))); $studentCreated=(bool)($student[$createdColumn]??false); ?>
                <label class="flex items-center gap-3 px-4 py-3 transition <?= $studentCreated?'cursor-not-allowed opacity-60':'cursor-pointer hover:bg-slate-50' ?>">
                    <input type="checkbox" name="profile_ids[]" value="<?= (int)$student['internship_profile_id'] ?>" data-company="<?= h($company) ?>" data-recipient="<?= h($recipientPreview) ?>" class="student-checkbox h-4 w-4 rounded border-slate-300 <?= $studentCreated?'text-emerald-700 focus:ring-emerald-500':($companySupplement?'text-amber-700 focus:ring-amber-500':'text-red-700 focus:ring-red-500') ?>" <?= $studentCreated?'disabled':'' ?>>
                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-slate-100 text-sm font-bold text-slate-700"><?= h(mb_substr($student['firstname'],0,1)) ?></span>
                    <span><strong class="text-slate-900"><?= h($student['student_number']) ?></strong><span class="ml-1 text-slate-600"><?= h(trim(($student['prefix_short']??'').' '.$student['firstname'].' '.$student['lastname'])) ?></span></span>
                </label>
            <?php endforeach; ?>
        </div>
    </div>
<?php endforeach; ?>
        </div>
    </section>
<?php endforeach; ?>
<?php if(!$groups): ?><div class="p-8 text-center text-gray-500">ยังไม่มี RMUTI_Pro-Ex_02 ที่อาจารย์ทั้ง 2 คนลงนามครบและพร้อมจัดทำหนังสือ</div><?php endif; ?></div></div></section>
</form>
</main></div><script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script><script src="../assets/js/form-confirm.js"></script><script>
const recipientPreview=document.getElementById('recipient-preview');
const officialForm=document.querySelector('form[data-confirm-form]');
const studentBoxes=document.querySelectorAll('.student-checkbox:not(:disabled)');
const companySelectBoxes=document.querySelectorAll('.company-select-checkbox:not(:disabled)');
const officialSelectAll=document.getElementById('official-select-all');
const documentType=document.querySelector('[name="document_type"]');
const referenceText=document.getElementById('reference-text');
const referenceField=document.getElementById('reference-field');
const responseDueDate=document.querySelector('[name="response_due_date"]');

document.querySelectorAll('[data-section-toggle]').forEach(button=>button.addEventListener('click',()=>{
    const panel=document.getElementById(button.getAttribute('aria-controls'));
    if(!panel)return;
    const expanded=button.getAttribute('aria-expanded')==='true';
    button.setAttribute('aria-expanded',String(!expanded));
    panel.hidden=expanded;
    const icon=button.querySelector('[data-section-toggle-icon]');
    icon?.classList.toggle('fa-chevron-up',!expanded);
    icon?.classList.toggle('fa-chevron-down',expanded);
}));

function updateReferenceText(){
    const isSend=documentType?.value==='send';
    referenceField?.classList.toggle('hidden',!isSend);
    if(referenceText)referenceText.value='แบบตอบรับนักศึกษา จำนวน ๒ แผ่น';
}
documentType?.addEventListener('change',updateReferenceText);

function selectedCompanies(){
    return [...new Set([...document.querySelectorAll('.student-checkbox:checked')].map(box=>box.dataset.company))];
}

function updateSelectedCompanyPreview(){
    const selected=[...document.querySelectorAll('.student-checkbox:checked')];
    const companies=selectedCompanies();
    document.querySelectorAll('[data-company-group]').forEach(group=>{
        const company=group.dataset.companyGroup;
        group.classList.toggle('is-selected',companies.includes(company));
    });
    companySelectBoxes.forEach(companyBox=>{
        const companyStudents=[...studentBoxes].filter(box=>box.dataset.company===companyBox.dataset.company);
        const selectedStudents=companyStudents.filter(box=>box.checked);
        companyBox.checked=companyStudents.length>0&&selectedStudents.length===companyStudents.length;
        companyBox.indeterminate=selectedStudents.length>0&&selectedStudents.length<companyStudents.length;
    });
    if(officialSelectAll){
        const selectedCount=selected.length;
        officialSelectAll.checked=studentBoxes.length>0&&selectedCount===studentBoxes.length;
        officialSelectAll.indeterminate=selectedCount>0&&selectedCount<studentBoxes.length;
    }
    document.dispatchEvent(new Event('official-selection-changed'));
    if(!selected.length){recipientPreview.value='';return;}
    if(companies.length===1){
        recipientPreview.value=selected[0].dataset.recipient;
        return;
    }
    recipientPreview.value=`เลือก ${selected.length} คน จาก ${companies.length} บริษัท ระบบกำหนดผู้รับตามแต่ละบริษัท`;
}

function selectAllStudents(){
    const boxesByCompany={};
    studentBoxes.forEach(box=>{(boxesByCompany[box.dataset.company]??=[]).push(box);});
    const oversized=[];
    Object.entries(boxesByCompany).forEach(([company,boxes])=>{
        if(boxes.length>5){
            boxes.forEach(box=>{box.checked=false;});
            oversized.push(company);
            return;
        }
        boxes.forEach(box=>{box.checked=true;});
    });
    updateSelectedCompanyPreview();
    if(oversized.length){
        Swal.fire({icon:'info',title:'บางบริษัทยังไม่ได้เลือกทั้งหมด',text:`${oversized.join(', ')} มีนักศึกษาเกิน 5 คน กรุณาเลือกเองไม่เกินบริษัทละ 5 คน`,confirmButtonText:'ตกลง',confirmButtonColor:'#dc2626'});
    }
}

officialSelectAll?.addEventListener('change',()=>{
    if(!officialSelectAll.checked){
        studentBoxes.forEach(box=>{box.checked=false;});
        updateSelectedCompanyPreview();
        return;
    }
    selectAllStudents();
});

studentBoxes.forEach(box=>box.addEventListener('change',()=>{
    if(box.checked){
        const selectedSameCompany=[...document.querySelectorAll('.student-checkbox:checked')].filter(other=>other.dataset.company===box.dataset.company);
        if(selectedSameCompany.length>5){
            box.checked=false;
            Swal.fire({icon:'info',title:'เลือกรายชื่อได้สูงสุด 5 คนต่อบริษัท',text:'หนังสือของบริษัทนี้รองรับนักศึกษาได้ไม่เกิน 5 คน',confirmButtonText:'ตกลง',confirmButtonColor:'#dc2626'});
        }
    }
    updateSelectedCompanyPreview();
}));

companySelectBoxes.forEach(companyBox=>companyBox.addEventListener('change',()=>{
    const companyStudents=[...studentBoxes].filter(box=>box.dataset.company===companyBox.dataset.company);
    if(companyBox.checked&&companyStudents.length>5){
        companyBox.checked=false;
        companyBox.indeterminate=companyStudents.some(box=>box.checked);
        Swal.fire({icon:'info',title:'เลือกทั้งบริษัทไม่ได้',text:'หนังสือหนึ่งฉบับรองรับนักศึกษาได้ไม่เกิน 5 คน กรุณาเลือกรายชื่อเอง',confirmButtonText:'ตกลง',confirmButtonColor:'#dc2626'});
        return;
    }
    companyStudents.forEach(box=>{box.checked=companyBox.checked;});
    updateSelectedCompanyPreview();
}));

officialForm?.addEventListener('submit',event=>{
    if(documentType?.value==='request'&&!responseDueDate?.value){
        event.preventDefault();
        event.stopImmediatePropagation();
        Swal.fire({
            icon:'warning',
            title:'กรุณาระบุวันกำหนดตอบกลับ',
            text:'กรุณาเลือกวันกำหนดตอบกลับก่อนสร้างหนังสือ',
            confirmButtonText:'ตกลง',
            confirmButtonColor:'#dc2626'
        }).then(()=>responseDueDate?.focus());
        return;
    }
    if(document.querySelector('.student-checkbox:checked')) return;
    event.preventDefault();
    event.stopImmediatePropagation();
    const createdCompanies=[...document.querySelectorAll('[data-company-group][data-document-created="true"]')]
        .map(group=>group.dataset.companyGroup)
        .filter(Boolean);
    if(createdCompanies.length){
        const companyPreview=createdCompanies.slice(0,3).join(', ')+(createdCompanies.length>3?` และอีก ${createdCompanies.length-3} บริษัท`:'');
        Swal.fire({
            icon:'info',
            title:'มีเอกสารถูกสร้างไว้แล้ว',
            text:`${companyPreview} มีเอกสารประเภทนี้อยู่ในระบบแล้ว หากต้องการสร้างฉบับใหม่ ต้องลบรายการเดิมจากหน้าประวัติเอกสารก่อน`,
            showCancelButton:true,
            confirmButtonText:'ไปหน้าประวัติเอกสาร',
            cancelButtonText:'อยู่หน้านี้',
            confirmButtonColor:'#047857',
            cancelButtonColor:'#64748b'
        }).then(result=>{if(result.isConfirmed)window.location.href='official_history.php';});
        return;
    }
    Swal.fire({
        icon:'warning',
        title:'ยังไม่ได้เลือกนักศึกษา',
        text:'กรุณาเลือกนักศึกษาอย่างน้อย 1 คน',
        confirmButtonText:'ตกลง',
        confirmButtonColor:'#dc2626'
    }).then(()=>document.querySelector('.student-checkbox')?.scrollIntoView({behavior:'smooth',block:'center'}));
},true);
</script></body></html>
