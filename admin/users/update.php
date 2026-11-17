<?php

session_start();

require_once "../../middleware/admin.php";
require_once "../../config/database.php";
require_once "../../helpers/csrf_helper.php";

verify_csrf_or_abort();


// =============================
// รับข้อมูล
// =============================

$user_id = intval($_POST['user_id'] ?? 0);

$email = trim($_POST['email'] ?? '');

$status = trim($_POST['status'] ?? '');
$from = in_array($_POST['from'] ?? '', ['teachers', 'staffs', 'students', 'users'], true)
    ? (string) $_POST['from']
    : 'users';
$embedded = ($_POST['embed'] ?? '') === '1';
$editReturnSuffix = '&from=' . rawurlencode($from) . ($embedded ? '&embed=1' : '');
$returnDestination = match ($from) {
    'teachers' => '../teachers/index.php',
    'staffs' => '../staffs/index.php',
    'students' => '../students/index.php',
    default => 'index.php',
};


// =============================
// ตรวจสอบ User ID
// =============================

if ($user_id <= 0) {

    $_SESSION['admin_error'] =
        "ข้อมูลผู้ใช้งานไม่ถูกต้อง";

    header("Location: " . $returnDestination);

    exit;

}


// =============================
// ตรวจสอบ Email
// =============================

if (!filter_var($email, FILTER_VALIDATE_EMAIL) || !preg_match('/@(?:[a-z0-9-]+\.)*rmuti\.ac\.th$/i', $email)) {

    $_SESSION['admin_error'] =
        "กรุณาใช้อีเมล @rmuti.ac.th เท่านั้น";

    header(
        "Location: edit.php?id=" . $user_id . $editReturnSuffix
    );

    exit;

}


// =============================
// ตรวจสอบ Status
// =============================

if (!in_array(

    $status,

    [

        'active',

        'inactive'

    ],

    true

)) {

    $_SESSION['admin_error'] =
        "สถานะไม่ถูกต้อง";

    header(
        "Location: edit.php?id=" . $user_id . $editReturnSuffix
    );

    exit;

}


try {


    $pdo->beginTransaction();


    // =============================
    // ดึงข้อมูล User
    // =============================

    $stmt = $pdo->prepare("

        SELECT

            u.user_id,

            u.email,

            s.student_id,

            e.employee_id,

            e.employee_type

        FROM users u

        LEFT JOIN student s

            ON u.user_id = s.user_id

        LEFT JOIN employee e

            ON u.user_id = e.user_id

        WHERE u.user_id = ?

        LIMIT 1

    ");

    $stmt->execute([

        $user_id

    ]);

    $user = $stmt->fetch(PDO::FETCH_ASSOC);


    if (!$user) {

        throw new Exception(

            "ไม่พบข้อมูลผู้ใช้งาน"

        );

    }


    // =============================
    // ตรวจสอบ Email ซ้ำ
    // =============================

    $stmt = $pdo->prepare("

        SELECT user_id

        FROM users

        WHERE email = ?

        AND user_id != ?

        LIMIT 1

    ");

    $stmt->execute([

        $email,

        $user_id

    ]);


    if ($stmt->fetch()) {

        throw new Exception(

            "Email นี้ถูกใช้งานโดยผู้ใช้งานอื่นแล้ว"

        );

    }


    // =============================
    // อัปเดต users
    // =============================

    $stmt = $pdo->prepare("

        UPDATE users

        SET

            email = ?,

            status = ?

        WHERE user_id = ?

    ");

    $stmt->execute([

        $email,

        $status,

        $user_id

    ]);


    // =============================
    // ตรวจสอบ Role
    // =============================

    if (!empty($user['student_id'])) {

        $role = 'student';

    } elseif (

        !empty($user['employee_id'])

        &&

        $user['employee_type'] === 'teacher'

    ) {

        $role = 'teacher';

    } elseif (

        !empty($user['employee_id'])

        &&

        $user['employee_type'] === 'staff'

    ) {

        $role = 'staff';

    } else {

        $role = 'admin';

    }


    // =============================
    // ตรวจสอบ Role เดิม
    // =============================

    $stmt = $pdo->prepare("

        SELECT user_role_id

        FROM user_roles

        WHERE user_id = ?

        LIMIT 1

    ");

    $stmt->execute([

        $user_id

    ]);

    $role_id = $stmt->fetchColumn();


    if ($role_id) {


        $stmt = $pdo->prepare("

            UPDATE user_roles

            SET role = ?

            WHERE user_role_id = ?

        ");

        $stmt->execute([

            $role,

            $role_id

        ]);

    } else {


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

            $role

        ]);

    }


    // =============================
    // Commit
    // =============================

    $pdo->commit();


    $_SESSION['admin_success'] =

        "แก้ไขข้อมูลผู้ใช้งานสำเร็จ";


    header("Location: " . $returnDestination);

    exit;


} catch (Exception $e) {


    if ($pdo->inTransaction()) {

        $pdo->rollBack();

    }


    $_SESSION['admin_error'] =

        $e->getMessage();


    header(

        "Location: edit.php?id=" . $user_id . $editReturnSuffix

    );

    exit;

}
