<?php

require_once __DIR__ . '/../../middleware/admin.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/csrf_helper.php';

$pageTitle = 'จัดการประเภทเอกสาร';
$keyword = trim((string) ($_GET['search'] ?? ''));
$sql = '
    SELECT dt.document_type_id, dt.document_code, dt.document_name, dt.description, dt.status,
           COUNT(DISTINCT rw.workflow_id) AS workflow_count,
           COUNT(DISTINCT rws.workflow_step_id) AS workflow_step_count
    FROM document_type dt
    LEFT JOIN review_workflows rw
        ON rw.document_type_id = dt.document_type_id AND rw.workflow_status = "active"
    LEFT JOIN review_workflow_steps rws ON rws.workflow_id = rw.workflow_id';
$params = [];
if ($keyword !== '') {
    $sql .= ' WHERE dt.document_code LIKE ? OR dt.document_name LIKE ? OR dt.description LIKE ?';
    $like = '%' . $keyword . '%';
    $params = [$like, $like, $like];
}
$sql .= ' GROUP BY dt.document_type_id, dt.document_code, dt.document_name, dt.description, dt.status ORDER BY dt.document_code ASC';
$statement = $pdo->prepare($sql);
$statement->execute($params);
$documentTypes = $statement->fetchAll(PDO::FETCH_ASSOC);

include __DIR__ . '/../layouts/admin_header.php';
include __DIR__ . '/../layouts/admin_sidebar.php';
include __DIR__ . '/../layouts/admin_navbar.php';
?>

