<?php
session_start();
require_once '../middleware/staff.php';
require_once '../config/database.php';
require_once '../helpers/csrf_helper.php';
require_once '../helpers/internship_supervision_helper.php';
require_once '../helpers/pagination_helper.php';

$staff = supervisionEmployee($pdo, (int)$_SESSION['user_id'], 'staff');
if (!$staff) { http_response_code(403); renderPlainErrorAlert('ไม่มีสิทธิ์'); }
$staff['effective_role'] = 'staff';
if($_SERVER['REQUEST_METHOD']==='POST'){verify_csrf_or_abort();try{saveSupervision($pdo,$staff,$_POST);$returnPage=max(1,(int)($_POST['return_page']??1));$returnSearch=trim((string)($_POST['return_search']??''));$returnStatus=trim((string)($_POST['return_status']??''));$returnGroupId=max(0,(int)($_POST['return_group_id']??0));header('Location: supervision.php?saved=1&profile_id='.(int)($_POST['profile_id']??0).'&search='.urlencode($returnSearch).'&status='.urlencode($returnStatus).'&group_id='.$returnGroupId.'&page='.$returnPage.'#supervision-list');exit;}catch(RuntimeException $e){$saveError=$e->getMessage();}}
$allRows = supervisionRows($pdo, (int)$staff['department_id']);
$availableGroups = [];
foreach ($allRows as $row) {
    $groupId = (int) ($row['group_id'] ?? 0);
    if ($groupId > 0) $availableGroups[$groupId] = (string) ($row['group_name'] ?? '');
}
asort($availableGroups, SORT_NATURAL | SORT_FLAG_CASE);
$selectedGroupId = max(0, (int) ($_GET['group_id'] ?? 0));
if ($selectedGroupId > 0 && !isset($availableGroups[$selectedGroupId])) $selectedGroupId = 0;
$groupRows = $selectedGroupId > 0
    ? array_values(array_filter($allRows, static fn(array $row): bool => (int) ($row['group_id'] ?? 0) === $selectedGroupId))
    : $allRows;
