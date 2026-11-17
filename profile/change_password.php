<?php

session_start();

require_once "../config/database.php";
require_once "../helpers/password_helper.php";


/*
    ตรวจสอบว่ามีการ Login แล้วหรือไม่
*/

if (!isset($_SESSION['user_id'])) {

    header("Location: ../auth/login.php");

    exit;

}


$user_id = $_SESSION['user_id'];

$error = "";

$success = "";


/*
    เมื่อกดเปลี่ยนรหัสผ่าน
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {


    $current_password =
        trim($_POST['current_password'] ?? '');


    $new_password =
        trim($_POST['new_password'] ?? '');


    $confirm_password =
        trim($_POST['confirm_password'] ?? '');


    /*
        ดึงรหัสผ่านปัจจุบัน
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

        $error =
            "ไม่พบข้อมูลผู้ใช้งาน";


    } elseif (!verifyStoredUserPassword($current_password, (string)$user['password'])) {

        $error =
            "รหัสผ่านปัจจุบันไม่ถูกต้อง";


    } elseif (

        $new_password === ''

    ) {

        $error =
            "กรุณากรอกรหัสผ่านใหม่";


    } elseif (

        strlen($new_password) < 6

    ) {

        $error =
            "รหัสผ่านใหม่ต้องมีอย่างน้อย 6 ตัวอักษร";


    } elseif (

        $new_password
        !==
        $confirm_password

    ) {

        $error =
            "รหัสผ่านใหม่ไม่ตรงกัน";


    } elseif (

        $new_password
        ===
        $current_password

    ) {

        $error =
            "รหัสผ่านใหม่ต้องไม่เหมือนรหัสผ่านเดิม";


    } else {


        /*
            อัปเดตรหัสผ่าน
        */

        $stmt = $pdo->prepare("

            UPDATE users

            SET password = ?

            WHERE user_id = ?

        ");

        $stmt->execute([

            $new_password,
            $user_id

        ]);


        $success =
            "เปลี่ยนรหัสผ่านสำเร็จ";

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
<link rel="stylesheet" href="/Project/assets/css/responsive.css?v=20260910-1">


<link
rel="stylesheet"
href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">


</head>


<body class="bg-gray-100">


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


เปลี่ยนรหัสผ่าน


</h2>


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
class="space-y-4
mt-6">


<div>

<label
class="block
mb-2">


รหัสผ่านปัจจุบัน


</label>


<input

type="password"

name="current_password"

required

class="w-full
border
rounded-lg
p-3">


</div>


<div>

<label
class="block
mb-2">


รหัสผ่านใหม่


</label>


<input

type="password"

name="new_password"

required

class="w-full
border
rounded-lg
p-3">


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
p-3">


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


เปลี่ยนรหัสผ่าน


</button>


</form>


<div
class="text-center
mt-6">


<a

href="../index.php"

class="text-gray-600
hover:text-red-600">


กลับหน้าหลัก


</a>


</div>


</div>


</div>


</body>


</html>
