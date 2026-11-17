<?php

session_start();

date_default_timezone_set('Asia/Bangkok');

require_once "../config/database.php";
require_once "../config/mail.php";
require_once "../helpers/csrf_helper.php";

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: login.php?mode=first-login');
    exit;
}

verify_csrf_or_abort();

$email = trim($_POST['email'] ?? '');
$source = $_POST['source'] ?? 'first-login';
$errorLocation = $source === 'employee-register'
    ? 'employee_register.php'
    : 'first_login.php';

if ($email == "") {

    $_SESSION['error'] = "กรุณากรอก Email";

    header("Location: " . $errorLocation);
    exit;

}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $_SESSION['error'] = "รูปแบบอีเมลไม่ถูกต้อง";
    header("Location: " . $errorLocation);
    exit;
}

if (!preg_match('/@rmuti\\.ac\\.th$/i', $email)) {
    $_SESSION['error'] = "กรุณาใช้อีเมลมหาวิทยาลัย @rmuti.ac.th เท่านั้น";
    header("Location: " . $errorLocation);
    exit;
}

/*
|--------------------------------------------------------------------------
| ค้นหาผู้ใช้
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("

SELECT

    u.user_id,
    u.email,
    u.status,
    u.first_login,
    ur.role

FROM users u

LEFT JOIN user_roles ur
ON u.user_id = ur.user_id

WHERE u.email = ?

LIMIT 1

");

$stmt->execute([$email]);

$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {

    $_SESSION['error'] = "ไม่สามารถส่ง OTP ได้ กรุณาตรวจสอบอีเมลหรือสถานะบัญชี";

    header("Location: " . $errorLocation);
    exit;

}

/*
|--------------------------------------------------------------------------
| ตรวจสอบ Role
|--------------------------------------------------------------------------
*/

if (
    $user['role'] != "teacher" &&
    $user['role'] != "staff"
) {

    $_SESSION['error'] = "ไม่สามารถส่ง OTP ได้ กรุณาตรวจสอบอีเมลหรือสถานะบัญชี";

    header("Location: " . $errorLocation);
    exit;

}

/*
|--------------------------------------------------------------------------
| ตรวจสอบสถานะ
|--------------------------------------------------------------------------
*/

if ($user['status'] != "active") {

    $_SESSION['error'] = "ไม่สามารถส่ง OTP ได้ กรุณาตรวจสอบอีเมลหรือสถานะบัญชี";

    header("Location: " . $errorLocation);
    exit;

}

/*
|--------------------------------------------------------------------------
| ตรวจสอบ First Login
|--------------------------------------------------------------------------
*/

if ((int) $user['first_login'] !== 1) {

    $_SESSION['error'] = "บัญชีนี้ได้ตั้งรหัสผ่านแล้ว";

    header("Location: login.php?mode=first-login");
    exit;

}

/*
|--------------------------------------------------------------------------
| สร้าง OTP
|--------------------------------------------------------------------------
*/

$otp = str_pad(
    random_int(0,999999),
    6,
    "0",
    STR_PAD_LEFT
);

$expire_at = date(
    "Y-m-d H:i:s",
    time()+300
);

/*
|--------------------------------------------------------------------------
| ปิด OTP เก่า
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("

UPDATE password_reset_otp

SET used=1

WHERE user_id=?

AND used=0

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

INSERT INTO password_reset_otp(

user_id,
otp_code,
expire_at,
used

)

VALUES(

?,
?,
?,
0

)

");

$stmt->execute([

$user['user_id'],
$otp,
$expire_at

]);

$otp_id = (int) $pdo->lastInsertId();

/*
|--------------------------------------------------------------------------
| ส่ง Email
|--------------------------------------------------------------------------
*/

$email_sent = sendOTPEmail(

    $user['email'],
    $otp,
    $expire_at

);

if(!$email_sent){

    $pdo->prepare("UPDATE password_reset_otp SET used=1 WHERE otp_id=?")->execute([$otp_id]);

    $_SESSION['error']="ไม่สามารถส่ง OTP ได้";

    header("Location:" . $errorLocation);

    exit;

}

/*
|--------------------------------------------------------------------------
| Session
|--------------------------------------------------------------------------
*/

$_SESSION['first_login_user_id']=$user['user_id'];

$_SESSION['first_login_email']=$user['email'];

$_SESSION['first_login_source'] = $source === 'employee-register' ? 'employee-register' : 'first-login';

unset(
    $_SESSION['reset_user_id'],
    $_SESSION['reset_email'],
    $_SESSION['verified_otp_id'],
    $_SESSION['reset_verified']
);

$_SESSION['success']="ส่ง OTP ไปยัง Email เรียบร้อยแล้ว";

/*
|--------------------------------------------------------------------------
| ไปหน้า Verify OTP
|--------------------------------------------------------------------------
*/

header("Location:verify_otp.php");

exit;
