<?php

session_start();
require_once "../config/database.php";
require_once "../helpers/activity_log_helper.php";
require_once "../helpers/session_security_helper.php";

$logoutUserId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
if ($logoutUserId) {
    logActivity($pdo, $logoutUserId, 'logout', 'ออกจากระบบ');
    revokeUserSessionToken($pdo, $logoutUserId, (string) ($_SESSION['session_token'] ?? ''));
}

/*
|--------------------------------------------------------------------------
| ล้างข้อมูล Session ทั้งหมด
|--------------------------------------------------------------------------
*/

$_SESSION = [];

/*
|--------------------------------------------------------------------------
| ลบ Session Cookie
|--------------------------------------------------------------------------
*/

if (ini_get("session.use_cookies")) {

    $params = session_get_cookie_params();

    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );

}

/*
|--------------------------------------------------------------------------
| ทำลาย Session
|--------------------------------------------------------------------------
*/

session_destroy();

/*
|--------------------------------------------------------------------------
| กลับไปหน้าเว็บไซต์หลัก
|--------------------------------------------------------------------------
*/

header("Location: ../index.php");

exit;
