<?php
session_start();
require_once '../middleware/staff.php';
require_once '../config/database.php';
require_once '../helpers/csrf_helper.php';
require_once '../helpers/pagination_helper.php';

$staffStmt = $pdo->prepare('SELECT employee_id,department_id,firstname,lastname FROM employee WHERE user_id=? AND employee_type IN ("staff","teacher") LIMIT 1');
$staffStmt->execute([(int) $_SESSION['user_id']]);
$staff = $staffStmt->fetch(PDO::FETCH_ASSOC);
if (!$staff || !(int) $staff['department_id']) { http_response_code(403); renderPlainErrorAlert('ไม่พบสิทธิ์เจ้าหน้าที่สาขา'); }

$departmentStmt = $pdo->prepare('SELECT d.department_id,d.department_abbreviation,d.department_name,f.faculty_id,f.faculty_abbreviation,f.faculty_name FROM department d LEFT JOIN faculty f ON f.faculty_id=d.faculty_id WHERE d.department_id=? LIMIT 1');
$departmentStmt->execute([(int) $staff['department_id']]);
$department = $departmentStmt->fetch(PDO::FETCH_ASSOC);
if (!$department) { http_response_code(403); renderPlainErrorAlert('ไม่พบข้อมูลสาขาวิชา'); }
$departmentCode = rtrim(strtoupper(trim((string) $department['department_abbreviation'])), '.');

$error = '';
$editError = '';
$editGroupId = 0;
$editGroupNumber = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_or_abort();
    $action = (string) ($_POST['action'] ?? 'create');
    $groupNumber = trim((string) ($_POST['group_number'] ?? ''));
    try {
        if (!preg_match('/^\d{5}$/', $groupNumber)) throw new RuntimeException('รหัสกลุ่มเรียนต้องเป็นตัวเลข 5 หลัก');
        $groupName = $departmentCode.'.'.$groupNumber;
        $editGroupId = (int) ($_POST['group_id'] ?? 0);
        if ($action === 'edit' && $editGroupId < 1) throw new RuntimeException('ไม่พบกลุ่มเรียนที่ต้องการแก้ไข');
        if ($action !== 'create' && $action !== 'edit') throw new RuntimeException('คำขอไม่ถูกต้อง');
        if ($action === 'edit') {
            $ownerStmt = $pdo->prepare('SELECT COUNT(*) FROM group_class WHERE group_id=? AND department_id=?');
            $ownerStmt->execute([$editGroupId, $staff['department_id']]);
            if (!(int) $ownerStmt->fetchColumn()) throw new RuntimeException('ไม่พบกลุ่มเรียนในสาขาของคุณ');
        }
        $duplicateSql = 'SELECT COUNT(*) FROM group_class WHERE group_name=?';
        $duplicateParams = [$groupName];
        if ($action === 'edit') { $duplicateSql .= ' AND group_id<>?'; $duplicateParams[] = $editGroupId; }
        $duplicateStmt = $pdo->prepare($duplicateSql);
        $duplicateStmt->execute($duplicateParams);
        if ((int) $duplicateStmt->fetchColumn()) throw new RuntimeException('กลุ่มเรียน '.$groupName.' มีอยู่ในระบบแล้ว');
        if ($action === 'edit') {
            $updateStmt = $pdo->prepare('UPDATE group_class SET group_name=? WHERE group_id=? AND department_id=?');
            $updateStmt->execute([$groupName, $editGroupId, $staff['department_id']]);
            header('Location: group_classes.php?updated=1');
        } else {
            $insertStmt = $pdo->prepare('INSERT INTO group_class(group_name,department_id) VALUES (?,?)');
            $insertStmt->execute([$groupName, $staff['department_id']]);
            header('Location: group_classes.php?created=1');
        }
        exit;
    } catch (Throwable $exception) {
        if ($action === 'edit') { $editError = $exception->getMessage(); $editGroupNumber = $groupNumber; }
        else { $error = $exception->getMessage(); }
    }
}

