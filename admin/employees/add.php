<?php

require_once __DIR__ . "/../../middleware/admin.php";
require_once __DIR__ . "/../../config/database.php";
require_once __DIR__ . "/../../helpers/csrf_helper.php";

$pageTitle = "เพิ่มบุคลากร";

$error = "";
$selectedEmployeeType = ($_GET['employee_type'] ?? '') === 'teacher' ? 'teacher' : 'staff';

$returnUrl = match ($selectedEmployeeType) {
    'teacher' => '../teachers/index.php',
    'staff' => '../staffs/index.php',
    default => '../dashboard.php',
};

$departments = $pdo->query("
    SELECT
        department_id,
        department_name
    FROM department
    ORDER BY department_name ASC
")->fetchAll(PDO::FETCH_ASSOC);


if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    verify_csrf_or_abort();

    $employee_code = trim(
        $_POST['employee_code'] ?? ''
    );

    $firstname = trim(
        $_POST['firstname'] ?? ''
    );

    $lastname = trim(
        $_POST['lastname'] ?? ''
    );

    $email = trim(
        $_POST['email'] ?? ''
    );

    $department_id =
        $_POST['department_id'] ?? '';

    $prefix_id = (int) ($_POST['prefix_id'] ?? 0);
    $position_id = (int) ($_POST['position_id'] ?? 0);
    $phone = trim($_POST['phone'] ?? '');

    $employee_type = $selectedEmployeeType;


    if ($employee_code === '') {

        $error = "กรุณากรอกรหัสบุคลากร";

    } elseif ($firstname === '') {

        $error = "กรุณากรอกชื่อ";

    } elseif ($lastname === '') {

        $error = "กรุณากรอกนามสกุล";

    } elseif ($email === '') {

        $error = "กรุณากรอกอีเมล";

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

        $error = "รูปแบบอีเมลไม่ถูกต้อง";

    } elseif (!preg_match('/@(?:[a-z0-9-]+\.)*rmuti\.ac\.th$/i', $email)) {

        $error = "กรุณาใช้อีเมล @rmuti.ac.th เท่านั้น";

    } elseif ($department_id === '') {

        $error = "กรุณาเลือกสาขาวิชา";

    } elseif ($phone !== '' && mb_strlen($phone) > 30) {

        $error = "เบอร์โทรศัพท์ยาวเกิน 30 ตัวอักษร";

    } else {

        try {

            $pdo->beginTransaction();

            if ($prefix_id > 0) {
                $prefixStmt = $pdo->prepare("SELECT COUNT(*) FROM prefixes WHERE prefix_id=? AND status='active'");
                $prefixStmt->execute([$prefix_id]);
                if (!(int) $prefixStmt->fetchColumn()) throw new Exception('คำนำหน้าไม่ถูกต้อง');
            }
            if ($position_id > 0) {
                $positionStmt = $pdo->prepare('SELECT COUNT(*) FROM positions WHERE position_id=?');
                $positionStmt->execute([$position_id]);
                if (!(int) $positionStmt->fetchColumn()) throw new Exception('ตำแหน่งไม่ถูกต้อง');
            }


            /*
                ตรวจสอบรหัสบุคลากรซ้ำ
            */

            $stmt = $pdo->prepare("
                SELECT employee_id
                FROM employee
                WHERE employee_code = ?
            ");

            $stmt->execute([
                $employee_code
            ]);

            if ($stmt->fetch()) {

                throw new Exception(
                    "รหัสบุคลากรนี้มีอยู่แล้ว"
                );

            }


            /*
                ตรวจสอบอีเมลซ้ำใน users
            */

            $stmt = $pdo->prepare("
                SELECT user_id
                FROM users
                WHERE email = ?
            ");

            $stmt->execute([
                $email
            ]);

            if ($stmt->fetch()) {

                throw new Exception(
                    "อีเมลนี้มีบัญชีผู้ใช้งานแล้ว"
                );

            }


            /*
                สร้าง username
                ใช้รหัสบุคลากร
            */

            $username =
                $employee_code;


            /*
                สร้างบัญชี users
            */

            $stmt = $pdo->prepare("
                INSERT INTO users
                (
                    username,
                    email,
                    password,
                    first_login,
                    status
                )
                VALUES (?, ?, 'pending-password', 1, 'active')
            ");

            $stmt->execute([

                $username,
                $email

            ]);

            $user_id =
                $pdo->lastInsertId();


            /*
                กำหนดสิทธิ์
            */

            $stmt = $pdo->prepare("
                INSERT INTO user_roles
                (
                    user_id,
                    role
                )
                VALUES (?, ?)
            ");

            $stmt->execute([

                $user_id,
                $employee_type

            ]);


            /*
                สร้างข้อมูล employee
            */

            $stmt = $pdo->prepare("
                INSERT INTO employee
                (
                    user_id,
                    employee_code,
                    prefix_id,
                    firstname,
                    lastname,
                    phone,
                    department_id,
                    employee_type
                )
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $stmt->execute([

                $user_id,
                $employee_code,
                $prefix_id ?: null,
                $firstname,
                $lastname,
                $phone !== '' ? $phone : null,
                $department_id,
                $employee_type

            ]);


            $employee_id = (int) $pdo->lastInsertId();
            if ($position_id > 0) {
                $pdo->prepare('INSERT INTO employee_position(employee_id,position_id,is_primary) VALUES (?,?,1)')->execute([$employee_id, $position_id]);
            }

            $pdo->commit();


            header('Location: '.$returnUrl);

            exit;


        } catch (Exception $e) {

            if (
                $pdo->inTransaction()
            ) {

                $pdo->rollBack();

            }

            $error =
                $e->getMessage();

        }

    }

}


include __DIR__ . "/../layouts/admin_header.php";
include __DIR__ . "/../layouts/admin_sidebar.php";
include __DIR__ . "/../layouts/admin_navbar.php";

?>


<div class="max-w-3xl mx-auto">


    <div class="mb-6">

        <h1 class="text-3xl font-bold text-gray-800">

            เพิ่มบุคลากร

        </h1>

        <p class="text-gray-500 mt-1">

            เพิ่มข้อมูลอาจารย์หรือเจ้าหน้าที่เข้าสู่ระบบ

        </p>

    </div>


    <?php if ($error !== ''): ?>

        <div
            class="bg-red-100
                   text-red-700
                   p-4
                   rounded-lg
                   mb-6"
        >

            <?= htmlspecialchars($error) ?>

        </div>

    <?php endif; ?>


    <div
        class="bg-white
               rounded-xl
               shadow
               p-6"
    >

        <form method="POST">
            <?= csrf_field() ?>


            <!-- Employee Code -->

            <div class="mb-5">

                <label
                    class="block
                           text-gray-700
                           font-semibold
                           mb-2"
                >

                    รหัสบุคลากร

                </label>

                <input
                    type="text"
                    name="employee_code"
                    required

                    value="<?= htmlspecialchars(
                        $_POST['employee_code']
                        ?? ''
                    ) ?>"

                    class="w-full
                           border
                           border-gray-300
                           rounded-lg
                           px-4
                           py-3"

                    placeholder="เช่น EMP001"
                >

            </div>


            <!-- Firstname -->

            <div class="mb-5">

                <label
                    class="block
                           text-gray-700
                           font-semibold
                           mb-2"
                >

                    ชื่อ

                </label>

                <input
                    type="text"
                    name="firstname"
                    required

                    value="<?= htmlspecialchars(
                        $_POST['firstname']
                        ?? ''
                    ) ?>"

                    class="w-full
                           border
                           border-gray-300
                           rounded-lg
                           px-4
                           py-3"
                >

            </div>


            <!-- Lastname -->

            <div class="mb-5">

                <label
                    class="block
                           text-gray-700
                           font-semibold
                           mb-2"
                >

                    นามสกุล

                </label>

                <input
                    type="text"
                    name="lastname"
                    required

                    value="<?= htmlspecialchars(
                        $_POST['lastname']
                        ?? ''
                    ) ?>"

                    class="w-full
                           border
                           border-gray-300
                           rounded-lg
                           px-4
                           py-3"
                >

            </div>


            <!-- Email -->

            <div class="mb-5">

                <label
                    class="block
                           text-gray-700
                           font-semibold
                           mb-2"
                >

                    อีเมล

                </label>

                <input
                    type="email"
                    name="email"
                    required
                    pattern=".+@rmuti\.ac\.th$"

                    value="<?= htmlspecialchars(
                        $_POST['email']
                        ?? ''
                    ) ?>"

                    class="w-full
                           border
                           border-gray-300
                           rounded-lg
                           px-4
                           py-3"

                    placeholder="example@rmuti.ac.th"
                >

            </div>


            <!-- Department -->

            <div class="mb-5">

                <label
                    class="block
                           text-gray-700
                           font-semibold
                           mb-2"
                >

                    สาขาวิชา

                </label>

                <select
                    name="department_id"
                    required

                    class="w-full
                           border
                           border-gray-300
                           rounded-lg
                           px-4
                           py-3"
                >

                    <option value="">

                        -- เลือกสาขาวิชา --

                    </option>


                    <?php foreach (
                        $departments
                        as $department
                    ): ?>

                        <option

                            value="<?=
                                $department[
                                    'department_id'
                                ]
                            ?>"

                            <?= (
                                ($_POST[
                                    'department_id'
                                ] ?? '')
                                ==
                                $department[
                                    'department_id'
                                ]
                            )
                                ? 'selected'
                                : ''
                            ?>
                        >

                            <?= htmlspecialchars(
                                $department[
                                    'department_name'
                                ]
                            ) ?>

                        </option>

                    <?php endforeach; ?>

                </select>

            </div>


            <!-- Employee Type -->

            <div class="mb-6">

                <label
                    class="block
                           text-gray-700
                           font-semibold
                           mb-2"
                >

                    ประเภทบุคลากร (กำหนดโดยระบบ)

                </label>

                <input type="hidden" name="employee_type" value="<?= htmlspecialchars($selectedEmployeeType) ?>">
                <select
                    class="w-full
                           border
                           border-gray-300
                           rounded-lg
                           px-4
                           py-3"
                >

                    <option value="">

                        -- เลือกประเภท --

                    </option>

                    <option
                        value="teacher"

                        <?= (
                            $selectedEmployeeType === 'teacher'
                        )
                            ? 'selected'
                            : ''
                        ?>
                    >

                        อาจารย์

                    </option>

                    <option
                        value="staff"

                        <?= (
                            $selectedEmployeeType === 'staff'
                        )
                            ? 'selected'
                            : ''
                        ?>
                    >

                        เจ้าหน้าที่

                    </option>

                </select>

            </div>


            <!-- Information -->

            <div
                class="bg-blue-50
                       text-blue-700
                       p-4
                       rounded-lg
                       mb-6"
            >

                <i class="fa-solid fa-circle-info mr-2"></i>

                บัญชีใหม่จะยังไม่มีรหัสผ่าน
                ผู้ใช้งานจะต้องตั้งรหัสผ่านด้วยตนเอง
                เมื่อเข้าสู่ระบบครั้งแรก

            </div>


            <!-- Buttons -->

            <div class="flex justify-end gap-3">

                <a
                    href="<?= htmlspecialchars($returnUrl, ENT_QUOTES, 'UTF-8') ?>"

                    class="bg-gray-200
                           hover:bg-gray-300
                           text-gray-700
                           px-5
                           py-3
                           rounded-lg"
                >

                    ยกเลิก

                </a>


                <button
                    type="submit"

                    class="bg-red-600
                           hover:bg-red-700
                           text-white
                           px-5
                           py-3
                           rounded-lg"
                >

                    <i class="fa-solid fa-save mr-2"></i>

                    บันทึก

                </button>

            </div>


        </form>

    </div>

</div>


<?php

include __DIR__ . "/../layouts/admin_footer.php";

?>
