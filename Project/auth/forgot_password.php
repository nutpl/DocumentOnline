<?php
session_start();
date_default_timezone_set('Asia/Bangkok');

require_once "../config/mail.php";

require_once "../config/database.php";
require_once "../helpers/csrf_helper.php";

$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    verify_csrf_or_abort();

    $email = trim($_POST['email'] ?? '');

    if ($email === '') {

        $error = "กรุณากรอก Email";

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

        $error = "รูปแบบอีเมลไม่ถูกต้อง";

    } else {

        $stmt = $pdo->prepare("

            SELECT user_id, email

            FROM users

            WHERE email = ?

            AND status = 'active'

        ");

        $stmt->execute([
            $email
        ]);

        $user = $stmt->fetch(PDO::FETCH_ASSOC);


        if (!$user) {

            $error = "ไม่สามารถดำเนินการได้ กรุณาตรวจสอบอีเมลหรือสถานะบัญชี";

        } else {


            /*
            |--------------------------------------------------------------------------
            | สร้าง OTP 6 หลัก
            |--------------------------------------------------------------------------
            */

            $otp = str_pad(

                random_int(
                    0,
                    999999
                ),

                6,

                '0',

                STR_PAD_LEFT

            );


            /*
            |--------------------------------------------------------------------------
            | OTP หมดอายุใน 5 นาที
            |--------------------------------------------------------------------------
            */

            $expire_at = date(
    'Y-m-d H:i:s',
    time() + 300
);


            /*
            |--------------------------------------------------------------------------
            | ทำให้ OTP เก่าหมดอายุ
            |--------------------------------------------------------------------------
            */

            $stmt = $pdo->prepare("

                UPDATE password_reset_otp

                SET used = 1

                WHERE user_id = ?

                AND used = 0

            ");

            $stmt->execute([

                $user['user_id']

            ]);


            /*
            |--------------------------------------------------------------------------
            | บันทึก OTP ใหม่
            |--------------------------------------------------------------------------
            */

            $stmt = $pdo->prepare("

                INSERT INTO password_reset_otp

                (
                    user_id,
                    otp_code,
                    expire_at,
                    used
                )

                VALUES (?, ?, ?, 0)

            ");

            $stmt->execute([

                $user['user_id'],

                $otp,

                $expire_at

            ]);

            $otp_id = (int) $pdo->lastInsertId();


            /*
|-------------------------------------------------------------------------- 
| ส่ง OTP ไปยัง Email ที่ผู้ใช้กรอก
|-------------------------------------------------------------------------- 
*/

$email_sent =
    sendOTPEmail(
        $user['email'],
        $otp,
        $expire_at
    );

if (!$email_sent) {

    $pdo->prepare("UPDATE password_reset_otp SET used=1 WHERE otp_id=?")->execute([$otp_id]);

    $error =
        "ไม่สามารถส่ง OTP ไปยัง Email ได้";

} else {

    /*
    |-------------------------------------------------------------------------- 
    | เก็บข้อมูล Session
    |-------------------------------------------------------------------------- 
    */

    $_SESSION['reset_user_id'] =
        $user['user_id'];

    $_SESSION['reset_email'] =
        $user['email'];

    unset(
        $_SESSION['first_login_user_id'],
        $_SESSION['first_login_email'],
        $_SESSION['first_login_verified']
    );

    /*
    |-------------------------------------------------------------------------- 
    | ไปหน้าตรวจสอบ OTP
    |-------------------------------------------------------------------------- 
    */

    header(
        "Location: verify_otp.php"
    );

    exit;
}

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
ลืมรหัสผ่าน
</title>

<script src="https://cdn.tailwindcss.com"></script>
<link rel="stylesheet" href="/Project/assets/css/app-ui.css?v=20260902-2">
<link rel="stylesheet" href="/Project/assets/css/auth-index-modal.css?v=20260918-1">
<link rel="stylesheet" href="/Project/assets/css/responsive.css?v=20260910-1">
<script src="/Project/assets/js/unified-page-title.js?v=20260902-2" defer></script>

<link
rel="stylesheet"
href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">

</head>


<body class="auth-index-modal flex min-h-screen items-center justify-center p-4">
<?php include __DIR__ . '/auth_index_modal_background.php'; ?>
<div class="w-full max-w-md">
    <div class="rounded-xl bg-white p-7 shadow-xl sm:p-8">
        <div class="mb-3 flex flex-col items-center">
            <div class="flex h-[120px] w-[120px] items-center justify-center">
                <img src="/Project/assets/images/rmuti-university-seal.png" alt="ตรามหาวิทยาลัยเทคโนโลยีราชมงคล" class="h-full w-full object-contain">
            </div>
            <p class="mt-1.5 text-center text-[11px] font-bold tracking-wide text-slate-500">มหาวิทยาลัยเทคโนโลยีราชมงคลอีสาน</p>
        </div>

        <h1 class="mb-1 text-center text-2xl font-bold">ลืมรหัสผ่าน</h1>
        <p class="mb-5 text-center text-sm text-gray-500">กรอกอีเมลที่ใช้เข้าสู่ระบบ เพื่อรับรหัส OTP สำหรับตั้งรหัสผ่านใหม่</p>

        <?php if ($error !== ''): ?>
            <div class="mb-5 rounded-lg bg-red-50 p-3 text-sm text-red-700">
                <i class="fa-solid fa-circle-exclamation mr-1"></i><?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form method="POST" class="space-y-5">
            <?= csrf_field() ?>
            <div>
                <label for="reset-email" class="mb-2 block font-medium">อีเมล</label>
                <input id="reset-email" type="email" name="email" required autocomplete="email" placeholder="name@rmuti.ac.th" class="w-full rounded-lg border p-3">
                <p class="mt-2 text-sm text-red-600"><i class="fa-solid fa-circle-info mr-1"></i>ใช้อีเมลมหาวิทยาลัย @rmuti.ac.th เท่านั้น</p>
            </div>

            <button type="submit" class="w-full rounded-lg bg-red-600 py-3 text-white transition hover:bg-red-700">
                <i class="fa-solid fa-paper-plane mr-2"></i>ส่งรหัส OTP
            </button>
        </form>

        <div class="mt-4 border-t border-gray-200 pt-3 text-center">
            <a href="login.php" class="inline-flex items-center justify-center gap-2 rounded-lg px-3 py-1.5 text-sm font-semibold text-gray-600 transition hover:bg-gray-100 hover:text-red-700">
                <i></i>กลับหน้าเข้าสู่ระบบ
            </a>
        </div>
    </div>
</div>

</body>

</html>
