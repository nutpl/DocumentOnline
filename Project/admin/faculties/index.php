<?php

session_start();

require_once "../../middleware/admin.php";
require_once "../../config/database.php";
require_once "../../helpers/pagination_helper.php";

$pageTitle="จัดการคณะ";
$adminNavbarActions = [['href' => 'create.php', 'label' => 'เพิ่มคณะ', 'icon' => 'fa-plus']];

include "../layouts/admin_header.php";
include "../layouts/admin_sidebar.php";
include "../layouts/admin_navbar.php";
$search = trim($_GET['search'] ?? '');
$sql = "

SELECT

f.faculty_id,

f.faculty_abbreviation,

f.faculty_name,

COUNT(d.department_id) total_department

FROM faculty f

LEFT JOIN department d

ON f.faculty_id=d.faculty_id

WHERE 1=1

";

$params=[];
if($search!=''){

$sql.="

AND (f.faculty_abbreviation LIKE ? OR f.faculty_name LIKE ?)

";

$params[]="%$search%";
$params[]="%$search%";

}

$sql.="

GROUP BY f.faculty_id, f.faculty_abbreviation, f.faculty_name

";

$countSql = 'SELECT COUNT(*) FROM faculty f WHERE 1=1';
$countParams = [];
if ($search !== '') {
    $countSql .= ' AND (f.faculty_abbreviation LIKE ? OR f.faculty_name LIKE ?)';
    $countParams = ["%$search%", "%$search%"];
}
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($countParams);
$totalFaculties = (int) $countStmt->fetchColumn();
$perPage = selected_per_page(10);
$page = max(1, (int) ($_GET['page'] ?? 1));
$totalPages = max(1, (int) ceil($totalFaculties / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;
$sql .= " ORDER BY f.faculty_name ASC LIMIT $perPage OFFSET $offset";

$stmt=$pdo->prepare($sql);

$stmt->execute($params);

?>

<!-- Header -->

<section class="mb-5 flex flex-wrap items-end justify-between gap-4 border-b border-slate-200 pb-5">
    <div><h1 class="mt-1 text-2xl font-black text-slate-900">จัดการคณะ</h1><p class="mt-1 text-sm text-slate-600">ตรวจสอบ เพิ่ม แก้ไข และจัดการข้อมูลคณะในระบบ</p></div>
    <span class="rounded-xl bg-slate-100 px-4 py-2 text-sm font-bold text-slate-700"><i class="fa-solid fa-building-columns mr-1.5"></i><?= $totalFaculties ?> คณะ</span>
</section>

<div class="mb-5 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">

<form method="GET" id="faculty-search" data-jquery-search-form data-search-results="#faculty-list"
      class="grid gap-3 p-4 md:grid-cols-[minmax(0,1fr)_auto_auto] md:items-end">

<input

type="text"

name="search"

value="<?= htmlspecialchars($search) ?>"

placeholder="ค้นหารหัสหรือชื่อคณะ"

class="h-11 w-full rounded-xl border border-slate-300 px-3 text-sm text-slate-900 focus:border-red-500 focus:outline-none focus:ring-4 focus:ring-red-100">

<button

class="h-11 rounded-xl bg-red-700 px-5 text-sm font-bold text-white transition hover:bg-red-800">

<i class="fa-solid fa-search mr-2"></i>

ค้นหา

</button>

<?php if ($search !== ''): ?><a href="index.php" class="inline-flex h-11 items-center justify-center rounded-xl border border-slate-300 px-4 text-sm font-bold text-slate-600 transition hover:bg-slate-50">ล้าง</a><?php endif; ?>

</form>

</div>

<div id="faculty-list" data-jquery-search-result data-search-form="#faculty-search" class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm transition-opacity">

<div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 px-5 py-4"><div><h2 class="font-black text-slate-900">รายการคณะ</h2><p class="mt-0.5 text-sm text-slate-500">แสดงข้อมูลที่ตรงกับเงื่อนไขการค้นหา</p></div><span class="rounded-full bg-slate-100 px-3 py-1.5 text-xs font-bold text-slate-700"><?= $totalFaculties ?> รายการ</span></div>

<div class="overflow-x-auto">

<table class="min-w-[760px] w-full text-sm">

<thead class="bg-gray-100">

<tr>

<th class="px-5 py-3 text-center font-bold text-slate-600">
ตัวย่อคณะ

</th>

<th class="px-5 py-3 text-center font-bold text-slate-600">

ชื่อคณะ

</th>

<th class="px-5 py-3 text-center font-bold text-slate-600">

จำนวนสาขา

</th>

<th class="px-5 py-3 text-center font-bold text-slate-600">

จัดการ

</th>

</tr>

</thead>

<tbody>

<?php

while($row=$stmt->fetch(PDO::FETCH_ASSOC)):

$facultyDisplayName = trim((string) preg_replace('/^\s*คณะ\s*/u', '', (string) $row['faculty_name']));

?>

<tr class="border-b hover:bg-gray-50">

<td class="px-5 py-3 text-center font-semibold text-slate-900 whitespace-nowrap">

<?= htmlspecialchars($row['faculty_abbreviation']) ?>

</td>

<td class="px-5 py-3 text-center font-semibold text-slate-900">

<?= htmlspecialchars($facultyDisplayName) ?>

</td>

<td class="px-5 py-3 text-center">

<span class="inline-flex min-w-8 items-center justify-center rounded-full bg-slate-100 px-2.5 py-1 text-xs font-bold text-slate-700"><?= $row['total_department'] ?></span>

</td>

<td class="px-5 py-3 text-center">

<div class="flex items-center justify-center gap-2">

<a

href="edit.php?id=<?= $row['faculty_id'] ?>"
data-admin-edit-modal
data-edit-modal-title="แก้ไขข้อมูลคณะ"

class="inline-flex h-9 w-9 items-center justify-center rounded-lg bg-blue-50 text-blue-700 transition hover:bg-blue-600 hover:text-white focus:outline-none focus:ring-4 focus:ring-blue-100" title="แก้ไข">

<i class="fa-solid fa-pen text-sm"></i>

</a>

 <!-- ลบ -->
    <button
        class="deleteBtn inline-flex h-9 w-9 items-center justify-center rounded-lg bg-red-50 text-red-700 transition hover:bg-red-600 hover:text-white focus:outline-none focus:ring-4 focus:ring-red-100"
        data-id="<?= $row['faculty_id'] ?>"
        data-confirm-name="<?= htmlspecialchars($facultyDisplayName) ?>"
        title="ลบ">

        <i class="fa-solid fa-trash-can text-sm"></i>

    </button>

</div>

</td>

</tr>

<?php endwhile; ?>

</tbody>

</table>

</div>

<?php render_pagination($totalFaculties, $page, $perPage, 'page', 'faculty-list'); ?>

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

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>

document.querySelectorAll(".deleteBtn").forEach(button=>{

    button.addEventListener("click",function(){

        const id=this.dataset.id;

        Swal.fire({

            title:"ยืนยันการลบ",

            text:"คุณต้องการลบคณะนี้ใช่หรือไม่",

            icon:"warning",

            showCancelButton:true,

            confirmButtonText:"ลบ",

            cancelButtonText:"ยกเลิก",

            confirmButtonColor:"#dc2626"

        }).then((result)=>{

            if(result.isConfirmed){

                window.location="delete.php?id="+id;

            }

        });

    });

});

</script>

<script src="/Project/assets/js/admin-edit-modal.js?v=20261103-2"></script>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>

document.querySelectorAll(".deleteBtn").forEach(button=>{

button.addEventListener("click",function(){

const id=this.dataset.id;

Swal.fire({

title:"ยืนยันการลบ",

text:"คุณต้องการลบคณะนี้ใช่หรือไม่",

icon:"warning",

showCancelButton:true,

confirmButtonText:"ลบ",

cancelButtonText:"ยกเลิก",

confirmButtonColor:"#dc2626"

}).then((result)=>{

if(result.isConfirmed){

window.location="delete.php?id="+id;

}

});

});

});

</script>

<?php

include "../layouts/admin_footer.php";

?>
