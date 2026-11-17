<?php
session_start();
require_once '../middleware/staff.php';
require_once '../config/database.php';
require_once '../helpers/csrf_helper.php';
require_once '../helpers/official_document_number_helper.php';

$staffStmt=$pdo->prepare('SELECT employee_id,department_id,firstname,lastname FROM employee WHERE user_id=? AND employee_type IN ("staff","teacher") LIMIT 1');
$staffStmt->execute([$_SESSION['user_id']]);
$staff=$staffStmt->fetch(PDO::FETCH_ASSOC);
if(!$staff){http_response_code(403);renderPlainErrorAlert('ไม่มีสิทธิ์ใช้งาน');}

if($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='delete'){
    verify_csrf_or_abort();
    $officialId=(int)($_POST['official_document_id']??0);
    $check=$pdo->prepare('SELECT od.official_document_id
        FROM internship_official_document od
        JOIN internship_official_student os ON os.official_document_id=od.official_document_id
        JOIN internship_profile ip ON ip.internship_profile_id=os.internship_profile_id
        JOIN student s ON s.student_id=ip.student_id
        JOIN group_class g ON g.group_id=s.group_id
        WHERE od.official_document_id=? AND g.department_id=? LIMIT 1');
    $check->execute([$officialId,$staff['department_id']]);
    if(!$check->fetchColumn()){http_response_code(403);renderPlainErrorAlert('ไม่มีสิทธิ์ลบหนังสือนี้');}
    $pdo->prepare('DELETE FROM internship_official_document WHERE official_document_id=?')->execute([$officialId]);
    header('Location: official_history.php?deleted=1');
    exit;
}
if($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='delete_selected'){
    verify_csrf_or_abort();
    $officialIds=array_values(array_unique(array_filter(array_map('intval',(array)($_POST['official_document_ids']??[])),static fn($id)=>$id>0)));
    if(!$officialIds){header('Location: official_history.php');exit;}
    $placeholders=implode(',',array_fill(0,count($officialIds),'?'));
    $check=$pdo->prepare('SELECT DISTINCT od.official_document_id FROM internship_official_document od
        JOIN internship_official_student os ON os.official_document_id=od.official_document_id
        JOIN internship_profile ip ON ip.internship_profile_id=os.internship_profile_id
        JOIN student s ON s.student_id=ip.student_id
        JOIN group_class g ON g.group_id=s.group_id
        WHERE od.official_document_id IN ('.$placeholders.') AND g.department_id=?');
    $check->execute([...$officialIds,$staff['department_id']]);
    $allowedIds=array_map('intval',$check->fetchAll(PDO::FETCH_COLUMN));
    if(count($allowedIds)!==count($officialIds)){http_response_code(403);renderPlainErrorAlert('มีรายการที่ไม่มีสิทธิ์ลบ');}
    $pdo->beginTransaction();
    try{
        $delete=$pdo->prepare('DELETE FROM internship_official_document WHERE official_document_id IN ('.$placeholders.')');
        $delete->execute($allowedIds);
        $pdo->commit();
    }catch(Throwable $error){if($pdo->inTransaction())$pdo->rollBack();throw $error;}
    header('Location: official_history.php?deleted='.count($allowedIds));
    exit;
}

$search=trim((string)($_GET['search']??''));
$documentType=in_array(($_GET['type']??''),['request','send'],true)?$_GET['type']:'';
$historySql='SELECT od.*, COUNT(os.internship_profile_id) student_count FROM internship_official_document od
    JOIN internship_official_student os ON os.official_document_id=od.official_document_id
    JOIN internship_profile ip ON ip.internship_profile_id=os.internship_profile_id
    JOIN student s ON s.student_id=ip.student_id
    JOIN group_class g ON g.group_id=s.group_id
    WHERE g.department_id=?';
$historyParams=[$staff['department_id']];
if($search!==''){$historySql.=' AND (od.document_number LIKE ? OR od.company_name LIKE ? OR s.student_number LIKE ? OR s.firstname LIKE ? OR s.lastname LIKE ?)';$like='%'.$search.'%';array_push($historyParams,$like,$like,$like,$like,$like);}
if($documentType!==''){$historySql.=' AND od.document_type=?';$historyParams[]=$documentType;}
$historySql.=' GROUP BY od.official_document_id ORDER BY od.created_at DESC';
$historyStmt=$pdo->prepare($historySql);
$historyStmt->execute($historyParams);
$history=$historyStmt->fetchAll(PDO::FETCH_ASSOC);

$countStmt=$pdo->prepare('SELECT COUNT(*) FROM internship_application ia JOIN internship_profile ip ON ip.internship_profile_id=ia.internship_profile_id JOIN student s ON s.student_id=ip.student_id JOIN group_class g ON g.group_id=s.group_id WHERE ia.status="submitted" AND g.department_id=?');
$countStmt->execute([$staff['department_id']]);
$document_count=(int)$countStmt->fetchColumn();
$staff_name=trim($staff['firstname'].' '.$staff['lastname']);
$pageHeading='ประวัติหนังสือที่สร้าง';
function h($value): string{return htmlspecialchars((string)$value,ENT_QUOTES,'UTF-8');}
?>
<!doctype html>
<html lang="th">
<head>
    <link rel="icon" type="image/png" href="/Project/assets/images/rmuti-university-seal.png?v=20260911-1">

    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>ประวัติหนังสือที่สร้าง</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
</head>
<body class="min-h-screen bg-slate-100 text-slate-900">
<?php include 'sidebar.php'; ?>
<div class="min-h-screen lg:ml-72">
<?php include 'navbar.php'; ?>
<main class="p-4 sm:p-6 lg:p-8">
    <section class="management-hero"><span class="management-hero__icon"><i class="fa-solid fa-folder-open"></i></span><div class="min-w-0 flex-1"><p class="management-hero__eyebrow" style="font-size:.9rem;letter-spacing:.045em">เอกสารทางการสหกิจ</p><h1>ประวัติหนังสือที่สร้าง</h1><p>ค้นหา ดาวน์โหลด หรือจัดการหนังสือที่สร้างไว้</p></div><span class="inline-flex shrink-0 items-center gap-2 rounded-full border border-red-100 bg-red-50 px-3 py-2 text-sm font-bold text-red-700"><i class="fa-regular fa-file-lines"></i><?= count($history) ?> รายการ</span></section>
    <?php if(isset($_GET['deleted'])): ?><div hidden data-success-alert data-success-title="ลบข้อมูลสำเร็จ" data-success-text="ลบหนังสือราชการ <?= (int)$_GET['deleted'] ?> รายการแล้ว สามารถสร้างฉบับใหม่ได้" data-success-clean-param="deleted"></div><?php endif; ?>
    <form id="official-history-search" method="get" data-jquery-search-form data-search-results="#official-history-results" class="mt-5 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm"><div class="mb-3 flex items-center gap-2 text-sm font-bold text-slate-700"><span class="inline-flex h-8 w-8 items-center justify-center rounded-lg bg-slate-100 text-slate-600"><i class="fa-solid fa-filter"></i></span>ค้นหาและกรองประวัติ</div><div class="grid gap-3 sm:grid-cols-[1fr_220px_auto]">
        <label><span class="mb-1 block text-xs font-bold text-slate-500">ค้นหาหนังสือ</span><div class="relative"><i class="fa-solid fa-magnifying-glass absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400"></i><input type="search" name="search" value="<?=h($search)?>" placeholder="เลขหนังสือ บริษัท หรือนักศึกษา" class="w-full rounded-xl border border-slate-300 py-2.5 pl-10 pr-4 outline-none transition focus:border-red-500 focus:ring-4 focus:ring-red-100"></div></label>
        <label><span class="mb-1 block text-xs font-bold text-slate-500">ประเภทหนังสือ</span><select name="type" class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5"><option value="">ทุกประเภท</option><option value="request" <?=$documentType==='request'?'selected':''?>>ขอความอนุเคราะห์</option><option value="send" <?=$documentType==='send'?'selected':''?>>ใบขอส่งตัว</option></select></label>
        <button class="self-end rounded-xl bg-slate-900 px-5 py-2.5 font-semibold text-white transition hover:bg-slate-700"><i class="fa-solid fa-magnifying-glass mr-2"></i>ค้นหา</button></div>
    </form>
    <form id="official-history-batch-form" method="post" action="prepare_official_batch.php" class="mt-5 flex flex-col gap-3 rounded-2xl border border-emerald-200 bg-emerald-50/70 p-4 shadow-sm sm:flex-row sm:items-center sm:justify-between">
        <?= csrf_field() ?>
        <div class="flex items-start gap-3"><span class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-emerald-100 text-emerald-700"><i class="fa-solid fa-layer-group"></i></span><div><p class="font-bold text-emerald-950">ดาวน์โหลดไฟล์รวม</p><p id="history-batch-detail" class="mt-0.5 text-sm text-emerald-800">เลือกเอกสารที่มีประเภทและเลขหนังสือเดียวกัน</p></div></div>
        <div class="flex flex-wrap gap-2"><button type="submit" name="format" value="pdf" disabled class="history-batch-download inline-flex items-center justify-center rounded-xl bg-red-700 px-4 py-2.5 text-sm font-bold text-white shadow-sm transition hover:bg-red-800 disabled:cursor-not-allowed disabled:opacity-40"><i class="fa-solid fa-file-pdf mr-2"></i>PDF รวม</button><button type="submit" name="format" value="word" disabled class="history-batch-download inline-flex items-center justify-center rounded-xl bg-blue-700 px-4 py-2.5 text-sm font-bold text-white shadow-sm transition hover:bg-blue-800 disabled:cursor-not-allowed disabled:opacity-40"><i class="fa-solid fa-file-word mr-2"></i>Word รวม</button><button id="history-batch-delete" type="button" disabled class="inline-flex items-center justify-center rounded-xl border border-red-200 bg-white px-4 py-2.5 text-sm font-bold text-red-700 shadow-sm transition hover:bg-red-50 disabled:cursor-not-allowed disabled:opacity-40"><i class="fa-regular fa-trash-can mr-2"></i>ลบที่เลือก</button></div>
    </form>
    <form id="official-history-delete-form" method="post" class="hidden"><?= csrf_field() ?><input type="hidden" name="action" value="delete_selected"></form>
    <section id="official-history-results" data-jquery-search-result data-search-form="#official-history-search" class="mt-5 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
      <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 px-5 py-4"><label class="flex items-center gap-3 text-sm font-semibold text-slate-700">จำนวนรายการต่อหน้า <select id="history-per-page" class="rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm font-bold text-slate-700 outline-none transition focus:border-emerald-500 focus:ring-4 focus:ring-emerald-100"><option value="5" selected>5 รายการ</option><option value="10">10 รายการ</option><option value="15">15 รายการ</option><option value="25">25 รายการ</option><option value="50">50 รายการ</option><option value="100">100 รายการ</option><option value="250">250 รายการ</option><option value="500">500 รายการ</option></select></label><nav id="history-pagination" class="flex flex-wrap items-center justify-end gap-1" aria-label="แบ่งหน้าประวัติเอกสาร"></nav></div>
      <div class="overflow-x-auto">
        <table class="w-full min-w-[760px] text-sm">
            <thead class="border-b border-slate-200 bg-slate-50 text-slate-600"><tr><th class="w-14 px-5 py-4 text-center font-semibold"><input id="history-select-all" type="checkbox" class="h-4 w-4 rounded border-slate-300 text-emerald-700 focus:ring-emerald-500" title="เลือกทั้งหมดที่แสดง"></th><th class="px-5 py-4 text-left font-semibold">เลขหนังสือ</th><th class="w-36 whitespace-nowrap px-5 py-4 text-left font-semibold">ประเภท</th><th class="px-5 py-4 text-left font-semibold">สถานประกอบการ</th><th class="px-5 py-4 text-center font-semibold">จำนวนนักศึกษา</th><th class="w-[420px] px-5 py-4 text-center font-semibold">จัดการ</th></tr></thead>
            <tbody class="divide-y divide-slate-100">
            <?php foreach($history as $item): ?>
                <tr data-history-row class="transition hover:bg-red-50/40">
                    <td class="px-5 py-4 text-center"><input form="official-history-batch-form" type="checkbox" name="official_document_ids[]" value="<?= (int)$item['official_document_id'] ?>" data-document-type="<?= h($item['document_type']) ?>" data-document-number="<?= h($item['document_number']) ?>" class="history-document-checkbox h-4 w-4 rounded border-slate-300 text-emerald-700 focus:ring-emerald-500" aria-label="เลือก <?= h($item['company_name']) ?>"></td>
                    <td class="px-5 py-4">
                        <span class="font-semibold text-slate-900"><?= h(officialFormatDocumentNumber((string)$item['document_number'])) ?></span><br>
                        <span class="mt-1 inline-block text-xs text-slate-500"><i class="fa-regular fa-calendar mr-1"></i><?= h($item['document_date']) ?></span>
                    </td>
                    <td class="whitespace-nowrap px-5 py-4"><span class="inline-flex whitespace-nowrap rounded-full px-3 py-1 text-xs font-semibold <?= $item['document_type']==='request'?'bg-blue-50 text-blue-700':'bg-amber-50 text-amber-700' ?>"><?= $item['document_type']==='request'?'ขอความอนุเคราะห์':'ใบขอส่งตัว' ?></span></td>
                    <td class="px-5 py-4 font-medium text-slate-700"><i class="fa-regular fa-building mr-2 text-slate-400"></i><?= h($item['company_name']) ?></td>
                    <td class="px-5 py-4 text-center"><span class="inline-flex min-w-9 items-center justify-center rounded-full bg-slate-100 px-3 py-1 font-semibold text-slate-700"><?= (int)$item['student_count'] ?></span></td>
                    <?php $deleteName=($item['document_type']==='request'?'หนังสือขอความอนุเคราะห์':'ใบขอส่งตัว').' เลขที่ '.$item['document_number'].' บริษัท '.$item['company_name']; ?>
                    <td class="px-5 py-4"><div class="flex flex-nowrap items-center justify-center gap-2 whitespace-nowrap"><a href="generate_official_pdf.php?id=<?= (int)$item['official_document_id'] ?>&type=<?= h($item['document_type']) ?>" class="inline-flex h-10 shrink-0 items-center gap-2 rounded-xl bg-red-50 px-3.5 font-semibold text-red-700 transition hover:bg-red-600 hover:text-white" title="ดาวน์โหลด PDF"><i class="fa-solid fa-file-pdf"></i><span>PDF</span></a><a href="generate_official_word.php?id=<?= (int)$item['official_document_id'] ?>&type=<?= h($item['document_type']) ?>" class="inline-flex h-10 shrink-0 items-center gap-2 rounded-xl bg-blue-50 px-3.5 font-semibold text-blue-700 transition hover:bg-blue-600 hover:text-white" title="ดาวน์โหลด Word"><i class="fa-solid fa-file-word"></i><span>Word</span></a><?php if($item['document_type']==='request'): ?><a href="generate_official_pdf.php?id=<?= (int)$item['official_document_id'] ?>&type=response" class="inline-flex h-10 shrink-0 items-center gap-2 rounded-xl bg-emerald-50 px-3.5 font-semibold text-emerald-700 transition hover:bg-emerald-600 hover:text-white" title="ดาวน์โหลดแบบตอบรับ PDF สำหรับพิมพ์"><i class="fa-solid fa-file-signature"></i><span>ใบตอบรับ PDF</span></a><?php endif; ?><form method="post" data-confirm-action="delete" data-confirm-name="<?= h($deleteName) ?>" class="shrink-0"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="official_document_id" value="<?= (int)$item['official_document_id'] ?>"><button data-confirm-name="<?= h($deleteName) ?>" class="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-500 transition hover:border-red-200 hover:bg-red-50 hover:text-red-600" title="ลบหนังสือ" aria-label="ลบหนังสือ"><i class="fa-regular fa-trash-can"></i></button></form></div></td>
                </tr>
            <?php endforeach; ?>
            <?php if(!$history): ?><tr><td colspan="6" class="px-5 py-16 text-center text-gray-500"><i class="fa-regular fa-folder-open mb-3 block text-4xl text-gray-300"></i><span class="font-medium">ยังไม่มีประวัติหนังสือที่สร้าง</span></td></tr><?php endif; ?>
            </tbody>
        </table>
      </div>
    </section>
</main>
</div><script>
const initializeOfficialHistory = () => {
    const results = document.getElementById('official-history-results');
    if (!results || results.dataset.batchInitialized === 'true') return;
    results.dataset.batchInitialized = 'true';
    const all = results.querySelector('#history-select-all');
    const boxes = [...results.querySelectorAll('.history-document-checkbox')];
    const rows = [...results.querySelectorAll('tr[data-history-row]')];
    const detail = document.getElementById('history-batch-detail');
    const downloadButtons = [...document.querySelectorAll('.history-batch-download')];
    const deleteButton = document.getElementById('history-batch-delete');
    const deleteForm = document.getElementById('official-history-delete-form');
    const perPage = results.querySelector('#history-per-page');
    const pagination = results.querySelector('#history-pagination');
    let page = 1;

    const updateBatchSelection = () => {
        const selected = boxes.filter((box) => box.checked);
        const types = [...new Set(selected.map((box) => box.dataset.documentType))];
        const numbers = [...new Set(selected.map((box) => box.dataset.documentNumber))];
        const valid = selected.length > 0 && types.length === 1 && numbers.length === 1;
        downloadButtons.forEach((button) => { button.disabled = !valid; });
        if (deleteButton) deleteButton.disabled = selected.length === 0;
        if (!selected.length) detail.textContent = 'เลือกเอกสารที่มีประเภทและเลขหนังสือเดียวกัน';
        else if (!valid) detail.textContent = 'เลือกได้เฉพาะเอกสารประเภทเดียวกันและเลขหนังสือเดียวกัน';
        else detail.textContent = `เลือก ${selected.length} บริษัท · เลขที่ ${numbers[0]} · พร้อมดาวน์โหลดไฟล์รวม`;
        if (all) {
            all.checked = boxes.length > 0 && selected.length === boxes.length;
            all.indeterminate = selected.length > 0 && selected.length < boxes.length;
        }
    };
    const renderPage = () => {
        const size = Number(perPage?.value || 5);
        const pageCount = Math.max(1, Math.ceil(rows.length / size));
        page = Math.min(page, pageCount);
        rows.forEach((row, index) => { row.hidden = index < (page - 1) * size || index >= page * size; });
        if (pagination) {
            pagination.replaceChildren();
            if (pageCount > 1) {
                const createButton = (label, target, disabled = false) => {
                    const button = document.createElement('button');
                    button.type = 'button'; button.disabled = disabled;
                    button.className = `min-w-9 rounded-lg px-3 py-2 text-sm font-bold transition ${target === page ? 'bg-emerald-700 text-white' : 'border border-slate-200 bg-white text-slate-700 hover:border-emerald-300 hover:text-emerald-700'} disabled:cursor-not-allowed disabled:opacity-40`;
                    button.textContent = label;
                    button.addEventListener('click', () => { page = target; renderPage(); });
                    return button;
                };
                pagination.append(createButton('‹', Math.max(1, page - 1), page === 1));
                for (let item = 1; item <= pageCount; item++) pagination.append(createButton(String(item), item));
                pagination.append(createButton('›', Math.min(pageCount, page + 1), page === pageCount));
            }
        }
        updateBatchSelection();
    };
    all?.addEventListener('change', () => { boxes.forEach((box) => { box.checked = all.checked; }); updateBatchSelection(); });
    boxes.forEach((box) => box.addEventListener('change', updateBatchSelection));
    deleteButton?.addEventListener('click', async () => {
        const selected = boxes.filter((box) => box.checked);
        if (!selected.length || !deleteForm) return;
        const result = await Swal.fire({
            icon: 'warning',
            title: `ลบหนังสือ ${selected.length} รายการ?`,
            text: 'การลบจะทำให้สามารถสร้างหนังสือรายการเดิมใหม่ได้',
            showCancelButton: true,
            confirmButtonText: 'ลบรายการที่เลือก',
            cancelButtonText: 'ยกเลิก',
            confirmButtonColor: '#b91c1c',
            cancelButtonColor: '#64748b',
            focusCancel: true
        });
        if (!result.isConfirmed) return;
        deleteForm.querySelectorAll('[data-batch-delete-id]').forEach((input) => input.remove());
        selected.forEach((box) => {
            const input = document.createElement('input');
            input.type = 'hidden'; input.name = 'official_document_ids[]'; input.value = box.value;
            input.dataset.batchDeleteId = 'true'; deleteForm.append(input);
        });
        deleteForm.submit();
    });
    perPage?.addEventListener('change', () => { page = 1; renderPage(); });
    renderPage();
};
document.addEventListener('DOMContentLoaded', initializeOfficialHistory);
window.jQuery?.(document).on('jquery-live-search:updated', initializeOfficialHistory);
</script><script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script><script src="../assets/js/form-confirm.js"></script>
</body>
</html>
