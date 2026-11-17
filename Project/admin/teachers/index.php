<?php

require_once __DIR__ . "/../../middleware/admin.php";
require_once __DIR__ . "/../../config/database.php";
require_once __DIR__ . "/../../helpers/csrf_helper.php";
require_once __DIR__ . "/../../helpers/pagination_helper.php";

$pageTitle = "จัดการอาจารย์";
$adminNavbarActions = [
    ['href' => '#', 'target' => 'admin-personnel-add-modal', 'label' => 'เพิ่มอาจารย์', 'icon' => 'fa-user-plus'],
];

$search = trim($_GET['search'] ?? '');
$departmentId = isset($_GET['department_id']) && ctype_digit((string) $_GET['department_id'])
    ? (int) $_GET['department_id']
    : null;

$departmentStmt = $pdo->query("
    SELECT department_id, department_name
    FROM department
    ORDER BY department_name ASC
");
$departments = $departmentStmt->fetchAll(PDO::FETCH_ASSOC);

$sql = "
    SELECT
        e.employee_id,
        e.employee_code,
        e.firstname,
        e.lastname,
        e.user_id,
        e.department_id,
        e.employee_type,
        pr.prefix_short,

        u.username,
        u.email,
        u.status,

        d.department_name,
        f.faculty_name

    FROM employee e

    LEFT JOIN users u
        ON e.user_id = u.user_id

    LEFT JOIN department d
        ON e.department_id = d.department_id

    LEFT JOIN faculty f
        ON d.faculty_id = f.faculty_id

    LEFT JOIN prefixes pr
        ON e.prefix_id = pr.prefix_id

    WHERE e.employee_type = 'teacher'
";

$params = [];

if ($search !== '') {

    $sql .= "
        AND (
            e.employee_code LIKE ?
            OR e.firstname LIKE ?
            OR e.lastname LIKE ?
            OR pr.prefix_name LIKE ?
            OR pr.prefix_short LIKE ?
            OR u.email LIKE ?
            OR d.department_name LIKE ?
            OR f.faculty_name LIKE ?
        )
    ";

    $keyword = "%{$search}%";

    $params = [
        $keyword,
        $keyword,
        $keyword,
        $keyword,
        $keyword,
        $keyword,
        $keyword,
        $keyword
    ];
}

if ($departmentId !== null) {
    $sql .= " AND e.department_id = ? ";
    $params[] = $departmentId;
}

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM ($sql) AS filtered_teachers");
$countStmt->execute($params);
$totalTeachers = (int) $countStmt->fetchColumn();
$perPage = selected_per_page(5);
$totalPages = max(1, (int) ceil($totalTeachers / $perPage));
$page = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT) ?: 1;
$page = max(1, min($page, $totalPages));
$offset = ($page - 1) * $perPage;

$sql .= "
    ORDER BY e.employee_id DESC
    LIMIT $perPage OFFSET $offset
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);

$teachers = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ==================================
// ดึงข้อมูลกลุ่มที่อาจารย์เป็นที่ปรึกษา (ถ้ามี)
// และตำแหน่งของอาจารย์ ถ้ามีตารางเชื่อม
// ==================================

$employeeIds = array_column($teachers, 'employee_id');

$groupsMap = [];
$positionsMap = [];

function teacherDisplayPrefix(?string $prefix): string
{
    $prefix = trim((string) $prefix);
    return in_array($prefix, ['', 'นาย', 'นาง', 'นางสาว'], true) ? 'อาจารย์' : $prefix;
}

function teacherDisplayDepartment(?string $departmentName): string
{
    $name = preg_replace('/^\s*สาขาวิชา\s*/u', '', trim((string) $departmentName));
    return $name !== '' ? $name : '-';
}

