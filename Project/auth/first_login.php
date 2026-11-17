<?php
session_start();
require_once '../helpers/csrf_helper.php';
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <link rel="icon" type="image/png" href="/Project/assets/images/fet-logo-transparent.png?v=20260815-3">


<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<link rel="stylesheet" href="/Project/assets/css/responsive.css?v=20260910-2">

<title>เข้าใช้งานครั้งแรก</title>

<script src="https://cdn.tailwindcss.com"></script>
<link rel="stylesheet" href="/Project/assets/css/app-ui.css?v=20260902-2">
<link rel="stylesheet" href="/Project/assets/css/auth-index-modal.css?v=20260918-1">
<script src="/Project/assets/js/unified-page-title.js?v=20260902-2" defer></script>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

</head>

<body class="auth-index-modal">
<?php include __DIR__ . '/auth_index_modal_background.php'; ?>

<div class="min-h-screen flex items-center justify-center p-4">

<div class="bg-white w-full max-w-md rounded-xl shadow-lg p-5 sm:p-8">

<h1 class="text-3xl font-bold text-center mb-2">

เข้าใช้งานครั้งแรก

</h1>

<p class="text-center text-gray-500 mb-6">

สำหรับอาจารย์และเจ้าหน้าที่

</p>

<form action="send_otp.php" method="POST">
<?= csrf_field() ?>
<input type="hidden" name="source" value="first-login">

<div class="mb-5">

<label class="block mb-2">

Email

</label>

<input
type="email"
name="email"
required
pattern=".+@rmuti\.ac\.th$"
placeholder="example@rmuti.ac.th"
class="w-full border rounded-lg p-3">

</div>

<button
type="submit"
class="w-full bg-red-600 hover:bg-red-700 text-white py-3 rounded-lg">

ส่ง OTP

</button>

</form>

<div class="text-center mt-5">

<a
href="login.php"
class="text-red-600 hover:underline">

กลับหน้า Login

</a>

</div>

</div>

</div>

<?php if(isset($_SESSION['error'])): ?>

<script>

Swal.fire({

icon:"error",

title:"เกิดข้อผิดพลาด",

text:"<?= addslashes($_SESSION['error']) ?>"

});

</script>

<?php
unset($_SESSION['error']);
endif;
?>

<?php if(isset($_SESSION['success'])): ?>

<script>

Swal.fire({

icon:"success",

title:"สำเร็จ",

text:"<?= addslashes($_SESSION['success']) ?>"

});

</script>

<?php
unset($_SESSION['success']);
endif;
?>

</body>

</html>
