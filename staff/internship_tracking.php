<?php
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
require_once __DIR__.'/../config/database.php';
require_once __DIR__.'/../helpers/csrf_helper.php';
require_once __DIR__.'/../helpers/pagination_helper.php';
require_once __DIR__.'/../helpers/response_no_document_review_helper.php';
require_once __DIR__.'/../helpers/activity_log_helper.php';

$isCoordinatorTracking = ($trackingContext ?? '') === 'teacher_coordinator';
$trackingReadOnly = false;
if ($isCoordinatorTracking) {
    require_once __DIR__.'/../middleware/teacher.php';
    require_once __DIR__.'/../helpers/teacher_coordinator_helper.php';
    $coordinator = teacherCoordinatorProfile($pdo, (int)$_SESSION['user_id']);
    if (!$coordinator) { http_response_code(403); renderPlainErrorAlert('หน้านี้สำหรับอาจารย์ประสานงานฝึกประสบการณ์วิชาชีพ'); }
    $staff = [
        'employee_id' => $coordinator['employee_id'], 'user_id' => (int)$_SESSION['user_id'],
        'department_id' => $coordinator['department_id'], 'firstname' => $coordinator['firstname'],
        'lastname' => $coordinator['lastname'], 'prefix_name' => '',
    ];
} else {
    require_once __DIR__.'/../middleware/staff.php';
    $staffStmt = $pdo->prepare('SELECT e.employee_id,e.user_id,e.department_id,e.firstname,e.lastname,
        COALESCE(p.prefix_short,p.prefix_name,"") AS prefix_name
        FROM employee e LEFT JOIN prefixes p ON p.prefix_id=e.prefix_id
        WHERE e.user_id=? AND e.employee_type IN ("staff","teacher") LIMIT 1');
    $staffStmt->execute([$_SESSION['user_id']]);
    $staff = $staffStmt->fetch(PDO::FETCH_ASSOC);
    if (!$staff) { http_response_code(403); renderPlainErrorAlert('ไม่มีสิทธิ์ใช้งาน'); }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$trackingReadOnly) {
    verify_csrf_or_abort();
    $profileId = (int)($_POST['profile_id'] ?? 0);
    $applicationStatus = trim((string)($_POST['application_status'] ?? ''));
    $employmentStatus = trim((string)($_POST['employment_status'] ?? 'pending'));
    $notes = trim((string)($_POST['notes'] ?? ''));

    if (!in_array($applicationStatus, ['', 'applied', 'not_applied'], true)) {
        $saveError = 'สถานะสมัครงานในระบบไม่ถูกต้อง';
    } elseif (!in_array($employmentStatus, ['pending', 'hired', 'not_hired'], true)) {
        $saveError = 'สถานะการรับเข้าทำงานไม่ถูกต้อง';
    } else {
        $allowed = $pdo->prepare('SELECT ip.internship_profile_id
            FROM internship_profile ip
            JOIN internship_application ia ON ia.internship_profile_id=ip.internship_profile_id AND ia.status="submitted"
            JOIN student s ON s.student_id=ip.student_id
            JOIN group_class g ON g.group_id=s.group_id
            WHERE ip.internship_profile_id=? AND g.department_id=? LIMIT 1');
        $allowed->execute([$profileId, $staff['department_id']]);
        if (!$allowed->fetchColumn()) {
            $saveError = 'ไม่มีสิทธิ์แก้ไขข้อมูลนักศึกษารายการนี้';
        } else {
            if ($applicationStatus === 'applied') {
                $responseCheck = $pdo->prepare('SELECT COUNT(*) FROM internship_profile ip WHERE ip.internship_profile_id=? AND '.responseVerifiedForSendSql($pdo));
                $responseCheck->execute([$profileId]);
                if (!(int)$responseCheck->fetchColumn()) {
                    $saveError = 'ยังไม่สามารถบันทึกว่า “สมัครแล้ว” ได้ กรุณาให้นักศึกษาอัปโหลดใบตอบรับ หรือให้เจ้าหน้าที่ตรวจยืนยันกรณีไม่มีใบตอบรับก่อน';
                }
            }
        }
        if (empty($saveError)) {
            $save = $pdo->prepare('INSERT INTO internship_tracking
                (internship_profile_id,application_status,employment_status,notes,updated_by)
                VALUES (?,?,?,?,?)
                ON DUPLICATE KEY UPDATE
                application_status=VALUES(application_status),
                employment_status=VALUES(employment_status),
                notes=VALUES(notes),updated_by=VALUES(updated_by)');
            $save->execute([
                $profileId,
                $applicationStatus !== '' ? $applicationStatus : null,
                $employmentStatus,
                $notes !== '' ? $notes : null,
                $_SESSION['user_id'],
            ]);
            logActivity($pdo, (int) $_SESSION['user_id'], 'internship_tracking_updated', 'ปรับสถานะสมัครงานของรหัสรายการ #' . $profileId . ' เป็น ' . ($applicationStatus ?: 'ยังไม่ระบุ') . ' / ' . $employmentStatus);
            $returnParams = [
                'saved' => 1,
                'profile_id' => $profileId,
                'search' => trim((string)($_POST['return_search'] ?? '')),
                'academic_year' => trim((string)($_POST['return_academic_year'] ?? '')),
                'semester' => trim((string)($_POST['return_semester'] ?? '')),
                'response' => trim((string)($_POST['return_response'] ?? '')),
                'page' => max(1, (int)($_POST['return_page'] ?? 1)),
            ];
            header('Location: internship_tracking.php?'.http_build_query(array_filter($returnParams, static fn($value) => $value !== '')) .'#tracking-list');
            exit;
        }
    }
}

$keyword = trim((string)($_GET['search'] ?? ''));
$semester = trim((string)($_GET['semester'] ?? ''));
$academicYear = trim((string)($_GET['academic_year'] ?? ''));
$responseFilter = trim((string)($_GET['response'] ?? ''));
$conditions = ['g.department_id=?', 'ia.status="submitted"', 'ip.request_status="active"'];
$params = [(int)$staff['department_id']];
if ($keyword !== '') {
    $conditions[] = '(s.student_number LIKE ? OR s.firstname LIKE ? OR s.lastname LIKE ? OR ip.company_name LIKE ?)';
    $term = '%'.$keyword.'%';
    array_push($params, $term, $term, $term, $term);
}
if ($semester !== '') { $conditions[] = 'ic.semester=?'; $params[] = (int)$semester; }
if ($academicYear !== '') { $conditions[] = 'ic.academic_year=?'; $params[] = (int)$academicYear; }
if (in_array($responseFilter, ['sent','not_sent'], true)) {
    $conditions[] = $responseFilter === 'sent' ? responseVerifiedForSendSql($pdo) : 'rd.review_status="not_accepted"';
} elseif ($responseFilter === 'unset') {
    $conditions[] = '(rd.review_status IS NULL OR rd.review_status IN ("pending","revision_requested"))';
}

$fromSql = ' FROM internship_application ia
    JOIN internship_profile ip ON ip.internship_profile_id=ia.internship_profile_id
    JOIN internship_calendar ic ON ic.calendar_id=ip.calendar_id
    JOIN student s ON s.student_id=ip.student_id
    LEFT JOIN prefixes p ON p.prefix_id=s.prefix_id
    JOIN group_class g ON g.group_id=s.group_id
    LEFT JOIN internship_tracking it ON it.internship_profile_id=ip.internship_profile_id
    LEFT JOIN internship_response_document rd ON rd.internship_profile_id=ip.internship_profile_id
    WHERE '.implode(' AND ', $conditions);
$countStmt = $pdo->prepare('SELECT COUNT(*)'.$fromSql);
$countStmt->execute($params);
$totalItems = (int)$countStmt->fetchColumn();
$perPage = selected_per_page(5);
$totalPages = max(1, (int)ceil($totalItems / $perPage));
$currentPage = max(1, min((int)($_GET['page'] ?? 1), $totalPages));
$offset = ($currentPage - 1) * $perPage;

$noDocumentApprovedSql=responseNoDocumentApprovedSql($pdo);
$sql = 'SELECT ip.internship_profile_id,ip.company_name,ic.academic_year,ic.semester,
    s.student_number,s.firstname,s.lastname,p.prefix_short,g.group_name,
    CASE WHEN EXISTS (SELECT 1 FROM internship_official_student ios
        JOIN internship_official_document iod ON iod.official_document_id=ios.official_document_id
        WHERE ios.internship_profile_id=ip.internship_profile_id AND iod.document_type="request") THEN "sent" ELSE NULL END AS request_letter_status,
    it.response_status,it.application_status,
    CASE WHEN EXISTS (SELECT 1 FROM internship_official_student ios
        JOIN internship_official_document iod ON iod.official_document_id=ios.official_document_id
        WHERE ios.internship_profile_id=ip.internship_profile_id AND iod.document_type="send") THEN "sent" ELSE NULL END AS send_letter_status,
    CASE WHEN '.$noDocumentApprovedSql.' THEN "approved" ELSE rd.review_status END AS review_status,rd.review_note,
    ('.$noDocumentApprovedSql.') AS no_document_verified,
    it.employment_status,it.notes,it.updated_at
    '.$fromSql.' ORDER BY ip.company_name,s.student_number LIMIT '.$perPage.' OFFSET '.$offset;
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$filterStmt = $pdo->prepare('SELECT DISTINCT ic.academic_year,ic.semester
    FROM internship_application ia JOIN internship_profile ip ON ip.internship_profile_id=ia.internship_profile_id
    JOIN internship_calendar ic ON ic.calendar_id=ip.calendar_id
    JOIN student s ON s.student_id=ip.student_id JOIN group_class g ON g.group_id=s.group_id
    WHERE ia.status="submitted" AND ip.request_status="active" AND g.department_id=? ORDER BY ic.academic_year DESC,ic.semester DESC');
$filterStmt->execute([$staff['department_id']]);
$periods = $filterStmt->fetchAll(PDO::FETCH_ASSOC);

$staff_name = trim($staff['prefix_name'].$staff['firstname'].' '.$staff['lastname']);
$pageHeading = 'ติดตามสถานะสหกิจ';
$document_count = $totalItems;
function h($value): string { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
?>
<!doctype html>
<html lang="th">
<head>
    <link rel="icon" type="image/png" href="/Project/assets/images/fet-logo-transparent.png?v=20260815-3">

    <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
    <title>ติดตามสถานะสหกิจ</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
</head>
<body class="min-h-screen bg-slate-100 text-slate-900">
<?php if ($isCoordinatorTracking) { include __DIR__.'/../teacher/sidebar.php'; } else { include __DIR__.'/sidebar.php'; } ?>
<div class="min-h-screen lg:ml-72">
<?php if ($isCoordinatorTracking) { include __DIR__.'/../teacher/navbar.php'; } else { include __DIR__.'/navbar.php'; } ?>
<main class="p-4 sm:p-6 lg:p-8">
    <section class="management-hero">
        <span class="management-hero__icon"><i class="fa-solid fa-list-check"></i></span>
        <div class="min-w-0 flex-1"><p class="management-hero__eyebrow"><?= $isCoordinatorTracking ? 'อาจารย์ประสานงาน · เฉพาะสาขาของคุณ' : 'เฉพาะสาขาของคุณ' ?></p><h1>ติดตามสถานะสหกิจ</h1><p>บันทึกความคืบหน้าเอกสารและผลการรับเข้าทำงานของนักศึกษา</p></div>
        <span class="inline-flex shrink-0 items-center gap-2 rounded-full border border-red-100 bg-red-50 px-3 py-2 text-sm font-bold text-red-700"><i class="fa-solid fa-user-graduate"></i><?= $totalItems ?> รายการ</span>
    </section>
    <?php if (isset($_GET['saved'])): ?><div hidden data-success-alert data-success-title="บันทึกสำเร็จ" data-success-text="บันทึกข้อมูลติดตามแล้ว" data-success-clean-param="saved"></div><?php endif; ?>
    <?php if (!empty($saveError)): ?><div class="mt-4 rounded-lg border border-red-200 bg-red-50 p-3 text-red-800"><?= h($saveError) ?></div><?php endif; ?>

    <form method="get" id="tracking-search" data-jquery-search-form data-search-results="#tracking-list" class="mt-5 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
        <div class="mb-3 flex items-center gap-2 text-sm font-bold text-slate-700"><span class="inline-flex h-8 w-8 items-center justify-center rounded-lg bg-slate-100 text-slate-600"><i class="fa-solid fa-filter"></i></span>ค้นหาและกรองรายการ</div>
        <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-[2fr_1fr_1fr_1fr_auto]">
            <label><span class="mb-1 block text-xs font-bold text-slate-500">ค้นหา</span><input name="search" value="<?= h($keyword) ?>" placeholder="รหัส ชื่อนักศึกษา หรือบริษัท" class="w-full rounded-xl border border-slate-300 px-3 py-2.5 outline-none focus:border-red-500 focus:ring-4 focus:ring-red-100"></label>
            <label><span class="mb-1 block text-xs font-bold text-slate-500">ปีการศึกษา</span><select name="academic_year" class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5"><option value="">ทุกปีการศึกษา</option><?php foreach(array_values(array_unique(array_filter(array_column($periods,'academic_year')))) as $year): ?><option value="<?= h($year) ?>" <?= $academicYear===(string)$year?'selected':'' ?>>ปี <?= h($year) ?></option><?php endforeach; ?></select></label>
            <label><span class="mb-1 block text-xs font-bold text-slate-500">ภาคเรียน</span><select name="semester" class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5"><option value="">ทุกภาคเรียน</option><?php foreach(array_values(array_unique(array_filter(array_column($periods,'semester')))) as $term): ?><option value="<?= h($term) ?>" <?= $semester===(string)$term?'selected':'' ?>>ภาค <?= h($term) ?></option><?php endforeach; ?></select></label>
            <?php if ($trackingReadOnly): ?><input type="hidden" name="response" value="sent"><?php else: ?><label><span class="mb-1 block text-xs font-bold text-slate-500">หนังสือตอบรับ</span><select name="response" class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5"><option value="">ทุกสถานะ</option><option value="sent" <?= $responseFilter==='sent'?'selected':'' ?>>ตรวจสอบผ่าน</option><option value="not_sent" <?= $responseFilter==='not_sent'?'selected':'' ?>>ไม่รับเข้าฝึกงาน</option><option value="unset" <?= $responseFilter==='unset'?'selected':'' ?>>รออัปโหลด/ตรวจสอบ</option></select></label><?php endif; ?>
            <button class="self-end rounded-xl bg-slate-900 px-5 py-2.5 font-semibold text-white transition hover:bg-slate-700"><i class="fa-solid fa-magnifying-glass mr-2"></i>ค้นหา</button>
        </div>
    </form>

    <div id="tracking-list" data-jquery-search-result data-search-form="#tracking-search" class="mt-5 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm transition-opacity">
    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 bg-slate-50 px-5 py-4"><div><h2 class="font-bold text-slate-900">รายชื่อนักศึกษา</h2><p class="mt-0.5 text-sm text-slate-500"><?= $trackingReadOnly ? 'เลือกนักศึกษาเพื่อดูข้อมูลการติดตาม' : 'เลือกนักศึกษาเพื่อบันทึกข้อมูลการติดตาม' ?></p></div><span class="rounded-full border border-slate-200 bg-white px-3 py-1.5 text-sm font-semibold text-slate-600 shadow-sm"><?= $totalItems ?> รายการ</span></div>
    <div class="divide-y">
    <?php foreach ($rows as $row):
        $studentName = trim(($row['prefix_short'] ?? '').$row['firstname'].' '.$row['lastname']);
        $responseStatus = $row['review_status'] ?? '';
        $responseLabel = !empty($row['no_document_verified']) ? 'เจ้าหน้าที่ยืนยันแล้ว (ไม่มีใบตอบรับ)' : ($responseStatus === 'approved' ? 'ตรวจสอบผ่านแล้ว' : ($responseStatus === 'pending' ? 'รอเจ้าหน้าที่ตรวจ' : ($responseStatus === 'revision_requested' ? 'ส่งกลับแก้ไข' : ($responseStatus === 'not_accepted' ? 'ไม่รับเข้าฝึกงาน' : 'ยังไม่ได้อัปโหลด'))));
        $responseClass = $responseStatus === 'approved' ? 'bg-emerald-50 text-emerald-700 ring-emerald-200' : (in_array($responseStatus, ['revision_requested','not_accepted'], true) ? 'bg-red-50 text-red-700 ring-red-200' : 'bg-slate-100 text-slate-600 ring-slate-200');
        $canRecordApplication = $responseStatus === 'approved';
    ?>
        <form method="post" class="bg-white">
            <?php if (!$trackingReadOnly): ?><?= csrf_field() ?><input type="hidden" name="profile_id" value="<?= (int)$row['internship_profile_id'] ?>">
            <input type="hidden" name="return_search" value="<?= h($keyword) ?>"><input type="hidden" name="return_academic_year" value="<?= h($academicYear) ?>"><input type="hidden" name="return_semester" value="<?= h($semester) ?>"><input type="hidden" name="return_response" value="<?= h($responseFilter) ?>"><input type="hidden" name="return_page" value="<?= $currentPage ?>">
            <?php endif; ?>
            <details class="group" <?= isset($_GET['saved']) && (int)($_GET['profile_id']??0)===(int)$row['internship_profile_id']?'open':'' ?>>
            <summary class="flex cursor-pointer list-none flex-col gap-3 px-5 py-4 transition hover:bg-red-50/60 [&::-webkit-details-marker]:hidden sm:flex-row sm:items-center sm:justify-between">
                <div class="flex min-w-0 items-center gap-3"><span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-slate-100 font-bold text-slate-700"><?= h(mb_substr($row['firstname'], 0, 1)) ?></span><div class="min-w-0"><h3 class="truncate font-bold text-slate-900"><?= h($row['student_number'].' '.$studentName) ?></h3><p class="mt-1 text-sm text-slate-500"><i class="fa-solid fa-users mr-1.5 text-slate-400"></i><?= h($row['group_name']) ?> <span class="mx-1 text-slate-300">•</span>ภาค <?= h($row['semester'] ?: '-') ?>/<?= h($row['academic_year'] ?: '-') ?></p></div></div>
                <div class="flex min-w-0 items-center gap-3 sm:max-w-[50%]"><div class="min-w-0 text-left sm:text-right"><p class="truncate font-semibold text-red-700"><i class="fa-regular fa-building mr-1.5"></i><?= h($row['company_name']) ?></p><span class="mt-1 inline-flex rounded-full px-2.5 py-1 text-xs font-bold ring-1 ring-inset <?= $responseClass ?>">หนังสือตอบรับ: <?= $responseLabel ?></span></div><span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-slate-100 text-slate-500 transition group-open:rotate-180 group-open:bg-red-100 group-open:text-red-700"><i class="fa-solid fa-chevron-down"></i></span></div>
            </summary>
            <div class="border-t bg-slate-50/60 p-5">
            <p class="mb-3 text-sm font-bold text-slate-700"><i class="fa-solid fa-list-check mr-2 text-red-600"></i>สถานะเอกสารและความคืบหน้า</p>
            <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-5">
                <div class="rounded-lg border bg-white px-3 py-2.5"><span class="block text-sm font-bold">หนังสือสอบถาม</span><span class="mt-1 block text-sm <?=($row['request_letter_status']??'')==='sent'?'text-emerald-700':'text-slate-500'?>"><?=($row['request_letter_status']??'')==='sent'?'ระบบตรวจพบว่าออกหนังสือแล้ว':'ยังไม่ได้ออกหนังสือ'?></span></div>
                <div class="rounded-lg border bg-white px-3 py-2.5"><span class="block text-sm font-bold">หนังสือตอบรับ</span><span class="mt-1 block text-sm <?=$responseStatus==='approved'?'text-emerald-700':'text-slate-500'?>"><?=h($responseLabel)?></span><?php if (!$trackingReadOnly): ?><a href="response_letters.php" class="mt-1 inline-block text-xs font-bold text-red-700 hover:underline">ไปหน้าตรวจสอบ</a><?php endif; ?></div>
                <label><span class="mb-2 block text-sm font-bold">สมัครงานในระบบ</span><select name="application_status" <?= $trackingReadOnly ? 'disabled' : '' ?> class="w-full rounded-lg border px-3 py-2.5 disabled:cursor-not-allowed disabled:bg-slate-100 disabled:text-slate-500"><option value="">ยังไม่ระบุ</option><option value="applied" <?= ($row['application_status']??'')==='applied'?'selected':'' ?> <?= (!$canRecordApplication && ($row['application_status']??'')!=='applied') ? 'disabled' : '' ?>><?= $canRecordApplication ? 'สมัครแล้ว' : 'สมัครแล้ว (รอตรวจสอบใบตอบรับ)' ?></option><option value="not_applied" <?= ($row['application_status']??'')==='not_applied'?'selected':'' ?>>ยังไม่สมัคร</option></select><?php if (!$trackingReadOnly && !$canRecordApplication): ?><span class="mt-1.5 block text-xs font-medium text-amber-700"><i class="fa-solid fa-clock mr-1"></i>รอใบตอบรับ หรือการตรวจยืนยันกรณีไม่มีใบตอบรับ</span><?php endif; ?></label>
                <div class="rounded-lg border bg-white px-3 py-2.5"><span class="block text-sm font-bold">หนังสือส่งตัว</span><span class="mt-1 block text-sm <?=($row['send_letter_status']??'')==='sent'?'text-emerald-700':'text-slate-500'?>"><?php if(($row['send_letter_status']??'')==='sent'): ?>ออกหนังสือแล้ว — รอเริ่มฝึก<?php elseif(($row['request_letter_status']??'')==='sent' && $responseStatus==='approved' && ($row['application_status']??'')==='applied'): ?>พร้อมจัดทำใบส่งตัว<?php else: ?>รอให้ครบ 3 ขั้นตอนแรก<?php endif; ?></span></div>
                <label><span class="mb-2 block text-sm font-bold">รับเข้าทำงาน</span><select name="employment_status" <?= $trackingReadOnly ? 'disabled' : '' ?> class="w-full rounded-lg border px-3 py-2.5 disabled:cursor-not-allowed disabled:bg-slate-100 disabled:text-slate-500"><option value="pending" <?= ($row['employment_status']??'pending')==='pending'?'selected':'' ?>>ยังไม่ทราบผล</option><option value="hired" <?= ($row['employment_status']??'')==='hired'?'selected':'' ?>>รับเข้าทำงาน</option><option value="not_hired" <?= ($row['employment_status']??'')==='not_hired'?'selected':'' ?>>ไม่รับเข้าทำงาน</option></select></label>
            </div>
            <label class="mt-4 block rounded-xl border border-slate-200 bg-white p-3"><span class="mb-2 block text-sm font-bold text-slate-700">หมายเหตุ</span><textarea name="notes" rows="2" <?= $trackingReadOnly ? 'readonly' : '' ?> class="w-full rounded-lg border border-slate-300 px-3 py-2.5 <?= $trackingReadOnly ? 'bg-slate-100 text-slate-600' : '' ?>" placeholder="รายละเอียดเพิ่มเติม (ถ้ามี)"><?= h($row['notes']??'') ?></textarea></label>
            <div class="mt-4 flex items-center justify-between gap-3"><span class="text-xs text-slate-400"><?= !empty($row['updated_at'])?'แก้ไขล่าสุด '.h($row['updated_at']):'ยังไม่เคยบันทึกข้อมูลติดตาม' ?></span><?php if ($trackingReadOnly): ?><span class="rounded-lg bg-slate-200 px-3 py-2 text-xs font-bold text-slate-600"><i class="fa-solid fa-eye mr-1.5"></i>ดูข้อมูลเท่านั้น</span><?php else: ?><button class="rounded-xl bg-red-700 px-4 py-2.5 font-semibold text-white transition hover:bg-red-800"><i class="fa-solid fa-floppy-disk mr-2"></i>บันทึกข้อมูล</button><?php endif; ?></div>
            </div>
            </details>
        </form>
    <?php endforeach; ?>
    <?php if (!$rows): ?><div class="p-10 text-center text-gray-500">ไม่พบข้อมูลนักศึกษาตามเงื่อนไขที่เลือก</div><?php endif; ?>
    </div>
    <?php render_pagination($totalItems, $currentPage, $perPage, 'page', 'tracking-list'); ?>
    </div>
</main></div>
</body></html>
