<?php

session_start();
require_once '../middleware/staff.php';
require_once '../config/database.php';

$stmt = $pdo->prepare('SELECT e.employee_id,e.department_id,e.firstname,e.lastname,u.email,d.department_name,f.faculty_name
    FROM employee e
    JOIN users u ON u.user_id=e.user_id
    LEFT JOIN department d ON d.department_id=e.department_id
    LEFT JOIN faculty f ON f.faculty_id=d.faculty_id
    WHERE e.user_id=? AND e.employee_type IN ("staff","teacher") LIMIT 1');
$stmt->execute([$_SESSION['user_id']]);
$staff = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$staff) {
    http_response_code(403);
    renderPlainErrorAlert('ไม่พบข้อมูลเจ้าหน้าที่');
}

$staff_name = trim($staff['firstname'] . ' ' . $staff['lastname']);
$pageHeading = 'แผงควบคุมเจ้าหน้าที่';
?>
<!doctype html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>เจ้าหน้าที่</title>
    <link rel="icon" type="image/png" href="/Project/assets/images/fet-logo-transparent.png?v=20260815-2">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
</head>
<body class="min-h-screen bg-gray-100 text-gray-900">
<?php include 'sidebar.php'; ?>
<div class="min-h-screen lg:ml-72">
    <?php include 'navbar.php'; ?>
    <main class="p-4 sm:p-6 lg:p-8">
        <section class="management-hero">
            <span class="management-hero__icon"><i class="fa-solid fa-user-tie"></i></span>
            <div class="min-w-0 flex-1">
                <p class="management-hero__eyebrow">ข้อมูลผู้ใช้งาน</p>
                <h1>เจ้าหน้าที่สาขา</h1>
                <div class="mt-4 grid gap-2.5 text-sm text-slate-600 sm:grid-cols-2">
                    <p class="flex items-center gap-2"><i class="fa-solid fa-building-columns w-4 text-red-600"></i><span><?= htmlspecialchars($staff['faculty_name'] ?? '-') ?></span></p>
                    <p class="flex items-center gap-2"><i class="fa-solid fa-building w-4 text-red-600"></i><span><?= htmlspecialchars($staff['department_name'] ?? '-') ?></span></p>
                    <p class="flex items-center gap-2 sm:col-span-2"><i class="fa-solid fa-envelope w-4 text-red-600"></i><span><?= htmlspecialchars($staff['email'] ?? '-') ?></span></p>
                </div>
            </div>
            <img src="/Project/assets/images/fet-logo-transparent.png" alt="โลโก้คณะวิศวกรรมศาสตร์และเทคโนโลยี" class="hidden h-20 w-auto object-contain opacity-90 lg:block">
        </section>

        <section class="mt-6 border-t border-gray-200 pt-6">
            <div class="mb-4 flex items-center justify-between"><div><h2 class="text-xl font-bold">การทำงานหลัก</h2><p class="text-sm text-gray-500">ทางลัดสำหรับงานที่ใช้งานเป็นประจำ</p></div></div>
            <div class="grid gap-3 md:grid-cols-3">
                <a href="documents.php" class="flex items-center gap-4 rounded-lg bg-white p-4 shadow-sm"><i class="fa-solid fa-file-arrow-down text-2xl text-red-600"></i><div><strong>ตรวจเอกสารสหกิจ</strong><p class="text-sm text-gray-500">ดู PDF และส่งออกรายการ</p></div></a>
                <a href="teachers.php" class="flex items-center gap-4 rounded-lg bg-white p-4 shadow-sm"><i class="fa-solid fa-user-plus text-2xl text-blue-600"></i><div><strong>จัดการอาจารย์</strong><p class="text-sm text-gray-500">เพิ่ม แก้ไข หรือปิดบัญชี</p></div></a>
                <a href="../auth/forgot_password.php" class="flex items-center gap-4 rounded-lg bg-white p-4 shadow-sm"><i class="fa-solid fa-envelope-circle-check text-2xl text-amber-600"></i><div><strong>เปลี่ยนผ่าน OTP</strong><p class="text-sm text-gray-500">ยืนยันผ่านอีเมลของคุณ</p></div></a>
            </div>
        </section>
    </main>
</div>
</body>
</html>