<div class="mx-auto max-w-7xl space-y-6">
    <section class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-slate-950 via-slate-900 to-red-950 px-6 py-7 text-white shadow-xl sm:px-8">
        <div class="absolute -right-16 -top-20 h-52 w-52 rounded-full border-[32px] border-white/5"></div>
        <div class="relative flex items-start gap-4">
            <span class="inline-flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-red-600/20 text-red-300 ring-1 ring-red-400/20"><i class="fa-solid fa-file-lines text-xl"></i></span>
            <div>
                <p class="mb-1 text-xs font-bold uppercase tracking-[0.2em] text-red-300">Document types</p>
                <h1 class="text-2xl font-black sm:text-3xl">จัดการประเภทเอกสาร</h1>
                <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-300">ตรวจสอบประเภทเอกสาร กำหนดลำดับผู้พิจารณา และเปิด–ปิดการใช้งานสำหรับการสร้างเอกสารใหม่</p>
            </div>
        </div>
    </section>

    <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <form method="get" class="flex flex-col gap-3 sm:flex-row sm:items-center">
            <label class="relative block flex-1"><span class="sr-only">ค้นหาประเภทเอกสาร</span><i class="fa-solid fa-magnifying-glass absolute left-4 top-1/2 -translate-y-1/2 text-sm text-slate-400"></i><input type="search" name="search" value="<?= htmlspecialchars($keyword, ENT_QUOTES, 'UTF-8') ?>" placeholder="ค้นหาจากรหัส ชื่อ หรือรายละเอียดเอกสาร" class="h-11 w-full rounded-xl border border-slate-200 bg-slate-50 pl-11 pr-4 text-sm text-slate-800 outline-none transition placeholder:text-slate-400 focus:border-red-300 focus:bg-white focus:ring-4 focus:ring-red-100"></label>
            <button type="submit" class="inline-flex h-11 items-center justify-center gap-2 rounded-xl bg-slate-900 px-5 text-sm font-bold text-white transition hover:bg-red-700"><i class="fa-solid fa-search text-xs"></i>ค้นหา</button>
            <?php if ($keyword !== ''): ?><a href="index.php" class="inline-flex h-11 items-center justify-center gap-2 rounded-xl border border-slate-200 px-4 text-sm font-semibold text-slate-600 transition hover:border-red-200 hover:bg-red-50 hover:text-red-700"><i class="fa-solid fa-rotate-left text-xs"></i>ล้างการค้นหา</a><?php endif; ?>
        </form>
    </section>

    <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 px-5 py-4 sm:px-6"><div><h2 class="text-lg font-black text-slate-900">รายการประเภทเอกสาร</h2><p class="mt-1 text-sm text-slate-500">พบ <?= count($documentTypes) ?> รายการ<?= $keyword !== '' ? ' จากการค้นหา' : '' ?></p></div><span class="inline-flex items-center gap-2 rounded-full bg-slate-100 px-3 py-1.5 text-xs font-bold text-slate-600"><i class="fa-solid fa-list-check text-red-600"></i><?= count($documentTypes) ?> ประเภท</span></div>
        <div class="overflow-x-auto">
            <table class="w-full min-w-[850px] text-sm">
                <thead class="bg-slate-50 text-xs uppercase tracking-wider text-slate-500"><tr><th class="px-6 py-4 text-left">ประเภทเอกสาร</th><th class="px-6 py-4 text-left">รายละเอียด</th><th class="px-6 py-4 text-center">ลำดับผู้พิจารณา</th><th class="px-6 py-4 text-center">สถานะ</th><th class="px-6 py-4 text-center">จัดการ</th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                <?php if (!$documentTypes): ?>
                    <tr><td colspan="5" class="px-6 py-16 text-center"><span class="mx-auto mb-4 inline-flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-100 text-xl text-slate-400"><i class="fa-regular fa-folder-open"></i></span><h3 class="font-bold text-slate-700">ไม่พบประเภทเอกสาร</h3><p class="mt-1 text-sm text-slate-400">ลองเปลี่ยนคำค้นหาอีกครั้ง</p></td></tr>
                <?php endif; ?>
                <?php foreach ($documentTypes as $type): ?>
                    <?php
                    $isActive = $type['status'] === 'active';
                    $hasWorkflow = (int) $type['workflow_count'] > 0;
                    $workflowAvailable = preg_match('/^R\\.?(01|12|14|18|24)$/i', (string) $type['document_code']) === 1
                        || strtoupper((string) $type['document_code']) === 'INTERNSHIP_INFO';
                    ?>
                    <tr class="transition hover:bg-slate-50/80">
                        <td class="px-6 py-4"><div class="flex items-center gap-3"><span class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-red-50 text-red-700"><i class="fa-solid fa-file-lines"></i></span><div><div class="font-black text-red-700"><?= htmlspecialchars($type['document_code'], ENT_QUOTES, 'UTF-8') ?></div><div class="mt-0.5 font-semibold text-slate-800"><?= htmlspecialchars($type['document_name'], ENT_QUOTES, 'UTF-8') ?></div></div></div></td>
                        <td class="max-w-md px-6 py-4 text-xs leading-5 text-slate-500"><?= htmlspecialchars(trim((string) $type['description']) ?: 'ไม่ได้ระบุรายละเอียด', ENT_QUOTES, 'UTF-8') ?></td>
                        <td class="px-6 py-4 text-center"><?php if (!$workflowAvailable): ?><span class="inline-flex whitespace-nowrap rounded-full bg-slate-100 px-3 py-1.5 text-xs font-bold text-slate-500">ไม่ต้องกำหนด</span><?php elseif ($hasWorkflow): ?><span class="inline-flex whitespace-nowrap rounded-full bg-emerald-50 px-3 py-1.5 text-xs font-bold text-emerald-700"><i class="fa-solid fa-check mr-1"></i>กำหนดแล้ว <?= (int) $type['workflow_step_count'] ?> ขั้นตอน</span><?php else: ?><span class="inline-flex whitespace-nowrap rounded-full bg-amber-50 px-3 py-1.5 text-xs font-bold text-amber-700"><i class="fa-solid fa-clock mr-1"></i>ยังไม่ได้กำหนด</span><?php endif; ?></td>
                        <td class="px-6 py-4 text-center"><span class="inline-flex whitespace-nowrap rounded-full px-3 py-1.5 text-xs font-bold <?= $isActive ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-600' ?>"><i class="fa-solid <?= $isActive ? 'fa-circle-check' : 'fa-circle-pause' ?> mr-1"></i><?= $isActive ? 'เปิดใช้งาน' : 'ปิดใช้งาน' ?></span></td>
                        <td class="px-6 py-4"><div class="flex items-center justify-center gap-2"><?php if ($workflowAvailable): ?><a href="../workflow/index.php?document_type_id=<?= (int) $type['document_type_id'] ?>&amp;edit=1" class="inline-flex h-10 whitespace-nowrap items-center justify-center gap-2 rounded-xl border border-blue-200 bg-blue-50 px-3 text-xs font-bold text-blue-700 transition hover:-translate-y-0.5 hover:bg-blue-100" title="กำหนดลำดับผู้พิจารณา"><i class="fa-solid fa-diagram-project"></i>ผู้พิจารณา</a><?php else: ?><span class="inline-flex h-10 whitespace-nowrap items-center rounded-xl bg-slate-50 px-3 text-xs font-semibold text-slate-400">ไม่ต้องกำหนด</span><?php endif; ?><form method="post" action="toggle_document_type_status.php" class="inline-flex document-type-status-form" data-document-name="<?= htmlspecialchars($type['document_name'], ENT_QUOTES, 'UTF-8') ?>" data-next-status="<?= $isActive ? 'inactive' : 'active' ?>"><?= csrf_field() ?><input type="hidden" name="document_type_id" value="<?= (int) $type['document_type_id'] ?>"><input type="hidden" name="status" value="<?= $isActive ? 'inactive' : 'active' ?>"><button type="submit" class="inline-flex h-10 w-10 items-center justify-center rounded-xl text-sm font-bold text-white shadow-sm transition hover:-translate-y-0.5 <?= $isActive ? 'bg-slate-700 hover:bg-slate-800' : 'bg-emerald-600 hover:bg-emerald-700' ?>" title="<?= $isActive ? 'ปิดใช้งาน' : 'เปิดใช้งาน' ?>" aria-label="<?= $isActive ? 'ปิดใช้งาน' : 'เปิดใช้งาน' ?> <?= htmlspecialchars($type['document_name'], ENT_QUOTES, 'UTF-8') ?>"><i class="fa-solid <?= $isActive ? 'fa-pause' : 'fa-play' ?>"></i></button></form></div></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>

<script>
document.querySelectorAll('.document-type-status-form').forEach((form) => {
    form.addEventListener('submit', (event) => {
        event.preventDefault();
        const opening = form.dataset.nextStatus === 'active';
        Swal.fire({ icon: 'question', title: `${opening ? 'เปิดใช้งาน' : 'ปิดใช้งาน'}ประเภทเอกสาร?`, text: `${form.dataset.documentName}${opening ? ' จะกลับมาใช้สร้างเอกสารใหม่ได้' : ' จะไม่แสดงสำหรับการสร้างเอกสารใหม่'}`, showCancelButton: true, confirmButtonText: opening ? 'เปิดใช้งาน' : 'ปิดใช้งาน', cancelButtonText: 'ยกเลิก', confirmButtonColor: opening ? '#059669' : '#475569', reverseButtons: true }).then((result) => { if (result.isConfirmed) form.submit(); });
    });
});
</script>

<?php include __DIR__ . '/../layouts/admin_footer.php'; ?>
