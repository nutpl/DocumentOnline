<?php

session_start();

require_once "../../middleware/admin.php";
require_once "../../config/database.php";
require_once "../../helpers/pagination_helper.php";

$pageTitle = "จัดการสาขาวิชา";
$adminNavbarActions = [['href' => 'create.php', 'label' => 'เพิ่มสาขาวิชา', 'icon' => 'fa-plus']];

function departmentDisplayName(?string $departmentName): string
{
    $name = preg_replace('/^\s*สาขาวิชา\s*/u', '', trim((string) $departmentName));
    return $name !== '' ? $name : '-';
}

// =========================
// Search
// =========================

$search = trim($_GET['search'] ?? '');

// =========================
// SQL
// =========================

$sql = "

SELECT

    d.department_id,
    d.department_abbreviation,
    d.department_name,

    f.faculty_name,

    COUNT(DISTINCT g.group_id) AS total_group

FROM department d

LEFT JOIN faculty f
ON d.faculty_id = f.faculty_id

LEFT JOIN group_class g
ON d.department_id = g.department_id

WHERE 1=1

";

$params = [];
$countSql = "
SELECT COUNT(DISTINCT d.department_id)
FROM department d
LEFT JOIN faculty f ON d.faculty_id = f.faculty_id
WHERE 1=1
";

if($search != ''){

    $sql .= "

    AND (

        d.department_abbreviation LIKE ?

        OR

        d.department_name LIKE ?

        OR

        f.faculty_name LIKE ?

    )

    ";

    $keyword = "%{$search}%";

    $params[] = $keyword;
    $params[] = $keyword;
    $params[] = $keyword;

    $countSql .= "
        AND (
            d.department_abbreviation LIKE ?
            OR d.department_name LIKE ?
            OR f.faculty_name LIKE ?
        )
    ";

}

$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$totalDepartments = (int) $countStmt->fetchColumn();
$perPage = selected_per_page(10);
$totalPages = max(1, (int) ceil($totalDepartments / $perPage));
$page = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT) ?: 1;
$page = max(1, min($page, $totalPages));
$offset = ($page - 1) * $perPage;

$sql .= "

GROUP BY

    d.department_id,
    d.department_abbreviation,
    d.department_name,
    f.faculty_name

ORDER BY d.department_id DESC

LIMIT $perPage OFFSET $offset

";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);

$departments = $stmt->fetchAll(PDO::FETCH_ASSOC);

include "../layouts/admin_header.php";
include "../layouts/admin_sidebar.php";
include "../layouts/admin_navbar.php";

?>

<div class="w-full p-4 sm:p-6">

    <!-- Header -->

    <section class="mb-5 flex flex-wrap items-end justify-between gap-4 border-b border-slate-200 pb-5">
        <div><p class="text-sm font-bold text-red-700">โครงสร้างองค์กร</p><h1 class="mt-1 text-2xl font-black text-slate-900">จัดการสาขาวิชา</h1><p class="mt-1 text-sm text-slate-600">ตรวจสอบ เพิ่ม แก้ไข และจัดการข้อมูลสาขาวิชาในระบบ</p></div>
        <span class="rounded-xl bg-slate-100 px-4 py-2 text-sm font-bold text-slate-700"><i class="fa-solid fa-sitemap mr-1.5"></i><?= $totalDepartments ?> สาขา</span>
    </section>

    <!-- Search -->

    <div class="mb-5 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">

        <form method="GET" id="department-search" data-jquery-search-form data-search-results="#department-list"
              class="grid gap-3 p-4 md:grid-cols-[minmax(0,1fr)_auto_auto] md:items-end">

            <input
                type="text"
                name="search"
                value="<?= htmlspecialchars($search) ?>"
                placeholder="ค้นหารหัสสาขา ชื่อสาขา หรือคณะ"
                class="h-11 w-full rounded-xl border border-slate-300 px-3 text-sm text-slate-900 focus:border-red-500 focus:outline-none focus:ring-4 focus:ring-red-100">

            <button
                class="h-11 rounded-xl bg-red-700 px-5 text-sm font-bold text-white transition hover:bg-red-800">

                <i class="fa-solid fa-search mr-2"></i>

                ค้นหา

            </button>

            <?php if($search!=''): ?>

                <a href="index.php"
                   class="inline-flex h-11 items-center justify-center rounded-xl border border-slate-300 px-4 text-sm font-bold text-slate-600 transition hover:bg-slate-50">

                    ล้าง

                </a>

            <?php endif; ?>

        </form>

    </div>

    <!-- Table -->

