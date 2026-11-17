<?php

session_start();

// The delete action uses a CSRF-protected POST request. Avoid restoring an
// older page from the browser cache that still contained the legacy GET link.
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

require_once "../../middleware/admin.php";
require_once "../../config/database.php";
require_once "../../helpers/pagination_helper.php";
require_once "../../helpers/csrf_helper.php";

$pageTitle = "จัดการนักศึกษา";
$adminNavbarActions = [];

include "../layouts/admin_header.php";
include "../layouts/admin_sidebar.php";
include "../layouts/admin_navbar.php";


// ==================================================
// รับคำค้นหา
// ==================================================

$search = trim(
    $_GET['search'] ?? ''
);

$departmentId = isset($_GET['department_id']) && ctype_digit((string) $_GET['department_id'])
    ? (int) $_GET['department_id']
    : null;
$groupId = isset($_GET['group_id']) && ctype_digit((string) $_GET['group_id'])
    ? (int) $_GET['group_id']
    : null;

if ($departmentId === null) {
    $groupId = null;
}

$departments = $pdo->query("
    SELECT department_id, department_name
    FROM department
    ORDER BY department_name ASC
")->fetchAll(PDO::FETCH_ASSOC);

$groups = $pdo->query("
    SELECT group_id, department_id, group_name
    FROM group_class
    ORDER BY group_name ASC
")->fetchAll(PDO::FETCH_ASSOC);


// ==================================================
// SQL
// ==================================================

$sql = "

    SELECT

        s.student_id,

        s.student_number,

        s.firstname,

        s.lastname,

        COALESCE(p.prefix_short, p.prefix_name, '-') AS prefix_name,

        u.email,

        g.group_name,

        d.department_name,

        f.faculty_name,

        CONCAT(

            e.firstname,

            ' ',

            e.lastname

        ) AS advisor_name,

        u.user_id,

        u.status

    FROM student s


    LEFT JOIN group_class g

        ON s.group_id = g.group_id


    LEFT JOIN department d

        ON g.department_id =
           d.department_id


    LEFT JOIN faculty f

        ON d.faculty_id =
           f.faculty_id


    LEFT JOIN employee e

        ON g.advisor_employee_id =
           e.employee_id

    LEFT JOIN prefixes p

        ON s.prefix_id = p.prefix_id


    LEFT JOIN users u

        ON s.user_id =
           u.user_id

    WHERE 1 = 1

";


// ==================================================
// ค้นหา
// ==================================================

$params = [];


if ($search !== '') {

    $sql .= "

        AND (

    s.student_number LIKE ?

OR  s.firstname LIKE ?

OR  s.lastname LIKE ?

OR  p.prefix_name LIKE ?

OR  p.prefix_short LIKE ?

OR  u.email LIKE ?

OR  g.group_name LIKE ?

OR  d.department_name LIKE ?

OR  f.faculty_name LIKE ?

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
    $keyword,
    $keyword

];

}

if ($departmentId !== null) {

    $sql .= " AND g.department_id = ? ";
    $params[] = $departmentId;

}

if ($groupId !== null) {

    $sql .= " AND s.group_id = ? ";
    $params[] = $groupId;

}


$countStmt = $pdo->prepare("SELECT COUNT(*) FROM ($sql) AS filtered_students");
$countStmt->execute($params);
$totalStudents = (int) $countStmt->fetchColumn();
$perPage = selected_per_page(5);
$totalPages = max(1, (int) ceil($totalStudents / $perPage));
$page = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT) ?: 1;
$page = max(1, min($page, $totalPages));
$offset = ($page - 1) * $perPage;

$sql .= "

    ORDER BY

        s.student_number ASC

    LIMIT $perPage OFFSET $offset

";


$stmt = $pdo->prepare($sql);

$stmt->execute($params);

?>

<!-- Header -->

<section class="mb-5 flex flex-wrap items-end justify-between gap-4 border-b border-slate-200 pb-5">
    <div>
        <p class="text-sm font-bold text-red-700">ข้อมูลผู้ใช้</p>
        <h1 class="mt-1 text-2xl font-black text-slate-900">จัดการนักศึกษา</h1>
        <p class="mt-1 text-sm text-slate-600">ค้นหา ตรวจสอบ และจัดการข้อมูลนักศึกษาในระบบ</p>
    </div>
    <span class="rounded-xl bg-slate-100 px-4 py-2 text-sm font-bold text-slate-700"><i class="fa-solid fa-user-graduate mr-1.5"></i><?= $totalStudents ?> คน</span>
</section>

<!-- Search -->

<div class="mb-5 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">

    <form id="student-admin-search" data-jquery-search-form data-search-results="#student-list"

        method="GET"

        class="grid gap-3 p-4 md:grid-cols-[220px_220px_minmax(0,1fr)_auto_auto] md:items-end"

    >

        <select
            id="department_id"
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

        <select
            id="group_id"
            name="group_id"
            class="h-11 w-full rounded-xl border border-slate-300 bg-white px-3 text-sm text-slate-800 focus:border-red-500 focus:outline-none focus:ring-4 focus:ring-red-100 disabled:cursor-not-allowed disabled:bg-slate-100"
            <?= $departmentId === null ? 'disabled' : '' ?>
        >
            <option value="">ทุกกลุ่มเรียน</option>
        </select>

        <input

            type="text"

            name="search"

            value="<?= htmlspecialchars($search) ?>"

            placeholder="ค้นหารหัส คำนำหน้า ชื่อ อีเมล กลุ่มเรียน สาขา หรือคณะ"

            class="h-11 w-full rounded-xl border border-slate-300 px-3 text-sm text-slate-900 focus:border-red-500 focus:outline-none focus:ring-4 focus:ring-red-100"

        >

        <button

            type="submit"

            class="h-11 rounded-xl bg-red-700 px-5 text-sm font-bold text-white transition hover:bg-red-800"

        >

            <i class="fa-solid fa-search mr-2"></i>

            ค้นหา

        </button>


        <?php if ($search !== '' || $departmentId !== null || $groupId !== null): ?>

            <a

                href="index.php"

                class="inline-flex h-11 items-center justify-center rounded-xl border border-slate-300 px-4 text-sm font-bold text-slate-600 transition hover:bg-slate-50"

            >

                ล้างค้นหา

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

<div id="student-list" data-jquery-search-result data-search-form="#student-admin-search" class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm transition-opacity">

    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 px-5 py-4">
        <div><h2 class="font-black text-slate-900">รายชื่อนักศึกษา</h2><p class="mt-0.5 text-sm text-slate-500"></p></div>
        <span class="rounded-full bg-slate-100 px-3 py-1.5 text-xs font-bold text-slate-700"><?= $totalStudents ?> รายการ</span>
    </div>


    <div class="overflow-x-auto">

        <table class="min-w-[1280px] w-full text-sm">

            <thead class="bg-gray-100">

                <tr>

                    <th class="text-left px-4 py-3 font-bold text-slate-600">

                        รหัสนักศึกษา

                    </th>

                    <th class="text-left px-4 py-3 font-bold text-slate-600">

                        คำนำหน้า

                    </th>

                    <th class="text-left px-4 py-3 font-bold text-slate-600">

                        ชื่อ-นามสกุล

                    </th>

                    <th class="text-left px-4 py-3 font-bold text-slate-600">

                        อีเมล

                    </th>

                    <th class="text-left px-4 py-3 font-bold text-slate-600">

                        กลุ่มเรียน

                    </th>

                    <th class="text-left px-4 py-3 font-bold text-slate-600">

                        สาขา

                    </th>

                    <th class="text-left px-4 py-3 font-bold text-slate-600">

                        คณะ

                    </th>

                    <th class="text-center px-4 py-3 font-bold text-slate-600">

                        สถานะบัญชี

                    </th>
                    <th class="text-center px-4 py-3 font-bold text-slate-600">

                    จัดการ

                    </th>

                </tr>

            </thead>


            <tbody>

                <?php

                while (

                    $row = $stmt->fetch(

                        PDO::FETCH_ASSOC

                    )

                ):

                ?>

                <tr

                    class="border-b

                           hover:bg-gray-50"

                >

                    <td class="px-4 py-3 font-semibold text-slate-900 whitespace-nowrap">

                        <?= htmlspecialchars(

                            $row['student_number']

                        ) ?>

                    </td>


                    <td class="px-4 py-3 text-slate-700 whitespace-nowrap">

                        <?= htmlspecialchars($row['prefix_name']) ?>

                    </td>

                    <td class="px-4 py-3 font-semibold text-slate-900 whitespace-nowrap">

                        <?= htmlspecialchars(

                            $row['firstname']

                            . ' '

                            . $row['lastname']

                        ) ?>

                    </td>


                    <td class="px-4 py-3 text-slate-700 whitespace-nowrap">

                        <?= htmlspecialchars($row['email'] ?: '-') ?>

                    </td>

                    <td class="px-4 py-3 text-slate-700 whitespace-nowrap">

                        <?= htmlspecialchars($row['group_name'] ?: '-') ?>

                    </td>

                    <td class="px-4 py-3 text-slate-700 whitespace-nowrap">

                        <?= htmlspecialchars($row['department_name'] ?: '-') ?>

                    </td>

                    <td class="px-4 py-3 text-slate-700 whitespace-nowrap">

                        <?= htmlspecialchars($row['faculty_name'] ?: '-') ?>

                    </td>

                    <td class="px-4 py-3 text-center">

                        <?php if (

                            $row['user_id']

                        ): ?>

                            <?php if (

                                $row['status']

                                ===

                                'active'

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

                        <?php else: ?>

                            <span

                                class="status-badge status-badge--neutral"

                            >

                                ยังไม่สมัครสมาชิก

                            </span>

                        <?php endif; ?>

                    </td>

                    <td class="px-4 py-3 text-center">

    <div class="flex items-center justify-center gap-2">

        <a
            href="../users/edit.php?id=<?= (int) $row['user_id'] ?>&amp;from=students"
            class="inline-flex h-9 w-9 items-center justify-center rounded-lg bg-blue-50 text-blue-700 transition hover:bg-blue-600 hover:text-white focus:outline-none focus:ring-4 focus:ring-blue-100"
            title="แก้ไข">

            <i class="fa-solid fa-pen text-sm"></i>

        </a>

        <button
            type="button"
            class="deleteBtn inline-flex h-9 w-9 items-center justify-center rounded-lg bg-red-50 text-red-700 transition hover:bg-red-600 hover:text-white focus:outline-none focus:ring-4 focus:ring-red-100"
            data-id="<?= $row['student_id'] ?>"
            data-confirm-name="<?= htmlspecialchars(trim($row['firstname'] . ' ' . $row['lastname'])) ?>"
            title="ลบ">

            <i class="fa-solid fa-trash-can text-sm"></i>

        </button>

        <button
            class="openViewModalBtn order-first inline-flex h-9 w-9 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-600 transition hover:border-slate-800 hover:bg-slate-800 hover:text-white focus:outline-none focus:ring-4 focus:ring-slate-200"
            data-id="<?= $row['student_id'] ?>"
            data-student-code="<?= htmlspecialchars($row['student_number']) ?>"
            data-firstname="<?= htmlspecialchars($row['firstname']) ?>"
            data-lastname="<?= htmlspecialchars($row['lastname']) ?>"
            data-email="<?= htmlspecialchars($row['email'] ?? '-') ?>"
            data-faculty="<?= htmlspecialchars($row['faculty_name'] ?? '-') ?>"
            data-department="<?= htmlspecialchars($row['department_name'] ?? '-') ?>"
            data-group="<?= htmlspecialchars($row['group_name'] ?? '-') ?>"
            data-prefix="<?= htmlspecialchars($row['prefix_name'] ?? '-') ?>"
            data-advisor="<?= htmlspecialchars($row['advisor_name'] ?? 'ยังไม่ได้กำหนด') ?>"
            data-status="<?= $row['user_id'] ? (($row['status'] ?? '') === 'active' ? 'เปิดใช้งาน' : 'ปิดใช้งาน') : 'ยังไม่สมัครสมาชิก' ?>"
            title="ดูรายละเอียด">

            <i class="fa-solid fa-eye text-sm"></i>

        </button>

    </div>

</td>

                </tr>

                <?php endwhile; ?>

            </tbody>

        </table>

    </div>

    <?php render_pagination($totalStudents, $page, $perPage, 'page', 'student-list'); ?>

</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<form id="delete-student-form" method="post" action="delete.php" class="hidden">
    <?= csrf_field() ?>
    <input type="hidden" name="student_id" value="">
</form>

<script>

const departmentSelect = document.getElementById('department_id');
const groupSelect = document.getElementById('group_id');
const groups = <?= json_encode($groups, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
const selectedGroupId = <?= json_encode($groupId) ?>;

function populateGroups(resetSelection = false) {

    const departmentId = departmentSelect.value;
    const groupId = resetSelection ? '' : String(selectedGroupId ?? '');

    groupSelect.innerHTML = '<option value="">ทุกกลุ่มเรียน</option>';
    groupSelect.disabled = !departmentId;

    if (!departmentId) {
        return;
    }

    groups
        .filter((group) => String(group.department_id) === departmentId)
        .forEach((group) => {
            const option = document.createElement('option');
            option.value = group.group_id;
            option.textContent = group.group_name;
            option.selected = String(group.group_id) === groupId;
            groupSelect.appendChild(option);
        });

}

departmentSelect.addEventListener('change', () => populateGroups(true));
populateGroups();

document.querySelectorAll(".deleteBtn").forEach(button=>{

    button.addEventListener("click",function(){

        const id=this.dataset.id;

        Swal.fire({

            title:"ยืนยันการลบ",

            text:"ข้อมูลนักศึกษาจะถูกลบ",

            icon:"warning",

            showCancelButton:true,

            confirmButtonColor:"#dc2626",

            confirmButtonText:"ลบ",

            cancelButtonText:"ยกเลิก"

        }).then((result)=>{

            if(result.isConfirmed){
                const deleteForm = document.getElementById('delete-student-form');
                deleteForm.querySelector('[name="student_id"]').value = id;
                deleteForm.submit();

            }

        });

    });

});

</script>
<script>

document.querySelectorAll('.openViewModalBtn').forEach(btn => {

    btn.addEventListener('click', function() {

        const modal = document.getElementById('studentViewModal');
        const overlay = document.getElementById('studentViewOverlay');

        // fill modal fields from data attributes
        modal.querySelector('.mv-student-code').textContent = this.dataset.studentCode || '-';
        modal.querySelector('.mv-name').textContent = (this.dataset.firstname || '') + ' ' + (this.dataset.lastname || '');
        modal.querySelector('.mv-email').textContent = this.dataset.email || '-';
        modal.querySelector('.mv-faculty').textContent = this.dataset.faculty || '-';
        modal.querySelector('.mv-department').textContent = this.dataset.department || '-';
        modal.querySelector('.mv-group').textContent = this.dataset.group || '-';
        modal.querySelector('.mv-prefix').textContent = this.dataset.prefix || '-';
        modal.querySelector('.mv-advisor').textContent = this.dataset.advisor || '-';
        modal.querySelector('.mv-status').textContent = this.dataset.status || '-';

        overlay.classList.remove('hidden');
        modal.classList.remove('hidden');
        modal.classList.add('flex');

    });

});

// modal close handlers
document.addEventListener('click', function(e) {

    const overlay = document.getElementById('studentViewOverlay');
    const modal = document.getElementById('studentViewModal');

    if (!overlay || !modal) return;

    if (e.target.closest('#studentViewClose') || e.target.matches('#studentViewOverlay')) {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
        overlay.classList.add('hidden');
    }

});

</script>

<!-- Student View Modal -->
<div id="studentViewOverlay" class="fixed inset-0 z-[70] hidden bg-slate-950/50 backdrop-blur-sm"></div>

<div id="studentViewModal" class="fixed inset-0 z-[80] hidden items-center justify-center p-4" role="dialog" aria-modal="true">

    <div class="w-full max-w-2xl overflow-hidden rounded-2xl bg-white shadow-2xl">

        <div class="flex items-center justify-between p-4 border-b">

            <h3 class="text-lg font-semibold">รายละเอียดนักศึกษา</h3>

            <button id="studentViewClose" class="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-slate-200 text-slate-500 hover:bg-slate-100" aria-label="ปิดหน้าต่าง"><i class="fa-solid fa-xmark"></i></button>

        </div>

        <div class="p-6 text-sm text-gray-700">

            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">

                <div><strong>รหัสนักศึกษา:</strong> <span class="mv-student-code"></span></div>

                <div><strong>ชื่อ-นามสกุล:</strong> <span class="mv-name"></span></div>

                <div><strong>อีเมล:</strong> <span class="mv-email"></span></div>

                <div><strong>คณะ:</strong> <span class="mv-faculty"></span></div>

                <div><strong>สาขา:</strong> <span class="mv-department"></span></div>

                <div><strong>กลุ่มเรียน:</strong> <span class="mv-group"></span></div>

                <div><strong>คำนำหน้า:</strong> <span class="mv-prefix"></span></div>

                <div><strong>อาจารย์ที่ปรึกษา:</strong> <span class="mv-advisor"></span></div>

                <div class="md:col-span-2"><strong>สถานะบัญชี:</strong> <span class="mv-status"></span></div>

            </div>

        </div>

    </div>

</div>
<?php

include "../layouts/admin_footer.php";

?>
