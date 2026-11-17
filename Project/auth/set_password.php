<?php

session_start();

require_once "../config/database.php";
require_once "../helpers/password_helper.php";
require_once "../helpers/csrf_helper.php";


/*
    ตรวจสอบว่ามีสิทธิ์ตั้งรหัสผ่านหรือไม่
*/

if (
    !isset($_SESSION['first_login_user_id'])
) {

    header(
        "Location: login.php"
    );

    exit;

}


$user_id =
    $_SESSION['first_login_user_id'];


$error = "";

$success = "";


/*
    เมื่อกดบันทึก
*/

if (
    $_SERVER['REQUEST_METHOD']
    === 'POST'
) {

    verify_csrf_or_abort();


    $password =
        trim(
            $_POST['password']
            ?? ''
        );


    $confirm_password =
        trim(
            $_POST['confirm_password']
            ?? ''
        );


    if (
        $password === ''
    ) {

        $error =
            "กรุณากรอกรหัสผ่าน";

    } elseif (
        strlen($password) < 6
    ) {

        $error =
            "รหัสผ่านต้องมีอย่างน้อย 6 ตัวอักษร";

    } elseif (
        $password !==
        $confirm_password
    ) {

        $error =
            "รหัสผ่านไม่ตรงกัน";

    } else {


        /*
            บันทึกรหัสผ่าน
        */

        $stmt = $pdo->prepare("

            UPDATE users

            SET
                password = ?,
                first_login = 0

            WHERE user_id = ?

        ");


        $stmt->execute([

            prepareUserPasswordForStorage($password),
            $user_id

        ]);


        /*
            ล้าง Session First Login
        */

        unset(

            $_SESSION[
                'first_login_user_id'
            ],

            $_SESSION[
                'first_login_email'
            ]

        );


        $success =
            "ตั้งรหัสผ่านสำเร็จ";


        /*
            กลับไปหน้า Login
        */

        header(
            "refresh:2;url=login.php"
        );

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

ตั้งรหัสผ่าน

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

<body
class="auth-index-modal">
<?php include __DIR__ . '/auth_index_modal_background.php'; ?>

<div
class="min-h-screen
flex
items-center
justify-center">

<div
class="bg-white
rounded-xl
shadow-xl
p-8
w-full
max-w-md">

<h1
class="text-center
text-3xl
font-bold
text-red-600">

RMUTI

</h1>

<p
class="text-center
text-gray-500
mt-2">

Document Management System

</p>

<h2
class="text-center
text-2xl
font-semibold
mt-6">

ตั้งรหัสผ่าน

</h2>

<p
class="text-center
text-gray-500
mt-2">

กรุณาตั้งรหัสผ่านสำหรับการเข้าสู่ระบบ

</p>

<?php if (
    $error !== ''
): ?>

<div
class="bg-red-100
text-red-700
rounded-lg
p-3
mt-4">

<?= htmlspecialchars(
    $error
) ?>

</div>

<?php endif; ?>

<?php if (
    $success !== ''
): ?>

<div
class="bg-green-100
text-green-700
rounded-lg
p-3
mt-4">

<?= htmlspecialchars(
    $success
) ?>

</div>

<?php endif; ?>

<form
method="POST"
class="space-y-4
mt-6">

<?= csrf_field() ?>

<div>

<label
class="block
mb-2">

รหัสผ่านใหม่

</label>

<input

type="password"

name="password"

required

class="w-full
border
rounded-lg
p-3"

placeholder="อย่างน้อย 6 ตัวอักษร">

</div>

<div>

<label
class="block
mb-2">

ยืนยันรหัสผ่านใหม่

</label>

<input

type="password"

name="confirm_password"

required

class="w-full
border
rounded-lg
p-3"

placeholder="กรอกรหัสผ่านอีกครั้ง">

</div>

<button

type="submit"

class="w-full
bg-red-600
hover:bg-red-700
text-white
rounded-lg
py-3">

<i
class="fa-solid
fa-key
mr-2">

</i>

ตั้งรหัสผ่าน

</button>

</form>

</div>

</div>

</body>

</html>
