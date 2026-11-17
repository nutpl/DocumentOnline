<?php

session_start();

require_once "../../middleware/admin.php";
require_once "../../config/database.php";
require_once "../../helpers/pagination_helper.php";

$pageTitle = "จัดการกลุ่มเรียน";
$adminNavbarActions = [];
$search = trim($_GET['search'] ?? '');
$departmentId = isset($_GET['department_id']) && ctype_digit((string) $_GET['department_id'])
    ? (int) $_GET['department_id']
    : null;
$departments = $pdo->query('SELECT department_id, department_name FROM department ORDER BY department_name')->fetchAll(PDO::FETCH_ASSOC);

include __DIR__ . "/../layouts/admin_header.php";
include __DIR__ . "/../layouts/admin_sidebar.php";
include __DIR__ . "/../layouts/admin_navbar.php";


// ==================================================
// ดึงข้อมูลกลุ่มเรียน
// ==================================================

$sql = "
SELECT

    g.group_id,
    g.group_name,

    d.department_name,
    f.faculty_name,

    CONCAT(
        CASE
            WHEN COALESCE(p.prefix_short, p.prefix_name, '') IN ('นาย', 'นาง', 'นางสาว') THEN 'อาจารย์'
            ELSE COALESCE(p.prefix_short, p.prefix_name, '')
        END,
        CASE WHEN COALESCE(p.prefix_short, p.prefix_name, '') <> '' THEN ' ' ELSE '' END,
        e.firstname,
        ' ',
        e.lastname
    ) AS advisor_name,

    (
        SELECT COUNT(*)
        FROM student s
        WHERE s.group_id = g.group_id
    ) AS student_count

FROM group_class g

LEFT JOIN department d
ON g.department_id = d.department_id

LEFT JOIN faculty f
ON d.faculty_id = f.faculty_id

LEFT JOIN employee e
ON g.advisor_employee_id = e.employee_id

LEFT JOIN prefixes p
ON e.prefix_id = p.prefix_id

WHERE 1=1
";

$params = [];

if ($search != '') {

    $sql .= "

    AND (

        g.group_name LIKE ?

        OR d.department_name LIKE ?

        OR f.faculty_name LIKE ?

    )

    ";

    $keyword = "%{$search}%";

    $params = [
        $keyword,
        $keyword,
        $keyword
    ];
}

if ($departmentId !== null) {
    $sql .= ' AND g.department_id = ? ';
    $params[] = $departmentId;
}
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM ($sql) AS filtered_groups");
$countStmt->execute($params);
$totalGroups = (int) $countStmt->fetchColumn();
$perPage = selected_per_page(10);
$totalPages = max(1, (int) ceil($totalGroups / $perPage));
$page = max(1, (int) ($_GET['page'] ?? 1));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;
$sql .= "

ORDER BY

    f.faculty_name,
    d.department_name,
    g.group_name

LIMIT $perPage OFFSET $offset

";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);

?>

<!-- Header -->

<section class="mb-5 flex flex-wrap items-end justify-between gap-4 border-b border-slate-200 pb-5">
    <div><p class="text-sm font-bold text-red-700">ข้อมูลโครงสร้างการศึกษา</p><h1 class="mt-1 text-2xl font-black text-slate-900">จัดการกลุ่มเรียน</h1><p class="mt-1 text-sm text-slate-600">เพิ่ม แก้ไข และกำหนดอาจารย์ที่ปรึกษาให้กลุ่มเรียนได้ทุกสาขาวิชา</p></div>
    <div class="flex flex-wrap items-center gap-2"><span class="rounded-xl bg-slate-100 px-4 py-2 text-sm font-bold text-slate-700"><i class="fa-solid fa-people-group mr-1.5"></i><?= $totalGroups ?> กลุ่ม</span><a href="create.php" data-admin-edit-modal data-edit-modal-title="เพิ่มกลุ่มเรียน" class="inline-flex items-center gap-2 rounded-xl bg-red-700 px-4 py-2 text-sm font-bold text-white shadow-sm transition hover:bg-red-800"><i class="fa-solid fa-plus"></i>เพิ่มกลุ่มเรียน</a></div>
</section>
<div class="mb-5 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">

