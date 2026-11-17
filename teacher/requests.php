<?php
session_start();
require_once '../middleware/teacher.php';
require_once '../config/database.php';

$teacherStmt=$pdo->prepare('SELECT e.employee_id,e.department_id,e.employee_code,e.firstname,e.lastname
    FROM employee e WHERE e.user_id=? AND e.employee_type="teacher" LIMIT 1');
$teacherStmt->execute([$_SESSION['user_id']]);
$teacher=$teacherStmt->fetch(PDO::FETCH_ASSOC);
if(!$teacher){http_response_code(403);renderPlainErrorAlert('ไม่พบข้อมูลอาจารย์');}
$focusDocumentId=0;
if ((int)($_GET['notification_id'] ?? 0) > 0) {
    $focusStmt=$pdo->prepare('SELECT document_id FROM notifications WHERE notification_id=? AND recipient_user_id=? LIMIT 1');
    $focusStmt->execute([(int)$_GET['notification_id'],(int)$_SESSION['user_id']]);
    $focusDocumentId=(int)$focusStmt->fetchColumn();
}

$requestStmt=$pdo->prepare('SELECT DISTINCT d.document_id,d.document_status,d.submitted_at,s.student_number,s.firstname,s.lastname,
    COALESCE(sp.prefix_short,sp.prefix_name,"") AS student_prefix,dt.document_code,dt.document_name,
    CASE WHEN drp.process_id IS NOT NULL
         THEN IF(current_step.reviewer_user_id=? AND current_step.step_status="current","current","approved")
         ELSE IF(d.document_status="submitted","current","approved") END AS teacher_review_status,
    CASE WHEN current_step.reviewer_user_id=? THEN current_step.step_name ELSE NULL END AS current_step_name
    FROM documents d
    JOIN student s ON s.student_id=d.student_id
    LEFT JOIN prefixes sp ON sp.prefix_id=s.prefix_id
    JOIN document_template t ON t.template_id=d.template_id
    JOIN document_type dt ON dt.document_type_id=t.document_type_id
    LEFT JOIN document_review_processes drp ON drp.document_id=d.document_id
    LEFT JOIN document_review_step_instances current_step ON current_step.process_id=drp.process_id AND current_step.step_order=drp.current_step_order
    WHERE d.document_status IN ("in_review","revision_requested","approved")
      AND drp.process_id IS NOT NULL AND (
            (current_step.reviewer_user_id=? AND current_step.step_status="current"
             AND NOT (UPPER(dt.document_code)="INTERNSHIP_INFO" AND (current_step.role_code="internship_coordinator" OR current_step.step_name="อาจารย์ประสานงานฝึกประสบการณ์วิชาชีพ")))
            OR EXISTS(SELECT 1 FROM document_review_step_instances own_step WHERE own_step.process_id=drp.process_id AND own_step.reviewer_user_id=? AND own_step.step_status="approved"
                      AND NOT (UPPER(dt.document_code)="INTERNSHIP_INFO" AND (own_step.role_code="internship_coordinator" OR own_step.step_name="อาจารย์ประสานงานฝึกประสบการณ์วิชาชีพ")))
      ) ORDER BY d.submitted_at DESC,d.document_id DESC');
$requestStmt->execute([
    $_SESSION['user_id'],$_SESSION['user_id'],$_SESSION['user_id'],$_SESSION['user_id']
]);
$allRequests=$requestStmt->fetchAll(PDO::FETCH_ASSOC);
$pending_document_count=count(array_filter($allRequests,static fn(array $row):bool=>$row['teacher_review_status']==='current'));
$search=trim((string)($_GET['search']??''));
$status=in_array(($_GET['status']??''),['current','approved'],true)?$_GET['status']:'';
$requests=array_values(array_filter($allRequests,static function(array $row)use($search,$status):bool{
    if($status!==''&&$row['teacher_review_status']!==$status)return false;
    if($search==='')return true;
    return mb_stripos(implode(' ',[$row['student_number'],$row['student_prefix'],$row['firstname'],$row['lastname'],$row['document_code'],$row['document_name']]),$search)!==false;
}));
$pageHeading='พิจารณาคำร้องเอกสาร R';
function h($value):string{return htmlspecialchars((string)$value,ENT_QUOTES,'UTF-8');}
?>
<!doctype html>
<html lang="th">
<head>
    <link rel="icon" type="image/png" href="/Project/assets/images/fet-logo-transparent.png?v=20260815-3">
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>พิจารณาคำร้องเอกสาร R</title><script src="https://cdn.tailwindcss.com"></script><link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css"><style>.notification-focus{background:#fff7ed!important;box-shadow:inset 4px 0 0 #dc2626,0 0 0 2px #fecaca;animation:notification-focus-pulse 1.4s ease-out 2}@keyframes notification-focus-pulse{50%{box-shadow:inset 4px 0 0 #dc2626,0 0 0 5px rgba(220,38,38,.16)}}</style></head>
<body class="min-h-screen bg-gray-100 text-gray-900">
<?php include 'sidebar.php'; ?>
<div class="min-h-screen lg:ml-72"><?php include 'navbar.php'; ?>
<main class="p-4 sm:p-6 lg:p-8">
    <div><h1 class="text-3xl font-bold">พิจารณาคำร้องเอกสาร R</h1><p class="mt-1 text-gray-500">รายการคำร้องเอกสาร R ที่รอการพิจารณาในฐานะอาจารย์ที่ปรึกษาหรืออาจารย์ผู้เกี่ยวข้อง</p></div>
    <?php if(isset($_GET['reviewed'])):?><div hidden data-success-alert data-success-title="บันทึกสำเร็จ" data-success-text="บันทึกผลการพิจารณาแล้ว" data-success-clean-param="reviewed"></div><?php endif;?>
    <?php if(($_GET['notice']??'')==='not-pending'):?><div hidden data-success-alert data-success-icon="info" data-success-title="ดำเนินการเรียบร้อยแล้ว" data-success-text="เอกสารรายการนี้ได้รับการพิจารณาไปแล้ว จึงไม่ต้องบันทึกผลซ้ำ" data-success-clean-param="notice"></div><?php endif;?>
    <form id="teacher-request-search" method="get" data-jquery-search-form data-search-results="#teacher-request-results" class="mt-6 grid gap-3 rounded-xl border bg-white p-5 shadow-sm sm:grid-cols-[1fr_220px_auto]">
        <label><span class="mb-1.5 block text-sm font-semibold">ค้นหาคำร้องเอกสาร R</span><input type="search" name="search" value="<?=h($search)?>" placeholder="รหัส ชื่อนักศึกษา หรือชื่อเอกสาร" class="w-full rounded-lg border px-3 py-2.5"></label>
        <label><span class="mb-1.5 block text-sm font-semibold">สถานะ</span><select name="status" class="w-full rounded-lg border bg-white px-3 py-2.5"><option value="">ทุกสถานะ</option><option value="current" <?=$status==='current'?'selected':''?>>รอพิจารณา</option><option value="approved" <?=$status==='approved'?'selected':''?>>อนุมัติแล้ว</option></select></label>
        <button class="self-end rounded-lg bg-gray-900 px-5 py-2.5 font-semibold text-white"><i class="fa-solid fa-magnifying-glass mr-2"></i>ค้นหา</button>
    </form>
    <section id="teacher-request-results" data-jquery-search-result data-search-form="#teacher-request-search" class="mt-5 overflow-hidden rounded-xl border bg-white shadow-sm">
        <div class="flex items-center justify-between gap-3 border-b bg-gray-50 px-5 py-4"><div><h2 class="font-bold">รายการคำร้องเอกสาร R</h2><p class="mt-1 text-sm text-gray-500">เลือกพิจารณาหรือดาวน์โหลดเอกสาร R ที่ตรวจแล้ว</p></div><span class="rounded-lg border bg-white px-3 py-1.5 text-sm font-semibold text-gray-600"><?=count($requests)?> รายการ</span></div>
        <?php if($requests):?><div class="overflow-x-auto"><table class="w-full min-w-[780px] text-sm"><thead class="bg-gray-50 text-gray-600"><tr><th class="px-6 py-3 text-left">นักศึกษา</th><th class="px-6 py-3 text-left">เอกสาร</th><th class="px-6 py-3 text-left">วันที่ส่ง</th><th class="px-6 py-3 text-center">สถานะ</th><th class="px-6 py-3 text-center">ดำเนินการ</th></tr></thead><tbody>
        <?php foreach($requests as $document): $isCurrent=$document['teacher_review_status']==='current'; ?><tr class="border-t hover:bg-gray-50 <?= (int)$document['document_id'] === $focusDocumentId ? 'notification-focus' : '' ?>"><td class="px-6 py-4"><strong><?=h($document['student_number'])?></strong><br><?=h(trim($document['student_prefix'].$document['firstname'].' '.$document['lastname']))?></td><td class="px-6 py-4"><strong class="text-red-700"><?=h($document['document_code'])?></strong><br><?=h($document['document_name'])?><?php if($isCurrent&&!empty($document['current_step_name'])):?><br><span class="mt-1 inline-flex text-xs font-bold text-blue-700">ขั้น: <?=h($document['current_step_name'])?></span><?php endif;?></td><td class="px-6 py-4"><?=h($document['submitted_at']??'-')?></td><td class="px-6 py-4 text-center"><span class="status-badge <?=$isCurrent?'status-badge--warning':'status-badge--success'?>"><?=$isCurrent?'รอพิจารณา':'อนุมัติแล้ว'?></span></td><td class="px-6 py-4 text-center"><?php if($isCurrent):?><a href="request_review.php?document=<?=(int)$document['document_id']?>" class="inline-flex items-center rounded-lg bg-red-700 px-4 py-2.5 font-bold text-white hover:bg-red-800"><i class="fa-solid fa-magnifying-glass mr-2"></i>พิจารณา</a><?php else:?><a href="../student/download_document.php?document=<?=(int)$document['document_id']?>" class="inline-flex items-center rounded-lg border px-4 py-2.5 font-bold text-gray-700"><i class="fa-solid fa-file-pdf mr-2 text-red-600"></i>PDF</a><?php endif;?></td></tr><?php endforeach;?>
        </tbody></table></div><?php else:?><div class="p-12 text-center text-gray-500"><i class="fa-solid fa-file-circle-check mb-4 text-5xl text-gray-300"></i><p>ไม่พบคำร้องเอกสาร R ตามเงื่อนไขที่เลือก</p></div><?php endif;?>
    </section>
</main></div><?php if ($focusDocumentId > 0): ?><script>window.setTimeout(()=>document.querySelector('.notification-focus')?.scrollIntoView({behavior:'smooth',block:'center'}),120);</script><?php endif; ?>
</body></html>
