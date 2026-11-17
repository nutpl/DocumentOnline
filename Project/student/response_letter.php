<?php
session_start();
require_once '../middleware/student.php';
require_once '../config/database.php';
require_once '../helpers/csrf_helper.php';
require_once '../helpers/response_no_document_review_helper.php';

$studentStmt = $pdo->prepare('SELECT student_id FROM student WHERE user_id=? LIMIT 1');
$studentStmt->execute([$_SESSION['user_id']]);
$studentId = (int)$studentStmt->fetchColumn();
if ($studentId < 1) { http_response_code(403); renderPlainErrorAlert('ไม่พบข้อมูลนักศึกษา'); }

$noDocumentReviewJoin = responseNoDocumentReviewAvailable($pdo) ? ' LEFT JOIN internship_response_no_document_review ndr ON ndr.internship_profile_id=ip.internship_profile_id AND ndr.declaration_updated_at=rdecl.updated_at ' : '';
$noDocumentReviewFields = responseNoDocumentReviewAvailable($pdo) ? ',ndr.review_status AS no_document_review_status,ndr.review_note AS no_document_review_note' : '';
$profileStmt = $pdo->prepare('SELECT ip.internship_profile_id,ip.company_name,ip.recipient_name,ip.recipient_position,ia.status AS application_status,
        rd.response_document_id,rd.original_filename,rd.review_status,rd.review_note,rd.uploaded_at,rd.reviewed_at,
        rdecl.reported_result,rdecl.evidence_state,rdecl.student_note,rdecl.declared_at'.$noDocumentReviewFields.'
    FROM internship_profile ip
    LEFT JOIN internship_application ia ON ia.internship_profile_id=ip.internship_profile_id
    LEFT JOIN internship_response_document rd ON rd.internship_profile_id=ip.internship_profile_id
    LEFT JOIN internship_response_declaration rdecl ON rdecl.internship_profile_id=ip.internship_profile_id '.$noDocumentReviewJoin.'
    WHERE ip.student_id=? AND ip.request_status="active" ORDER BY ip.internship_profile_id DESC LIMIT 1');
$profileStmt->execute([$studentId]);
$record = $profileStmt->fetch(PDO::FETCH_ASSOC);
$pageHeading = 'หนังสือตอบรับจากสถานประกอบการ';
function responseH($value): string { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }

$status = $record['review_status'] ?? '';
$statusMap = [
    'pending' => ['รอเจ้าหน้าที่ตรวจสอบ', 'bg-amber-100 text-amber-800', 'ไฟล์ถูกส่งแล้ว รอเจ้าหน้าที่ตรวจสอบความถูกต้อง'],
    'approved' => ['ตรวจสอบผ่านแล้ว', 'bg-emerald-100 text-emerald-800', 'ระบบนำผลไปตรวจสอบความพร้อมสำหรับจัดทำใบส่งตัว'],
    'revision_requested' => ['ส่งกลับให้แก้ไข', 'bg-red-100 text-red-700', 'กรุณาอัปโหลดไฟล์ฉบับที่ถูกต้องอีกครั้ง'],
    'not_accepted' => ['สถานประกอบการไม่รับ', 'bg-red-100 text-red-700', 'กรุณาเริ่มทำคำร้องสหกิจใหม่เพื่อหาสถานประกอบการแห่งใหม่'],
];
[$statusLabel, $statusClass, $statusText] = $statusMap[$status] ?? ['ยังไม่ได้อัปโหลด', 'bg-slate-100 text-slate-700', 'เมื่อได้รับหนังสือตอบรับจากสถานประกอบการแล้ว ให้อัปโหลดไฟล์ PDF หรือ Word ที่หน้านี้'];
$canUpload = $record && $record['application_status'] === 'submitted' && in_array($status, ['', 'revision_requested'], true);
$declaredResultLabels = [
    'accepted' => 'นักศึกษาแจ้งว่า: บริษัทรับเข้าฝึกงาน',
    'not_accepted' => 'นักศึกษาแจ้งว่า: บริษัทไม่รับเข้าฝึกงาน',
    'unknown' => 'นักศึกษาแจ้งว่า: ยังไม่ทราบผลจากบริษัท',
];
$declaredResult = (string)($record['reported_result'] ?? '');
$evidenceState = (string)($record['evidence_state'] ?? '');
?>
<!doctype html>
<html lang="th"><head>
    <link rel="icon" type="image/png" href="/Project/assets/images/fet-logo-transparent.png?v=20260815-3">
    <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?= responseH($pageHeading) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
</head>
<body class="min-h-screen bg-slate-100 text-slate-900">
<?php include 'sidebar.php'; ?>
<div class="min-h-screen lg:ml-72"><?php include 'navbar.php'; ?>
<main class="p-4 sm:p-6 lg:p-8">
    <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div><p class="text-sm font-bold text-red-700">ขั้นตอนหลังสถานประกอบการตอบกลับ</p><h1 class="mt-1 text-2xl font-black text-slate-900">อัปโหลดหนังสือตอบรับ</h1><p class="mt-2 max-w-2xl text-sm leading-6 text-slate-600">อัปโหลดไฟล์ PDF หรือ Word ที่ได้รับจากสถานประกอบการ ไม่ว่าจะรับหรือไม่รับเข้าฝึกงาน เจ้าหน้าที่จะตรวจสอบก่อนระบบดำเนินขั้นตอนถัดไป</p></div>
            <span class="inline-flex self-start rounded-full px-3 py-1.5 text-sm font-bold <?= $statusClass ?>"><?= responseH($statusLabel) ?></span>
        </div>
        <?php if (!$record): ?>
            <div class="mt-6 rounded-xl border border-dashed border-slate-300 bg-slate-50 p-6 text-sm text-slate-600"><i class="fa-solid fa-circle-info mr-2 text-slate-400"></i>กรุณากรอกและส่งคำร้องสหกิจก่อน จึงจะอัปโหลดหนังสือตอบรับได้</div>
        <?php else: ?>
            <div class="mt-6 grid gap-4 border-y border-slate-100 py-5 sm:grid-cols-2"><div><p class="text-xs font-bold text-slate-400">สถานประกอบการ</p><p class="mt-1 font-bold text-slate-800"><?= responseH($record['company_name'] ?: '-') ?></p></div><div><p class="text-xs font-bold text-slate-400">ผลการตรวจสอบ</p><p class="mt-1 font-bold text-slate-800"><?= responseH($statusText) ?></p></div></div>
            <?php if (!empty($record['review_note'])): ?><div class="mt-5 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800"><p class="font-bold"><i class="fa-solid fa-comment-dots mr-2"></i>ข้อความจากเจ้าหน้าที่</p><p class="mt-1 whitespace-pre-line"><?= responseH($record['review_note']) ?></p></div><?php endif; ?>
            <?php if ($evidenceState === 'no_document' || $evidenceState === 'awaiting_result'): ?><?php $noDocumentReviewStatus=(string)($record['no_document_review_status']??''); ?><div class="mt-5 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900"><p class="font-bold"><i class="fa-solid fa-user-clock mr-2"></i><?= responseH($declaredResultLabels[$declaredResult] ?? 'รอเจ้าหน้าที่ติดตามผล') ?></p><p class="mt-1"><?= $noDocumentReviewStatus==='approved'?'เจ้าหน้าที่ยืนยันข้อมูลแล้ว ระบบสามารถดำเนินขั้นตอนถัดไปได้':'รายการนี้ยังไม่มีเอกสาร เจ้าหน้าที่จะตรวจสอบหรือติดตามข้อมูลด้วยตนเอง' ?></p><?php if (!empty($record['student_note'])): ?><p class="mt-2 whitespace-pre-line rounded-lg bg-white/70 p-3"><b>รายละเอียดจากนักศึกษา:</b> <?= responseH($record['student_note']) ?></p><?php endif; ?><?php if (!empty($record['no_document_review_note'])): ?><p class="mt-2 whitespace-pre-line rounded-lg bg-white/70 p-3"><b>ข้อความจากเจ้าหน้าที่:</b> <?= responseH($record['no_document_review_note']) ?></p><?php endif; ?></div><?php endif; ?>
            <?php if (!empty($record['original_filename'])): ?><?php $responseExtension = strtolower(pathinfo((string)$record['original_filename'], PATHINFO_EXTENSION)); $responseIsWord = in_array($responseExtension, ['doc', 'docx'], true); ?><div class="mt-5 flex flex-wrap items-center gap-3 rounded-xl bg-slate-50 p-4 text-sm"><i class="fa-solid <?= $responseIsWord ? 'fa-file-word text-blue-600' : 'fa-file-pdf text-red-600' ?> text-xl"></i><div class="min-w-0 flex-1"><p class="truncate font-bold text-slate-800"><?= responseH($record['original_filename']) ?></p><p class="text-xs text-slate-500">อัปโหลดเมื่อ <?= responseH(date('d/m/Y H:i น.', strtotime($record['uploaded_at']))) ?></p></div></div><?php endif; ?>
            <?php if (!$canUpload && $status === '' && $record['application_status'] !== 'submitted'): ?>
                <div class="mt-5 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900"><i class="fa-solid fa-lock mr-2"></i>ส่งคำร้องสหกิจฉบับลงนามครบให้เจ้าหน้าที่ก่อน จึงจะอัปโหลดหนังสือตอบรับได้</div>
            <?php elseif ($canUpload): ?>
                <form action="upload_response_letter.php" method="post" enctype="multipart/form-data" class="mt-5 rounded-xl border border-slate-200 bg-slate-50 p-4 sm:p-5">
                    <?= csrf_field() ?>
                    <label class="block"><span class="mb-2 block text-sm font-bold text-slate-700">ไฟล์หนังสือตอบรับจากสถานประกอบการ (PDF หรือ Word) <span class="text-red-600">*</span></span><input type="file" name="response_pdf" accept="application/pdf,.pdf,application/msword,.doc,application/vnd.openxmlformats-officedocument.wordprocessingml.document,.docx" required class="block w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm"></label>
                    <div class="mt-4 flex flex-wrap items-center justify-between gap-3"><p class="text-xs text-slate-500">รองรับ PDF, Word (.doc, .docx) ขนาดไม่เกิน 10 MB</p><button class="inline-flex items-center gap-2 rounded-xl bg-red-700 px-5 py-2.5 font-bold text-white transition hover:bg-red-800"><i class="fa-solid fa-cloud-arrow-up"></i><?= $status === 'revision_requested' ? 'ส่งไฟล์ฉบับแก้ไข' : 'อัปโหลดให้เจ้าหน้าที่ตรวจสอบ' ?></button></div>
                </form>
                <form action="declare_response_outcome.php" method="post" class="mt-4 overflow-hidden rounded-xl border border-amber-200 bg-amber-50/70">
                    <?= csrf_field() ?>
                    <details class="group">
                        <summary class="flex cursor-pointer list-none items-center justify-between gap-4 p-4 sm:p-5"><div class="min-w-0"><h2 class="font-bold text-amber-950"><i class="fa-solid fa-file-circle-question mr-2"></i>กรณีบริษัทไม่มีใบตอบรับ</h2><p class="mt-1 max-w-2xl text-sm leading-6 text-amber-900">กดเพื่อแจ้งข้อมูลที่ทราบให้เจ้าหน้าที่ติดตาม</p></div><span class="flex shrink-0 items-center gap-3"><span class="hidden rounded-full bg-white px-3 py-1 text-xs font-bold text-amber-800 sm:inline">ต้องตรวจโดยเจ้าหน้าที่</span><i class="fa-solid fa-chevron-down text-amber-700 transition group-open:rotate-180"></i></span></summary>
                        <div class="border-t border-amber-200/80 p-4 sm:p-5"><div class="mb-4 rounded-lg bg-white/70 px-3 py-2 text-xs font-semibold text-amber-800 sm:hidden">ต้องตรวจโดยเจ้าหน้าที่</div>
                            <div class="grid gap-3 sm:grid-cols-2"><label><span class="mb-1.5 block text-sm font-semibold text-slate-700">สถานะที่ทราบจากบริษัท</span><select name="reported_result" class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm"><option value="unknown" <?= $declaredResult === 'unknown' ? 'selected' : '' ?>>ยังไม่ได้รับผล / ยังไม่ทราบผล</option><option value="accepted" <?= $declaredResult === 'accepted' ? 'selected' : '' ?>>บริษัทแจ้งว่ารับเข้าฝึกงาน</option><option value="not_accepted" <?= $declaredResult === 'not_accepted' ? 'selected' : '' ?>>บริษัทแจ้งว่าไม่รับเข้าฝึกงาน</option></select></label><label><span class="mb-1.5 block text-sm font-semibold text-slate-700">เหตุผลหรือช่องทางที่แจ้ง <span class="text-red-600">*</span></span><input name="student_note" value="<?= responseH($record['student_note'] ?? '') ?>" required maxlength="1000" class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm" placeholder="เช่น บริษัทแจ้งทางโทรศัพท์หรืออีเมล"></label><label><span class="mb-1.5 block text-sm font-semibold text-slate-700">ชื่อผู้รับหนังสือ <span class="text-red-600">*</span></span><input name="recipient_name" value="<?= responseH($record['recipient_name'] ?? '') ?>" required maxlength="255" class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm" placeholder="เช่น นายสมชาย ใจดี"></label><label><span class="mb-1.5 block text-sm font-semibold text-slate-700">ตำแหน่งผู้รับหนังสือ <span class="text-red-600">*</span></span><input name="recipient_position" value="<?= responseH($record['recipient_position'] ?? '') ?>" required maxlength="180" class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm" placeholder="เช่น ผู้จัดการฝ่ายทรัพยากรบุคคล"></label></div>
                            <div class="mt-4 flex justify-end"><button class="inline-flex items-center gap-2 rounded-xl border border-amber-300 bg-white px-4 py-2.5 text-sm font-bold text-amber-900 hover:bg-amber-100"><i class="fa-solid fa-bell"></i>แจ้งเจ้าหน้าที่ให้ติดตาม</button></div>
                        </div>
                    </details>
                </form>
            <?php elseif ($status === 'not_accepted'): ?>
                <form action="restart_internship_request.php" method="post" class="mt-5" data-confirm-form data-confirm-title="เริ่มทำคำร้องใหม่?" data-confirm-text="ระบบจะเก็บคำร้องเดิมไว้เป็นประวัติ แล้วเปิดคำร้องฉบับใหม่สำหรับสถานประกอบการแห่งใหม่">
                    <?= csrf_field() ?><input type="hidden" name="restart_reason" value="rejected"><button class="inline-flex items-center gap-2 rounded-xl bg-red-700 px-5 py-2.5 font-bold text-white"><i class="fa-solid fa-rotate-right"></i>เริ่มทำคำร้องสหกิจใหม่</button>
                </form>
            <?php elseif ($status === 'approved'): ?>
                <form action="restart_internship_request.php" method="post" class="mt-5" data-relocation-form>
                    <?= csrf_field() ?><input type="hidden" name="restart_reason" value="relocation"><button class="inline-flex items-center gap-2 rounded-xl border border-amber-300 bg-amber-50 px-5 py-2.5 font-bold text-amber-800 hover:bg-amber-100"><i class="fa-solid fa-building-circle-exclamation"></i>ย้ายที่ฝึกงาน / ทำคำร้องใหม่</button>
                </form>
            <?php endif; ?>
        <?php endif; ?>
    </section>
</main></div>
<?php if (isset($_GET['uploaded'])): ?><div hidden data-success-alert data-success-title="อัปโหลดเรียบร้อย" data-success-text="เจ้าหน้าที่จะตรวจสอบเอกสารและแจ้งผลอีกครั้ง" data-success-clean-param="uploaded"></div><?php endif; ?>
<?php if (isset($_GET['declared'])): ?><div hidden data-success-alert data-success-title="แจ้งข้อมูลให้เจ้าหน้าที่แล้ว" data-success-text="รายการนี้จะอยู่ในคิวติดตาม และระบบจะไม่สรุปผลแทนเจ้าหน้าที่" data-success-clean-param="declared" data-success-icon="info"></div><?php endif; ?>
<script>
(() => {
    const form = document.querySelector('[data-relocation-form]');
    form?.addEventListener('submit', async event => {
        if (form.dataset.confirmed === 'true') return;
        event.preventDefault();
        const result = await Swal.fire({
            icon: 'warning', title: 'ยืนยันการย้ายที่ฝึกงาน?',
            text: 'คำร้องเดิมและเอกสารทั้งหมดจะยังเก็บเป็นประวัติ จากนั้นคุณจะกรอกคำร้องฉบับใหม่สำหรับสถานประกอบการแห่งใหม่',
            showCancelButton: true, confirmButtonText: 'ยืนยันการย้าย', cancelButtonText: 'ยกเลิก',
            confirmButtonColor: '#b45309', cancelButtonColor: '#64748b', reverseButtons: true
        });
        if (result.isConfirmed) { form.dataset.confirmed = 'true'; form.submit(); }
    });
    const alerts = {
        invalid: ['warning', 'ข้อมูลไม่ถูกต้อง', 'ไม่พบเหตุผลสำหรับการเริ่มคำร้องใหม่'],
        relocation_not_ready: ['warning', 'ยังย้ายที่ฝึกงานไม่ได้', 'ยังไม่พบใบส่งตัวเดิมที่สามารถย้ายที่ฝึกงานได้'],
        rejected_not_ready: ['warning', 'ยังเริ่มคำร้องใหม่ไม่ได้', 'ยังไม่สามารถเริ่มคำร้องใหม่ได้ในขณะนี้'],
        failed: ['error', 'เริ่มคำร้องใหม่ไม่สำเร็จ', 'ระบบไม่สามารถเริ่มทำคำร้องใหม่ได้ กรุณาลองใหม่อีกครั้ง']
    };
    const alertKey = new URLSearchParams(location.search).get('restart_alert');
    if (alertKey === 'relocation_complete' || alertKey === 'rejected_complete') {
        const isRelocation = alertKey === 'relocation_complete';
        Swal.fire({ icon: 'success', title: isRelocation ? 'ย้ายที่ฝึกงานเรียบร้อย' : 'เปิดคำร้องใหม่เรียบร้อย', text: 'ระบบเก็บคำร้องเดิมไว้ในประวัติแล้ว', confirmButtonText: 'กรอกคำร้องใหม่', confirmButtonColor: '#b91c1c', allowOutsideClick: false })
            .then(() => location.replace('internship_form.php?restarted=' + (isRelocation ? 'relocation' : 'rejected') + '&from=internship'));
    } else if (alerts[alertKey]) {
        const [icon, title, text] = alerts[alertKey];
        Swal.fire({icon, title, text, confirmButtonText: 'ตกลง', confirmButtonColor: '#b91c1c'}).then(() => history.replaceState({}, '', 'response_letter.php'));
    }
})();
</script>
<script src="../assets/js/form-confirm.js"></script></body></html>
