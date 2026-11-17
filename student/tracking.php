<?php
require_once 'auth.php';

$studentStatement = $pdo->prepare(
    'SELECT student_id,student_number,firstname,lastname FROM student WHERE user_id=? LIMIT 1'
);
$studentStatement->execute([$_SESSION['user_id']]);
$student = $studentStatement->fetch(PDO::FETCH_ASSOC);
if (!$student) {
    http_response_code(403);
    renderPlainErrorAlert('ไม่พบข้อมูลนักศึกษา');
}

$trackingStatement = $pdo->prepare(
    'SELECT d.document_id,d.document_status,d.submitted_at,d.updated_at,
            dt.document_code,dt.document_name,drp.process_id,drp.process_status,
            drp.current_step_order,current_step.step_name AS current_step_name
     FROM documents d
     INNER JOIN document_template t ON t.template_id=d.template_id
     INNER JOIN document_type dt ON dt.document_type_id=t.document_type_id
     INNER JOIN document_review_processes drp ON drp.document_id=d.document_id
     LEFT JOIN document_review_step_instances current_step
       ON current_step.process_id=drp.process_id
      AND current_step.step_order=drp.current_step_order
     WHERE d.student_id=?
     ORDER BY COALESCE(d.submitted_at,d.updated_at) DESC,d.document_id DESC'
);
$trackingStatement->execute([(int) $student['student_id']]);
$documents = $trackingStatement->fetchAll(PDO::FETCH_ASSOC);

$summary = ['in_review' => 0, 'revision_requested' => 0, 'approved' => 0];
foreach ($documents as $document) {
    $key = (string) $document['process_status'];
    if (array_key_exists($key, $summary)) {
        $summary[$key]++;
    }
}

$statusMeta = [
    'in_review' => ['รออาจารย์ที่ปรึกษาพิจารณา', 'bg-sky-100 text-sky-700', 'fa-hourglass-half'],
    'revision_requested' => ['ส่งกลับให้แก้ไข', 'bg-red-100 text-red-700', 'fa-rotate-left'],
    'approved' => ['ผ่านการพิจารณาแล้ว', 'bg-emerald-100 text-emerald-700', 'fa-circle-check'],
];
$selectedStatus = (string) ($_GET['status'] ?? '');
if (!array_key_exists($selectedStatus, $statusMeta)) {
    $selectedStatus = '';
}
$visibleDocuments = $selectedStatus === ''
    ? $documents
    : array_values(array_filter($documents, static fn(array $document): bool => (string) $document['process_status'] === $selectedStatus));
$visibleStatusLabel = $selectedStatus === '' ? 'รายการเอกสารที่ส่งแล้ว' : 'เอกสารสถานะ: ' . $statusMeta[$selectedStatus][0];
$pageHeading = 'ติดตามสถานะเอกสาร';
function trackingDateTime(?string $value): string
{
    if (!$value) return '-';
    $timestamp = strtotime($value);
    return $timestamp ? date('d/m/Y H:i', $timestamp) . ' น.' : $value;
}
function trackingEscape($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}
?>
<!doctype html>
<html lang="th">
<head>
    <link rel="icon" type="image/png" href="/Project/assets/images/fet-logo-transparent.png?v=20260815-3">
    <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
    <title>ติดตามสถานะเอกสาร</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
