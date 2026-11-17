<?php

session_start();

require_once "../config/database.php";
require_once "../helpers/password_helper.php";
require_once "../helpers/csrf_helper.php";


/*
|--------------------------------------------------------------------------
| ตรวจสอบว่า Login แล้วหรือยัง
|--------------------------------------------------------------------------
*/

if (!isset($_SESSION['user_id'])) {

    header("Location: login.php");

    exit;

}


$user_id = $_SESSION['user_id'];

$dashboardRoutes = [
    'admin' => '../admin/dashboard.php',
    'student' => '../student/dashboard.php',
    'teacher' => '../teacher/dashboard.php',
    'staff' => '../staff/dashboard.php',
];
$dashboardUrl = $dashboardRoutes[$_SESSION['role'] ?? ''] ?? 'login.php';

$error = "";

$success = "";


/*
|--------------------------------------------------------------------------
| เมื่อกดเปลี่ยนรหัสผ่าน
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    verify_csrf_or_abort();

    $current_password =
        trim($_POST['current_password'] ?? '');

    $new_password =
        trim($_POST['new_password'] ?? '');

    $confirm_password =
        trim($_POST['confirm_password'] ?? '');


    /*
    |--------------------------------------------------------------------------
    | ตรวจสอบข้อมูล
    |--------------------------------------------------------------------------
    */

    if ($current_password === '') {

        $error = "กรุณากรอกรหัสผ่านเดิม";

    } elseif ($new_password === '') {

        $error = "กรุณากรอกรหัสผ่านใหม่";

    } elseif (strlen($new_password) < 6) {

        $error = "รหัสผ่านใหม่ต้องมีอย่างน้อย 6 ตัวอักษร";

    } elseif ($new_password !== $confirm_password) {

        $error = "รหัสผ่านใหม่ไม่ตรงกัน";

    } else {


        /*
        |--------------------------------------------------------------------------
        | ดึงรหัสผ่านปัจจุบัน
        |--------------------------------------------------------------------------
        */

        $stmt = $pdo->prepare("

            SELECT password

            FROM users

            WHERE user_id = ?

            AND status = 'active'

        ");

        $stmt->execute([
            $user_id
        ]);

        $user = $stmt->fetch(
            PDO::FETCH_ASSOC
        );


        if (!$user) {

            $error = "ไม่พบข้อมูลผู้ใช้งาน";

        } elseif (!verifyStoredUserPassword($current_password, (string)$user['password'])) {

            $error = "รหัสผ่านเดิมไม่ถูกต้อง";

        } elseif (
            $current_password === $new_password
        ) {

            $error = "รหัสผ่านใหม่ต้องแตกต่างจากรหัสผ่านเดิม";

        } else {


            /*
            |--------------------------------------------------------------------------
            | บันทึกรหัสผ่านใหม่
            |--------------------------------------------------------------------------
            */

            $stmt = $pdo->prepare("

                UPDATE users

                SET password = ?

                WHERE user_id = ?

            ");

            $stmt->execute([

                prepareUserPasswordForStorage($new_password),

                $user_id

            ]);


            $success =
                "เปลี่ยนรหัสผ่านสำเร็จ";


            /*
            |--------------------------------------------------------------------------
            | ล้างข้อมูลช่องกรอก
            |--------------------------------------------------------------------------
            */

            $_POST = [];

            header('Location: ' . $dashboardUrl . '?password_changed=1');
            exit;

        }

    }

}

?>

<!DOCTYPE html>

<html lang="th">

<head>
    <link rel="icon" type="image/png" href="/Project/assets/images/fet-logo-transparent.png?v=20260815-3">


<meta charset="UTF-8">

<meta
name="viewport"
content="width=device-width, initial-scale=1.0">

<title>
เปลี่ยนรหัสผ่าน
</title>

<script src="https://cdn.tailwindcss.com"></script>
<link rel="stylesheet" href="/Project/assets/css/app-ui.css?v=20260902-2">
<link rel="stylesheet" href="/Project/assets/css/auth-index-modal.css?v=20260918-1">
<link rel="stylesheet" href="/Project/assets/css/responsive.css?v=20260910-1">
<script src="/Project/assets/js/unified-page-title.js?v=20260902-2" defer></script>

<link
rel="stylesheet"
href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
<script src="/Project/assets/js/password-visibility.js?v=20260901-1" defer></script>

</head>


<body class="auth-index-modal flex min-h-screen items-center justify-center p-4">
<?php include __DIR__ . '/auth_index_modal_background.php'; ?>


<div class="w-full max-w-md">


<div
class="bg-white
       rounded-xl
       shadow-xl
       p-5
       sm:p-6
       w-full
       max-w-md">


<div class="mb-2 flex flex-col items-center">
    <div class="flex h-[120px] w-[120px] items-center justify-center">
        <img src="/Project/assets/images/rmuti-university-seal.png" alt="ตรามหาวิทยาลัยเทคโนโลยีราชมงคล" class="h-full w-full object-contain">
    </div>
    <p class="mt-1 text-center text-[11px] font-bold tracking-wide text-slate-500">มหาวิทยาลัยเทคโนโลยีราชมงคลอีสาน</p>
</div>


<h2
class="text-center
       text-2xl
       font-bold
       mb-1">

เปลี่ยนรหัสผ่าน

</h2>

<p class="mb-4 text-center text-sm text-gray-500">ตั้งรหัสผ่านใหม่เพื่อรักษาความปลอดภัยของบัญชี</p>


<?php if ($error !== ''): ?>

<div
class="bg-red-100
       text-red-700
       rounded-lg
       p-3
       mt-4">

<?= htmlspecialchars($error) ?>

</div>

<?php endif; ?>


<?php if ($success !== ''): ?>

<div
class="bg-green-100
       text-green-700
       rounded-lg
       p-3
       mt-4">

<?= htmlspecialchars($success) ?>

</div>

<?php endif; ?>


<form
method="POST"
class="space-y-4">

<?= csrf_field() ?>


<div>

<label
class="block
       mb-2
       font-semibold">

รหัสผ่านเดิม

</label>


<input

id="current-password"

type="password"

name="current_password"

required

autocomplete="current-password"

class="w-full
       border
       rounded-lg
       px-3
       py-2.5"

placeholder="กรอกรหัสผ่านเดิม">

</div>


<div>

<label
class="block
       mb-2
       font-semibold">

รหัสผ่านใหม่

</label>


<input

id="new-password"

type="password"

name="new_password"

required

autocomplete="new-password"

class="w-full
       border
       rounded-lg
       px-3
       py-2.5"

placeholder="อย่างน้อย 6 ตัวอักษร">

</div>


<div>

<label
class="block
       mb-2
       font-semibold">

ยืนยันรหัสผ่านใหม่

</label>


<input

id="confirm-password"

type="password"

name="confirm_password"

required

autocomplete="new-password"

class="w-full
       border
       rounded-lg
       px-3
       py-2.5"

placeholder="กรอกรหัสผ่านใหม่อีกครั้ง">

</div>


<button

type="submit"

class="w-full
       bg-red-600
       hover:bg-red-700
       text-white
       rounded-lg
       py-2.5">

<i
class="fa-solid
       fa-key
       mr-2">

</i>

เปลี่ยนรหัสผ่าน

</button>


</form>


<div
class="text-center
       mt-5">


<a
href="<?= htmlspecialchars($dashboardUrl) ?>"

class="inline-flex
       items-center
       justify-center
       gap-2
       rounded-lg
       px-3
       py-1.5
       text-sm
       font-semibold
       text-gray-600
       transition
       hover:bg-gray-100
       hover:text-red-700">

<i
class="fa-solid
       fa-arrow-left
       mr-1">

</i>

กลับหน้าหลัก

</a>


</div>


</div>

</div>

</body>

</html>
