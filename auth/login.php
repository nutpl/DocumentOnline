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
    <title>เข้าสู่ระบบ</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="/Project/assets/css/app-ui.css?v=20260902-2">
    <link rel="stylesheet" href="/Project/assets/css/responsive.css?v=20260915-1">
    <link rel="stylesheet" href="/Project/assets/css/auth-campus-background.css?v=20260917-1">
    <link rel="stylesheet" href="/Project/assets/css/auth-index-modal.css?v=20260918-1">
    <script src="/Project/assets/js/unified-page-title.js?v=20260902-2" defer></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="/Project/assets/js/password-visibility.js?v=20260901-1" defer></script>
</head>
<body class="auth-campus-page auth-index-modal flex items-center justify-center p-4">
<?php include __DIR__ . '/auth_index_modal_background.php'; ?>
<div class="w-full max-w-md">
    <div class="auth-campus-card rounded-xl p-5 sm:p-6">
        <div class="mb-2 flex flex-col items-center">
            <div class="flex h-[120px] w-[120px] items-center justify-center">
                <img src="/Project/assets/images/rmuti-university-seal.png" alt="ตรามหาวิทยาลัยเทคโนโลยีราชมงคล" class="h-full w-full object-contain">
            </div>
            <p class="mt-1 text-center text-[11px] font-bold tracking-wide text-slate-500">มหาวิทยาลัยเทคโนโลยีราชมงคลอีสาน</p>
        </div>
        <h3 class="text-2xl font-bold text-center mb-1">เข้าสู่ระบบ</h3>
        <p class="mb-4 text-center text-sm text-gray-500">ระบบจัดการเอกสารคำร้องของคณะผ่านเว็บแอปพลิเคชัน</p>

        <form action="login_process.php" method="POST" class="space-y-4">
            <?= csrf_field() ?>
            <div>
                <label class="block mb-2 font-medium" for="login-email">อีเมล</label>
                <input id="login-email" type="email" name="email" required autocomplete="username" class="w-full border rounded-lg px-3 py-2.5" placeholder="name@rmuti.ac.th">
                <p class="mt-1.5 text-xs text-red-600">
                    <i class="fa-solid fa-circle-info mr-1"></i>ใช้อีเมลมหาวิทยาลัย @rmuti.ac.th เท่านั้น
                </p>
            </div>
            <div>
                <label class="block mb-2 font-medium" for="login-password">รหัสผ่าน</label>
                <input id="login-password" type="password" name="password" required autocomplete="current-password" class="w-full border rounded-lg px-3 py-2.5">
            </div>
            <button type="submit" class="w-full rounded-lg bg-red-600 py-2.5 text-white hover:bg-red-700">เข้าสู่ระบบ</button>
            <div class="text-center"><a href="forgot_password.php" class="text-sm font-semibold text-red-600 hover:underline">ลืมรหัสผ่าน</a></div>
        </form>

        <div class="mt-3 grid gap-1 border-t border-gray-200 pt-2 text-center sm:grid-cols-2">
            <a href="register.php" class="rounded-lg px-3 py-1.5 text-sm font-semibold text-red-600 transition hover:bg-red-50 hover:underline">สมัครสมาชิกสำหรับนักศึกษา</a>
            <a href="employee_register.php" class="rounded-lg px-3 py-1.5 text-sm font-semibold text-red-600 transition hover:bg-red-50 hover:underline">ลงทะเบียนอาจารย์ / เจ้าหน้าที่</a>
        </div>
        <div class="mt-2 text-center">
            <a href="../index.php" class="inline-flex items-center justify-center gap-2 rounded-lg px-3 py-1 text-sm font-semibold text-gray-600 transition hover:bg-gray-100 hover:text-red-700">
                <i></i>กลับหน้าหลัก
            </a>
        </div>
    </div>
</div>

<script>
function showAuthAlert(options) {
    return Swal.fire({
        ...options,
        customClass: { popup: 'auth-alert-popup' },
        didOpen: (popup) => {
            const viewportHeight = window.visualViewport?.height || window.innerHeight;
            popup.style.setProperty('height', 'auto', 'important');
            popup.style.setProperty('min-height', '0', 'important');
            popup.style.setProperty('max-height', Math.max(260, viewportHeight - 32) + 'px', 'important');
            popup.style.setProperty('overflow-y', 'auto', 'important');
            const container = popup.closest('.swal2-container');
            if (container) container.style.setProperty('align-items', 'center', 'important');
        }
    });
}
</script>

<?php if (isset($_SESSION['error'])): ?>
<script>
showAuthAlert({icon: 'error', title: 'ดำเนินการไม่สำเร็จ', text: <?= json_encode($_SESSION['error'], JSON_UNESCAPED_UNICODE) ?>, confirmButtonColor: '#dc2626'});
</script>
<?php unset($_SESSION['error']); endif; ?>

<?php if (isset($_SESSION['success'])): ?>
<script>
showAuthAlert({icon: 'success', title: <?= json_encode($_SESSION['success'], JSON_UNESCAPED_UNICODE) ?>, confirmButtonColor: '#dc2626'});
</script>
<?php unset($_SESSION['success']); endif; ?>

<?php if (($_GET['reason'] ?? '') === 'session_replaced'): ?>
<script>
showAuthAlert({
    icon: 'warning',
    title: 'Session นี้สิ้นสุดแล้ว',
    text: 'บัญชีนี้มีการเข้าสู่ระบบจากอุปกรณ์อื่น กรุณาเข้าสู่ระบบใหม่',
    confirmButtonColor: '#dc2626'
});
</script>
<?php endif; ?>
</body>
</html>
