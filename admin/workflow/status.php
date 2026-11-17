<?php

require_once __DIR__ . '/../../middleware/admin.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/document_workflow_status_helper.php';

$search = trim((string) ($_GET['search'] ?? ''));
$status = in_array(($_GET['status'] ?? ''), ['in_review','revision_requested','approved'], true)
    ? (string) $_GET['status'] : '';
$where = [];
$params = [];
if ($search !== '') {
    $where[] = '(s.student_number LIKE ? OR s.firstname LIKE ? OR s.lastname LIKE ? OR dt.document_code LIKE ? OR dt.document_name LIKE ?)';
    $term = '%' . $search . '%';
    array_push($params, $term, $term, $term, $term, $term);
}
if ($status !== '') {
    $where[] = 'drp.process_status = ?';
    $params[] = $status;
}
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
$statement = $pdo->prepare(
    "SELECT drp.document_id, drp.process_status, drp.current_step_order, drp.started_at, drp.updated_at,
            d.document_status, dt.document_code, dt.document_name,
            s.student_number, COALESCE(sp.prefix_short,sp.prefix_name,'') AS student_prefix,
            s.firstname, s.lastname, current_step.step_name AS current_step_name,
            TRIM(CONCAT(
                CASE WHEN ep.prefix_name IN ('นาย','นาง','นางสาว') THEN 'อาจารย์'
                     ELSE COALESCE(NULLIF(ep.prefix_short,''),NULLIF(ep.prefix_name,''),'') END,
                CASE WHEN ee.employee_id IS NULL THEN '' ELSE ' ' END,
                COALESCE(ee.firstname,''), CASE WHEN ee.employee_id IS NULL THEN '' ELSE ' ' END, COALESCE(ee.lastname,'')
            )) AS current_reviewer_name
     FROM document_review_processes drp
     INNER JOIN documents d ON d.document_id = drp.document_id
     INNER JOIN student s ON s.student_id = d.student_id
     LEFT JOIN prefixes sp ON sp.prefix_id = s.prefix_id
     INNER JOIN document_template t ON t.template_id = d.template_id
     INNER JOIN document_type dt ON dt.document_type_id = t.document_type_id
     LEFT JOIN document_review_step_instances current_step
       ON current_step.process_id = drp.process_id AND current_step.step_order = drp.current_step_order
     LEFT JOIN employee ee ON ee.employee_id = current_step.reviewer_employee_id
     LEFT JOIN prefixes ep ON ep.prefix_id = ee.prefix_id
     {$whereSql}
     ORDER BY drp.updated_at DESC, drp.process_id DESC"
);
$statement->execute($params);
$processes = $statement->fetchAll(PDO::FETCH_ASSOC);
$selectedDocumentId = (int) ($_GET['document'] ?? 0);
$selectedWorkflow = $selectedDocumentId > 0 ? getDocumentWorkflowStatus($pdo, $selectedDocumentId) : null;

$pageTitle = 'ประวัติการดำเนินงาน';
function h($value): string { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); }
function adminWorkflowDateTime(?string $value): string {
    if (!$value) return '-';
    $timestamp = strtotime($value);
    return $timestamp ? date('d/m/Y H:i', $timestamp) . ' น.' : $value;
}
include __DIR__ . '/../layouts/admin_header.php';
include __DIR__ . '/../layouts/admin_sidebar.php';
include __DIR__ . '/../layouts/admin_navbar.php';
?>

