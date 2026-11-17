<?php

require_once __DIR__ . "/../../middleware/admin.php";
require_once __DIR__ . "/../../config/database.php";

$pageTitle = "จัดการบุคลากร";

$stmt = $pdo->query("
    SELECT
        e.employee_id,
        e.employee_code,
        e.firstname,
        e.lastname,
        u.email,
        e.employee_type,
        d.department_name
    FROM employee e
    JOIN users u ON u.user_id = e.user_id

    LEFT JOIN department d
        ON e.department_id = d.department_id

    ORDER BY e.employee_id DESC
");

$employees = $stmt->fetchAll(PDO::FETCH_ASSOC);

include __DIR__ . "/../layouts/admin_header.php";
include __DIR__ . "/../layouts/admin_sidebar.php";
include __DIR__ . "/../layouts/admin_navbar.php";

?>

<div class="max-w-7xl mx-auto">

    <div class="flex justify-between items-center mb-6">

        <div>

            <h1 class="text-3xl font-bold text-gray-800">

                จัดการบุคลากร

            </h1>

            <p class="text-gray-500 mt-1">

                จัดการข้อมูลอาจารย์และเจ้าหน้าที่

            </p>

        </div>

        <a
            href="add.php"

            class="admin-add-button bg-red-600
                   hover:bg-red-700
                   text-white
                   px-5
                   py-3
                   rounded-lg"
        >

            <i class="fa-solid fa-plus mr-2"></i>

            เพิ่มบุคลากร

        </a>

    </div>


    <div class="bg-white rounded-xl shadow overflow-x-auto">

        <table class="w-full min-w-[800px]">

            <thead class="bg-gray-100">

                <tr>

                    <th class="px-6 py-4 text-left">
                        รหัสบุคลากร
                    </th>

                    <th class="px-6 py-4 text-left">
                        ชื่อ-นามสกุล
                    </th>

                    <th class="px-6 py-4 text-left">
                        อีเมล
                    </th>

                    <th class="px-6 py-4 text-left">
                        สาขา
                    </th>

                    <th class="px-6 py-4 text-left">
                        ประเภท
                    </th>

                    <th class="px-6 py-4 text-center">
                        จัดการ
                    </th>

                </tr>

            </thead>

            <tbody>

                <?php foreach ($employees as $employee): ?>

                    <tr class="border-t">

                        <td class="px-6 py-4">

                            <?= htmlspecialchars(
                                $employee['employee_code']
                            ) ?>

                        </td>

                        <td class="px-6 py-4">

                            <?= htmlspecialchars(
                                $employee['firstname']
                                . " "
                                . $employee['lastname']
                            ) ?>

                        </td>

                        <td class="px-6 py-4">

                            <?= htmlspecialchars(
                                $employee['email']
                            ) ?>

                        </td>

                        <td class="px-6 py-4">

                            <?= htmlspecialchars(
                                $employee['department_name']
                                ?? '-'
                            ) ?>

                        </td>

                        <td class="px-6 py-4">

                            <?php if (
                                $employee['employee_type']
                                === 'teacher'
                            ): ?>

                                <span
                                    class="bg-blue-100
                                           text-blue-700
                                           px-3
                                           py-1
                                           rounded-full
                                           text-sm"
                                >

                                    อาจารย์

                                </span>

                            <?php else: ?>

                                <span
                                    class="bg-green-100
                                           text-green-700
                                           px-3
                                           py-1
                                           rounded-full
                                           text-sm"
                                >

                                    เจ้าหน้าที่

                                </span>

                            <?php endif; ?>

                        </td>

                        <td class="px-6 py-4 text-center">

                            <a
                                href="edit.php?id=
                                <?= $employee['employee_id'] ?>"

                                class="text-blue-600
                                       hover:text-blue-800"
                            >

                                <i class="fa-solid fa-pen"></i>

                            </a>

                        </td>

                    </tr>

                <?php endforeach; ?>

            </tbody>

        </table>

    </div>

</div>

<?php

include __DIR__ . "/../layouts/admin_footer.php";

?>
