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
    <title>ลงทะเบียนอาจารย์และเจ้าหน้าที่</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="/Project/assets/css/app-ui.css?v=20260902-2">
    <link rel="stylesheet" href="/Project/assets/css/responsive.css?v=20260910-1">
    <link rel="stylesheet" href="/Project/assets/css/auth-campus-background.css?v=20260917-1">
    <link rel="stylesheet" href="/Project/assets/css/auth-index-modal.css?v=20260918-1">
    <script src="/Project/assets/js/unified-page-title.js?v=20260902-2" defer></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body class="auth-campus-page auth-index-modal p-4">
<?php include __DIR__ . '/auth_index_modal_background.php'; ?>
    <main class="flex min-h-[calc(100vh-2rem)] items-center justify-center">
        <section class="auth-campus-card w-full max-w-md rounded-xl p-7 sm:p-8">
            <div class="mb-3 flex flex-col items-center">
                <div class="flex h-[120px] w-[120px] items-center justify-center">
                    <img src="/Project/assets/images/rmuti-university-seal.png" alt="ตรามหาวิทยาลัยเทคโนโลยีราชมงคลอีสาน" class="h-full w-full object-contain">
                </div>
                <p class="mt-1.5 text-center text-[11px] font-bold tracking-wide text-slate-500">มหาวิทยาลัยเทคโนโลยีราชมงคลอีสาน</p>
            </div>
            <h1 class="mb-1 text-center text-2xl font-bold text-slate-900">ลงทะเบียนเข้าใช้งานครั้งแรก</h1>
            <p class="mb-5 text-center text-sm text-gray-500">สำหรับอาจารย์และเจ้าหน้าที่ที่มีรายชื่อในระบบแล้ว</p>

            <div class="mb-5 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm leading-6 text-amber-900">
                <i class="fa-solid fa-circle-info mr-1"></i>
                กรุณาใช้อีเมลมหาวิทยาลัยเพื่อยืนยันตัวตน ระบบจะตรวจสอบรายชื่อและส่งรหัส OTP สำหรับตั้งรหัสผ่าน
            </div>

            <form action="send_otp.php" method="POST" class="space-y-5">
                <?= csrf_field() ?>
                <input type="hidden" name="source" value="employee-register">
                <div>
                    <label class="mb-2 block font-medium" for="email">อีเมลมหาวิทยาลัย</label>
                    <input id="email" type="email" name="email" required autocomplete="email" pattern=".+@rmuti\.ac\.th$" class="w-full rounded-lg border p-3" placeholder="name@rmuti.ac.th">
                    <p class="mt-2 text-sm text-red-600"><i class="fa-solid fa-circle-info mr-1"></i>ใช้อีเมล @rmuti.ac.th เท่านั้น</p>
                </div>
                <button type="submit" class="w-full rounded-lg bg-red-600 py-3 text-white transition hover:bg-red-700"><i class="fa-solid fa-envelope mr-2"></i>ส่งรหัสยืนยัน OTP</button>
            </form>

            <div class="mt-4 border-t border-gray-200 pt-3 text-center">
                <a href="login.php" class="inline-flex items-center gap-2 rounded-lg px-3 py-1.5 text-sm font-semibold text-gray-600 transition hover:bg-gray-100 hover:text-red-700"><i class="fa-solid fa-arrow-left"></i>กลับหน้าเข้าสู่ระบบ</a>
            </div>
        </section>
    </main>

<?php if (isset($_SESSION['error'])): ?>
<script>Swal.fire({icon:'error', title:'ดำเนินการไม่สำเร็จ', text:<?= json_encode($_SESSION['error'], JSON_UNESCAPED_UNICODE) ?>, confirmButtonColor:'#dc2626'});</script>
<?php unset($_SESSION['error']); endif; ?>
</body>
</html>