$groupSearch = trim((string) ($_GET['search'] ?? ''));
$currentPage = max(1, (int) ($_GET['page'] ?? 1));
$groupsSql = 'SELECT g.group_id,g.group_name,d.department_name,f.faculty_name,COUNT(s.student_id) student_count,CONCAT(CASE WHEN COALESCE(p.prefix_short,p.prefix_name,"") IN ("นาย","นาง","นางสาว","") THEN "อาจารย์" ELSE COALESCE(p.prefix_short,p.prefix_name,"") END," ",COALESCE(e.firstname,"")," ",COALESCE(e.lastname,"")) advisor_name FROM group_class g LEFT JOIN student s ON s.group_id=g.group_id LEFT JOIN department d ON d.department_id=g.department_id LEFT JOIN faculty f ON f.faculty_id=d.faculty_id LEFT JOIN employee e ON e.employee_id=g.advisor_employee_id LEFT JOIN prefixes p ON p.prefix_id=e.prefix_id WHERE g.department_id=?';
$groupParams = [$staff['department_id']];
if ($groupSearch !== '') { $groupsSql .= ' AND g.group_name LIKE ?'; $groupParams[] = '%'.$groupSearch.'%'; }
$groupsSql .= ' GROUP BY g.group_id ORDER BY g.group_name';
$groupsStmt = $pdo->prepare($groupsSql);
$groupsStmt->execute($groupParams);
$allGroups = $groupsStmt->fetchAll(PDO::FETCH_ASSOC);
$perPage = selected_per_page(10);
$groupTotal = count($allGroups);
$totalPages = max(1, (int) ceil($groupTotal / $perPage));
$currentPage = min($currentPage, $totalPages);
$groups = array_slice($allGroups, ($currentPage - 1) * $perPage, $perPage);

$staff_name = trim($staff['firstname'].' '.$staff['lastname']);
$pageHeading = 'จัดการกลุ่มเรียน';
$document_count = 0;
$navbarActions = [];
function h($value): string { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); }
?>
<!doctype html>
<html lang="th">
<head>
    <link rel="icon" type="image/png" href="/Project/assets/images/fet-logo-transparent.png?v=20260815-3">
    <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
    <title>จัดการกลุ่มเรียน</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <link rel="stylesheet" href="/Project/assets/css/app-ui.css?v=20260902-2">
