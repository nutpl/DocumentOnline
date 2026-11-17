<?php

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
require_once 'auth.php';
require_once '../helpers/csrf_helper.php';

$studentStatement = $pdo->prepare('SELECT student_id FROM student WHERE user_id=? LIMIT 1');
$studentStatement->execute([$_SESSION['user_id']]);
$studentId = (int) $studentStatement->fetchColumn();
if ($studentId < 1) { http_response_code(403); renderPlainErrorAlert('ไม่พบข้อมูลนักศึกษา'); }

$statement = $pdo->prepare(
    'SELECT d.document_id,d.document_status,d.submitted_at,d.updated_at,
            dt.document_code,dt.document_name,ia.status AS application_status,
            ia.signed_file,ia.submitted_at AS staff_submitted_at,ip.company_name,
            drp.process_status,current_step.step_name AS current_step_name
     FROM internship_application ia
     INNER JOIN internship_profile ip ON ip.internship_profile_id=ia.internship_profile_id
     INNER JOIN documents d ON d.document_id=ia.document_id
     INNER JOIN document_template t ON t.template_id=d.template_id
     INNER JOIN document_type dt ON dt.document_type_id=t.document_type_id
     LEFT JOIN document_review_processes drp ON drp.document_id=d.document_id
     LEFT JOIN document_review_step_instances current_step
       ON current_step.process_id=drp.process_id AND current_step.step_order=drp.current_step_order
     WHERE ip.student_id=? AND (drp.process_id IS NOT NULL OR d.document_status<>"draft")
     ORDER BY d.updated_at DESC,d.document_id DESC'
);
$statement->execute([$studentId]);
$documents = $statement->fetchAll(PDO::FETCH_ASSOC);
$pageHeading = 'ส่งเอกสารให้เจ้าหน้าที่';
function submissionEscape($value): string { return htmlspecialchars((string)$value,ENT_QUOTES,'UTF-8'); }
function submissionDate(?string $value): string { $time=$value?strtotime($value):false; return $time?date('d/m/Y H:i',$time).' น.':'-'; }
?>
<!doctype html>
<html lang="th">
<head>
    <link rel="icon" type="image/png" href="/Project/assets/images/fet-logo-transparent.png?v=20260815-3">
    <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
    <title>ส่งเอกสารให้เจ้าหน้าที่</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
