<?php

require_once __DIR__ . "/../../middleware/admin.php";
require_once __DIR__ . "/../../config/database.php";
require_once __DIR__ . "/../../helpers/csrf_helper.php";
require_once __DIR__ . "/../../helpers/pagination_helper.php";

$pageTitle = "จัดการเจ้าหน้าที่";
$adminNavbarActions = [
    ['href' => '#', 'target' => 'admin-personnel-add-modal', 'label' => 'เพิ่มเจ้าหน้าที่', 'icon' => 'fa-user-plus'],
];

$search = trim($_GET['search'] ?? '');

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

    WHERE e.employee_type = 'staff'
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

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM ($sql) AS filtered_staffs");
$countStmt->execute($params);
$totalStaffs = (int) $countStmt->fetchColumn();
$perPage = selected_per_page(5);
$totalPages = max(1, (int) ceil($totalStaffs / $perPage));
$page = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT) ?: 1;
$page = max(1, min($page, $totalPages));
$offset = ($page - 1) * $perPage;

$sql .= "
    ORDER BY e.employee_id DESC
    LIMIT $perPage OFFSET $offset
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);

$staffs = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
        <div><p class="text-sm font-bold text-red-700">ข้อมูลผู้ใช้</p><h1 class="mt-1 text-2xl font-black text-slate-900">จัดการเจ้าหน้าที่</h1><p class="mt-1 text-sm text-slate-600">ค้นหา ตรวจสอบ และจัดการข้อมูลเจ้าหน้าที่ในระบบ</p></div>
        <span class="rounded-xl bg-slate-100 px-4 py-2 text-sm font-bold text-slate-700"><i class="fa-solid fa-user-tie mr-1.5"></i><?= $totalStaffs ?> คน</span>
    </section>


    <!-- Search -->

    <div class="mb-5 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">

        <form method="GET" id="staff-search" data-jquery-search-form data-search-results="#staff-list" class="grid gap-3 p-4 md:grid-cols-[minmax(0,1fr)_auto_auto] md:items-end">

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

            <?php if ($search !== ''): ?>

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

    <div id="staff-list" data-jquery-search-result data-search-form="#staff-search" class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm transition-opacity">

        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 px-5 py-4">
            <div><h2 class="font-black text-slate-900">รายชื่อเจ้าหน้าที่</h2><p class="mt-0.5 text-sm text-slate-500"></p></div>
            <span class="rounded-full bg-slate-100 px-3 py-1.5 text-xs font-bold text-slate-700"><?= $totalStaffs ?> รายการ</span>
        </div>


        <div class="overflow-x-auto">

            <table class="min-w-[1120px] w-full text-sm">

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
                            สาขาวิชา
                        </th>

                        <th class="px-6 py-4 text-left">
                            อีเมล
                        </th>

                        <th class="px-4 py-3 text-left font-bold text-slate-600">

                            คณะ

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

                <?php if (empty($staffs)): ?>

                    <tr>

                        <td
                            colspan="8"
                            class="px-6 py-10 text-center text-gray-500"
                        >

                            <i
                                class="fa-solid fa-user-tie
                                       text-4xl mb-3"
                            ></i>

                            <p>
                                ไม่พบข้อมูลเจ้าหน้าที่
                            </p>

                        </td>

                    </tr>

                <?php endif; ?>


                <?php foreach (
                    $staffs as $staff
                ): ?>

                    <tr class="border-b hover:bg-gray-50">


                        <!-- Number -->

                        <!-- Employee Code -->

                        <td class="px-4 py-3 font-semibold text-slate-900 whitespace-nowrap">

                            <span
                                class="font-semibold text-gray-800"
                            >

                                <?= htmlspecialchars(
                                    $staff['employee_code']
                                ) ?>

                            </span>

                        </td>


                        <!-- Name -->

                        <td class="px-4 py-3 text-slate-700 whitespace-nowrap">

                            <?= htmlspecialchars($staff['prefix_short'] ?? '-') ?>

                        </td>

                        <td class="px-4 py-3 font-semibold text-slate-900 whitespace-nowrap">

                            <?= htmlspecialchars(
                                $staff['firstname']
                            ) ?>

                            <?= htmlspecialchars(
                                $staff['lastname']
                            ) ?>

                        </td>


                        <!-- Department -->

                        <td class="px-4 py-3 text-slate-700 whitespace-nowrap">

                            <?= htmlspecialchars(
                                $staff['department_name']
                                ?? '-'
                            ) ?>

                        </td>


                        <!-- Account -->

                        <td class="px-4 py-3 text-slate-700 whitespace-nowrap">

                            <div class="text-sm">

                                <?= htmlspecialchars(
                                    $staff['email']
                                    ?? '-'
                                ) ?>

                            </div>

                        </td>


                        <td class="px-4 py-3 text-slate-700 whitespace-nowrap">

                            <?= htmlspecialchars($staff['faculty_name'] ?? '-') ?>

                        </td>


                        <!-- Status -->

                        <td class="px-4 py-3">

                            <?php if (
                                $staff['status'] === 'active'
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
                                    href="../users/edit.php?id=<?= (int) $staff['user_id'] ?>&amp;from=staffs"
                                    data-admin-edit-modal
                                    data-edit-modal-title="แก้ไขบัญชีเจ้าหน้าที่"
                                    class="inline-flex h-9 w-9 items-center justify-center rounded-lg bg-blue-50 text-blue-700 transition hover:bg-blue-600 hover:text-white focus:outline-none focus:ring-4 focus:ring-blue-100"
                                    title="แก้ไข"
                                >

                                    <i class="fa-solid fa-pen text-sm"></i>

                                </a>

                                <form class="inline-flex" method="post" action="../employees/delete.php" onsubmit="return confirm('ยืนยันการลบข้อมูลเจ้าหน้าที่และบัญชีผู้ใช้นี้?');">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="employee_id" value="<?= (int) $staff['employee_id'] ?>">
                                    <input type="hidden" name="employee_type" value="staff">
                                    <button type="submit" class="inline-flex h-9 w-9 items-center justify-center rounded-lg bg-red-50 text-red-700 transition hover:bg-red-600 hover:text-white focus:outline-none focus:ring-4 focus:ring-red-100" title="ลบเจ้าหน้าที่">
                                        <i class="fa-solid fa-trash-can text-sm"></i>
                                    </button>
                                </form>

                                <button
                                    type="button"
                                    class="openStaffModalBtn order-first inline-flex h-9 w-9 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-600 transition hover:border-slate-800 hover:bg-slate-800 hover:text-white focus:outline-none focus:ring-4 focus:ring-slate-200"
                                    data-code="<?= htmlspecialchars($staff['employee_code']) ?>"
                                    data-name="<?= htmlspecialchars($staff['firstname'] . ' ' . $staff['lastname']) ?>"
                                    data-prefix="<?= htmlspecialchars($staff['prefix_short'] ?? '-') ?>"
                                    data-email="<?= htmlspecialchars($staff['email'] ?? '-') ?>"
                                    data-department="<?= htmlspecialchars($staff['department_name'] ?? '-') ?>"
                                    data-status="<?= ($staff['status'] ?? '') === 'active' ? 'เปิดใช้งาน' : 'ปิดใช้งาน' ?>"
                                    title="ดูรายละเอียด"
                                >
                                    <i class="fa-solid fa-eye text-sm"></i>
                                </button>

                            </div>

                        </td>

                    </tr>

                <?php endforeach; ?>

                </tbody>

            </table>

        </div>

        <?php render_pagination($totalStaffs, $page, $perPage, 'page', 'staff-list'); ?>

    </div>

</div>


<div id="staffViewOverlay" class="fixed inset-0 z-[70] hidden bg-slate-950/50 backdrop-blur-sm"></div>

<div id="staffViewModal" class="fixed inset-0 z-[80] hidden items-center justify-center p-4" role="dialog" aria-modal="true">
    <div class="w-full max-w-2xl overflow-hidden rounded-2xl bg-white shadow-2xl">
        <div class="flex items-center justify-between border-b p-4">
            <h3 class="text-lg font-semibold">รายละเอียดเจ้าหน้าที่</h3>
            <button type="button" class="staffViewClose inline-flex h-10 w-10 items-center justify-center rounded-xl border border-slate-200 text-slate-500 hover:bg-slate-100" aria-label="ปิดหน้าต่าง"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div class="grid grid-cols-1 gap-3 p-6 text-sm text-gray-700 md:grid-cols-2">
            <div><strong>รหัสบุคลากร:</strong> <span class="sv-code"></span></div>
            <div><strong>ชื่อ-นามสกุล:</strong> <span class="sv-name"></span></div>
            <div><strong>คำนำหน้า:</strong> <span class="sv-prefix"></span></div>
            <div><strong>อีเมล:</strong> <span class="sv-email"></span></div>
            <div><strong>สาขาวิชา:</strong> <span class="sv-department"></span></div>
            <div><strong>สถานะบัญชี:</strong> <span class="sv-status"></span></div>
        </div>
    </div>
</div>

<script>
document.querySelectorAll('.openStaffModalBtn').forEach((button) => {
    button.addEventListener('click', function () {
        const overlay = document.getElementById('staffViewOverlay');
        const modal = document.getElementById('staffViewModal');

        modal.querySelector('.sv-code').textContent = this.dataset.code || '-';
        modal.querySelector('.sv-name').textContent = this.dataset.name || '-';
        modal.querySelector('.sv-prefix').textContent = this.dataset.prefix || '-';
        modal.querySelector('.sv-email').textContent = this.dataset.email || '-';
        modal.querySelector('.sv-department').textContent = this.dataset.department || '-';
        modal.querySelector('.sv-status').textContent = this.dataset.status || '-';

        overlay.classList.remove('hidden');
        modal.classList.remove('hidden');
        modal.classList.add('flex');
    });
});

document.querySelectorAll('.staffViewClose, #staffViewOverlay').forEach((element) => {
    element.addEventListener('click', () => {
        document.getElementById('staffViewOverlay').classList.add('hidden');
        document.getElementById('staffViewModal').classList.add('hidden');
        document.getElementById('staffViewModal').classList.remove('flex');
    });
});
</script>

<script src="/Project/assets/js/admin-edit-modal.js?v=20261103-2"></script>

<?php

 $modalEmployeeType = 'staff';
 $modalEntityLabel = 'เจ้าหน้าที่';
 include __DIR__ . '/../layouts/personnel_add_modal.php';
include "../layouts/admin_footer.php";

?>
