<?php
session_start();
require "db.php";

if(!isset($_SESSION['user_id'])){
    header("Location: login.php");
    exit;
}

if($_SESSION['role'] != "staff"){
    die("ไม่มีสิทธิ์");
}

// ดึงข้อมูลเจ้าหน้าที่
$stmt = $pdo->prepare("
SELECT *
FROM staff
WHERE user_id=?
");

$stmt->execute([$_SESSION['user_id']]);

$staff = $stmt->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <link rel="icon" type="image/png" href="/Project/assets/images/fet-logo-transparent.png?v=20260815-3">

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="/Project/assets/css/responsive.css?v=20260911-2">
<script src="/Project/assets/js/responsive-tables.js?v=20260901-1" defer></script>
<title>Staff Dashboard</title>
<script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-gray-900 text-white">

<div class="flex flex-col md:flex-row">

<div class="w-full bg-black p-5 md:min-h-screen md:w-64">

<h1 class="text-3xl font-bold text-red-600">
RMUTI
</h1>

<p class="text-gray-400">
เจ้าหน้าที่สาขา
</p>

<hr class="my-5">

<a href="staff.php" class="block py-3 hover:text-red-500">
Dashboard
</a>

<a href="staff_documents.php" class="block py-3 hover:text-red-500">
เอกสารสหกิจ
</a>

<a href="change_password.php" class="block py-3 hover:text-red-500">
เปลี่ยนรหัสผ่าน
</a>

<a href="logout.php" class="block py-3 text-red-500">
Logout
</a>

</div>

<div class="flex-1 p-8">

<h2 class="text-3xl font-bold">
ยินดีต้อนรับ
<?= htmlspecialchars($staff['firstname']) ?>
</h2>

<p class="text-gray-400 mt-2">
สาขา : <?= htmlspecialchars($staff['department_id']) ?>
</p>

</div>

</div>

</body>
</html>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="/Project/assets/js/logout-confirm.js?v=20260911-2"></script>