$sharedInternshipPeriod = supervisionCurrentInternshipPeriod($pdo);
$summary = supervisionSummary($groupRows, $staff, $sharedInternshipPeriod);
$search = trim((string)($_GET['search'] ?? ''));
$status = in_array(($_GET['status'] ?? ''), ['assigned','unassigned','visit1_pending','visit2_pending','completed'], true) ? $_GET['status'] : '';
$filteredRows = array_values(array_filter($groupRows, static function(array $row) use ($search, $status): bool {
    $matchesSearch = $search === '' || mb_stripos(implode(' ', [
        $row['student_number'] ?? '', $row['firstname'] ?? '', $row['lastname'] ?? '',
        $row['company_name'] ?? '', $row['group_name'] ?? '', $row['supervisor_display_name'] ?? ''
    ]), $search) !== false;
    if (!$matchesSearch) return false;
    if ($status === 'assigned') return !empty($row['supervisor_employee_id']);
    if ($status === 'unassigned') return empty($row['supervisor_employee_id']);
    if ($status === 'visit1_pending') return empty($row['visit1_date']);
    if ($status === 'visit2_pending') return empty($row['visit2_date']);
    if ($status === 'completed') return !empty($row['visit1_date']) && !empty($row['visit2_date']);
    return true;
}));
$totalItems = count($filteredRows);
$perPage = selected_per_page(5);
$totalPages = max(1, (int)ceil($totalItems / $perPage));
$currentPage = max(1, min((int)($_GET['page'] ?? 1), $totalPages));
$rows = array_slice($filteredRows, ($currentPage - 1) * $perPage, $perPage);
$teachers = supervisionTeachers($pdo, (int)$staff['department_id']);
$staff_name = trim($staff['prefix_name'].$staff['firstname'].' '.$staff['lastname']);
$pageHeading = 'แผนนิเทศ';
$document_count = $totalItems;
$assignedCount = count(array_filter($allRows, static fn(array $row): bool => !empty($row['supervisor_employee_id'])));
$scheduledCount = count(array_filter($allRows, static fn(array $row): bool => !empty($row['visit1_date']) || !empty($row['visit2_date'])));
$completedCount = count(array_filter($allRows, static fn(array $row): bool => !empty($row['visit1_date']) && !empty($row['visit2_date'])));
function h($value): string { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
function supervisionDate($value): string { if(!$value)return '-';$months=[1=>'ม.ค.',2=>'ก.พ.',3=>'มี.ค.',4=>'เม.ย.',5=>'พ.ค.',6=>'มิ.ย.',7=>'ก.ค.',8=>'ส.ค.',9=>'ก.ย.',10=>'ต.ค.',11=>'พ.ย.',12=>'ธ.ค.'];$date=new DateTimeImmutable((string)$value);return (int)$date->format('j').' '.$months[(int)$date->format('n')].' '.((int)$date->format('Y')+543); }
?>
<!doctype html>
<html lang="th">
<head>
    <link rel="icon" type="image/png" href="/Project/assets/images/fet-logo-transparent.png?v=20260815-3">
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>แผนนิเทศ</title><script src="https://cdn.tailwindcss.com"></script><link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css"></head>
<body class="min-h-screen bg-slate-100 text-slate-900">
<?php include 'sidebar.php'; ?>
<div class="min-h-screen lg:ml-72"><?php include 'navbar.php'; ?>
<main class="p-4 sm:p-6 lg:p-8">
    <section class="flex flex-col gap-3 rounded-xl border border-slate-200 bg-white px-4 py-3 shadow-sm sm:flex-row sm:items-center sm:justify-between sm:px-5">
        <div class="flex items-center gap-3">
            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-red-50 text-red-700"><i class="fa-solid fa-calendar-check"></i></span>
            <div><h1 class="text-xl font-bold tracking-tight text-slate-900 sm:text-2xl">แผนนิเทศนักศึกษาสหกิจ</h1><p class="mt-0.5 text-sm text-slate-500">จัดการกำหนดการนิเทศของนักศึกษา</p></div>
        </div>
        <a href="export_internship_excel.php" aria-label="ดาวน์โหลดข้อมูลสหกิจเป็นไฟล์ Excel" class="group inline-flex items-center justify-center gap-2 self-start rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm font-bold text-emerald-700 transition hover:border-emerald-500 hover:bg-emerald-100 sm:self-auto">
            <i class="fa-solid fa-file-excel"></i><span>ดาวน์โหลด Excel</span><i class="fa-solid fa-arrow-down text-xs transition-transform group-hover:translate-y-0.5"></i>
        </a>
    </section>

    <?php if(isset($_GET['saved'])):?><div hidden data-success-alert data-success-title="บันทึกสำเร็จ" data-success-text="บันทึกแผนนิเทศเรียบร้อยแล้ว" data-success-clean-param="saved"></div><?php endif;?>
    <?php if(!empty($saveError)):?><div class="mt-4 rounded-xl border border-red-200 bg-red-50 p-4 text-red-800"><i class="fa-solid fa-circle-exclamation mr-2"></i><?=h($saveError)?></div><?php endif;?>

    

    <?php if ($groupRows): ?>
    <section class="mt-4 rounded-xl border border-slate-200 bg-white px-4 py-3.5 shadow-sm sm:px-5" aria-label="ข้อมูลกลุ่มสหกิจ">
        <div class="flex items-center gap-2.5"><span class="flex h-9 w-9 items-center justify-center rounded-lg bg-red-50 text-red-700"><i class="fa-solid fa-building-columns"></i></span><div><h2 class="font-bold">ข้อมูลการปฏิบัติงาน</h2><p class="text-sm text-slate-500"><?=h($summary['faculty_department'])?></p></div></div>
        <div class="mt-3 grid gap-3 border-t border-slate-100 pt-3 sm:grid-cols-2">
            <div><p class="text-xs font-semibold text-slate-400">อาจารย์ประสานงานฝึกประสบการณ์วิชาชีพ</p><p class="mt-0.5 font-semibold"><?=h(implode(', ',$summary['coordinators'])?:'-')?></p></div>
            <div><p class="text-xs font-semibold text-slate-400">ระยะเวลาปฏิบัติงาน</p><p class="mt-0.5 text-sm font-semibold leading-5"><?=h($summary['period']?:'-')?></p></div>
        </div>
    </section>
    <?php endif; ?>

    <form id="supervision-search" method="get" data-jquery-search-form data-search-results="#supervision-results" class="mt-4 grid gap-3 rounded-xl border border-slate-200 bg-white p-3.5 shadow-sm lg:grid-cols-[minmax(0,1fr)_190px_220px_auto]">
        <label><span class="mb-1 block text-sm font-bold text-slate-700">ค้นหานักศึกษาหรือบริษัท</span><input type="search" name="search" value="<?=h($search)?>" placeholder="รหัส ชื่อ บริษัท กลุ่มเรียน หรืออาจารย์" class="w-full rounded-lg border border-slate-300 px-3 py-2.5"></label>
        <label><span class="mb-1 block text-sm font-bold text-slate-700">กลุ่มเรียน</span><select name="group_id" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5"><option value="0">ทุกกลุ่มเรียน</option><?php foreach($availableGroups as $groupId=>$groupName):?><option value="<?=$groupId?>" <?=$selectedGroupId===$groupId?'selected':''?>><?=h($groupName)?></option><?php endforeach;?></select></label>
        <label><span class="mb-1 block text-sm font-bold text-slate-700">สถานะแผนนิเทศ</span><select name="status" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5"><option value="">ทุกสถานะ</option><option value="assigned" <?=$status==='assigned'?'selected':''?>>มีอาจารย์นิเทศแล้ว</option><option value="unassigned" <?=$status==='unassigned'?'selected':''?>>ยังไม่มีอาจารย์นิเทศ</option><option value="visit1_pending" <?=$status==='visit1_pending'?'selected':''?>>รอกำหนดครั้งที่ 1</option><option value="visit2_pending" <?=$status==='visit2_pending'?'selected':''?>>รอกำหนดครั้งที่ 2</option><option value="completed" <?=$status==='completed'?'selected':''?>>กำหนดครบ 2 ครั้ง</option></select></label>
        <button class="self-end rounded-lg bg-slate-900 px-5 py-2.5 font-semibold text-white"><i class="fa-solid fa-magnifying-glass mr-2"></i>ค้นหา</button>
    </form>

    <div id="supervision-results" data-jquery-search-result data-search-form="#supervision-search">
    <section id="supervision-list" class="mt-6">
        <div class="mb-4 flex flex-wrap items-end justify-between gap-3">
            <div><h2 class="text-xl font-bold">รายชื่อนักศึกษา</h2><p class="mt-1 text-sm text-slate-500">กดรายการเพื่อดูและแก้ไขกำหนดการนิเทศ</p></div>
            <span class="rounded-full border border-slate-200 bg-white px-3 py-1.5 text-sm font-semibold text-slate-600 shadow-sm">หน้า <?=$currentPage?> จาก <?=$totalPages?></span>
        </div>
        <div class="space-y-3">
        <?php foreach ($rows as $row):
            $studentName=trim(($row['prefix_short']??'').' '.$row['firstname'].' '.$row['lastname']);
            $hasTeacher=!empty($row['supervisor_employee_id']);
            $visit1Ready=!empty($row['visit1_date']);
            $visit2Ready=!empty($row['visit2_date']);
        ?>
        <form method="post" class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm transition hover:border-slate-300 hover:shadow-md"><?=csrf_field()?><input type="hidden" name="profile_id" value="<?=(int)$row['internship_profile_id']?>"><input type="hidden" name="expected_lock_version" value="<?=(int)($row['lock_version']??0)?>"><input type="hidden" name="return_page" value="<?=$currentPage?>"><input type="hidden" name="return_search" value="<?=h($search)?>"><input type="hidden" name="return_status" value="<?=h($status)?>"><input type="hidden" name="return_group_id" value="<?=$selectedGroupId?>">
            <details class="group" <?=isset($_GET['saved'])&&(int)($_GET['profile_id']??0)===(int)$row['internship_profile_id']?'open':''?>>
                <summary class="flex cursor-pointer list-none flex-col gap-4 p-5 [&::-webkit-details-marker]:hidden lg:flex-row lg:items-center">
                    <div class="flex min-w-0 flex-1 items-start gap-3">
                        <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-slate-100 font-bold text-slate-700"><?=h(mb_substr($row['firstname'],0,1))?></span>
                        <div class="min-w-0"><p class="text-xs font-semibold text-slate-400"><?=h($row['student_number'])?></p><h3 class="truncate font-bold text-slate-900"><?=h($studentName)?></h3><p class="mt-1 truncate text-sm text-slate-500"><i class="fa-regular fa-building mr-1.5"></i><?=h($row['company_name'])?></p></div>
                    </div>
                    <div class="flex flex-wrap items-center gap-2 lg:justify-end">
                        <span class="status-badge <?=$hasTeacher?'status-badge--info':'status-badge--neutral'?>"><i class="fa-solid fa-user-tie"></i><?=$hasTeacher?h($row['supervisor_display_name']):'ยังไม่ได้กำหนดอาจารย์'?></span>
                        <span class="status-badge <?=$visit1Ready?'status-badge--success':'status-badge--warning'?>">นิเทศครั้งที่ 1: <?=$visit1Ready?'กำหนดแล้ว':'รอกำหนด'?></span>
                        <span class="status-badge <?=$visit2Ready?'status-badge--success':'status-badge--neutral'?>">นิเทศครั้งที่ 2: <?=$visit2Ready?'กำหนดแล้ว':'รอกำหนด'?></span>
                        <span class="ml-1 flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-slate-100 text-slate-500 transition group-open:rotate-180 group-open:bg-red-50 group-open:text-red-700"><i class="fa-solid fa-chevron-down"></i></span>
                    </div>
                </summary>
                <div class="border-t border-slate-100 bg-slate-50/60 p-5 sm:p-6">
                    <label class="block"><span class="mb-2 block text-sm font-bold text-slate-700"><i class="fa-solid fa-chalkboard-user mr-2 text-red-600"></i>อาจารย์นิเทศ</span><select name="supervisor_employee_id" class="w-full rounded-xl border border-slate-300 bg-white px-3 py-3 outline-none transition focus:border-red-500 focus:ring-4 focus:ring-red-100"><option value="">ยังไม่มีผู้เลือก</option><?php foreach($teachers as $teacher):$teacherName=trim($teacher['prefix_name'].$teacher['firstname'].' '.$teacher['lastname']);?><option value="<?=(int)$teacher['employee_id']?>" <?=(int)($row['supervisor_employee_id']??0)===(int)$teacher['employee_id']?'selected':''?>><?=h($teacherName)?></option><?php endforeach;?></select></label>
                    <div class="mt-5 grid gap-4 xl:grid-cols-2">
                        <?php foreach([1,2] as $visit): ?>
                        <fieldset class="rounded-xl border border-slate-200 bg-white p-4"><legend class="px-2 text-sm font-bold text-slate-700"><span class="mr-2 inline-flex h-7 w-7 items-center justify-center rounded-full <?=$visit===1?'bg-red-600 text-white':'bg-slate-800 text-white'?>"><?=$visit?></span>การนิเทศครั้งที่ <?=$visit?></legend>
                            <div class="mt-2 grid gap-3 sm:grid-cols-3">
                                <label><span class="mb-1.5 block text-sm text-slate-600">วันที่</span><input type="date" name="visit<?=$visit?>_date" value="<?=h($row['visit'.$visit.'_date']??'')?>" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 outline-none focus:border-red-500 focus:ring-4 focus:ring-red-100"></label>
                                <label><span class="mb-1.5 block text-sm text-slate-600">เวลา</span><input type="time" name="visit<?=$visit?>_time" value="<?=h($row['visit'.$visit.'_time']??'')?>" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 outline-none focus:border-red-500 focus:ring-4 focus:ring-red-100"></label>
                                <label><span class="mb-1.5 block text-sm text-slate-600">รูปแบบ</span><select name="visit<?=$visit?>_mode" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 outline-none focus:border-red-500 focus:ring-4 focus:ring-red-100"><option value="">ยังไม่กำหนด</option><option value="Onsite" <?=($row['visit'.$visit.'_mode']??'')==='Onsite'?'selected':''?>>Onsite</option><option value="Online" <?=($row['visit'.$visit.'_mode']??'')==='Online'?'selected':''?>>Online</option></select></label>
                            </div>
                        </fieldset>
                        <?php endforeach; ?>
                    </div>
                    <div class="mt-5 flex flex-col-reverse gap-3 sm:flex-row sm:items-center sm:justify-between"><p class="text-xs text-slate-500"><i class="fa-solid fa-circle-info mr-1.5"></i>เจ้าหน้าที่สามารถบันทึกหรือแก้ไขข้อมูลแทนอาจารย์ได้</p><button class="inline-flex items-center justify-center rounded-xl bg-red-700 px-5 py-3 font-semibold text-white shadow-sm transition hover:bg-red-800 focus:outline-none focus:ring-4 focus:ring-red-200"><i class="fa-solid fa-floppy-disk mr-2"></i>บันทึกแผนนิเทศ</button></div>
                </div>
            </details>
        </form>
        <?php endforeach; ?>
        <?php if (!$rows): ?><div class="rounded-2xl border border-dashed border-slate-300 bg-white p-12 text-center"><i class="fa-regular fa-calendar-xmark text-4xl text-slate-300"></i><p class="mt-3 font-semibold text-slate-600">ยังไม่มีนักศึกษาส่งเอกสารฉบับลงชื่อ</p></div><?php endif; ?>
        </div>
    </section>
    <?php if ($totalItems > 0): ?><div class="mt-4 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm"><?php render_pagination($totalItems,$currentPage,$perPage,'page','supervision-list'); ?></div><?php endif; ?>
    </div>
</main></div>
</body></html>