</head>
<body class="min-h-screen bg-gray-100 text-gray-900">
<?php include 'sidebar.php'; ?>
<div class="min-h-screen lg:ml-72">
    <?php include 'navbar.php'; ?>
    <main class="student-page-content p-4 sm:p-6 lg:p-8">
        
        <section class="mt-6 grid gap-3 sm:grid-cols-3">
            <a href="tracking.php?status=in_review#tracking-list" class="group rounded-2xl border border-sky-100 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:border-sky-300 hover:shadow-md"><div class="flex items-center justify-between"><div><p class="text-sm font-semibold text-gray-500">กำลังพิจารณา</p><strong class="mt-1 block text-3xl text-sky-700"><?= (int) $summary['in_review'] ?></strong><span class="mt-2 inline-flex items-center gap-1 text-xs font-bold text-sky-700">ดูรายการ <i class="fa-solid fa-arrow-right transition group-hover:translate-x-1"></i></span></div><span class="inline-flex h-12 w-12 items-center justify-center rounded-2xl bg-sky-100 text-sky-700"><i class="fa-solid fa-hourglass-half"></i></span></div></a>
            <a href="tracking.php?status=revision_requested#tracking-list" class="group rounded-2xl border border-red-100 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:border-red-300 hover:shadow-md"><div class="flex items-center justify-between"><div><p class="text-sm font-semibold text-gray-500">ต้องแก้ไข</p><strong class="mt-1 block text-3xl text-red-600"><?= (int) $summary['revision_requested'] ?></strong><span class="mt-2 inline-flex items-center gap-1 text-xs font-bold text-red-600">ดูรายการ <i class="fa-solid fa-arrow-right transition group-hover:translate-x-1"></i></span></div><span class="inline-flex h-12 w-12 items-center justify-center rounded-2xl bg-red-100 text-red-600"><i class="fa-solid fa-rotate-left"></i></span></div></a>
            <a href="tracking.php?status=approved#tracking-list" class="group rounded-2xl border border-emerald-100 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:border-emerald-300 hover:shadow-md"><div class="flex items-center justify-between"><div><p class="text-sm font-semibold text-gray-500">ผ่านการพิจารณา</p><strong class="mt-1 block text-3xl text-emerald-700"><?= (int) $summary['approved'] ?></strong><span class="mt-2 inline-flex items-center gap-1 text-xs font-bold text-emerald-700">ดูรายการ <i class="fa-solid fa-arrow-right transition group-hover:translate-x-1"></i></span></div><span class="inline-flex h-12 w-12 items-center justify-center rounded-2xl bg-emerald-100 text-emerald-700"><i class="fa-solid fa-circle-check"></i></span></div></a>
        </section>

        <section id="tracking-list" class="mt-6 scroll-mt-6 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="flex items-center justify-between gap-3 border-b border-slate-100 px-5 py-5 sm:px-6"><div><h2 class="text-lg font-black"><?= trackingEscape($visibleStatusLabel) ?></h2><p class="mt-1 text-sm text-gray-500"><?= count($visibleDocuments) ?> รายการ</p></div><?php if ($selectedStatus !== ''): ?><a href="tracking.php#tracking-list" class="inline-flex shrink-0 items-center gap-2 rounded-xl border border-slate-200 px-3 py-2 text-sm font-bold text-slate-600 hover:border-sky-300 hover:text-sky-700"><i class="fa-solid fa-list"></i>ดูทั้งหมด</a><?php endif; ?></div>
            <?php if ($visibleDocuments): ?>
                <div class="divide-y divide-slate-100">
                    <?php foreach ($visibleDocuments as $document): $meta = $statusMeta[$document['process_status']] ?? [$document['process_status'], 'bg-gray-100 text-gray-700', 'fa-circle']; ?>
                        <article class="grid gap-4 px-5 py-5 transition hover:bg-sky-50/40 sm:px-6 lg:grid-cols-[minmax(0,1fr)_250px_auto] lg:items-center">
                            <div class="min-w-0"><div class="flex flex-wrap items-center gap-2"><span class="rounded-lg bg-slate-900 px-2.5 py-1 text-xs font-black text-white"><?= trackingEscape($document['document_code']) ?></span><h3 class="font-bold text-slate-900"><?= trackingEscape($document['document_name']) ?></h3></div><p class="mt-2 text-xs text-gray-500">ส่งเมื่อ <?= trackingEscape(trackingDateTime($document['submitted_at'])) ?></p></div>
                            <div><span class="inline-flex items-center gap-2 rounded-full px-3 py-1.5 text-xs font-bold <?= trackingEscape($meta[1]) ?>"><i class="fa-solid <?= trackingEscape($meta[2]) ?>"></i><?= trackingEscape($meta[0]) ?></span><?php if ($document['current_step_name']): ?><p class="mt-2 text-xs font-semibold text-sky-700">ขั้นปัจจุบัน: <?= trackingEscape($document['current_step_name']) ?></p><?php endif; ?></div>
                            <a href="document_status.php?document=<?= (int) $document['document_id'] ?>" class="inline-flex h-10 items-center justify-center gap-2 rounded-xl bg-sky-700 px-4 text-sm font-bold text-white shadow-sm hover:bg-sky-800"><i class="fa-solid fa-route"></i>ดูรายละเอียด</a>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="p-12 text-center text-gray-500"><span class="mx-auto inline-flex h-16 w-16 items-center justify-center rounded-full bg-sky-50 text-2xl text-sky-500"><i class="fa-solid fa-route"></i></span><p class="mt-4 font-semibold">ยังไม่มีเอกสารที่อยู่ในกระบวนการพิจารณา</p><a href="documents.php" class="mt-4 inline-flex rounded-xl bg-sky-700 px-4 py-2.5 font-bold text-white">สร้างคำร้อง</a></div>
            <?php endif; ?>
        </section>
    </main>
</div>
</body>
</html>
