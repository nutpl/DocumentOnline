<?php
session_start();
require_once '../middleware/teacher.php';
require_once '../config/database.php';
require_once '../helpers/functions.php';
require_once '../helpers/csrf_helper.php';
require_once '../helpers/internship_supervision_helper.php';
require_once '../helpers/pagination_helper.php';

$teacher = supervisionEmployee($pdo, (int)$_SESSION['user_id'], 'teacher');
if (!$teacher) {
    http_response_code(403);
    renderPlainErrorAlert('ไม่พบข้อมูลอาจารย์');
}
$teacher['effective_role'] = 'teacher';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_or_abort();
    try {
        saveSupervision($pdo, $teacher, $_POST);
        header('Location: supervision.php?company='.urlencode(trim((string)($_POST['return_company']??''))).'&profile_id='.(int)($_POST['profile_id'] ?? 0).'&search='.urlencode(trim((string)($_POST['return_search']??''))).'&page='.max(1,(int)($_POST['return_page']??1)).'&saved=1#supervision-form');
        exit;
    } catch (RuntimeException $e) {
        $saveError = $e->getMessage();
    }
}

$allRows = array_values(array_filter(
    supervisionRows($pdo, (int)$teacher['department_id']),
    static fn(array $row): bool => !empty($row['supervision_ready'])
));
$companyRows = [];
foreach ($allRows as $row) {
    $companyName = trim((string)($row['company_name'] ?? '')) ?: 'ไม่ระบุสถานประกอบการ';
    $companyRows[$companyName][] = $row;
}
uksort($companyRows, static fn(string $left, string $right): int => strnatcasecmp($left, $right));

$selectedCompany = trim((string)($_GET['company'] ?? ''));
if (!array_key_exists($selectedCompany, $companyRows)) {
    $selectedCompany = '';
}
$companyStudentRows = $selectedCompany !== '' ? $companyRows[$selectedCompany] : [];
$search = trim((string)($_GET['search'] ?? ''));
$filteredRows = array_values(array_filter($companyStudentRows, static function(array $row) use ($search): bool {
    if ($search === '') return true;
    return mb_stripos(implode(' ', [
        $row['student_number'] ?? '', $row['firstname'] ?? '', $row['lastname'] ?? '',
        $row['company_name'] ?? '', $row['group_name'] ?? ''
    ]), $search) !== false;
}));
$rowsPerPage = selected_per_page(10);
$totalFilteredRows = count($filteredRows);
$totalPages = max(1, (int)ceil($totalFilteredRows / $rowsPerPage));
$currentPage = min($totalPages, max(1, (int)($_GET['page'] ?? 1)));
$rows = array_slice($filteredRows, ($currentPage - 1) * $rowsPerPage, $rowsPerPage);
$selectedProfileId = (int)($_POST['profile_id'] ?? $_GET['profile_id'] ?? 0);
$selectedRow = null;
foreach ($companyStudentRows as $candidate) {
    if ((int)$candidate['internship_profile_id'] === $selectedProfileId) {
        $selectedRow = $candidate;
        break;
    }
}
$pending_document_count = 0;
$pageHeading = 'แผนนิเทศ';

function h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function teacherSupervisionDate($value): string
{
    if (!$value) return '-';
    $months = [1=>'ม.ค.',2=>'ก.พ.',3=>'มี.ค.',4=>'เม.ย.',5=>'พ.ค.',6=>'มิ.ย.',7=>'ก.ค.',8=>'ส.ค.',9=>'ก.ย.',10=>'ต.ค.',11=>'พ.ย.',12=>'ธ.ค.'];
    $date = new DateTimeImmutable((string)$value);
    return (int)$date->format('j').' '.$months[(int)$date->format('n')].' '.((int)$date->format('Y') + 543);
}
?>
<!doctype html>
<html lang="th">
<head>
    <link rel="icon" type="image/png" href="/Project/assets/images/fet-logo-transparent.png?v=20260815-3">

    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>แผนนิเทศ</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