<div class="mx-auto max-w-7xl space-y-6">
    <section class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-slate-950 via-slate-900 to-red-950 px-6 py-7 text-white shadow-xl sm:px-8">
        <div class="relative flex flex-col gap-5 md:flex-row md:items-center md:justify-between">
            <div class="flex items-start gap-4"><span class="inline-flex h-12 w-12 items-center justify-center rounded-2xl bg-red-600/20 text-red-300"><i class="fa-solid fa-clock-rotate-left text-xl"></i></span><div><p class="text-xs font-bold uppercase tracking-[0.2em] text-red-300">Administration history</p><h1 class="mt-1 text-2xl font-black sm:text-3xl">ประวัติการดำเนินงาน</h1><p class="mt-2 text-sm text-slate-300">ตรวจสอบสถานะและประวัติการพิจารณาคำร้องย้อนหลัง โดยไม่ใช่หน้าดำเนินงานเอกสาร</p></div></div>
            <a href="index.php" class="inline-flex items-center justify-center gap-2 rounded-xl border border-white/15 bg-white/10 px-5 py-3 text-sm font-bold hover:bg-white/20"><i class="fa-solid fa-diagram-project"></i>กำหนดลำดับ</a>
        </div>
    </section>

    <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <form method="get" class="grid gap-3 md:grid-cols-[1fr_230px_auto]">
            <label><span class="mb-2 block text-sm font-bold text-slate-700">ค้นหา</span><input type="search" name="search" value="<?= h($search) ?>" placeholder="รหัส ชื่อนักศึกษา หรือเอกสาร" class="h-11 w-full rounded-xl border border-slate-200 px-4 text-sm outline-none focus:border-red-300 focus:ring-4 focus:ring-red-100"></label>
            <label><span class="mb-2 block text-sm font-bold text-slate-700">สถานะ</span><select name="status" class="h-11 w-full rounded-xl border border-slate-200 bg-white px-3 text-sm"><option value="">ทุกสถานะ</option><option value="in_review" <?= $status==='in_review'?'selected':'' ?>>อยู่ระหว่างพิจารณา</option><option value="revision_requested" <?= $status==='revision_requested'?'selected':'' ?>>ส่งกลับให้แก้ไข</option><option value="approved" <?= $status==='approved'?'selected':'' ?>>ผ่านการพิจารณาแล้ว</option></select></label>
            <button class="self-end inline-flex h-11 items-center justify-center gap-2 rounded-xl bg-slate-900 px-5 text-sm font-bold text-white hover:bg-red-700"><i class="fa-solid fa-magnifying-glass"></i>ค้นหา</button>
        </form>
    </section>

    <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="flex items-center justify-between border-b border-slate-100 px-6 py-5"><div><h2 class="text-lg font-black">ประวัติคำร้องในระบบ</h2><p class="mt-1 text-sm text-slate-500">พบ <?= count($processes) ?> รายการ</p></div></div>
        <div class="overflow-x-auto"><table class="w-full min-w-[980px] text-sm"><thead class="bg-slate-50 text-xs uppercase tracking-wider text-slate-500"><tr><th class="px-6 py-4 text-left">นักศึกษา</th><th class="px-6 py-4 text-left">เอกสาร</th><th class="px-6 py-4 text-left">ขั้นปัจจุบัน</th><th class="px-6 py-4 text-center">สถานะ</th><th class="px-6 py-4 text-center">จัดการ</th></tr></thead><tbody class="divide-y divide-slate-100">
        <?php if (!$processes): ?><tr><td colspan="5" class="px-6 py-14 text-center text-slate-400">ยังไม่มีเอกสารในกระบวนการพิจารณา</td></tr><?php endif; ?>
        <?php foreach ($processes as $row): $meta=workflowProcessStatusMeta((string)$row['process_status']); ?>
            <tr class="hover:bg-slate-50/80"><td class="px-6 py-4"><strong><?=h($row['student_number'])?></strong><br><span class="text-slate-600"><?=h(trim($row['student_prefix'].$row['firstname'].' '.$row['lastname']))?></span></td><td class="px-6 py-4"><strong class="text-red-700"><?=h($row['document_code'])?></strong><br><span class="text-slate-600"><?=h($row['document_name'])?></span></td><td class="px-6 py-4"><?php if($row['current_step_name']):?><strong><?=h($row['current_step_name'])?></strong><br><span class="text-xs text-slate-500"><?=h($row['current_reviewer_name']?:'-')?></span><?php else:?><span class="text-slate-400">ดำเนินการครบแล้ว</span><?php endif;?></td><td class="px-6 py-4 text-center"><span class="inline-flex rounded-full px-3 py-1 text-xs font-bold <?=h($meta[1])?>"><?=h($meta[0])?></span></td><td class="px-6 py-4 text-center"><a href="?document=<?=(int)$row['document_id']?><?= $search!==''?'&search='.rawurlencode($search):'' ?><?= $status!==''?'&status='.rawurlencode($status):'' ?>" class="inline-flex h-10 items-center gap-2 rounded-xl border border-slate-200 px-4 font-bold text-slate-700 hover:border-blue-300 hover:bg-blue-50 hover:text-blue-700"><i class="fa-solid fa-eye"></i>รายละเอียด</a></td></tr>
        <?php endforeach; ?>
        </tbody></table></div>
    </section>

    <?php if ($selectedDocumentId > 0): ?>
        <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <?php if (!$selectedWorkflow): ?><p class="text-center text-slate-500">ไม่พบรายละเอียด Workflow ของเอกสารนี้</p><?php else: ?>
                <div class="flex flex-col gap-3 border-b border-slate-100 pb-5 sm:flex-row sm:items-center sm:justify-between"><div><p class="text-sm font-black text-red-700"><?=h($selectedWorkflow['document_code'])?></p><h2 class="mt-1 text-xl font-black"><?=h($selectedWorkflow['document_name'])?></h2><p class="mt-1 text-sm text-slate-500"><?=h($selectedWorkflow['student_number'].' '.$selectedWorkflow['student_prefix'].$selectedWorkflow['student_firstname'].' '.$selectedWorkflow['student_lastname'])?></p></div><?php $selectedMeta=workflowProcessStatusMeta((string)$selectedWorkflow['process_status']);?><span class="self-start rounded-full px-4 py-2 text-sm font-bold <?=h($selectedMeta[1])?>"><?=h($selectedMeta[0])?></span></div>
                <div class="mt-6 grid gap-4 md:grid-cols-<?= min(3,max(1,count($selectedWorkflow['steps']))) ?>"><?php foreach($selectedWorkflow['steps'] as $step):$stepMeta=workflowStepStatusMeta((string)$step['step_status']);?><article class="rounded-xl border border-slate-200 p-4"><p class="text-xs font-black text-slate-400">ขั้นที่ <?=(int)$step['step_order']?></p><h3 class="mt-1 font-black"><?=h($step['step_name'])?></h3><p class="mt-1 text-sm text-slate-600"><?=h($step['reviewer_name']?:'-')?></p><span class="mt-3 inline-flex rounded-full px-3 py-1 text-xs font-bold <?=h($stepMeta[1])?>"><?=h($stepMeta[0])?></span><?php if($step['latest_comment']):?><p class="mt-3 rounded-lg bg-red-50 p-2 text-xs text-red-700"><?=h($step['latest_comment'])?></p><?php endif;?></article><?php endforeach;?></div>
            <?php endif; ?>
        </section>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../layouts/admin_footer.php'; ?>