</head>
<body class="min-h-screen bg-slate-100 text-slate-900">
<?php include 'sidebar.php'; ?>
<div class="min-h-screen lg:ml-72">
<?php include 'navbar.php'; ?>
<main class="mx-auto max-w-6xl p-4 sm:p-6 lg:p-8">
    <?php if (isset($_GET['created'])): ?><div hidden data-success-alert data-success-title="เพิ่มกลุ่มเรียนสำเร็จ" data-success-text="เพิ่มกลุ่มเรียนในสาขาของคุณเรียบร้อยแล้ว" data-success-clean-param="created"></div><?php endif; ?>
    <?php if (isset($_GET['updated'])): ?><div hidden data-success-alert data-success-title="บันทึกสำเร็จ" data-success-text="แก้ไขข้อมูลกลุ่มเรียนเรียบร้อยแล้ว" data-success-clean-param="updated"></div><?php endif; ?>
    <?php if (!empty($_SESSION['group_error'])): ?><div class="mb-5 rounded-xl border border-red-200 bg-red-50 p-4 text-red-800"><?= h($_SESSION['group_error']) ?></div><?php unset($_SESSION['group_error']); endif; ?>
    <?php if ($error): ?><div class="mb-5 rounded-xl border border-red-200 bg-red-50 p-4 text-red-800"><?= h($error) ?></div><?php endif; ?>

    <section class="management-hero"><div class="management-hero__icon"><i class="fa-solid fa-users-rectangle"></i></div><div class="min-w-0 flex-1"><p class="management-hero__eyebrow">เฉพาะสาขาของคุณ</p><h1>จัดการกลุ่มเรียน</h1><p>สาขา<?= h($department['department_name']) ?> · รูปแบบชื่อกลุ่ม <?= h($departmentCode) ?>.xxxxx</p></div><button type="button" data-group-create-toggle class="inline-flex shrink-0 items-center gap-2 rounded-xl bg-red-700 px-4 py-2.5 text-sm font-bold text-white shadow-sm transition hover:bg-red-800"><i class="fa-solid fa-plus"></i>เพิ่มกลุ่มเรียน</button></section>

    <dialog id="group-create-dialog" class="w-[calc(100%_-_1.5rem)] max-w-3xl overflow-hidden rounded-2xl border border-slate-200 bg-white p-0 shadow-2xl backdrop:bg-slate-950/55">
        <form method="post" class="max-h-[calc(100dvh_-_2rem)] overflow-y-auto p-5 sm:p-6"><?= csrf_field() ?><div class="flex flex-col gap-3 border-b border-slate-100 pb-4 sm:flex-row sm:items-center sm:justify-between"><div class="flex items-center gap-3"><span class="inline-flex h-10 w-10 items-center justify-center rounded-xl bg-red-50 text-red-700"><i class="fa-solid fa-people-group"></i></span><div><h2 class="font-black">เพิ่มกลุ่มเรียนใหม่</h2><p class="text-sm text-slate-500">ตรวจสอบข้อมูลการศึกษา แล้วระบุรหัสกลุ่มเรียน 5 หลัก</p></div></div><button type="button" data-group-create-close class="self-start text-sm font-bold text-slate-500 hover:text-red-700"><i class="fa-solid fa-xmark mr-1"></i>ปิด</button></div><div class="mt-5 rounded-2xl border border-slate-200 bg-slate-50/70 p-4 sm:p-5"><div class="mb-4 flex items-start gap-2 text-sm text-slate-600"><i class="fa-solid fa-circle-info mt-0.5 text-red-600"></i><p>เจ้าหน้าที่เพิ่มกลุ่มเรียนได้เฉพาะสาขาที่รับผิดชอบ ระบบจึงกำหนดข้อมูลคณะ หลักสูตร และสาขาให้อัตโนมัติ</p></div><div class="grid gap-4 md:grid-cols-3"><label class="block text-sm font-bold text-slate-700">คณะ<select disabled class="mt-2 h-11 w-full cursor-not-allowed rounded-xl border border-slate-300 bg-white px-3 text-sm text-slate-700 disabled:opacity-100"><option><?= h(($department['faculty_abbreviation'] ? $department['faculty_abbreviation'].' — ' : '').$department['faculty_name']) ?></option></select></label><label class="block text-sm font-bold text-slate-700">หลักสูตร<select disabled class="mt-2 h-11 w-full cursor-not-allowed rounded-xl border border-slate-300 bg-white px-3 text-sm text-slate-700 disabled:opacity-100"><option>หลักสูตรระดับปริญญาตรี</option></select></label><label class="block text-sm font-bold text-slate-700">สาขาวิชา<select disabled class="mt-2 h-11 w-full cursor-not-allowed rounded-xl border border-slate-300 bg-white px-3 text-sm text-slate-700 disabled:opacity-100"><option><?= h($department['department_name']) ?></option></select></label></div><div class="mt-4 flex flex-col gap-3 rounded-xl border border-red-100 bg-white p-4 sm:flex-row sm:items-center sm:justify-between"><div><p class="text-xs font-bold uppercase tracking-wider text-red-700">ตัวย่อสาขา</p><p class="mt-1 text-xl font-black text-slate-900"><?= h($departmentCode) ?></p></div><div class="text-sm text-slate-600 sm:text-right"><p class="font-semibold text-slate-700">รูปแบบชื่อกลุ่มที่ระบบจะสร้าง</p><p class="mt-1 font-black text-red-700"><?= h($departmentCode) ?>.<span id="group-name-preview">xxxxx</span></p></div></div></div><div class="mt-5 max-w-md"><label class="block text-sm font-bold text-slate-700">รหัสกลุ่มเรียน 5 หลัก<div class="mt-2 flex"><span class="inline-flex items-center rounded-l-xl border border-r-0 border-slate-300 bg-slate-100 px-4 text-slate-600"><?= h($departmentCode) ?>.</span><input id="group-number" name="group_number" required maxlength="5" pattern="\d{5}" inputmode="numeric" value="<?= h($_POST['group_number'] ?? '') ?>" placeholder="เช่น 67231" class="min-w-0 flex-1 rounded-r-xl border border-slate-300 px-4 py-3 outline-none focus:border-red-500 focus:ring-4 focus:ring-red-100"></div></label><p class="mt-3 text-sm text-slate-500"><i class="fa-solid fa-circle-info mr-1.5 text-red-600"></i>กำหนดอาจารย์ที่ปรึกษาได้จากเมนู “กำหนดอาจารย์ที่ปรึกษา”</p></div><div class="mt-5 flex justify-end border-t border-slate-100 pt-4"><button class="rounded-xl bg-red-700 px-5 py-3 font-bold text-white transition hover:bg-red-800"><i class="fa-solid fa-floppy-disk mr-2"></i>บันทึกกลุ่มเรียน</button></div></form>
    </dialog>

    <dialog id="group-edit-dialog" class="w-[calc(100%_-_1.5rem)] max-w-xl overflow-hidden rounded-2xl border border-slate-200 bg-white p-0 shadow-2xl backdrop:bg-slate-950/55">
        <form method="post" class="p-5 sm:p-6">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="group_id" id="edit-group-id" value="<?= $editGroupId ?>">
            <div class="flex items-start justify-between gap-4 border-b border-slate-100 pb-4">
                <div class="flex items-center gap-3"><span class="inline-flex h-10 w-10 items-center justify-center rounded-xl bg-blue-50 text-blue-700"><i class="fa-solid fa-pen"></i></span><div><h2 class="font-black">แก้ไขกลุ่มเรียน</h2><p class="mt-0.5 text-sm text-slate-500">แก้ไขเฉพาะรหัสกลุ่มเรียน 5 หลัก</p></div></div>
                <button type="button" data-group-edit-close class="inline-flex h-9 w-9 items-center justify-center rounded-xl text-slate-500 transition hover:bg-slate-100 hover:text-red-700" aria-label="ปิด"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <?php if ($editError): ?><div class="mt-4 rounded-xl border border-red-200 bg-red-50 p-3 text-sm text-red-800"><?= h($editError) ?></div><?php endif; ?>
            <div class="mt-5 rounded-xl border border-slate-200 bg-slate-50 p-4"><p class="text-xs font-bold text-slate-500">สาขาวิชา</p><p class="mt-1 font-bold text-slate-800"><?= h($department['department_name']) ?> <span class="text-red-700">(<?= h($departmentCode) ?>)</span></p></div>
            <label class="mt-5 block text-sm font-bold text-slate-700">รหัสกลุ่มเรียน 5 หลัก<div class="mt-2 flex"><span class="inline-flex items-center rounded-l-xl border border-r-0 border-slate-300 bg-slate-100 px-4 text-slate-600"><?= h($departmentCode) ?>.</span><input id="edit-group-number" name="group_number" required maxlength="5" pattern="\d{5}" inputmode="numeric" value="<?= h($editGroupNumber) ?>" placeholder="เช่น 67231" class="min-w-0 flex-1 rounded-r-xl border border-slate-300 px-4 py-3 outline-none focus:border-red-500 focus:ring-4 focus:ring-red-100"></div></label>
            <div class="mt-6 flex justify-end gap-3 border-t border-slate-100 pt-5"><button type="button" data-group-edit-close class="rounded-xl border border-slate-300 px-5 py-3 text-sm font-bold text-slate-700 transition hover:bg-slate-50">ยกเลิก</button><button class="rounded-xl bg-red-700 px-5 py-3 text-sm font-bold text-white transition hover:bg-red-800"><i class="fa-solid fa-floppy-disk mr-2"></i>บันทึกการแก้ไข</button></div>
        </form>
    </dialog>

    <form method="get" class="mt-6 flex flex-col gap-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:flex-row"><label class="min-w-0 flex-1"><span class="mb-1 block text-xs font-bold text-slate-500">ค้นหากลุ่มเรียน</span><input type="search" name="search" value="<?= h($groupSearch) ?>" placeholder="เช่น <?= h($departmentCode) ?>.67231" class="w-full rounded-xl border border-slate-300 px-3 py-2.5"></label><button class="self-end rounded-xl bg-slate-900 px-5 py-2.5 font-semibold text-white"><i class="fa-solid fa-magnifying-glass mr-2"></i>ค้นหา</button><?php if ($groupSearch !== ''): ?><a href="group_classes.php" class="self-end rounded-xl border border-slate-300 px-5 py-2.5 font-semibold text-slate-600 hover:bg-slate-50">ล้าง</a><?php endif; ?></form>

    <section id="group-list" class="mt-5 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm"><div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 px-6 py-5"><div><h2 class="font-black">รายการกลุ่มเรียน</h2><p class="mt-1 text-sm text-slate-500">แสดงข้อมูลกลุ่มเรียนในสาขาของคุณ</p></div><span class="rounded-full bg-slate-100 px-3 py-1.5 text-sm font-bold text-slate-600"><?= $groupTotal ?> กลุ่มเรียน</span></div><div class="overflow-x-auto"><table class="w-full min-w-[960px] text-sm"><thead class="bg-slate-50 text-slate-600"><tr><th class="px-6 py-4 text-left">กลุ่มเรียน</th><th class="px-6 py-4 text-left">คณะ</th><th class="px-6 py-4 text-left">สาขา</th><th class="px-6 py-4 text-left">อาจารย์ที่ปรึกษา</th><th class="px-6 py-4 text-center">นักศึกษา</th><th class="px-6 py-4 text-center">จัดการ</th></tr></thead><tbody class="divide-y divide-slate-100"><?php foreach ($groups as $group): ?><tr class="transition hover:bg-red-50/40"><td class="px-6 py-4 font-bold"><?= h($group['group_name']) ?></td><td class="px-6 py-4 text-slate-600"><?= h($group['faculty_name'] ?: '-') ?></td><td class="px-6 py-4 text-slate-600"><?= h($group['department_name'] ?: '-') ?></td><td class="px-6 py-4"><?= trim((string) $group['advisor_name']) !== 'อาจารย์' ? '<span class="font-medium text-emerald-700"><i class="fa-solid fa-user-tie mr-1.5"></i>'.h($group['advisor_name']).'</span>' : '<span class="text-slate-400">ยังไม่กำหนด</span>' ?></td><td class="px-6 py-4 text-center"><span class="inline-flex min-w-9 items-center justify-center rounded-full bg-slate-100 px-3 py-1 font-semibold text-slate-700"><?= (int) $group['student_count'] ?></span></td><td class="px-6 py-4 text-center"><a href="group_class_edit.php?group_id=<?= (int) $group['group_id'] ?>&amp;page=<?= $currentPage ?>&amp;search=<?= urlencode($groupSearch) ?>" class="inline-flex h-9 w-9 items-center justify-center rounded-lg bg-blue-50 text-blue-700 transition hover:bg-blue-600 hover:text-white" title="แก้ไขกลุ่มเรียน"><i class="fa-solid fa-pen"></i></a></td></tr><?php endforeach; ?><?php if (!$groups): ?><tr><td colspan="6" class="px-6 py-14 text-center text-slate-400">ไม่พบกลุ่มเรียนตามเงื่อนไขที่ค้นหา</td></tr><?php endif; ?></tbody></table></div></section>
    <?php if ($groupTotal > 0): ?><div class="mt-4 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm"><?php render_pagination($groupTotal, $currentPage, $perPage, 'page', 'group-list'); ?></div><?php endif; ?>