</head>
<body class="min-h-screen bg-gray-100 text-gray-900">
<?php include 'sidebar.php'; ?>
<div class="min-h-screen lg:ml-72">
    <?php include 'navbar.php'; ?>
    <main class="p-4 sm:p-6 lg:p-8">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <h1 class="text-2xl font-bold text-gray-950 sm:text-3xl">แผนนิเทศนักศึกษาสหกิจ</h1>
                <p class="mt-1 text-sm text-gray-500 sm:text-base">เลือกสถานประกอบการ แล้วเลือกนักศึกษาที่ต้องกำหนดแผนนิเทศ</p>
            </div>
            <p class="text-sm font-semibold text-gray-500"><?= count($companyRows) ?> สถานประกอบการ · นักศึกษา <?= count($allRows) ?> คน</p>
        </div>

        <?php if (isset($_GET['saved'])): ?>
            <div hidden data-success-alert data-success-title="บันทึกสำเร็จ" data-success-text="บันทึกแผนนิเทศแล้ว" data-success-clean-param="saved"></div>
        <?php endif; ?>
        <?php if (!empty($saveError)): ?>
            <div class="mt-4 rounded-lg border border-red-200 bg-red-50 p-3 text-red-800"><?= h($saveError) ?></div>
        <?php endif; ?>

        <?php if (!$allRows): ?>
            <div class="mt-6 rounded-xl border bg-white p-8 text-center shadow-sm">
                <i class="fa-regular fa-calendar-xmark text-4xl text-gray-300"></i>
                <p class="mt-3 font-semibold">ยังไม่มีข้อมูลนักศึกษาสหกิจ</p>
                <p class="mt-1 text-sm text-gray-500">รายการจะแสดงเมื่อนักศึกษาส่งเอกสารสหกิจครบและพร้อมกำหนดแผนนิเทศแล้ว</p>
            </div>
        <?php endif; ?>

        <?php if ($allRows): ?>
        <section class="mt-6 rounded-xl border border-gray-200 bg-white p-4 shadow-sm sm:p-5">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 pb-4">
                <div><h2 class="text-lg font-bold text-gray-950">เลือกสถานประกอบการ</h2><p class="mt-1 text-sm text-gray-500">เลือกรายการหนึ่งเพื่อดูรายชื่อนักศึกษาที่ฝึกงาน ณ สถานที่นั้น</p></div>
                <?php if ($selectedCompany !== ''): ?><a href="supervision.php" class="inline-flex items-center rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-semibold text-gray-700 transition hover:border-red-300 hover:text-red-700"><i class="fa-solid fa-arrow-left mr-2"></i>เปลี่ยนสถานประกอบการ</a><?php endif; ?>
            </div>
            <div class="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
            <?php foreach ($companyRows as $companyName => $companyRowsForName): $isCurrentCompany = $companyName === $selectedCompany; ?>
                <a href="?company=<?= urlencode($companyName) ?>#supervision-list" class="group rounded-xl border p-4 transition <?= $isCurrentCompany ? 'border-red-300 bg-red-50 ring-2 ring-red-100' : 'border-gray-200 bg-white hover:-translate-y-0.5 hover:border-red-200 hover:shadow-md' ?>">
                    <div class="flex items-start gap-3"><span class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-lg <?= $isCurrentCompany ? 'bg-red-600 text-white' : 'bg-red-100 text-red-700' ?>"><i class="fa-solid fa-building"></i></span><div class="min-w-0 flex-1"><p class="line-clamp-2 font-bold text-gray-900"><?= h($companyName) ?></p><p class="mt-1 text-sm text-gray-500"><?= count($companyRowsForName) ?> นักศึกษา</p></div><i class="fa-solid fa-chevron-right mt-2 text-sm <?= $isCurrentCompany ? 'text-red-600' : 'text-gray-300 group-hover:text-red-500' ?>"></i></div>
                </a>
            <?php endforeach; ?>
            </div>
        </section>

        <?php if ($selectedCompany !== ''): ?>
        <div id="teacher-supervision-results" data-jquery-search-result data-search-form="#teacher-supervision-search">
        <form id="teacher-supervision-search" method="get" data-jquery-search-form data-search-results="#teacher-supervision-results" class="mt-6 rounded-xl border border-gray-200 bg-white p-4 shadow-sm sm:p-5">
            <input type="hidden" name="company" value="<?= h($selectedCompany) ?>">
            <div class="flex flex-col gap-3 lg:flex-row lg:items-end">
                <label for="supervision-search" class="min-w-0 flex-1">
                    <span class="mb-2 block text-sm font-bold text-gray-700"><i class="fa-solid fa-magnifying-glass mr-2 text-red-600"></i>ค้นหานักศึกษา</span>
                    <span class="relative block"><i class="fa-solid fa-user-graduate absolute left-4 top-1/2 -translate-y-1/2 text-gray-400"></i><input id="supervision-search" type="search" name="search" value="<?=h($search)?>" placeholder="ค้นหาจากรหัส ชื่อ หรือกลุ่มเรียน" class="w-full rounded-lg border border-gray-300 py-3 pl-11 pr-4 outline-none transition focus:border-red-500 focus:ring-4 focus:ring-red-100"></span>
                </label>
                <button class="inline-flex items-center justify-center rounded-lg bg-red-700 px-6 py-3 font-semibold text-white transition hover:bg-red-800"><i class="fa-solid fa-magnifying-glass mr-2"></i>ค้นหา</button>
            </div>
            <?php if (!$rows): ?><p class="mt-3 text-sm text-gray-500">ไม่พบนักศึกษาที่ตรงกับคำค้นหา</p><?php endif; ?>
        </form>

        <?php if ($rows): ?>
        <section id="supervision-list" class="mt-5 rounded-xl border border-gray-200 bg-white p-4 shadow-sm sm:p-5">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 pb-4">
                <div><h2 class="text-lg font-bold">นักศึกษาที่ฝึกงาน ณ <?= h($selectedCompany) ?></h2><p class="mt-1 text-sm text-gray-500">แสดงเฉพาะผู้ที่ส่งเอกสารสหกิจครบแล้ว</p></div>
                <span class="inline-flex items-center gap-2 rounded-full bg-red-50 px-3 py-1.5 text-sm font-bold text-red-700"><i class="fa-solid fa-list"></i><?= $totalFilteredRows ?> รายการ</span>
            </div>
            <div class="mt-4 grid gap-4 xl:grid-cols-2">
            <?php foreach($rows as $studentRow):
                $visit1Complete=!empty($studentRow['visit1_date'])&&!empty($studentRow['visit1_time'])&&!empty($studentRow['visit1_mode']);
                $visit2Complete=!empty($studentRow['visit2_date'])&&!empty($studentRow['visit2_time'])&&!empty($studentRow['visit2_mode']);
                $hasSupervisionData=!empty($studentRow['internship_supervision_id']);
                if($visit1Complete&&$visit2Complete){$statusLabel='กรอกข้อมูลครบแล้ว';$statusTone='status-badge--success';$statusIcon='fa-circle-check';}
                elseif($hasSupervisionData){$statusLabel='กรอกข้อมูลบางส่วน';$statusTone='status-badge--warning';$statusIcon='fa-clock';}
                else{$statusLabel='ยังไม่ได้กรอกข้อมูล';$statusTone='status-badge--danger';$statusIcon='fa-circle-exclamation';}
                $studentName=trim(($studentRow['prefix_short']??'').$studentRow['firstname'].' '.$studentRow['lastname']);
                $isSelected=$selectedProfileId===(int)$studentRow['internship_profile_id'];
                $supervisionReady = !empty($studentRow['supervision_ready']);
            ?>
                <article class="rounded-xl border p-4 transition hover:-translate-y-0.5 hover:shadow-md <?=$isSelected?'border-red-300 bg-red-50/60 ring-2 ring-red-100':'border-gray-200 bg-white'?>">
                    <div class="flex items-start gap-3">
                        <span class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-red-100 text-red-700"><i class="fa-solid fa-user-graduate"></i></span>
                        <div class="min-w-0 flex-1">
                            <p class="font-bold text-gray-900"><?=h($studentName)?></p>
                            <p class="mt-0.5 text-sm font-semibold text-red-700"><?=h($studentRow['student_number'])?></p>
                        </div>
                        <span class="status-badge <?=$statusTone?>"><i class="fa-solid <?=$statusIcon?>"></i><?=$statusLabel?></span>
                    </div>
                    <div class="mt-4 grid gap-2 rounded-lg bg-gray-50 p-3 text-sm text-gray-600 sm:grid-cols-2">
                        <p class="min-w-0"><i class="fa-solid fa-users-rectangle mr-2 w-4 text-gray-400"></i><?=h($studentRow['group_name'] ?: '-')?></p>
                        <p class="min-w-0 truncate" title="<?=h($studentRow['company_name'])?>"><i class="fa-solid fa-building mr-2 w-4 text-gray-400"></i><?=h($studentRow['company_name'] ?: '-')?></p>
                    </div>
                    <a href="?company=<?= urlencode($selectedCompany) ?>&search=<?=urlencode($search)?>&page=<?= $currentPage ?>&profile_id=<?=(int)$studentRow['internship_profile_id']?>#supervision-form" class="mt-4 inline-flex w-full items-center justify-center rounded-lg <?= $hasSupervisionData?'border border-gray-300 bg-white text-gray-700 hover:border-red-300 hover:text-red-700':'bg-red-700 text-white hover:bg-red-800' ?> px-4 py-2.5 text-sm font-bold transition">
                        <i class="fa-solid <?= $hasSupervisionData?'fa-pen-to-square':'fa-plus' ?> mr-2"></i><?= $hasSupervisionData?'แก้ไขแผนนิเทศ':'กำหนดแผนนิเทศ' ?>
                    </a>
                </article>
            <?php endforeach; ?>
            </div>
            <?php if ($totalFilteredRows > 0): ?><?php render_pagination($totalFilteredRows, $currentPage, $rowsPerPage, 'page', 'supervision-list'); ?><?php endif; ?>
        </section>
        <?php endif; ?>

        <div id="supervision-form" class="mt-6 scroll-mt-24">
        <?php if ($selectedRow): $row = $selectedRow; $supervisionReady = !empty($row['supervision_ready']); ?>
            <form method="post" class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
                <?= csrf_field() ?>
                <input type="hidden" name="profile_id" value="<?= (int)$row['internship_profile_id'] ?>">
                <input type="hidden" name="expected_lock_version" value="<?= (int)($row['lock_version'] ?? 0) ?>">
                <input type="hidden" name="return_company" value="<?= h($selectedCompany) ?>">
                <input type="hidden" name="return_search" value="<?=h($search)?>">
                <input type="hidden" name="return_page" value="<?= $currentPage ?>">
                <div class="flex flex-wrap items-start justify-between gap-4 border-b border-gray-200 px-5 py-5 sm:px-6">
                    <div class="flex items-center gap-3">
                        <span class="inline-flex h-11 w-11 items-center justify-center rounded-full bg-red-100 text-red-700"><i class="fa-solid fa-calendar-check"></i></span>
                        <div>
                            <p class="text-xs font-semibold text-red-700">กำหนดแผนนิเทศสำหรับ</p>
                            <h2 class="text-lg font-bold text-gray-950"><?= h(trim(($row['prefix_short'] ?? '').$row['firstname'].' '.$row['lastname'])) ?></h2>
                            <p class="text-sm text-gray-500"><?= h($row['student_number']) ?> · <?= h($row['group_name']) ?></p>
                        </div>
                    </div>
                    <div class="sm:text-right">
                        <p class="font-semibold text-gray-900"><?= h($row['company_name']) ?></p>
                        <p class="text-xs text-gray-500">ฝึกงาน <?= teacherSupervisionDate($row['start_date']) ?> – <?= teacherSupervisionDate($row['end_date']) ?></p>
                        <?php if (!empty($row['updated_by_display_name'])): ?>
                            <p class="mt-1 text-xs text-gray-500">แก้ไขล่าสุดโดย <?= h($row['updated_by_display_name']) ?> · <?= h($row['updated_at']) ?></p>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="grid gap-4 p-5 sm:p-6 xl:grid-cols-2">
                <?php if (!$supervisionReady): ?><div class="xl:col-span-2 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900"><p class="font-bold"><i class="fa-solid fa-lock mr-2"></i>ยังไม่สามารถกรอกแผนนิเทศได้</p><p class="mt-1">เจ้าหน้าที่ต้องบันทึกเอกสารสหกิจให้ครบในหน้าติดตามสถานะสหกิจก่อน: <?= h($row['supervision_missing_documents']) ?></p></div><?php endif; ?>
                <?php foreach ([1 => 'ครั้งที่ 1', 2 => 'ครั้งที่ 2'] as $visitNumber => $visitLabel): ?>
                    <section class="rounded-xl border border-gray-200 bg-gray-50 p-4">
                        <div class="mb-4 flex items-center gap-3">
                            <span class="inline-flex h-9 w-9 items-center justify-center rounded-lg bg-red-100 font-bold text-red-700"><?= $visitNumber ?></span>
                            <div><h3 class="font-bold">กำหนดการนิเทศ<?= $visitLabel ?></h3><p class="text-xs text-gray-500">ระบุวัน เวลา และรูปแบบการนิเทศ</p></div>
                        </div>
                        <div class="grid gap-4 sm:grid-cols-3">
                            <label><span class="mb-1.5 block text-sm font-semibold">วันที่นิเทศ</span><input type="date" name="visit<?= $visitNumber ?>_date" value="<?= h($row['visit'.$visitNumber.'_date'] ?? '') ?>" <?= !$supervisionReady?'disabled':'' ?> class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 disabled:bg-slate-100 disabled:text-slate-400 focus:border-red-500 focus:outline-none focus:ring-4 focus:ring-red-100"></label>
                            <label><span class="mb-1.5 block text-sm font-semibold">เวลา</span><input type="time" name="visit<?= $visitNumber ?>_time" value="<?= h($row['visit'.$visitNumber.'_time'] ?? '') ?>" <?= !$supervisionReady?'disabled':'' ?> class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 disabled:bg-slate-100 disabled:text-slate-400 focus:border-red-500 focus:outline-none focus:ring-4 focus:ring-red-100"></label>
                            <label><span class="mb-1.5 block text-sm font-semibold">รูปแบบ</span><select name="visit<?= $visitNumber ?>_mode" <?= !$supervisionReady?'disabled':'' ?> class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 disabled:bg-slate-100 disabled:text-slate-400 focus:border-red-500 focus:outline-none focus:ring-4 focus:ring-red-100"><option value="">ยังไม่กำหนด</option><option value="Onsite" <?= ($row['visit'.$visitNumber.'_mode'] ?? '') === 'Onsite' ? 'selected' : '' ?>>Onsite</option><option value="Online" <?= ($row['visit'.$visitNumber.'_mode'] ?? '') === 'Online' ? 'selected' : '' ?>>Online</option></select></label>
                        </div>
                    </section>
                <?php endforeach; ?>
                </div>
                <div class="flex justify-end border-t bg-gray-50 px-5 py-4 sm:px-6">
                    <button <?= !$supervisionReady?'disabled':'' ?> class="inline-flex items-center rounded-lg bg-red-700 px-5 py-2.5 font-semibold text-white transition hover:bg-red-800 disabled:cursor-not-allowed disabled:bg-slate-300">
                        <i class="fa-solid fa-floppy-disk mr-2"></i>บันทึกแผนนิเทศ
                    </button>
                </div>
            </form>
        <?php elseif ($allRows): ?>
            <div class="rounded-xl border border-dashed border-gray-300 bg-white p-8 text-center text-gray-500"><span class="mx-auto inline-flex h-12 w-12 items-center justify-center rounded-full bg-gray-100"><i class="fa-solid fa-hand-pointer text-xl text-gray-400"></i></span><p class="mt-3 font-semibold text-gray-700">เลือกนักศึกษาจากรายการด้านบน</p><p class="mt-1 text-sm">จากนั้นระบบจะแสดงแบบฟอร์มกำหนดแผนนิเทศที่ส่วนนี้</p></div>
        <?php endif; ?>
        </div>
        </div>
        <?php else: ?>
            <div class="mt-6 rounded-xl border border-dashed border-gray-300 bg-white p-8 text-center text-gray-500"><span class="mx-auto inline-flex h-12 w-12 items-center justify-center rounded-full bg-gray-100"><i class="fa-solid fa-building-circle-check text-xl text-gray-400"></i></span><p class="mt-3 font-semibold text-gray-700">เลือกสถานประกอบการเพื่อเริ่มกำหนดแผนนิเทศ</p><p class="mt-1 text-sm">ระบบจะแสดงรายชื่อนักศึกษาที่ฝึกงาน ณ สถานที่ที่เลือก</p></div>
        <?php endif; ?>
        <?php endif; ?>
    </main>
</div>
</body>
</html>
