<?php

session_start();
require_once '../middleware/staff.php';
require_once '../config/database.php';
require_once '../helpers/pagination_helper.php';
require_once '../helpers/csrf_helper.php';

$staffStmt = $pdo->prepare("SELECT employee_id,department_id,firstname,lastname FROM employee WHERE user_id=? AND employee_type IN ('staff','teacher') LIMIT 1");
$staffStmt->execute([$_SESSION['user_id']]);
$staff = $staffStmt->fetch(PDO::FETCH_ASSOC);
if (!$staff) {
    http_response_code(403);
    renderPlainErrorAlert('ไม่พบข้อมูลเจ้าหน้าที่');
}
$focusDocumentId=0;
if ((int)($_GET['notification_id'] ?? 0) > 0) {
    $focusStmt=$pdo->prepare('SELECT document_id FROM notifications WHERE notification_id=? AND recipient_user_id=? LIMIT 1');
    $focusStmt->execute([(int)$_GET['notification_id'],(int)$_SESSION['user_id']]);
    $focusDocumentId=(int)$focusStmt->fetchColumn();
}

$keyword = trim($_GET['search'] ?? '');
$conditions = ['g.department_id=?', 'd.document_status IN ("approved","submitted")', 'dt.document_code = "INTERNSHIP_INFO"'];
$params = [$staff['department_id']];
if ($keyword !== '') {
    $conditions[] = '(s.student_number LIKE ? OR s.firstname LIKE ? OR s.lastname LIKE ? OR dt.document_name LIKE ?)';
    $term = '%' . $keyword . '%';
    array_push($params, $term, $term, $term, $term);
}

$sql = 'SELECT d.document_id,ia.submitted_at,s.student_number,s.firstname,s.lastname,dt.document_code,dt.document_name,ip.company_name,ia.signed_file
    FROM documents d
    JOIN student s ON s.student_id=d.student_id
    JOIN group_class g ON g.group_id=s.group_id
    JOIN document_template t ON t.template_id=d.template_id
    JOIN document_type dt ON dt.document_type_id=t.document_type_id
    JOIN internship_application ia ON ia.document_id=d.document_id AND ia.status="submitted"
    JOIN internship_profile ip ON ip.internship_profile_id=ia.internship_profile_id
    WHERE ' . implode(' AND ', $conditions) . '
    ORDER BY d.submitted_at DESC,d.document_id DESC';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$allDocuments = $stmt->fetchAll(PDO::FETCH_ASSOC);
