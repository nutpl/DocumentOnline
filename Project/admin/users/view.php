<?php

session_start();

require_once "../../middleware/admin.php";
require_once "../../config/database.php";


// ==================================================
// ตรวจสอบ ID
// ==================================================

$user_id = intval(
    $_GET['id'] ?? 0
);

if ($user_id <= 0) {

    header(
        "Location: index.php"
    );

    exit;

}


// ==================================================
// ดึงข้อมูล User
// ==================================================

$stmt = $pdo->prepare("

    SELECT

        user_id,

        username,

        email,

        status,

        first_login,

        created_at

    FROM users

    WHERE user_id = ?

    LIMIT 1

");

$stmt->execute([

    $user_id

]);

$user = $stmt->fetch(

    PDO::FETCH_ASSOC

);


if (!$user) {

    die("ไม่พบข้อมูลผู้ใช้งาน");

}


// ==================================================
// ดึง Role
// ==================================================

$stmt = $pdo->prepare("

    SELECT role

    FROM user_roles

    WHERE user_id = ?

");

$stmt->execute([

    $user_id

]);

$roles = $stmt->fetchAll(

    PDO::FETCH_COLUMN

);


// ==================================================
// ตรวจสอบข้อมูล Student
// ==================================================

$stmt = $pdo->prepare("

    SELECT

        s.student_id,

        s.student_number,

        s.firstname,

        s.lastname,

        u.email,

        g.group_name,

        d.department_name,

        f.faculty_name,

        e.firstname AS advisor_firstname,

        e.lastname AS advisor_lastname

    FROM student s

    LEFT JOIN users u

        ON u.user_id = s.user_id

    LEFT JOIN group_class g

        ON s.group_id = g.group_id

    LEFT JOIN department d

        ON g.department_id = d.department_id

    LEFT JOIN faculty f

        ON d.faculty_id = f.faculty_id

    LEFT JOIN employee e

        ON g.advisor_employee_id = e.employee_id

    WHERE s.user_id = ?

    LIMIT 1

");

$stmt->execute([

    $user_id

]);

$student = $stmt->fetch(

    PDO::FETCH_ASSOC

);


// ==================================================
// ตรวจสอบข้อมูล Employee
// ==================================================

$stmt = $pdo->prepare("

    SELECT

        e.employee_id,

        e.employee_code,

        e.firstname,

        e.lastname,

        u.email,

        e.employee_type,

        pr.prefix_short,

        (
            SELECT GROUP_CONCAT(p.position_name SEPARATOR ', ')
            FROM employee_position ep
            JOIN positions p ON p.position_id = ep.position_id
            WHERE ep.employee_id = e.employee_id
        ) AS position_names,

        d.department_name,

        f.faculty_name

    FROM employee e

    LEFT JOIN users u

        ON u.user_id = e.user_id

    LEFT JOIN department d

        ON e.department_id = d.department_id

    LEFT JOIN faculty f

        ON d.faculty_id = f.faculty_id

    LEFT JOIN prefixes pr

        ON e.prefix_id = pr.prefix_id

    WHERE e.user_id = ?

    LIMIT 1

");

$stmt->execute([

    $user_id

]);

$employee = $stmt->fetch(

    PDO::FETCH_ASSOC

);


$pageTitle = "รายละเอียดผู้ใช้งาน";

include "../layouts/admin_header.php";

include "../layouts/admin_sidebar.php";

include "../layouts/admin_navbar.php";

?>


<!-- Header -->

<div

    class="flex
           flex-wrap
           gap-4

           items-center

           justify-between

           mb-6"

>

    <div>

        <h1 class="text-3xl font-bold">

            รายละเอียดผู้ใช้งาน

        </h1>

        <p class="text-gray-500 mt-1">

            ข้อมูลบัญชีและสิทธิ์ผู้ใช้งาน

        </p>

    </div>


    <a

        href="../dashboard.php"

        class="group inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-sm transition-all duration-200 hover:-translate-y-0.5 hover:border-red-200 hover:bg-red-50 hover:text-red-700 hover:shadow-md focus:outline-none focus:ring-4 focus:ring-red-100"

    >

        <span class="inline-flex h-7 w-7 items-center justify-center rounded-lg bg-slate-100 text-slate-500 transition-colors duration-200 group-hover:bg-red-100 group-hover:text-red-700">
            <i class="fa-solid fa-arrow-left text-xs transition-transform duration-200 group-hover:-translate-x-0.5"></i>
        </span>

        กลับ

    </a>

</div>


<!-- User Account -->

<div class="table-card mb-6">

    <div class="p-5 border-b">

        <h2 class="font-bold text-xl">

            ข้อมูลบัญชี

        </h2>

    </div>


    <div

        class="p-6

               grid

               grid-cols-1

               md:grid-cols-2

               gap-6"

    >

        <div>

            <p class="text-gray-500">

                User ID

            </p>

            <p class="font-bold text-lg">

                <?= $user['user_id'] ?>

            </p>

        </div>


        <div>

            <p class="text-gray-500">

                Username

            </p>

            <p class="font-bold text-lg">

                <?= htmlspecialchars(

                    $user['username']

                ) ?>

            </p>

        </div>


        <div>

            <p class="text-gray-500">

                Email

            </p>

            <p class="font-bold">

                <?= htmlspecialchars(

                    $user['email']

                    ?? '-'

                ) ?>

            </p>

        </div>


        <div>

            <p class="text-gray-500">

                สถานะ

            </p>


            <?php if (

                $user['status']

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

        </div>


        <div>

            <p class="text-gray-500">

                First Login

            </p>

            <p class="font-bold">

                <?php if (

                    $user['first_login']

                    == 1

                ): ?>

                    <span

                        class="text-orange-600"

                    >

                        ยังไม่ตั้งรหัสผ่าน

                    </span>

                <?php else: ?>

                    <span

                        class="text-green-600"

                    >

                        ตั้งรหัสผ่านแล้ว

                    </span>

                <?php endif; ?>

            </p>

        </div>


        <div>

            <p class="text-gray-500">

                วันที่สมัคร

            </p>

            <p class="font-bold">

                <?= htmlspecialchars(

                    $user['created_at']

                ) ?>

            </p>

        </div>

    </div>

</div>


<!-- Roles -->

<div class="table-card mb-6">

    <div class="p-5 border-b">

        <h2 class="font-bold text-xl">

            สิทธิ์การใช้งาน

        </h2>

    </div>


    <div class="p-6">

        <?php if (

            empty($roles)

        ): ?>

            <p class="text-red-600">

                บัญชีนี้ยังไม่มีสิทธิ์

            </p>

        <?php else: ?>

            <div

                class="flex

                       flex-wrap

                       gap-2"

            >

                <?php foreach (

                    $roles

                    as $role

                ): ?>

                    <span

                        class="bg-blue-100

                               text-blue-700

                               px-4

                               py-2

                               rounded-full

                               font-semibold"

                    >

                        <?= htmlspecialchars(

                            $role

                        ) ?>

                    </span>

                <?php endforeach; ?>

            </div>

        <?php endif; ?>

    </div>

</div>


<!-- Student Information -->

<?php if (

    $student

): ?>

<div class="table-card mb-6">

    <div class="p-5 border-b">

        <h2 class="font-bold text-xl">

            ข้อมูลนักศึกษา

        </h2>

    </div>


    <div

        class="p-6

               grid

               grid-cols-1

               md:grid-cols-2

               gap-6"

    >

        <div>

            <p class="text-gray-500">

                รหัสนักศึกษา

            </p>

            <p class="font-bold">

                <?= htmlspecialchars(

                    $student['student_number']

                ) ?>

            </p>

        </div>


        <div>

            <p class="text-gray-500">

                ชื่อ - นามสกุล

            </p>

            <p class="font-bold">

                <?= htmlspecialchars(

                    $student['firstname']

                    . ' '

                    . $student['lastname']

                ) ?>

            </p>

        </div>


        <div>

            <p class="text-gray-500">

                คณะ

            </p>

            <p class="font-bold">

                <?= htmlspecialchars(

                    $student['faculty_name']

                    ?? '-'

                ) ?>

            </p>

        </div>


        <div>

            <p class="text-gray-500">

                สาขา

            </p>

            <p class="font-bold">

                <?= htmlspecialchars(

                    $student['department_name']

                    ?? '-'

                ) ?>

            </p>

        </div>


        <div>

            <p class="text-gray-500">

                กลุ่มเรียน

            </p>

            <p class="font-bold">

                <?= htmlspecialchars(

                    $student['group_name']

                    ?? '-'

                ) ?>

            </p>

        </div>


        <div>

            <p class="text-gray-500">

                อาจารย์ที่ปรึกษา

            </p>

            <p class="font-bold">

                <?php if (

                    !empty(

                        $student[

                            'advisor_firstname'

                        ]

                    )

                ): ?>

                    <?= htmlspecialchars(

                        $student[

                            'advisor_firstname'

                        ]

                        . ' '

                        . $student[

                            'advisor_lastname'

                        ]

                    ) ?>

                <?php else: ?>

                    ไม่พบข้อมูล

                <?php endif; ?>

            </p>

        </div>

    </div>

</div>

<?php endif; ?>


<!-- Employee Information -->

<?php if (

    $employee

): ?>

<div class="table-card mb-6">

    <div class="p-5 border-b">

        <h2 class="font-bold text-xl">

            ข้อมูลบุคลากร

        </h2>

    </div>


    <div

        class="p-6

               grid

               grid-cols-1

               md:grid-cols-2

               gap-6"

    >

        <div>

            <p class="text-gray-500">

                รหัสบุคลากร

            </p>

            <p class="font-bold">

                <?= htmlspecialchars(

                    $employee[

                        'employee_code'

                    ]

                    ?? '-'

                ) ?>

            </p>

        </div>


        <div>

            <p class="text-gray-500">

                ชื่อ - นามสกุล

            </p>

            <p class="font-bold">

                <?= htmlspecialchars(

                    ($employee['prefix_short'] ?? '')

                    . ' '

                    . $employee['firstname']

                    . ' '

                    . $employee['lastname']

                ) ?>

            </p>

        </div>

        <div>

            <p class="text-gray-500">

                ตำแหน่งงาน

            </p>

            <p class="font-bold">

                <?= htmlspecialchars(

                    $employee['position_names']

                    ?? '-'

                ) ?>

            </p>

        </div>


        <div>

            <p class="text-gray-500">

                ประเภทบุคลากร

            </p>

            <p class="font-bold">

                <?= htmlspecialchars(

                    $employee[

                        'employee_type'

                    ]

                ) ?>

            </p>

        </div>


        <div>

            <p class="text-gray-500">

                สาขา / หน่วยงาน

            </p>

            <p class="font-bold">

                <?= htmlspecialchars(

                    $employee[

                        'department_name'

                    ]

                    ?? '-'

                ) ?>

            </p>

        </div>

    </div>

</div>

<?php endif; ?>

<div class="flex gap-3 mt-6">

    <a
        href="edit.php?id=<?= $user['user_id'] ?>&amp;from=view"
        class="bg-yellow-500 hover:bg-yellow-600 text-white px-5 py-2 rounded-lg">

        <i class="fa-solid fa-pen-to-square"></i>

        แก้ไขข้อมูล

    </a>

</div>
<?php

include "../layouts/admin_footer.php";

?>