</main>
</div>
<script>
const groupCreateDialog = document.getElementById('group-create-dialog');
document.querySelectorAll('[data-group-create-toggle]').forEach((button) => {
    button.addEventListener('click', () => {
        if (groupCreateDialog && !groupCreateDialog.open) groupCreateDialog.showModal();
    });
});
document.querySelectorAll('[data-group-create-close]').forEach((button) => button.addEventListener('click', () => groupCreateDialog?.close()));
<?php if ($error): ?>groupCreateDialog?.showModal();<?php endif; ?>

const groupNumber = document.getElementById('group-number');
const groupPreview = document.getElementById('group-name-preview');
groupPreview?.parentElement?.querySelector('p.font-semibold')?.remove();
if (groupNumber && groupPreview) {
    const updatePreview = () => { groupPreview.textContent = groupNumber.value || 'xxxxx'; };
    groupNumber.addEventListener('input', updatePreview);
    updatePreview();
}

const groupEditDialog = document.getElementById('group-edit-dialog');
const editGroupId = document.getElementById('edit-group-id');
const editGroupNumber = document.getElementById('edit-group-number');
document.querySelectorAll('a[href^="group_class_edit.php"]').forEach((link) => {
    link.addEventListener('click', (event) => {
        if (!groupEditDialog || !editGroupId || !editGroupNumber) return;
        event.preventDefault();
        const target = new URL(link.href);
        editGroupId.value = target.searchParams.get('group_id') || '';
        const groupName = link.closest('tr')?.querySelector('td')?.textContent?.trim() || '';
        const prefix = <?= json_encode($departmentCode, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
        editGroupNumber.value = groupName.replace(new RegExp('^' + prefix.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + '\\.+'), '');
        groupEditDialog.showModal();
        editGroupNumber.focus();
    });
});
document.querySelectorAll('[data-group-edit-close]').forEach((button) => button.addEventListener('click', () => groupEditDialog?.close()));
<?php if ($editError): ?>groupEditDialog?.showModal();<?php endif; ?>
</script>
</body>
</html>