$studentCount = count(array_unique(array_column($allDocuments, 'student_number')));
$document_count = count($allDocuments);
$documentsPerPage = selected_per_page(5);
$documentsTotalPages = max(1, (int) ceil($document_count / $documentsPerPage));
$documentsPage = max(1, min((int) ($_GET['page'] ?? 1), $documentsTotalPages));
if ($focusDocumentId > 0) foreach ($allDocuments as $index => $document) if ((int)$document['document_id'] === $focusDocumentId) { $documentsPage=(int)floor($index/$documentsPerPage)+1; break; }
$documents = array_slice($allDocuments, ($documentsPage - 1) * $documentsPerPage, $documentsPerPage);
$staff_name = trim($staff['firstname'] . ' ' . $staff['lastname']);
$pageHeading = 'เอกสารสหกิจ';
$deleteResult = (string) ($_GET['internship_delete'] ?? '');
$deleteMessages = [
    'deleted' => ['success', 'ส่งคำร้องกลับให้แก้ไขแล้ว', 'นักศึกษาต้องยื่นให้อาจารย์ที่ปรึกษาและอาจารย์ประสานงานพิจารณาใหม่ก่อนลงนาม'],
    'official_exists' => ['warning', 'ยังลบไม่ได้', 'รายการนี้ถูกนำไปสร้างหนังสือราชการแล้ว จึงไม่สามารถย้อนกลับเพื่ออัปโหลดใหม่ได้'],
    'not_found' => ['error', 'ไม่พบรายการ', 'ไม่พบใบคำร้องที่สามารถลบได้ หรือคุณไม่มีสิทธิ์จัดการรายการนี้'],
    'failed' => ['error', 'ลบไม่สำเร็จ', 'ระบบไม่สามารถลบไฟล์ใบคำร้องได้ กรุณาลองใหม่อีกครั้ง'],
];
function h($value): string {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}
function documentThaiDate($value): string {
    if (!$value) return '-';
    $months = [1=>'ม.ค.',2=>'ก.พ.',3=>'มี.ค.',4=>'เม.ย.',5=>'พ.ค.',6=>'มิ.ย.',7=>'ก.ค.',8=>'ส.ค.',9=>'ก.ย.',10=>'ต.ค.',11=>'พ.ย.',12=>'ธ.ค.'];
    $date = new DateTimeImmutable((string) $value);
    return (int)$date->format('j').' '.$months[(int)$date->format('n')].' '.((int)$date->format('Y')+543).' เวลา '.$date->format('H:i').' น.';
}
?>
<!doctype html>
<html lang="th">
<head>
    <link rel="icon" type="image/png" href="/Project/assets/images/fet-logo-transparent.png?v=20260815-3">

    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>เอกสารนักศึกษาในสาขา</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <style>.notification-focus{background:#fff7ed!important;box-shadow:inset 4px 0 0 #dc2626,0 0 0 2px #fecaca;animation:notification-focus-pulse 1.4s ease-out 2}@keyframes notification-focus-pulse{50%{box-shadow:inset 4px 0 0 #dc2626,0 0 0 5px rgba(220,38,38,.16)}}</style>
</head>
<body class="min-h-screen bg-slate-100 text-slate-900">
<?php include 'sidebar.php'; ?>
<div class="min-h-screen lg:ml-72">
<?php include 'navbar.php'; ?>
<main class="mx-auto w-full max-w-7xl p-4 sm:p-6 lg:p-8">
    <?php if (isset($deleteMessages[$deleteResult])): $message = $deleteMessages[$deleteResult]; ?>
        <div class="mb-5 rounded-xl border <?= $message[0] === 'success' ? 'border-emerald-200 bg-emerald-50 text-emerald-900' : ($message[0] === 'warning' ? 'border-amber-200 bg-amber-50 text-amber-900' : 'border-red-200 bg-red-50 text-red-900') ?> px-4 py-3" role="status"><strong><?= h($message[1]) ?></strong><span class="ml-2 text-sm"><?= h($message[2]) ?></span></div>
    <?php endif; ?>
    <section class="flex flex-col gap-4 border-b border-slate-200 pb-5 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <p class="text-sm font-bold text-red-600"><i class="fa-solid fa-briefcase mr-2"></i>งานเอกสารสหกิจ</p>
            <h1 class="mt-1 text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">เอกสารที่นักศึกษาส่งให้เจ้าหน้าที่</h1>
            <p class="mt-1.5 text-sm text-slate-500">เฉพาะเอกสารฉบับที่ผ่านการพิจารณาและลงนามครบถ้วนแล้ว</p>
        </div>
        <div class="inline-flex self-start overflow-hidden rounded-xl border border-slate-200 bg-white text-sm shadow-sm sm:self-auto">
            <span class="flex items-center gap-2 border-r border-slate-200 px-3.5 py-2.5 text-slate-600"><i class="fa-solid fa-file-circle-check text-emerald-600"></i><?= number_format($document_count) ?> เอกสาร</span>
            <span class="flex items-center gap-2 px-3.5 py-2.5 text-slate-600"><i class="fa-solid fa-user-graduate text-red-600"></i><?= number_format($studentCount) ?> นักศึกษา</span>
        </div>
    </section>

    <form method="get" id="staff-document-search" data-jquery-search-form data-search-results="#staff-document-list" class="mt-5 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-end">
            <label for="document-search" class="block min-w-0 flex-1 text-sm font-bold text-slate-700">ค้นหาเอกสาร
                <span class="relative mt-2 block"><i class="fa-solid fa-magnifying-glass absolute left-4 top-1/2 -translate-y-1/2 text-slate-400"></i><input id="document-search" name="search" value="<?= h($keyword) ?>" class="w-full rounded-xl border border-slate-300 py-3 pl-11 pr-4 font-normal outline-none transition placeholder:text-slate-400 focus:border-red-500 focus:ring-4 focus:ring-red-100" placeholder="รหัส ชื่อนักศึกษา สถานประกอบการ หรือชื่อเอกสาร"></span>
            </label>
            <button class="rounded-xl bg-red-700 px-5 py-3 font-semibold text-white shadow-sm transition hover:bg-red-800 focus:outline-none focus:ring-4 focus:ring-red-200"><i class="fa-solid fa-magnifying-glass mr-2"></i>ค้นหา</button>
            <?php if ($keyword !== ''): ?><a href="documents.php" data-search-reset class="inline-flex items-center justify-center rounded-xl border border-slate-300 px-4 py-3 font-semibold text-slate-600 transition hover:bg-slate-50" aria-label="ล้างคำค้น"><i class="fa-solid fa-xmark mr-2"></i>ล้าง</a><?php endif; ?>
        </div>
        <?php if ($keyword !== ''): ?><p class="mt-3 text-sm text-slate-500">ผลการค้นหา “<strong class="text-slate-700"><?= h($keyword) ?></strong>” พบ <?= number_format($document_count) ?> รายการ</p><?php endif; ?>
    </form>

    <section id="staff-document-list" data-jquery-search-result data-search-form="#staff-document-search" class="mt-5 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm transition-opacity">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 px-5 py-4">
            <div><h2 class="font-bold text-slate-900">รายการเอกสารที่พร้อมดำเนินการ</h2><p class="mt-0.5 text-sm text-slate-500">ดาวน์โหลดไฟล์ฉบับลงนามเพื่อนำไปจัดทำเอกสารสหกิจต่อ</p></div>
            <span class="rounded-full bg-emerald-50 px-3 py-1.5 text-xs font-bold text-emerald-700"><i class="fa-solid fa-circle-check mr-1.5"></i>ลงนามครบแล้ว</span>
        </div>

        <div class="hidden overflow-x-auto md:block">
            <table class="w-full min-w-[940px] text-sm">
                <thead class="border-b border-slate-200 bg-slate-50 text-slate-600"><tr><th class="px-5 py-3.5 text-left font-bold">นักศึกษา</th><th class="px-5 py-3.5 text-left font-bold">สถานประกอบการ</th><th class="px-5 py-3.5 text-left font-bold">เอกสาร</th><th class="px-5 py-3.5 text-left font-bold">วันที่ส่ง</th><th class="px-5 py-3.5 text-center font-bold">ไฟล์เอกสาร</th><th class="px-5 py-3.5 text-center font-bold">จัดการ</th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                <?php foreach ($documents as $document): ?>
                    <tr class="transition hover:bg-red-50/30 <?= (int)$document['document_id'] === $focusDocumentId ? 'notification-focus' : '' ?>">
                        <td class="px-5 py-4"><div class="flex items-center gap-3"><span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-red-50 font-bold text-red-700"><?= h(mb_substr($document['firstname'],0,1)) ?></span><div><strong class="block text-slate-900"><?= h($document['firstname'].' '.$document['lastname']) ?></strong><span class="mt-0.5 block text-xs text-slate-500"><?= h($document['student_number']) ?></span></div></div></td>
                        <td class="max-w-xs px-5 py-4"><span class="block truncate font-medium text-slate-700" title="<?= h($document['company_name']) ?>"><i class="fa-solid fa-building mr-2 text-slate-400"></i><?= h($document['company_name']) ?></span></td>
                        <td class="px-5 py-4"><span class="inline-flex rounded-md bg-red-50 px-2 py-1 text-[11px] font-bold text-red-700"><?= h($document['document_code']) ?></span><span class="mt-1.5 block font-medium text-slate-700"><?= h($document['document_name']) ?></span></td>
                        <td class="whitespace-nowrap px-5 py-4 text-slate-600"><i class="fa-regular fa-calendar mr-2 text-slate-400"></i><?= h(documentThaiDate($document['submitted_at'])) ?></td>
                        <td class="px-5 py-4 text-center"><a href="download_signed_internship.php?document=<?= (int)$document['document_id'] ?>" class="inline-flex h-10 w-10 items-center justify-center rounded-lg bg-red-700 text-white shadow-sm transition hover:-translate-y-0.5 hover:bg-red-800 focus:outline-none focus:ring-4 focus:ring-red-200" title="ดาวน์โหลดเอกสาร" aria-label="ดาวน์โหลดเอกสารของ <?= h($document['firstname'].' '.$document['lastname']) ?>"><i class="fa-solid fa-download"></i></a></td>
                        <td class="px-5 py-4 text-center"><form method="post" action="delete_signed_internship.php" class="inline" data-confirm-action="return" data-confirm-name="<?= h($document['student_number'].' '.$document['firstname'].' '.$document['lastname']) ?>"><?= csrf_field() ?><input type="hidden" name="document_id" value="<?= (int)$document['document_id'] ?>"><button type="submit" class="inline-flex h-10 whitespace-nowrap items-center justify-center rounded-lg border border-red-200 bg-red-50 px-3 text-sm font-bold text-red-700 transition hover:bg-red-100" title="ส่งกลับให้แก้ไขและยื่นตรวจใหม่"><i class="fa-solid fa-rotate-left mr-2"></i>ส่งกลับแก้ไข</button></form></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$documents): ?><tr><td colspan="6" class="p-12 text-center"><i class="fa-regular fa-folder-open text-4xl text-slate-300"></i><p class="mt-3 font-semibold text-slate-600">ไม่พบเอกสารที่ส่งแล้ว</p><p class="mt-1 text-sm text-slate-400"><?= $keyword!==''?'ลองเปลี่ยนคำค้นหาแล้วค้นหาอีกครั้ง':'เมื่อมีนักศึกษาส่งเอกสาร รายการจะแสดงที่นี่' ?></p></td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="divide-y divide-slate-100 md:hidden">
            <?php foreach ($documents as $document): ?>
                <article class="p-4 <?= (int)$document['document_id'] === $focusDocumentId ? 'notification-focus' : '' ?>">
                    <div class="flex items-start justify-between gap-3"><div class="flex min-w-0 items-center gap-3"><span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-red-50 font-bold text-red-700"><?= h(mb_substr($document['firstname'],0,1)) ?></span><div class="min-w-0"><h3 class="truncate font-bold text-slate-900"><?= h($document['firstname'].' '.$document['lastname']) ?></h3><p class="mt-0.5 text-xs text-slate-500"><?= h($document['student_number']) ?></p></div></div><a href="download_signed_internship.php?document=<?= (int)$document['document_id'] ?>" class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-red-700 text-white shadow-sm" aria-label="ดาวน์โหลดเอกสาร"><i class="fa-solid fa-download"></i></a></div>
                    <div class="mt-4 grid gap-2 rounded-xl bg-slate-50 p-3 text-sm"><p class="flex gap-2 text-slate-600"><i class="fa-solid fa-building mt-1 w-4 text-slate-400"></i><span><?= h($document['company_name']) ?></span></p><p class="flex gap-2 text-slate-600"><i class="fa-regular fa-calendar mt-1 w-4 text-slate-400"></i><span><?= h(documentThaiDate($document['submitted_at'])) ?></span></p></div>
                    <p class="mt-3 text-sm font-medium text-slate-700"><span class="mr-2 inline-flex rounded-md bg-red-50 px-2 py-1 text-[11px] font-bold text-red-700"><?= h($document['document_code']) ?></span><?= h($document['document_name']) ?></p>
                    <form method="post" action="delete_signed_internship.php" class="mt-3" data-confirm-action="return" data-confirm-name="<?= h($document['student_number'].' '.$document['firstname'].' '.$document['lastname']) ?>"><?= csrf_field() ?><input type="hidden" name="document_id" value="<?= (int)$document['document_id'] ?>"><button type="submit" class="inline-flex w-full items-center justify-center rounded-xl border border-red-200 bg-red-50 px-4 py-2.5 text-sm font-bold text-red-700"><i class="fa-solid fa-rotate-left mr-2"></i>ส่งกลับเพื่อยื่นตรวจใหม่</button></form>
                </article>
            <?php endforeach; ?>
            <?php if (!$documents): ?><div class="p-12 text-center"><i class="fa-regular fa-folder-open text-4xl text-slate-300"></i><p class="mt-3 font-semibold text-slate-600">ไม่พบเอกสารที่ส่งแล้ว</p><p class="mt-1 text-sm text-slate-400"><?= $keyword!==''?'ลองเปลี่ยนคำค้นหาแล้วค้นหาอีกครั้ง':'เมื่อมีนักศึกษาส่งเอกสาร รายการจะแสดงที่นี่' ?></p></div><?php endif; ?>
        </div>
        <?php render_pagination($document_count, $documentsPage, $documentsPerPage, 'page', 'staff-document-list'); ?>
    </section>
</main>
</div>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="../assets/js/form-confirm.js"></script>
<?php if ($focusDocumentId > 0): ?><script>window.setTimeout(()=>{const items=document.querySelectorAll('.notification-focus');const target=window.matchMedia('(max-width: 767px)').matches?items[1]||items[0]:items[0];target?.scrollIntoView({behavior:'smooth',block:'center'});},120);</script><?php endif; ?>
</body>
</html>