</head>
<body class="min-h-screen bg-gray-100 text-gray-900">
<?php include 'sidebar.php'; ?>
<div class="min-h-screen lg:ml-72"><?php include 'navbar.php'; ?>
<main class="p-4 sm:p-6 lg:p-8">
    <section class="rounded-2xl bg-gradient-to-r from-slate-950 to-emerald-900 p-6 text-white shadow-lg sm:p-8">
        <p class="text-sm font-bold text-emerald-200">เอกสารคำร้องสหกิจ</p>
        <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-200">เอกสารที่ยังอยู่ระหว่างการพิจารณาจะดูสถานะได้ แต่จะพิมพ์และอัปโหลดได้เมื่อผ่านอาจารย์ที่ปรึกษาและอาจารย์ประสานงานครบแล้วเท่านั้น</p>
    </section>

    <section class="mt-6 space-y-4">
        <?php foreach ($documents as $document):
            // Legacy submissions can retain document_status="submitted" after
            // staff returns the signed file for replacement.
            $ready = $document['application_status']==='waiting_signature'
                && in_array($document['document_status'], ['approved','submitted'], true);
            $sent = $document['application_status']==='submitted' && !empty($document['signed_file']);
            $revision = $document['document_status']==='revision_requested';
            $statusLabel = $sent ? 'ส่งให้เจ้าหน้าที่แล้ว' : ($ready ? 'ผ่านการพิจารณาแล้ว' : ($revision ? 'ส่งกลับให้แก้ไข' : 'อยู่ระหว่างการพิจารณา'));
            $statusClass = $sent ? 'bg-blue-100 text-blue-700' : ($ready ? 'bg-emerald-100 text-emerald-700' : ($revision ? 'bg-red-100 text-red-700' : 'bg-amber-100 text-amber-800'));
        ?>
        <article class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="grid gap-4 p-5 sm:p-6 lg:grid-cols-[minmax(0,1fr)_auto] lg:items-start">
                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-2"><span class="rounded-lg bg-slate-900 px-2.5 py-1 text-xs font-black text-white"><?=submissionEscape($document['document_code'])?></span><h2 class="font-black text-slate-900"><?=submissionEscape($document['document_name'])?></h2></div>
                    <p class="mt-2 text-sm text-slate-600">สถานประกอบการ: <?=submissionEscape($document['company_name']?:'-')?></p>
                    <p class="mt-1 text-xs text-slate-400">ส่งพิจารณาเมื่อ <?=submissionEscape(submissionDate($document['submitted_at']))?></p>
                    <?php if (!$ready && !$sent && !empty($document['current_step_name'])): ?><p class="mt-2 text-sm font-bold text-amber-700">ขั้นปัจจุบัน: <?=submissionEscape($document['current_step_name'])?></p><?php endif; ?>
                </div>
                <span class="inline-flex self-start rounded-full px-3 py-1.5 text-xs font-bold <?=$statusClass?>"><?=$statusLabel?></span>
            </div>

            <?php if ($ready): ?>
            <div class="border-t border-emerald-100 bg-emerald-50/50 p-5 sm:p-6">
                <div class="flex flex-col gap-4 xl:flex-row xl:items-end">
                    <a href="generate_internship_pdf.php?document=<?=(int)$document['document_id']?>" class="inline-flex h-11 shrink-0 items-center justify-center gap-2 rounded-xl bg-red-700 px-5 font-bold text-white hover:bg-red-800" title="พิมพ์เอกสาร"><i class="fa-solid fa-print"></i><span>พิมพ์เอกสาร</span></a>
                    <form action="upload_signed_internship.php" method="post" enctype="multipart/form-data" class="grid flex-1 gap-3 lg:grid-cols-[minmax(220px,1fr)_minmax(300px,1.3fr)_auto] lg:items-end">
                        <?=csrf_field()?><input type="hidden" name="document_id" value="<?=(int)$document['document_id']?>">
                        <label><span class="mb-1.5 block text-sm font-bold text-slate-700">PDF ที่ลงนามครบแล้ว</span><input type="file" name="signed_pdf" accept="application/pdf,.pdf" required class="block w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm"></label>
                        <label class="flex min-h-11 items-start gap-2 rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-950"><input type="checkbox" name="confirm_two_signatures" value="1" required class="mt-1"><span>ยืนยันว่ามีลายเซ็นอาจารย์ที่ปรึกษาและอาจารย์ประสานงานครบ 2 คน</span></label>
                        <button class="inline-flex h-11 items-center justify-center gap-2 rounded-xl bg-emerald-700 px-5 font-bold text-white hover:bg-emerald-800"><i class="fa-solid fa-paper-plane"></i>อัปโหลดให้เจ้าหน้าที่</button>
                    </form>
                </div>
                <p class="mt-3 text-xs text-slate-500">รองรับเฉพาะ PDF ขนาดไม่เกิน 10 MB</p>
            </div>
            <?php elseif ($sent): ?>
            <div class="border-t border-blue-100 bg-blue-50 px-5 py-4 text-sm text-blue-800"><i class="fa-solid fa-circle-check mr-2"></i>อัปโหลดให้เจ้าหน้าที่เมื่อ <?=submissionEscape(submissionDate($document['staff_submitted_at']))?></div>
            <?php else: ?>
            <div class="border-t border-slate-100 bg-slate-50 px-5 py-4 text-sm text-slate-500"><i class="fa-solid fa-lock mr-2"></i>ยังไม่สามารถพิมพ์หรืออัปโหลดได้จนกว่าเอกสารจะผ่านการพิจารณาครบทุกขั้นตอน</div>
            <?php endif; ?>
        </article>
        <?php endforeach; ?>

        <?php if (!$documents): ?><div class="rounded-2xl border border-dashed border-slate-300 bg-white p-12 text-center text-slate-500"><i class="fa-regular fa-folder-open text-5xl text-slate-300"></i><p class="mt-4 font-bold text-slate-700">ยังไม่มีคำร้องสหกิจที่ส่งพิจารณา</p><a href="internship.php" class="mt-4 inline-flex rounded-xl bg-red-700 px-5 py-2.5 font-bold text-white">จัดทำคำร้องสหกิจ</a></div><?php endif; ?>
    </section>
</main></div>
<?php if(isset($_GET['submitted'])):?><div hidden data-success-alert data-success-title="ส่งให้เจ้าหน้าที่แล้ว" data-success-text="อัปโหลดเอกสารฉบับลงนามเรียบร้อยแล้ว" data-success-clean-param="submitted"></div><?php endif;?>
</body></html>