<form id="group-search" data-jquery-search-form data-search-results="#group-list"
    method="GET"
    class="grid gap-3 p-4 md:grid-cols-[220px_minmax(0,1fr)_auto_auto] md:items-end">

    <select name="department_id" class="h-11 w-full rounded-xl border border-slate-300 bg-white px-3 text-sm text-slate-800 focus:border-red-500 focus:outline-none focus:ring-4 focus:ring-red-100">
        <option value="">ทุกสาขาวิชา</option>
        <?php foreach ($departments as $department): ?>
            <option value="<?= (int) $department['department_id'] ?>" <?= $departmentId === (int) $department['department_id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($department['department_name']) ?>
            </option>
        <?php endforeach; ?>
    </select>

    <input
        type="text"
        name="search"
        value="<?= htmlspecialchars($search) ?>"
        placeholder="ค้นหากลุ่มเรียน / สาขา / คณะ"
        class="h-11 w-full rounded-xl border border-slate-300 px-3 text-sm text-slate-900 focus:border-red-500 focus:outline-none focus:ring-4 focus:ring-red-100">

    <button
        class="h-11 rounded-xl bg-red-700 px-5 text-sm font-bold text-white transition hover:bg-red-800">

        <i class="fa-solid fa-search mr-2"></i>

        ค้นหา

    </button>

    <?php if($search!='' || $departmentId !== null): ?>

    <a
        href="index.php"
        class="inline-flex h-11 items-center justify-center rounded-xl border border-slate-300 px-4 text-sm font-bold text-slate-600 transition hover:bg-slate-50">

        ล้าง

    </a>

    <?php endif; ?>

</form>

</div>

<?php if(isset($_SESSION['success'])): ?>
<div hidden data-success-alert data-success-title="ดำเนินการสำเร็จ" data-success-text="<?= htmlspecialchars($_SESSION['success'], ENT_QUOTES, 'UTF-8') ?>"></div>

<?php unset($_SESSION['success']); ?>

<?php endif; ?>


<?php if(isset($_SESSION['error'])): ?>

<div class="bg-red-100 border border-red-300 text-red-700 px-5 py-4 rounded-lg mb-6">

    <i class="fa-solid fa-circle-exclamation mr-2"></i>

    <?= $_SESSION['error']; ?>

</div>

<?php unset($_SESSION['error']); ?>

<?php endif; ?>

<!-- Table -->

<div id="group-list" data-jquery-search-result data-search-form="#group-search" class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm transition-opacity">

    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 px-5 py-4"><div><h2 class="font-black text-slate-900">รายการกลุ่มเรียน</h2><p class="mt-0.5 text-sm text-slate-500">แสดงข้อมูลที่ตรงกับเงื่อนไขการค้นหา</p></div><span class="rounded-full bg-slate-100 px-3 py-1.5 text-xs font-bold text-slate-700"><?= $totalGroups ?> รายการ</span></div>


    <div class="overflow-x-auto">

        <table class="min-w-[960px] w-full text-sm">

            <thead class="bg-gray-100">

                <tr>

                    <th class="text-center px-5 py-3 font-bold text-slate-600">

                        กลุ่มเรียน

                    </th>

                    <th class="text-center px-5 py-3 font-bold text-slate-600">

                        คณะ

                    </th>

                    <th class="text-center px-5 py-3 font-bold text-slate-600">

                        สาขา

                    </th>

                    <th class="text-center px-5 py-3 font-bold text-slate-600">

                        อาจารย์ที่ปรึกษา

                    </th>

                    <th class="text-center px-5 py-3 font-bold text-slate-600">

                        นักศึกษา

                    </th>

                    <th class="text-center px-5 py-3 font-bold text-slate-600">

                        จัดการ

                    </th>

                </tr>

            </thead>


            <tbody>

                <?php

                while (

                    $row =
                    $stmt->fetch(
                        PDO::FETCH_ASSOC
                    )

                ):

                ?>

                <tr

                    class="border-b

                           hover:bg-gray-50"

                >

                    <td class="px-5 py-3 text-center font-semibold text-slate-900 whitespace-nowrap">

                        <?= htmlspecialchars(

                            $row['group_name']

                        ) ?>

                    </td>


                    <td class="px-5 py-3 text-center text-slate-700">

                        <?= htmlspecialchars(

                            $row['faculty_name']

                            ??

                            '-'

                        ) ?>

                    </td>


                    <td class="px-5 py-3 text-center text-slate-700">

                        <?= htmlspecialchars(

                            $row['department_name']

                            ??

                            '-'

                        ) ?>

                    </td>


                    <td class="px-5 py-3 text-center">

                        <?php if (

                            $row['advisor_name']

                        ): ?>

                            <span class="font-medium text-emerald-700">

                                <i

                                    class="fa-solid

                                           fa-user-tie

                                           mr-1"

                                ></i>

                                <?= htmlspecialchars(

                                    $row['advisor_name']

                                ) ?>

                            </span>

                        <?php else: ?>

                            <span class="text-slate-400">

                                ยังไม่ได้กำหนด

                            </span>

                        <?php endif; ?>

                    </td>


                    <td class="px-5 py-3 text-center">

                        <span

                            class="inline-flex min-w-10 items-center justify-center rounded-full bg-slate-100 px-2.5 py-1 text-xs font-bold text-slate-700"

                        >

                            <?= $row['student_count'] ?>

                            คน

                        </span>

                    </td>

                    <td class="px-5 py-3 text-center">
                        <a href="edit.php?id=<?= (int) $row['group_id'] ?>" data-admin-edit-modal data-edit-modal-title="แก้ไขข้อมูลกลุ่มเรียน" class="inline-flex h-9 items-center justify-center gap-1.5 rounded-lg bg-blue-50 px-3 text-xs font-bold text-blue-700 transition hover:bg-blue-600 hover:text-white" title="แก้ไขข้อมูลกลุ่มเรียน"><i class="fa-solid fa-pen"></i>แก้ไข</a>
                    </td>

                </tr>


                <?php endwhile; ?>

                <?php if ($totalGroups === 0): ?>
                    <tr><td colspan="6" class="px-5 py-12 text-center text-slate-500"><i class="fa-solid fa-people-group mb-3 block text-3xl text-slate-300"></i>ไม่พบข้อมูลกลุ่มเรียนตามเงื่อนไขที่ค้นหา</td></tr>
                <?php endif; ?>

            </tbody>

        </table>

    </div>

    <?php render_pagination($totalGroups, $page, $perPage, 'page', 'group-list'); ?>

</div>

<?php

include __DIR__ . "/../layouts/admin_footer.php";

?>
<script src="/Project/assets/js/admin-edit-modal.js?v=20261103-2"></script>
