<?php

session_start();


// ==================================================
// ตรวจสอบสิทธิ์ Admin
// ==================================================

require_once "../middleware/admin.php";


// ==================================================
// เชื่อมต่อฐานข้อมูล
// ==================================================

require_once "../config/database.php";
require_once "../helpers/pagination_helper.php";


// ==================================================
// ตั้งชื่อหน้า
// ==================================================

$pageTitle = "Admin Dashboard";


// ==================================================
// Layout
// ==================================================

include "layouts/admin_header.php";

include "layouts/admin_sidebar.php";

include "layouts/admin_navbar.php";


// ==================================================
// STATISTICS
// ==================================================


// จำนวนผู้ใช้งานทั้งหมด

$totalUsers = $pdo->query("

    SELECT COUNT(*)

    FROM users

")->fetchColumn();


// จำนวนนักศึกษา

$totalStudents = $pdo->query("

    SELECT COUNT(*)

    FROM student

")->fetchColumn();


// จำนวนอาจารย์

$totalTeachers = $pdo->query("

    SELECT COUNT(*)

    FROM employee

    WHERE employee_type = 'teacher'

")->fetchColumn();


// จำนวนเจ้าหน้าที่

$totalStaffs = $pdo->query("

    SELECT COUNT(*)

    FROM employee

    WHERE employee_type = 'staff'

")->fetchColumn();


// ==================================================
// ผู้ใช้งานล่าสุด
// ==================================================

$userType = $_GET['user_type'] ?? 'all';
if (!in_array($userType, ['all', 'student', 'teacher', 'staff'], true)) {
    $userType = 'all';
}
$userTypeLabels = [
    'all' => 'ผู้ใช้งานทั้งหมด',
    'student' => 'นักศึกษา',
    'teacher' => 'อาจารย์',
    'staff' => 'เจ้าหน้าที่',
];
$recentListLabel = $userTypeLabels[$userType];

$userFilterJoin = '';
$userFilterWhere = '';
if ($userType === 'student') {
    $userFilterJoin = ' INNER JOIN student filter_student ON filter_student.user_id = u.user_id ';
} elseif ($userType === 'teacher' || $userType === 'staff') {
    $userFilterJoin = ' INNER JOIN employee filter_employee ON filter_employee.user_id = u.user_id ';
    $userFilterWhere = ' WHERE filter_employee.employee_type = :user_type ';
}

$countStmt = $pdo->prepare('SELECT COUNT(DISTINCT u.user_id) FROM users u ' . $userFilterJoin . $userFilterWhere);
if ($userType === 'teacher' || $userType === 'staff') {
    $countStmt->bindValue(':user_type', $userType);
}
$countStmt->execute();
$filteredUserTotal = (int) $countStmt->fetchColumn();

$recentPerPage = selected_per_page(10);
$recentTotalPages = max(1, (int) ceil($filteredUserTotal / $recentPerPage));
$recentPage = filter_input(INPUT_GET, 'recent_page', FILTER_VALIDATE_INT) ?: 1;
$recentPage = max(1, min($recentPage, $recentTotalPages));
$recentOffset = ($recentPage - 1) * $recentPerPage;

$stmt = $pdo->prepare("

    SELECT

        u.user_id,

        u.username,

        u.email,

        u.status,

        u.created_at,

        COALESCE(
            (SELECT CONCAT(COALESCE(ps.prefix_short, ''), s.firstname, ' ', s.lastname) FROM student s LEFT JOIN prefixes ps ON ps.prefix_id=s.prefix_id WHERE s.user_id=u.user_id LIMIT 1),
            (SELECT CONCAT(
                CASE
                    WHEN e.employee_type = 'teacher'
                         AND (pe.prefix_short IS NULL OR TRIM(pe.prefix_short) = '' OR pe.prefix_short IN ('นาย', 'นาง', 'นางสาว'))
                    THEN 'อาจารย์'
                    ELSE COALESCE(pe.prefix_short, '')
                END,
                e.firstname, ' ', e.lastname
            ) FROM employee e LEFT JOIN prefixes pe ON pe.prefix_id=e.prefix_id WHERE e.user_id=u.user_id LIMIT 1),
            u.username
        ) AS full_name,
        COALESCE((SELECT s.student_number FROM student s WHERE s.user_id=u.user_id LIMIT 1), (SELECT e.employee_code FROM employee e WHERE e.user_id=u.user_id LIMIT 1), '-') AS person_code,
        COALESCE((SELECT e.employee_type FROM employee e WHERE e.user_id=u.user_id LIMIT 1), CASE WHEN EXISTS (SELECT 1 FROM student s WHERE s.user_id=u.user_id) THEN 'student' ELSE 'admin' END) AS account_type,
        COALESCE((SELECT e.phone FROM employee e WHERE e.user_id=u.user_id LIMIT 1), '-') AS phone,
        COALESCE((SELECT g.group_name FROM student s LEFT JOIN group_class g ON g.group_id=s.group_id WHERE s.user_id=u.user_id LIMIT 1), '-') AS group_name,
        COALESCE((SELECT d.department_name FROM employee e LEFT JOIN department d ON d.department_id=e.department_id WHERE e.user_id=u.user_id LIMIT 1), (SELECT d.department_name FROM student s LEFT JOIN group_class g ON g.group_id=s.group_id LEFT JOIN department d ON d.department_id=g.department_id WHERE s.user_id=u.user_id LIMIT 1), '-') AS department_name,
        COALESCE((SELECT f.faculty_name FROM employee e LEFT JOIN department d ON d.department_id=e.department_id LEFT JOIN faculty f ON f.faculty_id=d.faculty_id WHERE e.user_id=u.user_id LIMIT 1), (SELECT f.faculty_name FROM student s LEFT JOIN group_class g ON g.group_id=s.group_id LEFT JOIN department d ON d.department_id=g.department_id LEFT JOIN faculty f ON f.faculty_id=d.faculty_id WHERE s.user_id=u.user_id LIMIT 1), '-') AS faculty_name,
        COALESCE((SELECT GROUP_CONCAT(p.position_name SEPARATOR ', ') FROM employee e JOIN employee_position ep ON ep.employee_id=e.employee_id JOIN positions p ON p.position_id=ep.position_id WHERE e.user_id=u.user_id), '-') AS position_name,

        GROUP_CONCAT(

            ur.role

            SEPARATOR ', '

        ) AS roles

    FROM users u
    $userFilterJoin

    LEFT JOIN user_roles ur

        ON u.user_id = ur.user_id

    $userFilterWhere

    GROUP BY

        u.user_id

    ORDER BY

        u.user_id DESC

    LIMIT :limit OFFSET :offset

");

$stmt->bindValue(':limit', $recentPerPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $recentOffset, PDO::PARAM_INT);
if ($userType === 'teacher' || $userType === 'staff') {
    $stmt->bindValue(':user_type', $userType);
}
$stmt->execute();

$recentUsers = $stmt->fetchAll(

    PDO::FETCH_ASSOC

);

?>


<!-- ==================================================
     STATISTICS CARDS
================================================== -->


<div

    class="grid

           grid-cols-1

           md:grid-cols-2

           xl:grid-cols-4

           gap-6

           mb-8"

>


    <!-- USERS -->

    <div data-user-filter="all" role="button" tabindex="0" aria-label="แสดงผู้ใช้งานทั้งหมด"

        class="bg-white

               rounded-xl

               shadow-sm

               border

               p-6

               hover:shadow-md

               transition"

    >

        <div

            class="flex

                   items-center

                   justify-between"

        >

            <div>

                <p

                    class="text-gray-500

                           text-sm"

                >

                    ผู้ใช้งานทั้งหมด

                </p>


                <h2

                    class="text-3xl

                           font-bold

                           text-red-600

                           mt-2"

                >

                    <?= $totalUsers ?>

                </h2>

            </div>


            <div

                class="w-14

                       h-14

                       rounded-xl

                       bg-red-100

                       flex

                       items-center

                       justify-center"

            >

                <i

                    class="fa-solid

                           fa-users

                           text-red-600

                           text-2xl"

                ></i>

            </div>

        </div>

    </div>


    <!-- STUDENTS -->

    <div data-user-filter="student" role="button" tabindex="0" aria-label="แสดงรายชื่อนักศึกษา"

        class="bg-white

               rounded-xl

               shadow-sm

               border

               p-6

               hover:shadow-md

               transition"

    >

        <div

            class="flex

                   items-center

                   justify-between"

        >

            <div>

                <p

                    class="text-gray-500

                           text-sm"

                >

                    นักศึกษา

                </p>


                <h2

                    class="text-3xl

                           font-bold

                           text-blue-600

                           mt-2"

                >

                    <?= $totalStudents ?>

                </h2>

            </div>


            <div

                class="w-14

                       h-14

                       rounded-xl

                       bg-blue-100

                       flex

                       items-center

                       justify-center"

            >

                <i

                    class="fa-solid

                           fa-user-graduate

                           text-blue-600

                           text-2xl"

                ></i>

            </div>

        </div>
    </div>


    <!-- TEACHERS -->

    <div data-user-filter="teacher" role="button" tabindex="0" aria-label="แสดงรายชื่ออาจารย์"

        class="bg-white

               rounded-xl

               shadow-sm

               border

               p-6

               hover:shadow-md

               transition"

    >

        <div

            class="flex

                   items-center

                   justify-between"

        >

            <div>

                <p

                    class="text-gray-500

                           text-sm"

                >

                    อาจารย์

                </p>


                <h2

                    class="text-3xl

                           font-bold

                           text-green-600

                           mt-2"

                >

                    <?= $totalTeachers ?>

                </h2>

            </div>


            <div

                class="w-14

                       h-14

                       rounded-xl

                       bg-green-100

                       flex

                       items-center

                       justify-center"

            >

                <i

                    class="fa-solid

                           fa-chalkboard-user

                           text-green-600

                           text-2xl"

                ></i>

            </div>

        </div>

    </div>


    <!-- STAFF -->

    <div data-user-filter="staff" role="button" tabindex="0" aria-label="แสดงรายชื่อเจ้าหน้าที่"

        class="bg-white

               rounded-xl

               shadow-sm

               border

               p-6

               hover:shadow-md

               transition"

    >

        <div

            class="flex

                   items-center

                   justify-between"

        >

            <div>

                <p

                    class="text-gray-500

                           text-sm"

                >

                    เจ้าหน้าที่

                </p>


                <h2

                    class="text-3xl

                           font-bold

                           text-yellow-600

                           mt-2"

                >

                    <?= $totalStaffs ?>

                </h2>

            </div>


            <div

                class="w-14

                       h-14

                       rounded-xl

                       bg-yellow-100

                       flex

                       items-center

                       justify-center"

            >

                <i

                    class="fa-solid

                           fa-user-tie

                           text-yellow-600

                           text-2xl"

                ></i>

            </div>

        </div>
    </div>

</div>


<!-- ==================================================
     RECENT USERS
================================================== -->


<div

    id="recent-users"

    class="bg-white

           rounded-xl

           shadow-sm

           border

           overflow-hidden"

>

    <?php if ($filteredUserTotal > 0): ?>
        <?php render_per_page_selector($recentPerPage, 'recent-users'); ?>
    <?php endif; ?>


    <!-- Header -->

    <div

        class="p-6

               border-b

               flex

               items-center

               justify-between"

    >

        <div>

            <h2

                class="text-xl

                       font-bold

                       text-gray-800"

            >

                <?= htmlspecialchars($recentListLabel) ?>

            </h2>


            <p

                class="text-sm

                       text-gray-500

                       mt-1"

            >

                <?= $userType === 'all' ? 'รายการผู้ใช้งานที่สมัครล่าสุด' : 'รายชื่อผู้ใช้งานตามประเภทที่เลือก' ?>

            </p>

        </div>


    </div>


    <!-- Table -->

    <div

        class="overflow-x-auto"

    >

        <table

            class="min-w-full"

        >

            <thead

                class="bg-gray-50"

            >

                <tr>


                    <th

                        class="text-left

                               px-6

                               py-4

                               text-sm

                               font-semibold

                               text-gray-600"

                    >

                        ชื่อ-นามสกุล

                    </th>


                    <th

                        class="text-left

                               px-6

                               py-4

                               text-sm

                               font-semibold

                               text-gray-600"

                    >

                        อีเมล

                    </th>


                    <th

                        class="text-left

                               px-6

                               py-4

                               text-sm

                               font-semibold

                               text-gray-600"

                    >

                        สิทธิ์การใช้งาน

                    </th>


                    <th

                        class="text-left

                               px-6

                               py-4

                               text-sm

                               font-semibold

                               text-gray-600"

                    >

                        สถานะบัญชี

                    </th>


                    <th

                        class="text-center

                               px-6

                               py-4

                               text-sm

                               font-semibold

                               text-gray-600"

                    >

                        จัดการ

                    </th>

                </tr>

            </thead>


            <tbody>


                <?php if (

                    empty(

                        $recentUsers

                    )

                ): ?>


                    <tr>

                        <td

                            colspan="5"

                            class="text-center

                                   px-6

                                   py-10

                                   text-gray-500"

                        >

                            ยังไม่มีข้อมูลผู้ใช้งาน

                        </td>

                    </tr>


                <?php else: ?>


                    <?php foreach (

                        $recentUsers

                        as $row

                    ): ?>


                        <tr

                            class="border-t

                                   hover:bg-gray-50

                                   transition"

                        >


                            <!-- Full name -->

                            <td

                                class="px-6

                                       py-4"

                            >

                                <div

                                    class="font-semibold

                                           text-gray-800"

                                >

                                    <?= htmlspecialchars(

                                        $row['full_name'] ?: $row['username']

                                    ) ?>

                                </div>
                            </td>


                            <!-- Email -->

                            <td

                                class="px-6

                                       py-4

                                       text-gray-600"

                            >

                                <?= htmlspecialchars(

                                    $row[

                                        'email'

                                    ]

                                    ?? '-'

                                ) ?>

                            </td>


                            <!-- Role -->

                            <td

                                class="px-6

                                       py-4"

                            >

                                <?php if (

                                    empty(

                                        $row[

                                            'roles'

                                        ]

                                    )

                                ): ?>


                                    <span

                                        class="bg-gray-100

                                               text-gray-600

                                               px-3

                                               py-1

                                               rounded-full

                                               text-xs"

                                    >

                                        ไม่มีสิทธิ์

                                    </span>


                                <?php else: ?>


                                    <?php

                                    $roleList =

                                        explode(

                                            ', ',

                                            $row[

                                                'roles'

                                            ]

                                        );

                                    ?>


                                    <div

                                        class="flex

                                               flex-wrap

                                               gap-1"

                                    >

                                        <?php foreach (

                                            $roleList

                                            as $role

                                        ): ?>


                                            <span

                                                class="bg-blue-100

                                                       text-blue-700

                                                       px-3

                                                       py-1

                                                       rounded-full

                                                       text-xs

                                                       font-semibold"

                                            >

                                                <?= htmlspecialchars(

                                                    $role

                                                ) ?>

                                            </span>


                                        <?php endforeach; ?>

                                    </div>


                                <?php endif; ?>

                            </td>


                            <!-- Status -->

                            <td

                                class="px-6

                                       py-4"

                            >


                                <?php if (

                                    $row[

                                        'status'

                                    ]

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


                            </td>


                            <!-- Action -->

                            <td

                                class="px-6

                                       py-4

                                       text-center"

                            >


                                <button
                                    type="button"
                                    data-user-detail
                                    data-username="<?= htmlspecialchars($row['username'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                    data-full-name="<?= htmlspecialchars($row['full_name'] ?? '-', ENT_QUOTES, 'UTF-8') ?>"
                                    data-code="<?= htmlspecialchars($row['person_code'] ?? '-', ENT_QUOTES, 'UTF-8') ?>"
                                    data-email="<?= htmlspecialchars($row['email'] ?? '-', ENT_QUOTES, 'UTF-8') ?>"
                                    data-roles="<?= htmlspecialchars($row['roles'] ?: 'ไม่มีสิทธิ์', ENT_QUOTES, 'UTF-8') ?>"
                                    data-status="<?= ($row['status'] ?? '') === 'active' ? 'เปิดใช้งาน' : 'ปิดใช้งาน' ?>"
                                    data-type="<?= $row['account_type'] === 'teacher' ? 'อาจารย์' : ($row['account_type'] === 'staff' ? 'เจ้าหน้าที่' : ($row['account_type'] === 'admin' ? 'ผู้ดูแลระบบ' : 'นักศึกษา')) ?>"
                                    data-phone="<?= htmlspecialchars($row['phone'] ?? '-', ENT_QUOTES, 'UTF-8') ?>"
                                    data-group="<?= htmlspecialchars($row['group_name'] ?? '-', ENT_QUOTES, 'UTF-8') ?>"
                                    data-department="<?= htmlspecialchars($row['department_name'] ?? '-', ENT_QUOTES, 'UTF-8') ?>"
                                    data-faculty="<?= htmlspecialchars($row['faculty_name'] ?? '-', ENT_QUOTES, 'UTF-8') ?>"
                                    data-position="<?= htmlspecialchars($row['position_name'] ?? '-', ENT_QUOTES, 'UTF-8') ?>"
                                    data-created="<?= htmlspecialchars($row['created_at'] ?? '-', ENT_QUOTES, 'UTF-8') ?>"

                                    class="inline-flex

                                           items-center

                                           justify-center

                                           w-9

                                           h-9

                                           rounded-lg

                                           bg-blue-100

                                           text-blue-600

                                           hover:bg-blue-600

                                           hover:text-white

                                           transition"

                                    title="ดูรายละเอียด"

                                >

                                    <i

                                        class="fa-solid

                                               fa-eye"

                                    ></i>

                                </button>


                            </td>


                        </tr>


                    <?php endforeach; ?>


                <?php endif; ?>


            </tbody>

        </table>

    </div>

</div>

<div id="dashboard-user-modal" class="fixed inset-0 z-[80] hidden overflow-y-auto bg-slate-950/50 p-4 backdrop-blur-sm" role="dialog" aria-modal="true" aria-labelledby="dashboard-user-modal-title">
    <div class="flex min-h-full items-center justify-center">
        <section class="w-full max-w-lg overflow-hidden rounded-2xl bg-white shadow-2xl">
            <header class="flex items-start justify-between gap-4 border-b border-slate-100 px-5 py-4">
                <div><p class="text-sm font-bold text-red-700">รายละเอียดผู้ใช้งาน</p><h2 id="dashboard-user-modal-title" class="mt-1 text-xl font-black text-slate-900" data-modal-username></h2></div>
                <button type="button" data-close-user-modal class="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-slate-200 text-slate-500 hover:bg-slate-100" aria-label="ปิดหน้าต่าง"><i class="fa-solid fa-xmark"></i></button>
            </header>
            <dl class="grid gap-x-5 gap-y-4 p-5 text-sm sm:grid-cols-2">
                <div><dt class="font-bold text-slate-500">ชื่อ-นามสกุล</dt><dd class="mt-1 font-semibold text-slate-900" data-modal-full-name></dd></div>
                <div><dt class="font-bold text-slate-500">ประเภทผู้ใช้งาน</dt><dd class="mt-1 font-semibold text-slate-900" data-modal-type></dd></div>
                <div><dt class="font-bold text-slate-500">รหัสนักศึกษา / บุคลากร</dt><dd class="mt-1 font-semibold text-slate-900" data-modal-code></dd></div>
                <div><dt class="font-bold text-slate-500">อีเมล</dt><dd class="mt-1 break-all font-semibold text-slate-900" data-modal-email></dd></div>
                <div><dt class="font-bold text-slate-500">เบอร์โทรศัพท์</dt><dd class="mt-1 font-semibold text-slate-900" data-modal-phone></dd></div>
                <div><dt class="font-bold text-slate-500">สิทธิ์การใช้งาน</dt><dd class="mt-1 font-semibold text-slate-900" data-modal-roles></dd></div>
                <div><dt class="font-bold text-slate-500">ตำแหน่ง</dt><dd class="mt-1 font-semibold text-slate-900" data-modal-position></dd></div>
                <div><dt class="font-bold text-slate-500">กลุ่มเรียน</dt><dd class="mt-1 font-semibold text-slate-900" data-modal-group></dd></div>
                <div><dt class="font-bold text-slate-500">สาขาวิชา</dt><dd class="mt-1 font-semibold text-slate-900" data-modal-department></dd></div>
                <div><dt class="font-bold text-slate-500">คณะ</dt><dd class="mt-1 font-semibold text-slate-900" data-modal-faculty></dd></div>
                <div><dt class="font-bold text-slate-500">สถานะบัญชี</dt><dd class="mt-1 font-semibold text-slate-900" data-modal-status></dd></div>
                <div><dt class="font-bold text-slate-500">วันที่สร้างบัญชี</dt><dd class="mt-1 font-semibold text-slate-900" data-modal-created></dd></div>
            </dl>
        </section>
    </div>
</div>

<style>
    [data-user-filter] { cursor: pointer; }
    [data-user-filter]:hover { transform: translateY(-3px); border-color: #fecaca; box-shadow: 0 12px 24px rgba(15,23,42,.09); }
    [data-user-filter][data-active="true"] { border-color: #ef4444 !important; box-shadow: 0 0 0 3px rgba(239,68,68,.12), 0 12px 24px rgba(15,23,42,.08); }
</style>
<script>
(() => {
    const activeFilter = <?= json_encode($userType) ?>;
    const showFilter = (filter) => {
        const url = new URL(window.location.href);
        if (filter === 'all') url.searchParams.delete('user_type');
        else url.searchParams.set('user_type', filter);
        url.searchParams.delete('recent_page');
        url.hash = 'recent-users';
        window.location.assign(url.toString());
    };
    document.querySelectorAll('[data-user-filter]').forEach((card) => {
        card.dataset.active = card.dataset.userFilter === activeFilter ? 'true' : 'false';
        card.addEventListener('click', () => showFilter(card.dataset.userFilter));
        card.addEventListener('keydown', (event) => {
            if (event.key === 'Enter' || event.key === ' ') { event.preventDefault(); showFilter(card.dataset.userFilter); }
        });
    });
})();

(() => {
    const modal = document.getElementById('dashboard-user-modal');
    if (!modal) return;
    const fields = {
        username: modal.querySelector('[data-modal-username]'), fullName: modal.querySelector('[data-modal-full-name]'),
        code: modal.querySelector('[data-modal-code]'), email: modal.querySelector('[data-modal-email]'), type: modal.querySelector('[data-modal-type]'),
        phone: modal.querySelector('[data-modal-phone]'), roles: modal.querySelector('[data-modal-roles]'), position: modal.querySelector('[data-modal-position]'),
        group: modal.querySelector('[data-modal-group]'), department: modal.querySelector('[data-modal-department]'), faculty: modal.querySelector('[data-modal-faculty]'),
        status: modal.querySelector('[data-modal-status]'), created: modal.querySelector('[data-modal-created]'),
    };
    const close = () => { modal.classList.add('hidden'); document.body.classList.remove('overflow-hidden'); };
    document.querySelectorAll('[data-user-detail]').forEach((button) => button.addEventListener('click', () => {
        Object.entries(fields).forEach(([key, field]) => { field.textContent = button.dataset[key] || '-'; });
        modal.classList.remove('hidden'); document.body.classList.add('overflow-hidden');
        modal.querySelector('[data-close-user-modal]')?.focus();
    }));
    modal.querySelectorAll('[data-close-user-modal]').forEach((button) => button.addEventListener('click', close));
    modal.addEventListener('click', (event) => { if (event.target === modal) close(); });
    document.addEventListener('keydown', (event) => { if (event.key === 'Escape' && !modal.classList.contains('hidden')) close(); });
})();
</script>

<?php


// ==================================================
// Footer
// ==================================================

include "layouts/admin_footer.php";

?>
