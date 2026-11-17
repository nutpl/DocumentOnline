<?php
session_start();
require_once __DIR__ . '/../middleware/teacher.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/teacher_coordinator_helper.php';
require_once __DIR__ . '/../helpers/official_document_number_helper.php';

$coordinator = teacherCoordinatorProfile($pdo, (int)$_SESSION['user_id']);
if (!$coordinator) {
    http_response_code(403);
    renderPlainErrorAlert('หน้านี้สำหรับอาจารย์ประสานงานฝึกประสบการณ์วิชาชีพเท่านั้น');
}
$search = trim((string)($_GET['search'] ?? ''));
$type = in_array($_GET['type'] ?? '', ['request', 'send'], true) ? $_GET['type'] : '';
$sql = 'SELECT od.*, parent.document_number AS parent_document_number, COUNT(os.internship_profile_id) AS student_count
    FROM internship_official_document od
    LEFT JOIN internship_official_document parent ON parent.official_document_id=od.parent_official_document_id
    JOIN internship_official_student os ON os.official_document_id=od.official_document_id
    JOIN internship_profile ip ON ip.internship_profile_id=os.internship_profile_id
    JOIN student s ON s.student_id=ip.student_id
    JOIN group_class g ON g.group_id=s.group_id
    WHERE g.department_id=?';
$params = [(int)$coordinator['department_id']];
if ($search !== '') {
    $sql .= ' AND (od.document_number LIKE ? OR od.company_name LIKE ? OR s.student_number LIKE ? OR s.firstname LIKE ? OR s.lastname LIKE ?)';
    $like = '%' . $search . '%';
    array_push($params, $like, $like, $like, $like, $like);
}
if ($type !== '') { $sql .= ' AND od.document_type=?'; $params[] = $type; }
$sql .= ' GROUP BY od.official_document_id ORDER BY od.created_at DESC';
$stmt = $pdo->prepare($sql); $stmt->execute($params); $documents = $stmt->fetchAll(PDO::FETCH_ASSOC);
$pageHeading = 'ดาวน์โหลดหนังสือสหกิจ';
function h($value): string { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
?>
<!doctype html>
<html lang="th"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>ดาวน์โหลดหนังสือสหกิจ</title><script src="https://cdn.tailwindcss.com"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
</head><body class="min-h-screen bg-slate-100 text-slate-900">
<?php include __DIR__ . '/sidebar.php'; ?>
<div class="min-h-screen lg:ml-72"><?php include __DIR__ . '/navbar.php'; ?><main class="p-4 sm:p-6 lg:p-8">
<section class="management-hero"><span class="management-hero__icon"><i class="fa-solid fa-file-arrow-down"></i></span><div class="min-w-0 flex-1"><p class="management-hero__eyebrow">อาจารย์ประสานงานฝึกประสบการณ์วิชาชีพ</p><h1>ดาวน์โหลดหนังสือสหกิจ</h1><p>ช่วยดาวน์โหลดหนังสือขอความอนุเคราะห์และใบขอส่งตัวของสาขา โดยไม่มีสิทธิ์สร้าง แก้ไข หรือลบเอกสาร</p></div><span class="inline-flex shrink-0 items-center gap-2 rounded-full border border-red-100 bg-red-50 px-3 py-2 text-sm font-bold text-red-700"><i class="fa-regular fa-file-lines"></i><?=count($documents)?> รายการ</span></section>
<form method="get" class="mt-5 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm"><div class="grid gap-3 sm:grid-cols-[1fr_220px_auto]"><label><span class="mb-1 block text-xs font-bold text-slate-500">ค้นหาหนังสือ</span><input type="search" name="search" value="<?=h($search)?>" placeholder="เลขหนังสือ บริษัท หรือนักศึกษา" class="w-full rounded-xl border border-slate-300 px-4 py-2.5"></label><label><span class="mb-1 block text-xs font-bold text-slate-500">ประเภทหนังสือ</span><select name="type" class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5"><option value="">ทุกประเภท</option><option value="request" <?=$type==='request'?'selected':''?>>ขอความอนุเคราะห์</option><option value="send" <?=$type==='send'?'selected':''?>>ใบขอส่งตัว</option></select></label><button class="self-end rounded-xl bg-slate-900 px-5 py-2.5 font-semibold text-white"><i class="fa-solid fa-magnifying-glass mr-2"></i>ค้นหา</button></div></form>
<section class="mt-5 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm"><div class="border-b border-slate-100 px-5 py-4"><h2 class="font-bold">รายการหนังสือที่สร้างแล้ว</h2><p class="mt-1 text-sm text-slate-500">แสดงเฉพาะเอกสารของสาขาที่อาจารย์ประสานงานรับผิดชอบ</p></div><div class="divide-y divide-slate-100"><?php foreach($documents as $document):?><article class="flex flex-col gap-4 p-5 lg:flex-row lg:items-center lg:justify-between"><div class="min-w-0"><div class="flex flex-wrap items-center gap-2"><h3 class="font-bold text-slate-900"><?=h(officialFormatDocumentNumber((string)$document['document_number']))?></h3><span class="rounded-full px-3 py-1 text-xs font-semibold <?=$document['document_type']==='request'?'bg-blue-50 text-blue-700':'bg-amber-50 text-amber-700'?>"><?=$document['document_type']==='request'?(!empty($document['is_supplemental'])?'ขอความอนุเคราะห์เพิ่มเติม':'ขอความอนุเคราะห์'):'ใบขอส่งตัว'?></span></div><p class="mt-1 font-medium text-slate-700"><?=h($document['company_name'])?></p><p class="mt-1 text-sm text-slate-500">วันที่ <?=h($document['document_date'])?> <span class="mx-1 text-slate-300">•</span> นักศึกษา <?= (int)$document['student_count']?> คน</p><?php if($document['document_type']==='request' && !empty($document['is_supplemental'])):?><p class="mt-2 text-xs font-semibold text-amber-800"><i class="fa-solid fa-link mr-1"></i>ฉบับเพิ่มเติมของเลขที่ <?=h($document['parent_document_number'] ? officialFormatDocumentNumber((string)$document['parent_document_number']) : 'หนังสือเดิม')?></p><?php endif;?></div><div class="flex shrink-0 flex-wrap gap-2"><a href="generate_official_pdf.php?id=<?=(int)$document['official_document_id']?>&amp;type=<?=h($document['document_type'])?>" class="inline-flex items-center gap-2 rounded-xl bg-red-50 px-4 py-2.5 font-bold text-red-700 hover:bg-red-600 hover:text-white"><i class="fa-solid fa-file-pdf"></i>PDF</a><a href="generate_official_word.php?id=<?=(int)$document['official_document_id']?>&amp;type=<?=h($document['document_type'])?>" class="inline-flex items-center gap-2 rounded-xl bg-blue-50 px-4 py-2.5 font-bold text-blue-700 hover:bg-blue-600 hover:text-white"><i class="fa-solid fa-file-word"></i>Word</a></div></article><?php endforeach;?><?php if(!$documents):?><div class="p-12 text-center text-slate-500"><i class="fa-regular fa-folder-open mb-3 block text-4xl text-slate-300"></i>ยังไม่มีหนังสือที่สร้างในสาขานี้</div><?php endif;?></div></section>
</main></div><script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script></body></html>
