<?php
session_start();
require_once '../middleware/teacher.php';
require_once '../config/database.php';
require_once '../helpers/teacher_coordinator_helper.php';

$coordinator = teacherCoordinatorProfile($pdo, (int) $_SESSION['user_id']);
if (!$coordinator) {
    http_response_code(403);
    renderPlainErrorAlert('หน้านี้สำหรับอาจารย์ประสานงานฝึกประสบการณ์วิชาชีพเท่านั้น');
}

$allRequests = teacherCoordinatorRequests($pdo, (int) $_SESSION['user_id']);
$search = trim((string) ($_GET['search'] ?? ''));
$status = in_array(($_GET['status'] ?? ''), ['current','approved','revision_requested'], true)
    ? (string) $_GET['status'] : '';
$requests = array_values(array_filter($allRequests, static function (array $row) use ($search, $status): bool {
    if ($status !== '' && $row['step_status'] !== $status) return false;
    if ($search === '') return true;
    return mb_stripos(implode(' ', [
        $row['student_number'],$row['student_prefix'],$row['firstname'],$row['lastname'],
        $row['document_code'],$row['document_name'],
    ]), $search) !== false;
}));
$pending_document_count = count(array_filter($allRequests, static fn(array $row): bool => $row['step_status'] === 'current'));
$pageHeading = 'คำร้องสหกิจ — อาจารย์ประสานงาน';
function h($value): string { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); }
$statusMeta = [
    'current' => ['รอพิจารณา','status-badge--warning','fa-hourglass-half'],
    'approved' => ['อนุมัติแล้ว','status-badge--success','fa-circle-check'],
    'revision_requested' => ['ส่งกลับให้แก้ไข','status-badge--danger','fa-rotate-left'],
];
?>
<!doctype html><html lang="th"><head>
<link rel="icon" type="image/png" href="/Project/assets/images/fet-logo-transparent.png?v=20260815-3">
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>คำร้องสหกิจ — อาจารย์ประสานงาน</title>
<script src="https://cdn.tailwindcss.com"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
</head><body class="min-h-screen bg-slate-100 text-slate-900">
<?php include 'sidebar.php'; ?><div class="min-h-screen lg:ml-72"><?php include 'navbar.php'; ?>
<main class="p-4 sm:p-6 lg:p-8">
    <section class="management-hero">
        <div class="management-hero__icon"><i class="fa-solid fa-briefcase"></i></div>
        <div>
            <p class="management-hero__eyebrow">สำหรับอาจารย์ประสานงานฝึกประสบการณ์วิชาชีพ</p>
            <h1>พิจารณาคำร้องสหกิจ</h1>
            <p>แสดงเฉพาะคำร้องที่อาจารย์ที่ปรึกษาอนุมัติแล้ว ก่อนให้นักศึกษาพิมพ์เอกสารฉบับจริง</p>
        </div>
    </section>
    <?php if(isset($_GET['reviewed'])):?><div hidden data-success-alert data-success-title="บันทึกสำเร็จ" data-success-text="บันทึกผลการพิจารณาคำร้องสหกิจแล้ว" data-success-clean-param="reviewed"></div><?php endif;?>
    <?php if(($_GET['notice']??'')==='not-pending'):?><div hidden data-success-alert data-success-icon="info" data-success-title="ดำเนินการแล้ว" data-success-text="เอกสารรายการนี้ได้รับการพิจารณาไปแล้ว" data-success-clean-param="notice"></div><?php endif;?>
    <section class="mt-6 grid gap-4 sm:grid-cols-3" aria-label="กรองรายการตามสถานะ">
        <a href="?status=current#coordinator-results" class="group rounded-xl border bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:border-amber-300 hover:shadow-md <?= $status==='current' ? 'border-amber-400 ring-2 ring-amber-100' : 'border-slate-200' ?>" title="ดูรายการที่รอพิจารณา"><div class="flex items-start justify-between gap-3"><p class="text-sm text-slate-500">รอพิจารณา</p><i class="fa-solid fa-arrow-right text-xs text-slate-300 transition group-hover:translate-x-1 group-hover:text-amber-600"></i></div><strong class="mt-2 block text-3xl text-amber-600"><?= count(array_filter($allRequests,fn($r)=>$r['step_status']==='current')) ?></strong><p class="mt-2 text-xs font-semibold text-amber-700">กดเพื่อดูรายการ</p></a>
        <a href="?status=approved#coordinator-results" class="group rounded-xl border bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:border-emerald-300 hover:shadow-md <?= $status==='approved' ? 'border-emerald-400 ring-2 ring-emerald-100' : 'border-slate-200' ?>" title="ดูรายการที่อนุมัติแล้ว"><div class="flex items-start justify-between gap-3"><p class="text-sm text-slate-500">อนุมัติแล้ว</p><i class="fa-solid fa-arrow-right text-xs text-slate-300 transition group-hover:translate-x-1 group-hover:text-emerald-600"></i></div><strong class="mt-2 block text-3xl text-emerald-600"><?= count(array_filter($allRequests,fn($r)=>$r['step_status']==='approved')) ?></strong><p class="mt-2 text-xs font-semibold text-emerald-700">กดเพื่อดูรายการ</p></a>
        <a href="?status=revision_requested#coordinator-results" class="group rounded-xl border bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:border-red-300 hover:shadow-md <?= $status==='revision_requested' ? 'border-red-400 ring-2 ring-red-100' : 'border-slate-200' ?>" title="ดูรายการที่ส่งกลับให้แก้ไข"><div class="flex items-start justify-between gap-3"><p class="text-sm text-slate-500">ส่งกลับให้แก้ไข</p><i class="fa-solid fa-arrow-right text-xs text-slate-300 transition group-hover:translate-x-1 group-hover:text-red-600"></i></div><strong class="mt-2 block text-3xl text-red-600"><?= count(array_filter($allRequests,fn($r)=>$r['step_status']==='revision_requested')) ?></strong><p class="mt-2 text-xs font-semibold text-red-700">กดเพื่อดูรายการ</p></a>
    </section>
    <form id="coordinator-search" method="get" data-jquery-search-form data-search-results="#coordinator-results" class="mt-5 grid gap-3 rounded-xl border bg-white p-5 shadow-sm sm:grid-cols-[1fr_220px_auto]">
        <label><span class="mb-1.5 block text-sm font-semibold">ค้นหานักศึกษาหรือเอกสาร</span><input type="search" name="search" value="<?=h($search)?>" placeholder="รหัส ชื่อ หรือชื่อเอกสาร" class="w-full rounded-lg border px-3 py-2.5"></label>
        <label><span class="mb-1.5 block text-sm font-semibold">สถานะ</span><select name="status" class="w-full rounded-lg border bg-white px-3 py-2.5"><option value="">ทุกสถานะ</option><option value="current" <?=$status==='current'?'selected':''?>>รอพิจารณา</option><option value="approved" <?=$status==='approved'?'selected':''?>>อนุมัติแล้ว</option><option value="revision_requested" <?=$status==='revision_requested'?'selected':''?>>ส่งกลับให้แก้ไข</option></select></label>
        <button class="self-end rounded-lg bg-slate-950 px-5 py-2.5 font-semibold text-white"><i class="fa-solid fa-magnifying-glass mr-2"></i>ค้นหา</button>
    </form>
    <section id="coordinator-results" data-jquery-search-result data-search-form="#coordinator-search" class="mt-5 overflow-hidden rounded-xl border bg-white shadow-sm">
        <div class="flex items-center justify-between border-b bg-slate-50 px-5 py-4"><div><h2 class="font-bold">รายการคำร้องสหกิจ</h2><p class="mt-1 text-sm text-slate-500">แสดงเฉพาะงานในหน้าที่อาจารย์ประสานงาน</p></div><span class="rounded-lg border bg-white px-3 py-1.5 text-sm font-semibold"><?=count($requests)?> รายการ</span></div>
        <?php if($requests):?><div class="overflow-x-auto"><table class="w-full min-w-[820px] text-sm"><thead class="bg-slate-50 text-slate-600"><tr><th class="px-6 py-3 text-left">นักศึกษา</th><th class="px-6 py-3 text-left">เอกสาร</th><th class="px-6 py-3 text-left">วันที่ส่ง</th><th class="px-6 py-3 text-center">สถานะ</th><th class="px-6 py-3 text-center">ดำเนินการ</th></tr></thead><tbody>
        <?php foreach($requests as $document): $meta=$statusMeta[$document['step_status']]??[$document['step_status'],'status-badge--neutral','fa-circle']; ?><tr class="border-t hover:bg-slate-50"><td class="px-6 py-4"><strong><?=h($document['student_number'])?></strong><br><?=h(trim($document['student_prefix'].$document['firstname'].' '.$document['lastname']))?></td><td class="px-6 py-4"><strong class="text-red-700"><?=h($document['document_code'])?></strong><br><?=h($document['document_name'])?><?php if($document['revision_comment']):?><p class="mt-1 text-xs font-semibold text-red-700">หมายเหตุ: <?=h($document['revision_comment'])?></p><?php endif;?></td><td class="px-6 py-4"><?=h($document['activated_at']??$document['submitted_at']??'-')?></td><td class="px-6 py-4 text-center"><span class="status-badge <?=$meta[1]?>"><i class="fa-solid <?=$meta[2]?> mr-1"></i><?=$meta[0]?></span></td><td class="px-6 py-4 text-center"><?php if($document['step_status']==='current'):?><a href="request_review.php?document=<?=(int)$document['document_id']?>&from=coordinator" class="inline-flex items-center rounded-lg bg-red-700 px-4 py-2.5 font-bold text-white hover:bg-red-800"><i class="fa-solid fa-magnifying-glass mr-2"></i>พิจารณา</a><?php else:?><a href="../student/download_document.php?document=<?=(int)$document['document_id']?>" class="inline-flex h-10 w-10 items-center justify-center rounded-lg border text-red-700" title="เปิด PDF" aria-label="เปิด PDF"><i class="fa-solid fa-file-pdf"></i></a><?php endif;?></td></tr><?php endforeach;?>
        </tbody></table></div><?php else:?><div class="p-12 text-center text-slate-500"><i class="fa-solid fa-clipboard-check mb-4 text-5xl text-slate-300"></i><p>ไม่มีคำร้องสหกิจตามเงื่อนไขที่เลือก</p></div><?php endif;?>
    </section>
</main></div></body></html>
