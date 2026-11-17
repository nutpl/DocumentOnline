<?php
session_start();
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
require_once '../middleware/student.php';
require_once '../config/database.php';
require_once '../helpers/csrf_helper.php';
require_once '../helpers/theme_helper.php';

$stmt = $pdo->prepare('SELECT s.student_id,s.student_number,s.firstname,s.lastname,p.prefix_short,g.group_name,d.department_name,f.faculty_name
 FROM student s LEFT JOIN prefixes p ON p.prefix_id=s.prefix_id LEFT JOIN group_class g ON g.group_id=s.group_id
 LEFT JOIN department d ON d.department_id=g.department_id LEFT JOIN faculty f ON f.faculty_id=d.faculty_id WHERE s.user_id=? LIMIT 1');
$stmt->execute([$_SESSION['user_id']]);
$student = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$student) { http_response_code(403); renderPlainErrorAlert('ไม่พบข้อมูลนักศึกษา'); }
$studentDisplayName=trim(($student['prefix_short']??'').($student['firstname']??'').' '.($student['lastname']??''));
$facultyDisplayName=preg_replace('/^\s*คณะ\s*/u','',(string)($student['faculty_name']??''));
$stmt = $pdo->prepare('SELECT * FROM internship_profile WHERE student_id=? AND request_status="active" ORDER BY internship_profile_id DESC LIMIT 1'); $stmt->execute([$student['student_id']]); $profile = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
$calendars = $pdo->query('SELECT calendar_id,academic_year,semester,start_date,end_date FROM internship_calendar WHERE status="active" ORDER BY academic_year DESC,semester')->fetchAll(PDO::FETCH_ASSOC);
$selectedCalendarId=(int)($profile['calendar_id']??0);
$stmt = $pdo->prepare('SELECT ia.*,d.document_status,
 (SELECT dsi.step_name FROM document_review_processes drp JOIN document_review_step_instances dsi ON dsi.process_id=drp.process_id AND dsi.step_order=drp.current_step_order WHERE drp.document_id=ia.document_id LIMIT 1) AS current_step_name,
 (SELECT de.detail FROM document_events de WHERE de.document_id=ia.document_id AND de.event_type="revision_requested" ORDER BY de.created_at DESC,de.event_id DESC LIMIT 1) AS revision_comment
 FROM internship_application ia JOIN internship_profile ip ON ip.internship_profile_id=ia.internship_profile_id
 JOIN documents d ON d.document_id=ia.document_id WHERE ip.student_id=? AND ip.request_status="active" ORDER BY ia.internship_application_id DESC LIMIT 1');
$stmt->execute([$student['student_id']]);
$application = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
$applicationStatus = (string)($application['status'] ?? 'draft');
$documentStatus = (string)($application['document_status'] ?? 'draft');
$locked = in_array($applicationStatus, ['in_review','waiting_signature','submitted'], true);
$backTargets = [
    'history' => 'history.php',
    'internship' => 'internship.php',
    'submissions' => 'internship_submissions.php',
    'dashboard' => 'dashboard.php',
];
$returnFrom = (string)($_GET['from'] ?? 'internship');
if (!isset($backTargets[$returnFrom])) { $returnFrom = 'internship'; }
$backUrl = $backTargets[$returnFrom];
function val(array $data,string $key): string { return htmlspecialchars((string)($data[$key] ?? ''),ENT_QUOTES,'UTF-8'); }
function input(array $data,string $name,string $label,bool $required=false,string $type='text'): void { $req=$required?'required':''; $star=$required?' *':''; echo '<label class="block"><span class="field-label">'.htmlspecialchars($label.$star).'</span><input type="'.$type.'" name="'.$name.'" value="'.val($data,$name).'" '.$req.' class="form-control"></label>'; }
?>
<!doctype html><html lang="th"><head>
    <link rel="icon" type="image/png" href="/Project/assets/images/fet-logo-transparent.png?v=20260815-3">
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>RMUTI_Pro-Ex_02</title><script src="https://cdn.tailwindcss.com"></script><link rel="stylesheet" href="/Project/assets/css/responsive.css?v=20261108-1"><?php renderThemeAssets(); ?><link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css"><style>
body{font-family:'TH Sarabun PSK',Tahoma,sans-serif}.internship-form section{border:1px solid #e2e8f0;border-radius:1rem;background:#fff;padding:1.5rem;box-shadow:0 1px 3px rgba(15,23,42,.06)}.internship-form section h2{display:flex;align-items:center;gap:.65rem;margin:0;font-size:1.05rem;font-weight:700;color:#0f172a}.internship-form section h2:before{content:'';width:.45rem;height:1.5rem;border-radius:999px;background:#dc2626}.internship-section-toggle{display:flex;width:100%;align-items:center;justify-content:space-between;text-align:left}.internship-section-body{padding-top:1.25rem}.internship-section-body.hidden{display:none}.field-label,.internship-form label>span{display:block;margin-bottom:.45rem;font-size:.75rem;font-weight:600;color:#475569}.form-control,.internship-form input:not([type=checkbox]):not([type=file]),.internship-form select,.internship-form textarea{width:100%;border:1px solid #cbd5e1!important;border-radius:.75rem!important;background:#fff;padding:.7rem .85rem!important;color:#0f172a;outline:none;transition:.2s}.internship-form input:focus,.internship-form select:focus,.internship-form textarea:focus{border-color:#dc2626!important;box-shadow:0 0 0 3px rgba(220,38,38,.1)}.internship-form input:disabled{background:#f8fafc!important;color:#64748b}.internship-form textarea{min-height:7rem;resize:vertical}.internship-form fieldset:disabled{opacity:.72}@media(min-width:1280px){html,body{height:100%;overflow:hidden}.internship-workspace{height:calc(100vh - 4rem);overflow:hidden}.internship-workspace-inner,.internship-workspace-grid{height:100%;min-height:0}.internship-preview-scroll,.internship-form-scroll{height:100%;min-height:0;overflow-y:auto;overscroll-behavior:contain;scrollbar-gutter:stable}.internship-preview-scroll{padding-right:.25rem}.internship-form-scroll{scrollbar-gutter:stable}}
</style></head>
<body class="min-h-screen bg-slate-100 text-slate-900"><header class="sticky top-0 z-30 flex h-16 items-center justify-between border-b border-slate-200 bg-white/95 px-4 shadow-sm backdrop-blur sm:px-6"><a href="<?= htmlspecialchars($backUrl,ENT_QUOTES,'UTF-8') ?>" class="inline-flex items-center gap-2 text-sm font-semibold text-slate-600 transition hover:text-red-700" aria-label="ย้อนกลับ"><i class="fa-solid fa-arrow-left"></i><span></span></a><div class="min-w-0 text-right"><p class="truncate text-sm font-bold text-slate-900">RMUTI_Pro-Ex_02 — แบบคำร้องขอฝึกประสบการณ์วิชาชีพ</p><p class="text-xs text-slate-500">กรอกข้อมูลและตรวจสอบตัวอย่างเอกสาร</p></div></header><main class="internship-workspace p-4 sm:p-6 lg:p-8"><div class="internship-workspace-inner mx-auto max-w-[1500px]">
<?php if (($profile['request_reason'] ?? 'initial') !== 'initial'): ?><div class="mb-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900"><i class="fa-solid fa-circle-info mr-2"></i><b>คำร้องฉบับใหม่:</b> <?= ($profile['request_reason'] ?? '') === 'relocation' ? 'ย้ายที่ฝึกงาน' : 'สถานประกอบการไม่รับเข้าฝึกงาน' ?> — ระบบเก็บคำร้องเดิมไว้ในประวัติแล้ว</div><?php endif; ?>
<div class="internship-workspace-grid grid items-start gap-6 xl:grid-cols-[minmax(0,1fr)_520px]">
<div class="internship-preview-scroll min-w-0"><aside class="overflow-hidden rounded-2xl border border-slate-200 bg-slate-200 p-3 shadow-sm sm:p-5"><div id="internship-pdf-wrap" class="relative mx-auto w-full overflow-hidden bg-white shadow-lg"><canvas id="internship-pdf-canvas" class="block h-auto w-full"></canvas></div></aside></div>
<div class="internship-form-scroll min-w-0 overflow-x-hidden rounded-2xl border border-slate-200 bg-white shadow-sm"><div class="flex items-center justify-between border-b border-slate-200 bg-slate-50 px-6 py-5"><div><p class="text-xs font-bold text-red-600">แบบคำร้อง RMUTI_Pro-Ex_02</p><h2 class="mt-1 text-xl font-bold text-slate-950">ข้อมูลสำหรับกรอกเอกสาร</h2></div><span class="inline-flex h-12 w-12 items-center justify-center rounded-xl bg-red-100 text-red-700"><i class="fa-solid fa-file-pen text-xl"></i></span></div><div class="space-y-5 p-5 sm:p-6"><div><h3 class="text-xl font-bold text-slate-950">กรอกคำร้องขอฝึกประสบการณ์วิชาชีพ</h3><p class="mt-2 text-sm leading-6 text-slate-500">กรอกข้อมูลให้ครบถ้วนและบันทึกฉบับร่าง เพื่ออัปเดตข้อมูลบนตัวอย่างกระดาษด้านซ้าย</p></div>
<?php if ($applicationStatus === 'in_review'): ?><section class="mb-6 rounded-md border border-blue-200 bg-blue-50 p-5 text-blue-950"><h2 class="font-bold">รอ<?= htmlspecialchars((string)($application['current_step_name'] ?: 'อาจารย์ที่เกี่ยวข้อง'),ENT_QUOTES,'UTF-8') ?>พิจารณา</h2><p class="mt-1 text-sm">เอกสารกำลังอยู่ในขั้นตอนพิจารณาตามลำดับ ระหว่างนี้ยังไม่สามารถแก้ไขหรือพิมพ์เอกสารได้</p></section><?php endif; ?>
<?php if ($documentStatus === 'revision_requested'): ?><section class="mb-6 rounded-md border border-red-200 bg-red-50 p-5 text-red-950"><h2 class="font-bold">อาจารย์ที่ปรึกษาส่งกลับให้แก้ไข</h2><p class="mt-1 text-sm"><?= htmlspecialchars((string)($application['revision_comment'] ?: 'กรุณาตรวจสอบและแก้ไขข้อมูลก่อนส่งให้อาจารย์ที่ปรึกษาอีกครั้ง'),ENT_QUOTES,'UTF-8') ?></p></section><?php endif; ?>
<?php if ($applicationStatus === 'waiting_signature'): ?><div class="mb-5 rounded-md border border-amber-200 bg-amber-50 p-4 text-amber-900"><strong>ผ่านการพิจารณาในระบบครบ 2 ขั้นแล้ว:</strong> พิมพ์เอกสารและนำไปให้อาจารย์ที่ปรึกษากับอาจารย์ประสานงานลงนามบนเอกสารให้ครบ 2 คน แล้วจึงอัปโหลดให้เจ้าหน้าที่</div><?php endif; ?>
<?php if ($applicationStatus === 'submitted'): ?><section class="mb-6 rounded-md border border-green-200 bg-green-50 p-5 text-green-950"><h2 class="font-bold">ส่งเอกสารฉบับลงชื่อให้เจ้าหน้าที่แล้ว</h2><p class="mt-1 text-sm">เจ้าหน้าที่สามารถตรวจสอบและดำเนินการเอกสารสหกิจต่อได้แล้ว หากต้องแก้ไขกรุณาติดต่อเจ้าหน้าที่</p></section><?php endif; ?>
<?php if ($applicationStatus === 'waiting_signature'): ?><section class="mb-6 rounded-md border border-emerald-200 bg-emerald-50 p-5"><h2 class="font-bold text-emerald-900">เอกสารพร้อมพิมพ์และอัปโหลดแล้ว</h2><p class="mt-2 text-sm text-emerald-800">ไปที่เมนู “ส่งเอกสารให้เจ้าหน้าที่” เพื่อพิมพ์เอกสาร นำไปลงนาม และอัปโหลดฉบับลงนามครบ</p><a href="internship_submissions.php" class="mt-4 inline-flex rounded-md bg-emerald-700 px-4 py-2.5 font-semibold text-white"><i class="fa-solid fa-file-arrow-up mr-2"></i>ไปหน้าส่งเอกสารให้เจ้าหน้าที่</a></section><?php endif; ?>
<form action="save_internship.php" method="post" class="internship-form space-y-6" data-confirm-form data-confirm-title="ส่งคำร้องให้อาจารย์ที่ปรึกษา?" data-confirm-text="โปรดตรวจสอบข้อมูลให้ครบถ้วนก่อนส่งให้อาจารย์ที่ปรึกษาพิจารณา"><?= csrf_field() ?><input type="hidden" name="return_from" value="<?= htmlspecialchars($returnFrom,ENT_QUOTES,'UTF-8') ?>"><fieldset <?= $locked?'disabled':'' ?> class="space-y-6">
<section class="rounded-md border bg-white p-5"><h2 class="mb-4 text-lg font-bold">ส่วนที่ 1 ข้อมูลนักศึกษา</h2><div class="grid gap-4 md:grid-cols-2">
<label class="block"><span class="mb-1 block text-sm font-medium">ชื่อ-นามสกุล</span><input disabled value="<?= htmlspecialchars($studentDisplayName,ENT_QUOTES,'UTF-8') ?>" class="w-full rounded-md border bg-slate-100 px-3 py-2"></label>
<label class="block"><span class="mb-1 block text-sm font-medium">รหัสนักศึกษา</span><input disabled value="<?= val($student,'student_number') ?>" class="w-full rounded-md border bg-slate-100 px-3 py-2"></label>
<label class="block"><span class="mb-1 block text-sm font-medium">สาขาวิชา</span><input disabled value="<?= val($student,'department_name') ?>" class="w-full rounded-md border bg-slate-100 px-3 py-2"></label>
<label class="block"><span class="mb-1 block text-sm font-medium">คณะ</span><input disabled value="<?= htmlspecialchars($facultyDisplayName,ENT_QUOTES,'UTF-8') ?>" class="w-full rounded-md border bg-slate-100 px-3 py-2"></label>
<?php input($profile,'student_phone','โทรศัพท์',true); input($profile,'year_level','ชั้นปีที่',true,'number'); ?>
<label class="block md:col-span-2"><span class="mb-1 block text-sm font-medium">ปีการศึกษาและภาคเรียน *</span><select id="internship-calendar" name="calendar_id" required class="w-full rounded-md border px-3 py-2"><option value="">เลือกปีการศึกษาและภาคเรียน</option><?php foreach($calendars as $calendar): $term=(int)$calendar['semester']===3?'ฤดูร้อน':'ภาคเรียนที่ '.$calendar['semester']; ?><option value="<?= (int)$calendar['calendar_id'] ?>" data-year="<?= (int)$calendar['academic_year'] ?>" data-semester="<?= (int)$calendar['semester'] ?>" data-start="<?= val($calendar,'start_date') ?>" data-end="<?= val($calendar,'end_date') ?>" <?= (int)$calendar['calendar_id']===$selectedCalendarId?'selected':'' ?>>ปีการศึกษา <?= (int)$calendar['academic_year'] ?> <?= htmlspecialchars($term,ENT_QUOTES,'UTF-8') ?></option><?php endforeach; ?></select><?php if(!$calendars):?><span class="mt-2 block text-sm text-red-700">ยังไม่มีปฏิทินการศึกษาที่เปิดใช้งาน กรุณาติดต่อผู้ดูแลระบบ</span><?php endif;?></label>
<input type="hidden" name="academic_year"><input type="hidden" name="semester"><input type="hidden" name="start_date"><input type="hidden" name="end_date">
<label class="block"><span class="mb-1 block text-sm font-medium">วันออกฝึก</span><input id="calendar-start-display" disabled class="w-full rounded-md border bg-slate-100 px-3 py-2"></label>
<label class="block"><span class="mb-1 block text-sm font-medium">วันสุดท้ายของการฝึก</span><input id="calendar-end-display" disabled class="w-full rounded-md border bg-slate-100 px-3 py-2"></label>
<label class="block"><span class="mb-1 block text-sm font-medium">วันที่ยื่นคำร้อง *</span><input type="date" name="request_date" required value="<?= val($application,'request_date') ?: date('Y-m-d') ?>" class="w-full rounded-md border px-3 py-2"></label>
</div><div class="mt-5 grid gap-2 text-sm md:grid-cols-2"><?php foreach ([['qualification_department','ผ่านการพิจารณาคุณสมบัติจากสาขา'],['qualification_preparation','ผ่านรายวิชาเตรียมความพร้อม'],['qualification_gpa','คะแนนเฉลี่ยสะสมไม่ต่ำกว่า 2.00'],['qualification_company','มีคุณสมบัติตามที่สถานประกอบการกำหนด']] as [$n,$l]): ?><label class="flex items-start gap-2"><input type="checkbox" name="<?= $n ?>" value="1" <?= !empty($profile[$n])?'checked':'' ?> class="mt-1"><span><?= $l ?></span></label><?php endforeach; ?></div></section>
<section class="rounded-md border bg-white p-5"><h2 class="mb-4 text-lg font-bold">ส่วนที่ 2 สถานประกอบการ</h2><div class="grid gap-4 md:grid-cols-2">
<?php input($profile,'recipient_name','ชื่อบุคคลที่ทำหนังสือเรียนถึง',true); input($profile,'recipient_position','ตำแหน่งผู้รับหนังสือ',true); input($profile,'recipient_department','แผนก/ฝ่ายผู้รับหนังสือ'); input($profile,'company_name','ชื่อหน่วยงาน',true); input($profile,'company_number','เลขที่',true); input($profile,'company_moo','หมู่ที่'); input($profile,'company_building','อาคาร'); input($profile,'company_floor','ชั้น'); input($profile,'company_road','ถนน'); input($profile,'company_alley','ซอย'); input($profile,'subdistrict','ตำบล/แขวง',true); input($profile,'district','อำเภอ/เขต',true); input($profile,'province','จังหวัด',true); input($profile,'postal_code','รหัสไปรษณีย์',true); input($profile,'company_phone','โทรศัพท์สถานประกอบการ',true); input($profile,'company_fax','โทรสาร'); input($profile,'company_email','อีเมลสถานประกอบการ',false,'email'); ?>
</div></section>
<section class="rounded-md border bg-white p-5"><h2 class="mb-4 text-lg font-bold">บุคคลติดต่อในสถานประกอบการ</h2><div class="grid gap-4 md:grid-cols-2">
<?php input($profile,'contact_name','ชื่อ-นามสกุล',true); input($profile,'contact_position','ตำแหน่ง',true); input($profile,'contact_department','แผนก/ฝ่าย'); input($profile,'contact_phone','โทรศัพท์',true); input($profile,'contact_email','อีเมล',false,'email'); input($profile,'job_position','ตำแหน่งงานที่ฝึก'); ?>
<label class="block md:col-span-2"><span class="mb-1 block text-sm font-medium">ลักษณะงานที่คาดว่าจะปฏิบัติ</span><textarea name="job_description" class="min-h-24 w-full rounded-md border px-3 py-2"><?= val($profile,'job_description') ?></textarea></label></div></section>
<section class="rounded-md border bg-white p-5"><h2 class="mb-3 text-lg font-bold">การประสานงาน</h2><div class="grid gap-4 md:grid-cols-2"><label><span class="mb-1 block text-sm font-medium">สถานะการประสาน</span><select name="contact_status" class="w-full rounded-md border px-3 py-2"><option value="contacted" <?= ($application['contact_status']??'')==='contacted'?'selected':'' ?>>ประสานสถานประกอบการแล้ว</option><option value="not_contacted" <?= ($application['contact_status']??'not_contacted')==='not_contacted'?'selected':'' ?>>ยังไม่ได้ประสาน</option></select></label><label><span class="mb-1 block text-sm font-medium">การนำหนังสือไปติดต่อ</span><select name="delivery_method" class="w-full rounded-md border px-3 py-2"><option value="student" <?= ($application['delivery_method']??'')==='student'?'selected':'' ?>>นักศึกษาถือเรื่องไปติดต่อด้วยตนเอง</option><option value="faculty" <?= ($application['delivery_method']??'faculty')==='faculty'?'selected':'' ?>>ให้คณะเป็นผู้ดำเนินการ</option></select></label></div></section>
</fieldset><?php if(!$locked): ?><div class="sticky bottom-4 flex flex-col-reverse gap-3 rounded-2xl border border-slate-200 bg-white/95 p-4 shadow-lg backdrop-blur sm:flex-row sm:justify-end"><button name="action" value="draft" formnovalidate class="rounded-xl border border-slate-300 bg-white px-5 py-3 text-sm font-bold text-slate-700 hover:bg-slate-50"><i class="fa-regular fa-floppy-disk mr-2"></i>บันทึกแบบร่าง</button><button name="action" value="submit_review" class="rounded-xl bg-red-700 px-5 py-3 text-sm font-bold text-white shadow-sm hover:bg-red-800"><i class="fa-solid fa-paper-plane mr-2"></i>ส่งให้อาจารย์ที่ปรึกษาตรวจ</button></div><?php endif; ?></form>
</div></div></div></main><script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script><script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script><script src="../assets/js/form-confirm.js"></script><script>
pdfjsLib.GlobalWorkerOptions.workerSrc='https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';
const internshipForm=document.querySelector('.internship-form');
const calendarSelect=document.getElementById('internship-calendar');
function applyInternshipCalendar(){
    const option=calendarSelect?.selectedOptions[0];
    const values={academic_year:option?.dataset.year||'',semester:option?.dataset.semester||'',start_date:option?.dataset.start||'',end_date:option?.dataset.end||''};
    Object.entries(values).forEach(([name,value])=>{const field=internshipForm?.querySelector(`[name="${name}"]`);if(field)field.value=value;});
    const format=value=>value?new Intl.DateTimeFormat('th-TH',{day:'numeric',month:'long',year:'numeric'}).format(new Date(value+'T00:00:00')):'ยังไม่ได้เลือกปฏิทิน';
    const startDisplay=document.getElementById('calendar-start-display'),endDisplay=document.getElementById('calendar-end-display');
    if(startDisplay)startDisplay.value=format(values.start_date);if(endDisplay)endDisplay.value=format(values.end_date);
}
calendarSelect?.addEventListener('change',()=>{applyInternshipCalendar();scheduleInternshipPreview();});
applyInternshipCalendar();
const previewCanvas=document.getElementById('internship-pdf-canvas');
const previewWrap=document.getElementById('internship-pdf-wrap');
let previewTimer=null,previewRequest=0,previewFetchRequest=0,previewRenderTask=null;
async function renderInternshipPdf(source){
    const requestNumber=++previewRequest;
    try{
        const loadingTask=pdfjsLib.getDocument(source);
        const pdf=await loadingTask.promise;
        if(requestNumber!==previewRequest)return;
        const page=await pdf.getPage(1);
        if(requestNumber!==previewRequest)return;
        const viewport=page.getViewport({scale:1.5});
        if(previewRenderTask){try{previewRenderTask.cancel();}catch(error){}}
        const nextCanvas=document.createElement('canvas');
        nextCanvas.width=viewport.width;nextCanvas.height=viewport.height;
        previewRenderTask=page.render({canvasContext:nextCanvas.getContext('2d'),viewport});
        await previewRenderTask.promise;
        if(requestNumber!==previewRequest)return;
        previewCanvas.width=viewport.width;previewCanvas.height=viewport.height;
        previewCanvas.style.width='100%';previewCanvas.style.height='auto';
        previewCanvas.getContext('2d').drawImage(nextCanvas,0,0);
        previewWrap.style.aspectRatio=viewport.width+'/'+viewport.height;
    }catch(error){
        if(error?.name==='RenderingCancelledException'||requestNumber!==previewRequest)return;
        previewWrap.innerHTML='<div class="p-10 text-center text-sm text-red-700">ไม่สามารถแสดงตัวอย่างเอกสารได้</div>';
    }
}
async function refreshInternshipPreview(){
    if(!internshipForm)return;
    const requestNumber=++previewFetchRequest;
    try{
        const response=await fetch('preview_internship_pdf.php',{method:'POST',body:new FormData(internshipForm),credentials:'same-origin'});
        if(!response.ok)throw new Error('Preview HTTP '+response.status);
        const buffer=await response.arrayBuffer();
        if(requestNumber!==previewFetchRequest)return;
        await renderInternshipPdf({data:new Uint8Array(buffer)});
    }catch(error){console.error('Preview update failed',error);}
}
function scheduleInternshipPreview(){clearTimeout(previewTimer);previewTimer=setTimeout(refreshInternshipPreview,800);}
renderInternshipPdf('preview_internship_pdf.php');
internshipForm?.addEventListener('input',scheduleInternshipPreview);
internshipForm?.addEventListener('change',scheduleInternshipPreview);
document.querySelectorAll('.internship-form fieldset > section').forEach((section,index)=>{const heading=section.querySelector(':scope > h2');if(!heading)return;const body=document.createElement('div');body.className='internship-section-body'+(index===0?'':' hidden');while(heading.nextSibling)body.appendChild(heading.nextSibling);const button=document.createElement('button');button.type='button';button.className='internship-section-toggle';heading.replaceWith(button);button.appendChild(heading);const icon=document.createElement('span');icon.className='inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-slate-100 text-slate-500';icon.innerHTML='<i class="fa-solid '+(index===0?'fa-chevron-up':'fa-chevron-down')+'"></i>';button.appendChild(icon);section.appendChild(body);button.setAttribute('aria-expanded',index===0?'true':'false');button.addEventListener('click',()=>{const isOpen=!body.classList.contains('hidden');body.classList.toggle('hidden',isOpen);button.setAttribute('aria-expanded',isOpen?'false':'true');icon.innerHTML='<i class="fa-solid '+(isOpen?'fa-chevron-down':'fa-chevron-up')+'"></i>';});});
document.querySelector('.internship-form')?.addEventListener('invalid',event=>{const body=event.target.closest('.internship-section-body');if(body?.classList.contains('hidden')){body.classList.remove('hidden');const button=body.previousElementSibling;button?.setAttribute('aria-expanded','true');const icon=button?.querySelector('span');if(icon)icon.innerHTML='<i class="fa-solid fa-chevron-up"></i>';}},true);
document.querySelector('.internship-form')?.addEventListener('submit',event=>{
    const form=event.currentTarget;
    const startDate=form.querySelector('[name="start_date"]')?.value||'';
    const endDate=form.querySelector('[name="end_date"]')?.value||'';
    if(startDate&&endDate&&endDate<startDate){
        event.preventDefault();
        event.stopImmediatePropagation();
        Swal.fire({icon:'warning',title:'วันที่ไม่ถูกต้อง',text:'วันที่สิ้นสุดต้องไม่น้อยกว่าวันที่เริ่มฝึก',confirmButtonText:'กลับไปแก้ไข',confirmButtonColor:'#dc2626'}).then(()=>form.querySelector('[name="end_date"]')?.focus());
    }
},true);
<?php if (isset($_GET['saved'])): ?>Swal.fire({icon:'success',title:'บันทึกสำเร็จ',text:'บันทึกข้อมูลฉบับร่างเรียบร้อยแล้ว',confirmButtonText:'ตกลง',confirmButtonColor:'#16a34a'});<?php endif; ?>
<?php if (isset($_GET['review_submitted'])): ?>Swal.fire({icon:'success',title:'ส่งคำร้องแล้ว',text:'ระบบส่งคำร้องให้อาจารย์ที่ปรึกษาพิจารณาแล้ว',confirmButtonText:'ตกลง',confirmButtonColor:'#16a34a'});<?php endif; ?>
<?php if (isset($_GET['submitted'])): ?>Swal.fire({icon:'success',title:'ส่งเอกสารให้เจ้าหน้าที่แล้ว',text:'อัปโหลดเอกสารที่อาจารย์ทั้ง 2 คนลงนามครบเรียบร้อยแล้ว',confirmButtonText:'ตกลง',confirmButtonColor:'#16a34a'});<?php endif; ?>
<?php if (isset($_GET['date_error'])): ?>Swal.fire({icon:'warning',title:'วันที่ไม่ถูกต้อง',text:'วันที่สิ้นสุดต้องไม่น้อยกว่าวันที่เริ่มฝึก',confirmButtonText:'กลับไปแก้ไข',confirmButtonColor:'#dc2626'}).then(()=>document.querySelector('[name="end_date"]')?.focus());<?php endif; ?>
<?php if (isset($_GET['reset']) || isset($_GET['restarted'])): ?>Swal.fire({icon:'success',title:'เริ่มคำร้องใหม่ได้แล้ว',text:'เก็บคำร้องเดิมเป็นประวัติเรียบร้อยแล้ว กรุณากรอกข้อมูลสำหรับคำร้องฉบับใหม่',confirmButtonText:'ตกลง',confirmButtonColor:'#16a34a'});<?php endif; ?>
</script></body></html>