<div id="department-list" data-jquery-search-result data-search-form="#department-search" class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm transition-opacity">

    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 px-5 py-4"><div><h2 class="font-black text-slate-900">รายการสาขาวิชา</h2><p class="mt-0.5 text-sm text-slate-500"></p></div><span class="rounded-full bg-slate-100 px-3 py-1.5 text-xs font-bold text-slate-700"><?= $totalDepartments ?> รายการ</span></div>

    <div class="overflow-x-auto">

        <table class="min-w-[800px] w-full text-sm">

            <thead class="bg-gray-100">

                <tr>

                    <th class="px-5 py-3 text-center font-bold text-slate-600">ตัวย่อสาขา</th>

                    <th class="px-5 py-3 text-center font-bold text-slate-600">สาขาวิชา</th>

                    <th class="px-5 py-3 text-center font-bold text-slate-600">คณะ</th>

                    <th class="px-5 py-3 text-center font-bold text-slate-600">จำนวนกลุ่มเรียน</th>

                    <th class="px-5 py-3 text-center font-bold text-slate-600">จัดการ</th>

                </tr>

            </thead>

            <tbody>

            <?php if(empty($departments)): ?>

                <tr>

                    <td colspan="5"
                        class="px-6 py-10 text-center text-gray-500">

                        <i class="fa-solid fa-building text-4xl mb-3"></i>

                        <p>ไม่พบข้อมูลสาขาวิชา</p>

                    </td>

                </tr>

            <?php endif; ?>


            <?php foreach($departments as $department): ?>

                <tr class="border-b hover:bg-gray-50">

                    <!-- รหัส -->

                    <td class="px-5 py-3 text-center font-semibold text-slate-900 whitespace-nowrap">

                        <?= htmlspecialchars($department['department_abbreviation']) ?>

                    </td>

                    <!-- ชื่อ -->

                    <td class="px-5 py-3 text-center font-semibold text-slate-900">

                        <?= htmlspecialchars(departmentDisplayName($department['department_name'])) ?>

                    </td>

                    <!-- คณะ -->

                    <td class="px-5 py-3 text-center text-slate-700">

                        <?= htmlspecialchars($department['faculty_name']) ?>

                    </td>

                    <!-- จำนวนกลุ่ม -->

                    <td class="px-5 py-3 text-center">

                        <span class="inline-flex min-w-8 items-center justify-center rounded-full bg-slate-100 px-2.5 py-1 text-xs font-bold text-slate-700">

                            <?= $department['total_group'] ?>

                        </span>

                    </td>

                    <!-- จัดการ -->

                    <td class="px-5 py-3 text-center">

                        <div class="flex items-center justify-center gap-2">

                            <!-- แก้ไข -->

                            <a
                                href="edit.php?id=<?= $department['department_id'] ?>"
                                data-admin-edit-modal
                                data-edit-modal-title="แก้ไขข้อมูลสาขา"
                                class="inline-flex h-9 w-9 items-center justify-center rounded-lg bg-blue-50 text-blue-700 transition hover:bg-blue-600 hover:text-white focus:outline-none focus:ring-4 focus:ring-blue-100" title="แก้ไข">

                                <i class="fa-solid fa-pen text-sm"></i>

                            </a>

                            <!-- ลบ -->

                            <button
                                class="deleteBtn inline-flex h-9 w-9 items-center justify-center rounded-lg bg-red-50 text-red-700 transition hover:bg-red-600 hover:text-white focus:outline-none focus:ring-4 focus:ring-red-100"
                                data-id="<?= $department['department_id'] ?>"
                                data-confirm-name="<?= htmlspecialchars($department['department_name']) ?>"
                                title="ลบ">

                                <i class="fa-solid fa-trash-can text-sm"></i>

                            </button>

                        </div>

                    </td>

                </tr>

            <?php endforeach; ?>

            </tbody>

        </table>

    </div>

</div>

<?php render_pagination($totalDepartments, $page, $perPage, 'page', 'department-list'); ?>

<!-- Success Message -->

<?php if(isset($_SESSION['success'])): ?>
<div hidden data-success-alert data-success-title="ดำเนินการสำเร็จ" data-success-text="<?= htmlspecialchars($_SESSION['success'], ENT_QUOTES, 'UTF-8') ?>"></div>

<?php unset($_SESSION['success']); ?>

<?php endif; ?>


<!-- Error Message -->

<?php if(isset($_SESSION['error'])): ?>

<div class="bg-red-100 border border-red-300 text-red-700 px-5 py-4 rounded-lg mt-6">

    <i class="fa-solid fa-circle-exclamation mr-2"></i>

    <?= $_SESSION['error']; ?>

</div>

<?php unset($_SESSION['error']); ?>

<?php endif; ?>

</div> <!-- ปิด max-w-7xl -->


<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>

document.querySelectorAll(".deleteBtn").forEach(button=>{

    button.addEventListener("click",function(){

        const id=this.dataset.id;

        Swal.fire({

            title:"ยืนยันการลบ",

            text:"เมื่อลบแล้วจะไม่สามารถกู้คืนข้อมูลได้",

            icon:"warning",

            showCancelButton:true,

            confirmButtonColor:"#dc2626",

            cancelButtonColor:"#6b7280",

            confirmButtonText:"ลบ",

            cancelButtonText:"ยกเลิก"

        }).then((result)=>{

            if(result.isConfirmed){

                window.location="delete.php?id="+id;

            }

        });

    });

});

</script>

<script src="/Project/assets/js/admin-edit-modal.js?v=20261103-2"></script>

<?php

include "../layouts/admin_footer.php";

?>
