<?php
require_once '../helpers/document_download_access_helper.php';
require_once '../helpers/document_external_signer_helper.php';
require_once 'auth.php';
require_once '../helpers/pagination_helper.php';
require_once '../helpers/csrf_helper.php';

$studentStmt=$pdo->prepare('SELECT s.student_id,s.student_number,s.firstname,s.lastname FROM student s WHERE s.user_id=? LIMIT 1');
$studentStmt->execute([$_SESSION['user_id']]);
$student=$studentStmt->fetch(PDO::FETCH_ASSOC);
if (!$student) { http_response_code(403); renderPlainErrorAlert('ไม่พบข้อมูลนักศึกษา'); }

$focusNotification=null;
$focusNotificationId=(int)($_GET['notification_id'] ?? 0);
if ($focusNotificationId > 0) {
    $focusStmt=$pdo->prepare('SELECT notification_id,document_id,title,message FROM notifications WHERE notification_id=? AND recipient_user_id=? LIMIT 1');
    $focusStmt->execute([$focusNotificationId,(int)$_SESSION['user_id']]);
    $focusNotification=$focusStmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

$stmt=$pdo->prepare('SELECT d.document_id,CASE WHEN dt.document_code="INTERNSHIP_INFO" AND ia.status="waiting_signature" THEN "waiting_signature" ELSE d.document_status END AS document_status,d.document_status AS stored_document_status,ia.status AS internship_status,d.created_at,d.updated_at,d.submitted_at,dt.document_code,dt.document_name,
    drp.process_id,drp.current_step_order,current_step.step_name AS current_step_name,
    (SELECT de.detail FROM document_events de WHERE de.document_id=d.document_id AND de.event_type="revision_requested" ORDER BY de.created_at DESC,de.event_id DESC LIMIT 1) AS revision_comment
    FROM documents d JOIN document_template t ON t.template_id=d.template_id JOIN document_type dt ON dt.document_type_id=t.document_type_id
    LEFT JOIN internship_application ia ON ia.document_id=d.document_id
    LEFT JOIN document_review_processes drp ON drp.document_id=d.document_id
    LEFT JOIN document_review_step_instances current_step ON current_step.process_id=drp.process_id AND current_step.step_order=drp.current_step_order
    WHERE d.student_id=? ORDER BY d.updated_at DESC,d.document_id DESC');
$stmt->execute([$student['student_id']]);
$rows=$stmt->fetchAll(PDO::FETCH_ASSOC);

// Only registration documents that are ready to print need an external
// signature reminder. Internship documents use their separate submission
// workflow and are deliberately excluded here.
$externalSignersByDocument = [];
foreach ($rows as $documentRow) {
    if ((string)$documentRow['document_code'] === 'INTERNSHIP_INFO'
        || (string)$documentRow['stored_document_status'] !== 'approved') {
        continue;
    }
    $externalSignersByDocument[(int)$documentRow['document_id']] = getDocumentExternalSigners($pdo, (int)$documentRow['document_id']);
}

$student_document_count=count($rows);
$student_draft_count=count(array_filter($rows,fn($row)=>in_array($row['document_status'],['draft','waiting_signature'],true)));
$student_submitted_count=count(array_filter($rows,fn($row)=>in_array($row['document_status'],['submitted','in_review'],true)));
$student_revision_count=count(array_filter($rows,fn($row)=>$row['document_status']==='revision_requested'));
$student_reviewed_count=count(array_filter($rows,fn($row)=>$row['document_status']==='approved'));
$student_cancel_count=count(array_filter($rows,fn($row)=>$row['document_status']==='cancel'));
$pageHeading='ประวัติเอกสาร';

$statusMeta=[
    'draft'=>['label'=>'ฉบับร่าง','classes'=>'status-badge status-badge--draft','icon'=>'fa-pen'],
    'submitted'=>['label'=>'ส่งคำร้องแล้ว','classes'=>'status-badge status-badge--success','icon'=>'fa-check'],
    'in_review'=>['label'=>'อยู่ระหว่างพิจารณา','classes'=>'status-badge status-badge--pending','icon'=>'fa-hourglass-half'],
    'revision_requested'=>['label'=>'ส่งกลับให้แก้ไข','classes'=>'status-badge status-badge--danger','icon'=>'fa-rotate-left'],
    'approved'=>['label'=>'ผ่านการพิจารณาแล้ว','classes'=>'status-badge status-badge--success','icon'=>'fa-circle-check'],
    'waiting_signature'=>['label'=>'อนุมัติครบแล้ว รอลงนามฉบับจริง','classes'=>'status-badge status-badge--success','icon'=>'fa-circle-check'],
    'cancel'=>['label'=>'ยกเลิกคำร้อง','classes'=>'status-badge status-badge--neutral','icon'=>'fa-ban'],
];
function thaiDateTime(?string $value): string {
    if (!$value) return '-';
    $timestamp=strtotime($value);
    return $timestamp ? date('d/m/Y H:i',$timestamp).' น.' : $value;
}
?>
<!doctype html>
<html lang="th">
<head>
    <link rel="icon" type="image/png" href="/Project/assets/images/fet-logo-transparent.png?v=20260815-3">

    <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
    <title>ประวัติเอกสาร</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <style>
        .notification-focus{background:#fff7ed!important;box-shadow:inset 4px 0 0 #dc2626,0 0 0 2px #fecaca;animation:notification-focus-pulse 1.4s ease-out 2}
        @keyframes notification-focus-pulse{50%{box-shadow:inset 4px 0 0 #dc2626,0 0 0 5px rgba(220,38,38,.16)}}
        .history-filter.is-active{border-color:#dc2626;background:#fff1f2;box-shadow:0 10px 20px rgba(185,28,28,.09)}
        .history-filter.is-active .history-filter-icon{background:#dc2626;color:#fff}
        .history-actions{display:inline-flex;align-items:center;gap:.4rem!important;margin-left:auto;padding:.35rem;border:1px solid #e2e8f0;border-radius:1rem;background:#f8fafc;box-shadow:0 1px 2px rgba(15,23,42,.04)}
        .history-actions>a,.history-actions>form>button{height:2.7rem!important;width:2.7rem!important;border:1px solid #e2e8f0!important;border-radius:.8rem!important;background:#fff!important;color:#475569!important;box-shadow:none!important;transition:all .18s ease}
        .history-actions>a:hover{border-color:#fca5a5!important;background:#fff1f2!important;color:#dc2626!important;transform:translateY(-1px)}
        .history-actions>form>button{border-color:#fee2e2!important;background:#fff5f5!important;color:#dc2626!important}
        .history-actions>form>button:hover{border-color:#dc2626!important;background:#dc2626!important;color:#fff!important;transform:translateY(-1px)}
        body.theme-dark .history-actions{border-color:#334155;background:#111827}
        body.theme-dark .history-actions>a{border-color:#475569!important;background:#1f2937!important;color:#e2e8f0!important}
        body.theme-dark .history-actions>form>button{border-color:#7f1d1d!important;background:#3f1d26!important;color:#fca5a5!important}
    </style>
</head>
<body class="min-h-screen bg-gray-100 text-gray-900">
<?php include 'sidebar.php'; ?>
<div class="min-h-screen lg:ml-72">
    <?php include 'navbar.php'; ?>
    <main class="p-5 sm:p-8 lg:p-10">
        <div class="mx-auto max-w-6xl">
        <?php if (isset($_GET['submitted'])): ?>
            <div hidden data-success-alert data-success-title="ส่งเอกสารเรียบร้อยแล้ว" data-success-text="คุณสามารถดาวน์โหลดเอกสาร PDF ได้จากรายการด้านล่าง" data-success-clean-param="submitted"></div>
        <?php endif; ?>
        <section class="border-l-[7px] border-red-700 pl-4 sm:pl-5">
            <p class="text-sm font-bold text-red-700">DOCUMENT CENTER</p>
            <h1 class="mt-1 text-2xl font-extrabold tracking-tight text-slate-950 sm:text-3xl">ประวัติเอกสารของฉัน</h1>
            <p class="mt-2 text-sm leading-6 text-slate-500 sm:text-base">ค้นหา ติดตาม และจัดการเอกสารทุกฉบับจากหน้าเดียว</p>
        </section>

        <section class="mt-7 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
            <button type="button" data-filter="all" class="history-filter status-filter is-active rounded-2xl border border-slate-200 bg-white p-4 text-left shadow-sm transition hover:-translate-y-0.5 hover:border-red-300"><span class="history-filter-icon inline-flex h-9 w-9 items-center justify-center rounded-xl bg-slate-100 text-slate-600"><i class="fa-solid fa-folder-open"></i></span><p class="mt-3 text-sm font-medium text-slate-500">ทั้งหมด</p><strong class="mt-1 block text-2xl text-slate-950"><?= $student_document_count ?></strong></button>
            <button type="button" data-filter="draft" class="history-filter status-filter rounded-2xl border border-slate-200 bg-white p-4 text-left shadow-sm transition hover:-translate-y-0.5 hover:border-amber-300"><span class="history-filter-icon inline-flex h-9 w-9 items-center justify-center rounded-xl bg-amber-50 text-amber-600"><i class="fa-solid fa-pen"></i></span><p class="mt-3 text-sm font-medium text-slate-500">ฉบับร่าง</p><strong class="mt-1 block text-2xl text-amber-600"><?= $student_draft_count ?></strong></button>
            <button type="button" data-filter="submitted" class="history-filter status-filter rounded-2xl border border-slate-200 bg-white p-4 text-left shadow-sm transition hover:-translate-y-0.5 hover:border-emerald-300"><span class="history-filter-icon inline-flex h-9 w-9 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600"><i class="fa-solid fa-paper-plane"></i></span><p class="mt-3 text-sm font-medium text-slate-500">ส่งแล้ว</p><strong class="mt-1 block text-2xl text-emerald-600"><?= $student_submitted_count ?></strong></button>
            <button type="button" data-filter="revision_requested" class="history-filter status-filter rounded-2xl border border-slate-200 bg-white p-4 text-left shadow-sm transition hover:-translate-y-0.5 hover:border-red-300"><span class="history-filter-icon inline-flex h-9 w-9 items-center justify-center rounded-xl bg-red-50 text-red-600"><i class="fa-solid fa-rotate-left"></i></span><p class="mt-3 text-sm font-medium text-slate-500">ต้องแก้ไข</p><strong class="mt-1 block text-2xl text-red-600"><?= $student_revision_count ?></strong></button>
            <button type="button" data-filter="approved" class="history-filter status-filter rounded-2xl border border-slate-200 bg-white p-4 text-left shadow-sm transition hover:-translate-y-0.5 hover:border-sky-300"><span class="history-filter-icon inline-flex h-9 w-9 items-center justify-center rounded-xl bg-sky-50 text-sky-600"><i class="fa-solid fa-circle-check"></i></span><p class="mt-3 text-sm font-medium text-slate-500">ผ่านการพิจารณา</p><strong class="mt-1 block text-2xl text-sky-600"><?= $student_reviewed_count ?></strong></button>
            <button type="button" data-filter="cancel" class="history-filter status-filter rounded-2xl border border-slate-200 bg-white p-4 text-left shadow-sm transition hover:-translate-y-0.5 hover:border-slate-400"><span class="history-filter-icon inline-flex h-9 w-9 items-center justify-center rounded-xl bg-slate-100 text-slate-500"><i class="fa-solid fa-ban"></i></span><p class="mt-3 text-sm font-medium text-slate-500">ยกเลิก</p><strong class="mt-1 block text-2xl text-slate-500"><?= $student_cancel_count ?></strong></button>
        </section>

        <section id="history-list" class="mt-7 overflow-hidden rounded-[22px] border border-slate-200 bg-white shadow-sm">
            <div class="flex flex-col gap-4 border-b border-slate-100 p-4 sm:flex-row sm:items-center sm:justify-between sm:px-6 sm:py-5">
                <div><h2 class="text-lg font-extrabold text-slate-900">รายการเอกสาร</h2><p class="mt-0.5 text-sm text-slate-500">เลือกสถานะด้านบนหรือค้นหารายการที่ต้องการ</p></div>
                <label class="relative block w-full sm:max-w-sm"><i class="fa-solid fa-magnifying-glass absolute left-4 top-1/2 -translate-y-1/2 text-slate-400"></i><input id="document-search" type="search" placeholder="ค้นหารหัสหรือชื่อเอกสาร..." class="w-full rounded-xl border border-slate-200 bg-slate-50 py-2.5 pl-11 pr-4 text-sm outline-none transition placeholder:text-slate-400 focus:border-red-500 focus:bg-white focus:ring-4 focus:ring-red-100"></label>
            </div>

            <div class="hidden overflow-x-auto md:block">
                <table class="w-full min-w-[760px] text-sm">
                    <thead class="bg-slate-950 text-slate-100"><tr><th class="px-6 py-3.5 text-left font-bold">เอกสาร</th><th class="px-6 py-3.5 text-left font-bold">วันที่สร้าง</th><th class="px-6 py-3.5 text-left font-bold">อัปเดตล่าสุด</th><th class="px-6 py-3.5 text-center font-bold">สถานะ</th><th class="px-6 py-3.5 text-right font-bold">การทำงาน</th></tr></thead>
                    <tbody id="history-table-body" class="divide-y divide-slate-100">
                    <?php foreach ($rows as $row): $meta=$statusMeta[$row['document_status']] ?? ['label'=>$row['document_status'],'classes'=>'bg-gray-100 text-gray-600','icon'=>'fa-circle']; $searchText=strtolower($row['document_code'].' '.$row['document_name']); $isInternship=$row['document_code']==='INTERNSHIP_INFO'; $viewUrl=$isInternship?'internship_form.php?from=history':'document_form.php?document='.(int)$row['document_id'].'&from=history'; $canEdit=in_array($row['document_status'],['draft','revision_requested'],true); $canDownload=studentCanDownloadDocument((string)$row['document_code'],(string)$row['stored_document_status'],$row['internship_status']!==null?(string)$row['internship_status']:null); $downloadUrl=studentDocumentDownloadUrl((int)$row['document_id'],(string)$row['document_code'],(string)$row['stored_document_status'],$row['internship_status']!==null?(string)$row['internship_status']:null); ?>
                        <tr class="document-row transition hover:bg-slate-50" data-document-id="<?= (int)$row['document_id'] ?>" data-status="<?= htmlspecialchars($row['document_status']) ?>" data-search="<?= htmlspecialchars($searchText) ?>">
                            <td class="px-6 py-5"><div class="flex min-w-0 items-center gap-3"><span class="inline-flex min-h-11 shrink-0 items-center justify-center whitespace-nowrap rounded-xl bg-red-50 px-3 py-2 text-sm font-extrabold text-red-700"><?= htmlspecialchars($row['document_code']) ?></span><div class="min-w-0"><p class="font-bold leading-6 text-slate-900"><?= htmlspecialchars($row['document_name']) ?></p><?php if($row['document_status']==='revision_requested'&&$row['revision_comment']): ?><p class="mt-1 text-xs font-semibold text-red-700">แก้ไข: <?= htmlspecialchars($row['revision_comment']) ?></p><?php endif; ?></div></div></td>
                            <td class="px-6 py-5 text-slate-500"><?= htmlspecialchars(thaiDateTime($row['created_at'])) ?></td><td class="px-6 py-5 text-slate-500"><?= htmlspecialchars(thaiDateTime($row['updated_at'])) ?></td>
                            <td class="px-6 py-4 text-center"><span class="inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-semibold <?= $meta['classes'] ?>"><i class="fa-solid <?= $meta['icon'] ?>"></i><?= htmlspecialchars($meta['label']) ?></span></td>
                            <td class="px-6 py-4"><div class="history-actions justify-end"><a href="<?= htmlspecialchars($viewUrl) ?>" class="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-slate-200 text-slate-700 transition hover:border-red-300 hover:bg-red-50 hover:text-red-700" title="<?= $canEdit?'แก้ไขเอกสาร':'ดูเอกสาร' ?>" aria-label="<?= $canEdit?'แก้ไขเอกสาร':'ดูเอกสาร' ?>"><i class="fa-solid <?= $canEdit?'fa-pen':'fa-eye' ?>"></i></a><?php if ($canDownload && !$isInternship): ?><a href="<?= htmlspecialchars($downloadUrl) ?>" class="student-print-button inline-flex h-10 w-10 items-center justify-center text-white" title="พิมพ์เอกสาร" aria-label="พิมพ์เอกสาร"><i class="fa-solid fa-print"></i></a><?php endif; ?><?php if($row['document_status']==='draft'): ?><form method="post" action="delete_document.php" data-confirm-action="delete" data-confirm-name="<?= htmlspecialchars($row['document_code'].' - '.$row['document_name']) ?>"><?= csrf_field() ?><input type="hidden" name="document_id" value="<?= (int)$row['document_id'] ?>"><button type="submit" class="inline-flex h-10 w-10 items-center justify-center rounded-xl bg-slate-800 text-white transition hover:bg-slate-950" title="ลบเอกสาร" aria-label="ลบเอกสาร"><i class="fa-solid fa-trash"></i></button></form><?php endif; ?></div></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div id="history-cards" class="divide-y md:hidden">
                <?php foreach ($rows as $row): $meta=$statusMeta[$row['document_status']] ?? ['label'=>$row['document_status'],'classes'=>'bg-gray-100 text-gray-600','icon'=>'fa-circle']; $searchText=strtolower($row['document_code'].' '.$row['document_name']); $isInternship=$row['document_code']==='INTERNSHIP_INFO'; $viewUrl=$isInternship?'internship_form.php?from=history':'document_form.php?document='.(int)$row['document_id'].'&from=history'; $canEdit=in_array($row['document_status'],['draft','revision_requested'],true); $canDownload=studentCanDownloadDocument((string)$row['document_code'],(string)$row['stored_document_status'],$row['internship_status']!==null?(string)$row['internship_status']:null); $downloadUrl=studentDocumentDownloadUrl((int)$row['document_id'],(string)$row['document_code'],(string)$row['stored_document_status'],$row['internship_status']!==null?(string)$row['internship_status']:null); ?>
                    <article class="document-card p-5" data-document-id="<?= (int)$row['document_id'] ?>" data-status="<?= htmlspecialchars($row['document_status']) ?>" data-search="<?= htmlspecialchars($searchText) ?>"><div class="flex items-start justify-between gap-3"><div class="min-w-0"><p class="font-extrabold text-red-700"><?= htmlspecialchars($row['document_code']) ?></p><h3 class="mt-1 font-bold leading-6 text-slate-900"><?= htmlspecialchars($row['document_name']) ?></h3><?php if($row['document_status']==='revision_requested'&&$row['revision_comment']): ?><p class="mt-2 text-xs font-semibold text-red-700">แก้ไข: <?= htmlspecialchars($row['revision_comment']) ?></p><?php endif; ?></div><span class="inline-flex shrink-0 items-center gap-1 rounded-full px-2.5 py-1 text-xs font-semibold <?= $meta['classes'] ?>"><i class="fa-solid <?= $meta['icon'] ?>"></i><?= htmlspecialchars($meta['label']) ?></span></div><p class="mt-3 text-xs text-slate-500"><i class="fa-regular fa-clock mr-1"></i>อัปเดต <?= htmlspecialchars(thaiDateTime($row['updated_at'])) ?></p><?php if($row['current_step_name']):?><p class="mt-1 text-xs font-bold text-blue-700">ขั้นปัจจุบัน: <?=htmlspecialchars($row['current_step_name'])?></p><?php endif;?><div class="history-actions mt-4 flex-wrap justify-end"><a href="<?= htmlspecialchars($viewUrl) ?>" class="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-slate-200 text-slate-700 transition hover:border-red-300 hover:bg-red-50 hover:text-red-700" title="<?= $canEdit?'แก้ไขเอกสาร':'ดูเอกสาร' ?>" aria-label="<?= $canEdit?'แก้ไขเอกสาร':'ดูเอกสาร' ?>"><i class="fa-solid <?= $canEdit?'fa-pen':'fa-eye' ?>"></i></a><?php if ($canDownload && !$isInternship): ?><a href="<?= htmlspecialchars($downloadUrl) ?>" class="student-print-button inline-flex h-10 w-10 items-center justify-center text-white" title="พิมพ์เอกสาร" aria-label="พิมพ์เอกสาร"><i class="fa-solid fa-print"></i></a><?php endif; ?><?php if($row['document_status']==='draft'): ?><form method="post" action="delete_document.php" data-confirm-action="delete" data-confirm-name="<?= htmlspecialchars($row['document_code'].' - '.$row['document_name']) ?>"><?= csrf_field() ?><input type="hidden" name="document_id" value="<?= (int)$row['document_id'] ?>"><button type="submit" class="inline-flex h-10 w-10 items-center justify-center rounded-xl bg-slate-800 text-white transition hover:bg-slate-950" title="ลบเอกสาร" aria-label="ลบเอกสาร"><i class="fa-solid fa-trash"></i></button></form><?php endif; ?></div></article>
                <?php endforeach; ?>
            </div>

            <div id="history-empty" class="<?= $rows?'hidden':'' ?> p-12 text-center text-slate-500"><i class="fa-regular fa-folder-open mb-4 text-5xl text-slate-300"></i><p class="font-bold text-slate-700">ไม่พบเอกสาร</p><p class="mt-1 text-sm">ลองเปลี่ยนคำค้นหาหรือตัวกรองสถานะ</p></div>
        </section>
        </div>
    </main>
</div>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="../assets/js/form-confirm.js"></script>
<?php if (!empty($_SESSION['student_history_success']) || !empty($_SESSION['student_history_error'])): ?>
<script>
Swal.fire({
    icon: <?= json_encode(!empty($_SESSION['student_history_success']) ? 'success' : 'error') ?>,
    title: <?= json_encode(!empty($_SESSION['student_history_success']) ? 'สำเร็จ' : 'เกิดข้อผิดพลาด') ?>,
    text: <?= json_encode($_SESSION['student_history_success'] ?? $_SESSION['student_history_error'], JSON_UNESCAPED_UNICODE) ?>,
    confirmButtonColor: '#dc2626'
});
</script>
<?php unset($_SESSION['student_history_success'], $_SESSION['student_history_error']); endif; ?>
<?php if (!empty($_SESSION['student_history_status_notice'])): ?>
<script>
Swal.fire({
    icon: 'info',
    title: 'ไม่พบข้อมูลการพิจารณาเอกสาร',
    text: 'กรุณาตรวจสอบสถานะเอกสารจากเมนู “ติดตามสถานะ”',
    confirmButtonText: 'กลับหน้าประวัติเอกสาร',
    confirmButtonColor: '#dc2626',
    allowOutsideClick: false,
    allowEscapeKey: false
});
</script>
<?php unset($_SESSION['student_history_status_notice']); endif; ?>
<script>
jQuery(function($){
    const registrationExternalSigners = <?= json_encode($externalSignersByDocument, JSON_UNESCAPED_UNICODE|JSON_HEX_APOS|JSON_HEX_QUOT) ?>;
    const escapeHtml = (value) => String(value).replace(/[&<>'"]/g, (character) => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#039;','"':'&quot;'}[character]));
    $(document).on('click', '.student-print-button', function(event){
        event.preventDefault();
        const link = this;
        const documentId = String($(link).closest('[data-document-id]').data('document-id'));
        const signers = registrationExternalSigners[documentId] || [];
        const signerItems = signers.length
            ? signers.map((signer) => `<li class="border-b border-amber-100 py-2 text-left last:border-0"><p class="font-bold text-slate-900">${escapeHtml(signer.position)}</p><p class="text-sm text-slate-600">${escapeHtml(signer.name)}</p></li>`).join('')
            : '<li class="py-2 text-left text-sm text-slate-700">ไม่พบข้อมูลผู้ลงนาม กรุณาติดต่อเจ้าหน้าที่ก่อนพิมพ์เอกสาร</li>';
        Swal.fire({
            icon: 'info',
            title: 'ก่อนพิมพ์เอกสาร',
            html: `<p class="mb-2 text-sm text-slate-600">กรุณานำเอกสารไปขอลงนามนอกระบบให้ครบ</p><ul class="m-0 list-none rounded-xl border border-amber-200 bg-amber-50 px-4 py-1">${signerItems}</ul>`,
            width: 540,
            padding: '1.25rem 1.5rem 1.1rem',
            showCancelButton: true,
            confirmButtonText: 'ดาวน์โหลดเอกสาร',
            cancelButtonText: 'ยกเลิก',
            confirmButtonColor: '#b91c1c',
            cancelButtonColor: '#64748b',
            didOpen: (popup) => {
                popup.querySelector('.swal2-icon').style.margin = '0.15em auto 0.3em';
                popup.querySelector('.swal2-title').style.margin = '0.15em 0 0';
                popup.querySelector('.swal2-html-container').style.margin = '0.55em 0 0';
                popup.querySelector('.swal2-actions').style.margin = '1rem auto 0';
            }
        }).then((result) => {
            if (result.isConfirmed) window.location.assign(link.href);
        });
    });
    let currentFilter='all';
    function updateHistory(){
        const term=$('#document-search').val().trim().toLocaleLowerCase('th');
        let visibleRows=0;
        $('.document-row, .document-card').each(function(){
            const $item=$(this);
            const status=String($item.data('status'));
            const statusMatches=currentFilter==='all'||status===currentFilter
                ||(currentFilter==='draft'&&status==='waiting_signature')
                ||(currentFilter==='submitted'&&status==='in_review');
            const text=String($item.data('search')).toLocaleLowerCase('th');
            const show=statusMatches&&text.includes(term);
            $item.toggleClass('hidden',!show);
            if(show&&$item.hasClass('document-row'))visibleRows++;
        });
        $('#history-empty').toggleClass('hidden',visibleRows>0);
    }
    $('.status-filter').on('click',function(){
        currentFilter=String($(this).data('filter'));
        $('.status-filter').removeClass('is-active');
        $(this).addClass('is-active');
        updateHistory();
    });
    $('#document-search').on('input',updateHistory);
    const focusDocumentId=<?= json_encode($focusNotification['document_id'] ?? null) ?>;
    if (focusDocumentId) {
        const selector=`[data-document-id="${focusDocumentId}"]`;
        const focusedItem=window.matchMedia('(max-width: 767px)').matches
            ? document.querySelector(`#history-cards ${selector}`)
            : document.querySelector(`#history-table-body ${selector}`);
        if (focusedItem) {
            focusedItem.classList.add('notification-focus');
            window.setTimeout(()=>focusedItem.scrollIntoView({behavior:'smooth',block:'center'}),120);
        }
    }
});
</script>
</body>
</html>
