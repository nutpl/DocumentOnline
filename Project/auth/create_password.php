<?php

session_start();

require_once "../config/database.php";
require_once "../helpers/password_helper.php";
require_once "../helpers/csrf_helper.php";

if (
    !isset($_SESSION['first_login_verified']) ||
    !isset($_SESSION['first_login_user_id'])
) {

    header("Location: login.php");
    exit;

}

$error = "";

if ($_SERVER['REQUEST_METHOD'] == "POST") {

    verify_csrf_or_abort();

    $password = trim($_POST['password'] ?? '');
    $confirm = trim($_POST['confirm_password'] ?? '');

    if ($password == "" || $confirm == "") {

        $error = "กรุณากรอกรหัสผ่าน";

    }

    elseif ($password != $confirm) {

        $error = "รหัสผ่านไม่ตรงกัน";

    }

    elseif (!preg_match('/^[A-Za-z0-9]{6,20}$/', $password)) {

        $error = "รหัสผ่านต้องเป็นภาษาอังกฤษหรือตัวเลข 6-20 ตัวเท่านั้น";

    }

    else {

        $stmt = $pdo->prepare("

        UPDATE users

        SET

            password=?,

            first_login=0

        WHERE user_id=?

        ");

        $stmt->execute([

            prepareUserPasswordForStorage($password),

            $_SESSION['first_login_user_id']

        ]);

        unset($_SESSION['first_login_verified']);
        unset($_SESSION['first_login_user_id']);
        unset($_SESSION['first_login_email']);
        unset($_SESSION['first_login_source']);

        $_SESSION['success']="ตั้งรหัสผ่านสำเร็จ";

        header("Location: login.php");
        exit;

    }

}

?>

<!DOCTYPE html>

<html lang="th">

<head>
    <link rel="icon" type="image/png" href="/Project/assets/images/fet-logo-transparent.png?v=20260815-3">


<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="/Project/assets/css/responsive.css?v=20260910-2">

<title>ตั้งรหัสผ่าน</title>

<script src="https://cdn.tailwindcss.com"></script>
<script src="/Project/assets/js/password-visibility.js?v=20260901-1" defer></script>
<link rel="stylesheet" href="/Project/assets/css/app-ui.css?v=20260902-2">
<link rel="stylesheet" href="/Project/assets/css/auth-index-modal.css?v=20260918-1">
<script src="/Project/assets/js/unified-page-title.js?v=20260902-2" defer></script>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

</head>

<body class="auth-index-modal">
<?php include __DIR__ . '/auth_index_modal_background.php'; ?>

<div class="min-h-screen flex items-center justify-center p-4">

<div class="bg-white rounded-xl shadow-lg p-5 sm:p-8 w-full max-w-md">

<h1 class="text-3xl font-bold text-center text-red-600">

ตั้งรหัสผ่าน

</h1>

<p class="text-center text-gray-500 mt-2">

เข้าใช้งานครั้งแรก

</p>

<?php if($error!=""): ?>

<div class="bg-red-100 text-red-700 rounded-lg p-3 mt-5">

<?= htmlspecialchars($error) ?>

</div>

<?php endif; ?>

<form method="POST" class="space-y-5 mt-6">
<?= csrf_field() ?>

<div>

<label class="font-semibold">

รหัสผ่านใหม่

</label>

<input

type="password"

name="password"

required

pattern="[A-Za-z0-9]{6,20}"

class="w-full border rounded-lg p-3"

>

</div>

<div>

<label class="font-semibold">

ยืนยันรหัสผ่าน

</label>

<input

type="password"

name="confirm_password"

required

pattern="[A-Za-z0-9]{6,20}"

class="w-full border rounded-lg p-3"

>

</div>

<button

class="w-full bg-red-600 hover:bg-red-700 text-white py-3 rounded-lg">

บันทึกรหัสผ่าน

</button>

</form>

</div>

</div>

</body>

</html>
