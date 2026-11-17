<?php

session_start();

require_once "../config/database.php";
require_once "../helpers/csrf_helper.php";


// ตรวจสอบว่ามีการขอ OTP หรือไม่

if (

    isset($_SESSION['reset_user_id'])

){

    $user_id = $_SESSION['reset_user_id'];

    $mode = "forgot";

}

elseif(

    isset($_SESSION['first_login_user_id'])

){

    $user_id = $_SESSION['first_login_user_id'];

    $mode = "first_login";

}

else{

    header("Location: login.php");

    exit;

}


$error = "";
$otpDestinationEmail = $mode === 'forgot'
    ? (string) ($_SESSION['reset_email'] ?? '')
    : (string) ($_SESSION['first_login_email'] ?? '');
$firstLoginSource = ($_SESSION['first_login_source'] ?? '') === 'employee-register'
    ? 'employee-register'
    : 'first-login';

$timerStmt = $pdo->prepare("
    SELECT expire_at, GREATEST(TIMESTAMPDIFF(SECOND, NOW(), expire_at), 0) AS remaining_seconds
    FROM password_reset_otp
    WHERE user_id = ? AND used = 0
    ORDER BY otp_id DESC
    LIMIT 1
");
$timerStmt->execute([$user_id]);
$activeOtp = $timerStmt->fetch(PDO::FETCH_ASSOC);
$remainingSeconds = (int) ($activeOtp['remaining_seconds'] ?? 0);


if (
    $_SERVER['REQUEST_METHOD']
    === 'POST'
) {

    verify_csrf_or_abort();


    $otp =
        trim(
            $_POST['otp']
            ?? ''
        );


    if (
        !preg_match(
            '/^[0-9]{6}$/',
            $otp
        )
    ) {

        $error =
            "กรุณากรอก OTP 6 หลัก";

    } else {


        /*
        ตรวจสอบ OTP
        */

        $stmt =
            $pdo->prepare("

                SELECT *

                FROM password_reset_otp

                WHERE user_id = ?

                AND otp_code = ?

                AND used = 0

                AND expire_at > NOW()

                ORDER BY otp_id DESC

                LIMIT 1

            ");


        $stmt->execute([

            $user_id,

            $otp

        ]);


        $otp_data =
            $stmt->fetch(
                PDO::FETCH_ASSOC
            );


        if (!$otp_data) {

            $error =
                "OTP ไม่ถูกต้องหรือหมดอายุแล้ว";

        } else {


            /*
            OTP ถูกต้อง
            */

if($mode=="forgot"){

    $_SESSION['verified_otp_id']=$otp_data['otp_id'];

    $_SESSION['reset_verified']=true;

    header("Location: reset_password.php");

    exit;

}

else{

    $stmt = $pdo->prepare("UPDATE password_reset_otp SET used=1 WHERE otp_id=?");
    $stmt->execute([$otp_data['otp_id']]);

    $_SESSION['first_login_verified']=true;

    header("Location: create_password.php");

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
ยืนยัน OTP
</title>

<script src="https://cdn.tailwindcss.com"></script>
<link rel="stylesheet" href="/Project/assets/css/app-ui.css?v=20260902-2">
<link rel="stylesheet" href="/Project/assets/css/auth-index-modal.css?v=20260918-1">
<script src="/Project/assets/js/unified-page-title.js?v=20260902-2" defer></script>
<link rel="stylesheet" href="/Project/assets/css/responsive.css?v=20260910-2">

<link
rel="stylesheet"
href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">

</head>


<body class="auth-index-modal min-h-screen p-4">
<?php include __DIR__ . '/auth_index_modal_background.php'; ?>
<main class="flex min-h-[calc(100vh-2rem)] items-center justify-center">
<section class="w-full max-w-md rounded-xl bg-white p-5 shadow-xl sm:p-6">
    <div class="mb-3 flex flex-col items-center">
        <div class="flex h-[120px] w-[120px] items-center justify-center">
            <img src="/Project/assets/images/rmuti-university-seal.png" alt="ตรามหาวิทยาลัยเทคโนโลยีราชมงคลอีสาน" class="h-full w-full object-contain">
        </div>
        <p class="mt-1 text-center text-[11px] font-bold tracking-wide text-slate-500">มหาวิทยาลัยเทคโนโลยีราชมงคลอีสาน</p>
    </div>
    <h1 class="text-center text-2xl font-bold text-slate-900">ยืนยันรหัส OTP</h1>
    <p class="mt-1 text-center text-sm text-gray-500">กรอกรหัสยืนยัน 6 หลักที่ระบบส่งให้</p>

<?php if ($otpDestinationEmail !== ''): ?>
<div class="mt-4 rounded-lg border border-sky-200 bg-sky-50 px-4 py-3 text-center">
    <p class="text-xs font-semibold text-slate-600">ระบบส่งรหัส OTP ไปที่อีเมล</p>
    <p class="mt-1 break-all text-sm font-bold text-sky-800"><i class="fa-solid fa-envelope mr-1"></i><?= htmlspecialchars($otpDestinationEmail) ?></p>
</div>
<?php endif; ?>

<div id="otp-timer-box" class="mt-5 rounded-lg border border-amber-200 bg-amber-50 p-4 text-center">
    <p class="text-sm text-amber-800">รหัส OTP จะหมดอายุใน</p>
    <p id="otp-timer" class="mt-1 text-3xl font-bold tabular-nums text-red-600">--:--</p>
    <p id="otp-expired-message" class="mt-1 hidden text-sm font-semibold text-red-700">OTP หมดอายุแล้ว กรุณาขอรหัสใหม่</p>
</div>


<?php if (
    $error !== ''
): ?>

<div
class="bg-red-100
       text-red-700
       rounded-lg
       p-3
       mt-4">

<?= htmlspecialchars($error) ?>

</div>

<?php endif; ?>




<form id="otp-form" method="POST" class="mt-6 space-y-5">
    <?= csrf_field() ?>
    <input id="otp-input" type="hidden" name="otp" pattern="[0-9]{6}" required>
    <div>
        <label class="mb-3 block text-center text-sm font-semibold text-slate-700">รหัส OTP 6 หลัก</label>
        <div id="otp-digits" class="flex justify-center gap-2 sm:gap-3" aria-label="กรอกรหัส OTP 6 หลัก">
            <?php for ($digitIndex = 0; $digitIndex < 6; $digitIndex++): ?>
                <input type="text" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]" maxlength="1" class="otp-digit h-12 w-10 rounded-lg border border-slate-300 bg-white text-center text-xl font-bold text-slate-900 outline-none transition focus:border-red-500 focus:ring-2 focus:ring-red-100 sm:h-14 sm:w-12" <?= $digitIndex === 0 ? 'autofocus' : '' ?> aria-label="OTP หลักที่ <?= $digitIndex + 1 ?>">
            <?php endfor; ?>
        </div>
        <p class="mt-3 text-center text-xs text-slate-500">สามารถวางรหัส OTP ทั้ง 6 หลักได้</p>
    </div>
    <button id="verify-otp-button" type="submit" class="w-full rounded-lg bg-red-600 py-3 font-semibold text-white transition hover:bg-red-700"><i class="fa-solid fa-check mr-2"></i>ยืนยัน OTP</button>
</form>


<div
class="text-center
       mt-5">


<?php if ($mode === 'first_login'): ?>
<form action="send_otp.php" method="POST">
    <?= csrf_field() ?>
    <input type="hidden" name="source" value="<?= htmlspecialchars($firstLoginSource) ?>">
    <input type="hidden" name="email" value="<?= htmlspecialchars($_SESSION['first_login_email'] ?? '') ?>">
    <button type="submit" class="text-blue-600 hover:underline">ขอ OTP ใหม่</button>
</form>
<?php else: ?>
<a href="forgot_password.php" class="text-blue-600 hover:underline">ขอ OTP ใหม่</a>
<?php endif; ?>


</div>


</section>
</main>

<script>
(() => {
    let remaining = <?= $remainingSeconds ?>;
    const timer = document.getElementById('otp-timer');
    const expiredMessage = document.getElementById('otp-expired-message');
    const input = document.getElementById('otp-input');
    const digitInputs = [...document.querySelectorAll('.otp-digit')];
    const form = document.getElementById('otp-form');
    const button = document.getElementById('verify-otp-button');

    const onlyDigits = (value) => String(value || '').replace(/\D/g, '');
    const syncOtp = () => {
        input.value = digitInputs.map((field) => field.value).join('');
    };
    const fillOtp = (value) => {
        onlyDigits(value).slice(0, 6).split('').forEach((digit, index) => {
            if (digitInputs[index]) digitInputs[index].value = digit;
        });
        syncOtp();
    };

    digitInputs.forEach((field, index) => {
        field.addEventListener('input', () => {
            const value = onlyDigits(field.value);
            if (value.length > 1) {
                fillOtp(value + digitInputs.slice(index + 1).map((next) => next.value).join(''));
                const target = digitInputs[Math.min(5, index + value.length - 1)];
                target?.focus();
                return;
            }
            field.value = value.slice(0, 1);
            syncOtp();
            if (field.value && index < digitInputs.length - 1) digitInputs[index + 1].focus();
        });
        field.addEventListener('keydown', (event) => {
            if (event.key === 'Backspace' && !field.value && index > 0) {
                digitInputs[index - 1].focus();
                digitInputs[index - 1].select();
            }
            if (event.key === 'ArrowLeft' && index > 0) digitInputs[index - 1].focus();
            if (event.key === 'ArrowRight' && index < digitInputs.length - 1) digitInputs[index + 1].focus();
        });
        field.addEventListener('paste', (event) => {
            event.preventDefault();
            fillOtp(event.clipboardData?.getData('text') || '');
            digitInputs[Math.min(5, onlyDigits(event.clipboardData?.getData('text') || '').length - 1)]?.focus();
        });
    });
    form?.addEventListener('submit', (event) => {
        syncOtp();
        if (!/^[0-9]{6}$/.test(input.value)) {
            event.preventDefault();
            digitInputs.find((field) => !field.value)?.focus();
        }
    });

    function renderTimer() {
        const minutes = Math.floor(remaining / 60);
        const seconds = remaining % 60;
        timer.textContent = `${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
        if (remaining <= 0) {
            timer.textContent = '00:00';
            timer.classList.add('text-gray-400');
            expiredMessage.classList.remove('hidden');
            digitInputs.forEach((field) => { field.disabled = true; });
            button.disabled = true;
            button.classList.add('cursor-not-allowed', 'opacity-50');
            return false;
        }
        return true;
    }

    if (renderTimer()) {
        const interval = setInterval(() => {
            remaining -= 1;
            if (!renderTimer()) clearInterval(interval);
        }, 1000);
    }
})();
</script>
</body>

</html>