if (!empty($employeeIds)) {

    // groups
    $in = implode(',', array_fill(0, count($employeeIds), '?'));

    $stmt = $pdo->prepare("SELECT advisor_employee_id, GROUP_CONCAT(group_name SEPARATOR ', ') AS advisor_groups, COUNT(*) AS cnt FROM group_class WHERE advisor_employee_id IN ($in) GROUP BY advisor_employee_id");
    $stmt->execute($employeeIds);

    while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $groupsMap[$r['advisor_employee_id']] = [
            'names' => $r['advisor_groups'],
            'count' => $r['cnt']
        ];
    }

    $stmt = $pdo->prepare("SELECT ep.employee_id, GROUP_CONCAT(p.position_name SEPARATOR ', ') AS positions FROM employee_position ep JOIN positions p ON ep.position_id = p.position_id WHERE ep.employee_id IN ($in) GROUP BY ep.employee_id");
    $stmt->execute($employeeIds);
    while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $positionsMap[$r['employee_id']] = $r['positions'];
    }

}

include "../layouts/admin_header.php";
include "../layouts/admin_sidebar.php";
include "../layouts/admin_navbar.php";

?>

<div class="w-full p-4 sm:p-6">

    <?php if (!empty($_SESSION['admin_success'])): ?>
        <div hidden data-success-alert data-success-title="ดำเนินการสำเร็จ" data-success-text="<?= htmlspecialchars($_SESSION['admin_success'], ENT_QUOTES, 'UTF-8') ?>"></div>
        <?php unset($_SESSION['admin_success']); ?>
    <?php endif; ?>

    <?php if (!empty($_SESSION['admin_error'])): ?>
        <div class="mb-6 rounded-lg bg-red-100 px-5 py-4 text-red-700">
            <?= htmlspecialchars($_SESSION['admin_error']) ?>
        </div>
        <?php unset($_SESSION['admin_error']); ?>
    <?php endif; ?>

    <!-- Header -->

    <section class="mb-5 flex flex-wrap items-end justify-between gap-4 border-b border-slate-200 pb-5">
        <div><p class="text-sm font-bold text-red-700">สิทธิ์ช่วยเหลือกรณีฉุกเฉิน</p><h1 class="mt-1 text-2xl font-black text-slate-900">จัดการอาจารย์</h1><p class="mt-1 text-sm text-slate-600">เพิ่ม แก้ไข ปิดใช้งาน หรือลบข้อมูลได้เมื่อเจ้าหน้าที่ต้องการความช่วยเหลือ</p></div>
        <span class="rounded-xl bg-slate-100 px-4 py-2 text-sm font-bold text-slate-700"><i class="fa-solid fa-chalkboard-user mr-1.5"></i><?= $totalTeachers ?> คน</span>
    </section>


    <!-- Search -->

    <div class="mb-5 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">

        <form method="GET" id="teacher-search" data-jquery-search-form data-search-results="#teacher-list" class="grid gap-3 p-4 md:grid-cols-[220px_minmax(0,1fr)_auto_auto] md:items-end">

            <select
                name="department_id"
                class="h-11 w-full rounded-xl border border-slate-300 bg-white px-3 text-sm text-slate-800 focus:border-red-500 focus:outline-none focus:ring-4 focus:ring-red-100"
            >
                <option value="">ทุกสาขาวิชา</option>
                <?php foreach ($departments as $department): ?>
                    <option
                        value="<?= (int) $department['department_id'] ?>"
                        <?= $departmentId === (int) $department['department_id'] ? 'selected' : '' ?>
                    >
                        <?= htmlspecialchars($department['department_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <input
                type="text"
                name="search"
                value="<?= htmlspecialchars($search) ?>"
                placeholder="ค้นหารหัส คำนำหน้า ชื่อ อีเมล สาขา หรือคณะ"

                class="h-11 w-full rounded-xl border border-slate-300 px-3 text-sm text-slate-900 focus:border-red-500 focus:outline-none focus:ring-4 focus:ring-red-100"
            >

            <button
                type="submit"

                class="h-11 rounded-xl bg-red-700 px-5 text-sm font-bold text-white transition hover:bg-red-800"
            >

                <i class="fa-solid fa-magnifying-glass mr-2"></i>

                ค้นหา

            </button>

            <?php if ($search !== '' || $departmentId !== null): ?>

                <a
                    href="index.php"

                    class="inline-flex h-11 items-center justify-center rounded-xl border border-slate-300 px-4 text-sm font-bold text-slate-600 transition hover:bg-slate-50"
                >

                    ล้าง

                </a>

            <?php endif; ?>

        </form>

    </div>


    <!-- Table -->

    <div id="teacher-list" data-jquery-search-result data-search-form="#teacher-search" class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm transition-opacity">

        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 px-5 py-4">
            <div><h2 class="font-black text-slate-900">รายชื่ออาจารย์</h2><p class="mt-0.5 text-sm text-slate-500"></p></div>
            <span class="rounded-full bg-slate-100 px-3 py-1.5 text-xs font-bold text-slate-700"><?= $totalTeachers ?> รายการ</span>
        </div>


        <div class="overflow-x-auto">

            <table class="min-w-[1260px] w-full text-sm">

                <thead class="bg-gray-100">

                    <tr>

                        <th class="px-4 py-3 text-left font-bold text-slate-600">
                            รหัสบุคลากร
                        </th>

                        <th class="px-4 py-3 text-left font-bold text-slate-600">
                            คำนำหน้า
                        </th>

                        <th class="px-4 py-3 text-left font-bold text-slate-600">
                            ชื่อ - นามสกุล
                        </th>

                        <th class="px-4 py-3 text-left font-bold text-slate-600">
                            อีเมล
                        </th>

                        <th class="px-4 py-3 text-left font-bold text-slate-600">
                            สาขาวิชา
                        </th>

                        <th class="px-4 py-3 text-left font-bold text-slate-600">
                            คณะ
                        </th>

                        <th class="px-4 py-3 text-center font-bold text-slate-600">
                            กลุ่มที่ปรึกษา
                        </th>

                        <th class="px-4 py-3 text-left font-bold text-slate-600">
                            สถานะ
                        </th>

                        <th class="px-4 py-3 text-center font-bold text-slate-600">
                            จัดการ
                        </th>

                    </tr>

                </thead>


                <tbody>

                <?php if (empty($teachers)): ?>

                    <tr>

                        <td
                            colspan="9"
                            class="px-6 py-10 text-center text-gray-500"
                        >

                            <i
                                class="fa-solid fa-chalkboard-user
                                       text-4xl mb-3"
                            ></i>

                            <p>
                                ไม่พบข้อมูลอาจารย์
                            </p>

                        </td>

                    </tr>

                <?php endif; ?>


                <?php foreach (
                    $teachers as $teacher
                ): ?>

                    <tr class="border-b hover:bg-gray-50">


                        <!-- Number -->

                        <!-- Employee Code -->

                        <td class="px-4 py-3 font-semibold text-slate-900 whitespace-nowrap">

                            <span class="font-semibold text-gray-800">

                                <?= htmlspecialchars(
                                    $teacher['employee_code']
                                ) ?>

                            </span>

                        </td>


                        <!-- Name -->

                        <td class="px-4 py-3 text-slate-700 whitespace-nowrap">

                            <?= htmlspecialchars(teacherDisplayPrefix($teacher['prefix_short'] ?? null)) ?>

                        </td>

                        <td class="px-4 py-3 font-semibold text-slate-900 whitespace-nowrap">

                            <?= htmlspecialchars(
                                $teacher['firstname']
                            ) ?>

                            <?= htmlspecialchars(
                                $teacher['lastname']
                            ) ?>

                        </td>


                        <!-- Department -->

                        <td class="px-4 py-3 text-slate-700 whitespace-nowrap">

                            <?= htmlspecialchars($teacher['email'] ?? '-') ?>

                        </td>

                        <td class="px-4 py-3 text-slate-700 whitespace-nowrap">

                            <?= htmlspecialchars(teacherDisplayDepartment($teacher['department_name'] ?? null)) ?>

                        </td>



                        <!-- Advisor count -->

                        <td class="px-4 py-3 text-slate-700 whitespace-nowrap">

                            <?= htmlspecialchars($teacher['faculty_name'] ?? '-') ?>

                        </td>

                        <td class="px-4 py-3 text-center">

                            <span class="inline-flex min-w-8 items-center justify-center rounded-full bg-slate-100 px-2.5 py-1 text-xs font-bold text-slate-700"><?= (int) ($groupsMap[$teacher['employee_id']]['count'] ?? 0) ?></span>

                        </td>

                        <!-- Account -->

                        <!-- Status -->

                        <td class="px-4 py-3">

                            <?php if (
                                $teacher['status'] === 'active'
                            ): ?>

                                <span
                                     class="status-badge status-badge--active"
                                >

                                    เปิดใช้งาน

                                </span>

                            <?php else: ?>

                                <span
                                     class="status-badge status-badge--inactive"
                                >

                                    ปิดใช้งาน

                                </span>

                            <?php endif; ?>

                        </td>


                        <!-- Action -->

                        <td class="px-4 py-3 text-center">

                            <div class="inline-flex items-center gap-2">

                                <a
                                    href="edit.php?id=<?= $teacher['employee_id'] ?>"
                                    data-admin-edit-modal
                                    data-edit-modal-title="แก้ไขข้อมูลอาจารย์"
                                    class="inline-flex h-9 w-9 items-center justify-center rounded-lg bg-blue-50 text-blue-700 transition hover:bg-blue-600 hover:text-white focus:outline-none focus:ring-4 focus:ring-blue-100"
                                    title="แก้ไข"
                                >

                                    <i class="fa-solid fa-pen text-sm"></i>

                                </a>

                                <form class="inline-flex" method="post" action="../employees/delete.php" onsubmit="return confirm('ยืนยันการลบข้อมูลอาจารย์และบัญชีผู้ใช้นี้?');">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="employee_id" value="<?= (int) $teacher['employee_id'] ?>">
                                    <input type="hidden" name="employee_type" value="teacher">
                                    <button type="submit" class="inline-flex h-9 w-9 items-center justify-center rounded-lg bg-red-50 text-red-700 transition hover:bg-red-600 hover:text-white focus:outline-none focus:ring-4 focus:ring-red-100" title="ลบอาจารย์">
                                        <i class="fa-solid fa-trash-can text-sm"></i>
                                    </button>
                                </form>

                                <button
                                    class="openTeacherModalBtn order-first inline-flex h-9 w-9 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-600 transition hover:border-slate-800 hover:bg-slate-800 hover:text-white focus:outline-none focus:ring-4 focus:ring-slate-200"
                                    data-id="<?= $teacher['employee_id'] ?>"
                                    data-code="<?= htmlspecialchars($teacher['employee_code']) ?>"
                                    data-name="<?= htmlspecialchars($teacher['firstname'] . ' ' . $teacher['lastname']) ?>"
                                    data-prefix="<?= htmlspecialchars(teacherDisplayPrefix($teacher['prefix_short'] ?? null)) ?>"
                                    data-email="<?= htmlspecialchars($teacher['email'] ?? '-') ?>"
                                    data-department="<?= htmlspecialchars(teacherDisplayDepartment($teacher['department_name'] ?? null)) ?>"
                                    data-status="<?= ($teacher['status'] ?? '') === 'active' ? 'เปิดใช้งาน' : 'ปิดใช้งาน' ?>"
                                    data-groups="<?= htmlspecialchars($groupsMap[$teacher['employee_id']]['names'] ?? '-') ?>"
                                    data-groups-count="<?= htmlspecialchars($groupsMap[$teacher['employee_id']]['count'] ?? 0) ?>"
                                    data-position="<?= htmlspecialchars($positionsMap[$teacher['employee_id']] ?? '-') ?>"
                                    title="ดูรายละเอียด">

                                    <i class="fa-solid fa-eye text-sm"></i>

                                </button>

                            </div>

                        </td>

                    </tr>

                <?php endforeach; ?>

                </tbody>

            </table>

        </div>

        <?php render_pagination($totalTeachers, $page, $perPage, 'page', 'teacher-list'); ?>

    </div>

</div>


<?php

 $modalEmployeeType = 'teacher';
 $modalEntityLabel = 'อาจารย์';
 include __DIR__ . '/../layouts/personnel_add_modal.php';
include "../layouts/admin_footer.php";
// Teacher view modal markup and JS
?>

<div id="teacherViewOverlay" class="fixed inset-0 z-[70] hidden bg-slate-950/50 backdrop-blur-sm"></div>

<div id="teacherViewModal" class="fixed inset-0 z-[80] hidden items-center justify-center p-4" role="dialog" aria-modal="true">

    <div class="w-full max-w-2xl overflow-hidden rounded-2xl bg-white shadow-2xl">

        <div class="flex items-center justify-between p-4 border-b">

            <h3 class="text-lg font-semibold">รายละเอียดอาจารย์</h3>

            <button id="teacherViewClose" class="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-slate-200 text-slate-500 hover:bg-slate-100" aria-label="ปิดหน้าต่าง"><i class="fa-solid fa-xmark"></i></button>

        </div>

        <div class="p-6 text-sm text-gray-700">

            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">

                <div><strong>รหัสบุคลากร:</strong> <span class="tv-code"></span></div>

                <div><strong>ชื่อ-นามสกุล:</strong> <span class="tv-name"></span></div>

                <div><strong>คำนำหน้า:</strong> <span class="tv-prefix"></span></div>

                <div><strong>อีเมล:</strong> <span class="tv-email"></span></div>

                <div><strong>สาขา:</strong> <span class="tv-department"></span></div>

                <div><strong>ตำแหน่ง:</strong> <span class="tv-position"></span></div>

                <div><strong>จำนวนที่ปรึกษา:</strong> <span class="tv-gcount"></span></div>

                <div class="md:col-span-2"><strong>ที่ปรึกษากลุ่ม:</strong> <span class="tv-groups"></span></div>

                <div class="md:col-span-2"><strong>สถานะบัญชี:</strong> <span class="tv-status"></span></div>

            </div>

        </div>

    </div>

</div>

<script>
document.querySelectorAll('.openTeacherModalBtn').forEach(btn => {
    btn.addEventListener('click', function(){
        const overlay = document.getElementById('teacherViewOverlay');
        const modal = document.getElementById('teacherViewModal');

        modal.querySelector('.tv-code').textContent = this.dataset.code || '-';
        modal.querySelector('.tv-name').textContent = this.dataset.name || '-';
        modal.querySelector('.tv-prefix').textContent = this.dataset.prefix || '-';
        modal.querySelector('.tv-email').textContent = this.dataset.email || '-';
        modal.querySelector('.tv-department').textContent = this.dataset.department || '-';
        modal.querySelector('.tv-position').textContent = this.dataset.position || '-';
        modal.querySelector('.tv-gcount').textContent = this.dataset.groupsCount || '0';
        modal.querySelector('.tv-groups').textContent = this.dataset.groups || '-';
        modal.querySelector('.tv-status').textContent = this.dataset.status || '-';

        overlay.classList.remove('hidden');
        modal.classList.remove('hidden');
        modal.classList.add('flex');
    });
});

document.addEventListener('click', function(e){
    const overlay = document.getElementById('teacherViewOverlay');
    const modal = document.getElementById('teacherViewModal');
    if(!overlay || !modal) return;
    if (e.target.closest('#teacherViewClose') || e.target.matches('#teacherViewOverlay')){
        modal.classList.add('hidden');
        modal.classList.remove('flex');
        overlay.classList.add('hidden');
    }
});
</script>

<script src="/Project/assets/js/admin-edit-modal.js?v=20261103-2"></script>

<?php

?>
