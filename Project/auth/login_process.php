<?php

session_start();

require_once "../config/database.php";
require_once "../helpers/activity_log_helper.php";
require_once "../helpers/session_security_helper.php";
require_once "../helpers/csrf_helper.php";
require_once "../helpers/password_helper.php";
require_once "../helpers/assistant_workspace_helper.php";

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: login.php');
    exit;
}

verify_csrf_or_abort();

$email = strtolower(trim($_POST['email'] ?? ''));
$password = trim($_POST['password'] ?? '');

if ($email === '' || $password === '') {

    $_SESSION['error'] = "กรุณากรอกอีเมลและรหัสผ่านให้ครบถ้วน";

    header("Location: login.php");
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

    $_SESSION['error'] = "รูปแบบอีเมลไม่ถูกต้อง";

    header("Location: login.php");
    exit;
}


/* ===============================
   ค้นหาผู้ใช้
================================ */

$stmt = $pdo->prepare("

SELECT

    u.*

FROM users u

WHERE u.email = ?

LIMIT 1

");

$stmt->execute([$email]);

$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {

    logActivity($pdo, null, 'login_failed', 'พยายามเข้าสู่ระบบด้วยอีเมลที่ไม่พบ');

    $_SESSION['error'] = "ไม่พบผู้ใช้งานจากอีเมลนี้";

    header("Location: login.php");
    exit;
}

// A user may have more than one explicitly assigned role. Never infer a role
// here: a missing role record must not grant any access.
$rolesStmt = $pdo->prepare('SELECT role FROM user_roles WHERE user_id=? ORDER BY FIELD(role,"teacher","staff","admin","student")');
$rolesStmt->execute([(int) $user['user_id']]);
$availableRoles = array_values(array_unique(array_column($rolesStmt->fetchAll(PDO::FETCH_ASSOC), 'role')));
if (!$availableRoles) {
    logActivity($pdo, (int) $user['user_id'], 'login_blocked', 'บัญชีไม่มีบทบาทสำหรับเข้าใช้งาน');
    $_SESSION['error'] = 'บัญชีนี้ยังไม่ได้กำหนดสิทธิ์เข้าใช้งาน';
    header('Location: login.php');
    exit;
}
$user['role'] = in_array('teacher', $availableRoles, true) ? 'teacher' : $availableRoles[0];

/*
|--------------------------------------------------------------------------
| ตรวจสอบการเข้าใช้งานครั้งแรก
|--------------------------------------------------------------------------
*/

if (

    ($user['role'] == "teacher" || $user['role'] == "staff")

    &&

    (
        (int) $user['first_login'] === 1
        || $user['password'] === null
        || str_starts_with((string) $user['password'], 'pending-pass')
    )

) {

    $_SESSION['email'] = $user['email'];

    $_SESSION['error'] = "บัญชีนี้ยังไม่ได้ตั้งรหัสผ่าน กรุณาเลือกเข้าใช้ครั้งแรกเพื่อรับ OTP";
    header("Location: login.php?mode=first-login");

    exit;

}

/* ===============================
   ตรวจสอบสถานะ
================================ */

if ($user['status'] != 'active') {

    logActivity($pdo, (int)$user['user_id'], 'login_blocked', 'บัญชีไม่ได้อยู่ในสถานะ active');

    $_SESSION['error'] = "บัญชีผู้ใช้นี้ถูกปิดการใช้งาน";

    header("Location: login.php");
    exit;
}


/* ===============================
   ตรวจสอบ Password
================================ */

if (!verifyStoredUserPassword($password, (string)$user['password'])) {

    logActivity($pdo, (int)$user['user_id'], 'login_failed', 'รหัสผ่านไม่ถูกต้อง');

    $_SESSION['error'] = "รหัสผ่านไม่ถูกต้อง";

    header("Location: login.php");
    exit;
}


/* ===============================
   Session
================================ */

// Replace the pre-authentication session ID after the credentials have been
// verified. This prevents an attacker from fixing a known session ID before
// the user signs in while preserving flash/session data used by the login flow.
session_regenerate_id(true);

try {
    $_SESSION['session_token'] = issueUserSessionToken($pdo, (int) $user['user_id']);
} catch (Throwable $exception) {
    $_SESSION['error'] = 'ไม่สามารถเริ่ม Session การใช้งานได้ กรุณาลองใหม่';
    header('Location: login.php');
    exit;
}

$_SESSION['user_id'] = $user['user_id'];

$_SESSION['role'] = $user['role'];

$_SESSION['roles'] = $availableRoles;

$_SESSION['active_role'] = $user['role'];

$_SESSION['assistant_workspace'] = isAssistantWorkspaceUser() && in_array($user['role'], ['admin', 'staff'], true);

$_SESSION['username'] = $user['username'];

$_SESSION['email'] = $user['email'];

$_SESSION['login_date'] = date('Y-m-d');

logActivity($pdo, (int)$user['user_id'], 'login_success', 'เข้าสู่ระบบในบทบาท ' . $user['role']);

if (!empty($_SESSION['assistant_workspace'])) {
    header('Location: ' . assistantWorkspaceRoute((string) $user['role']));
    exit;
}


/* ===============================
   Student
================================ */

if ($user['role'] == "student") {

    $stmt = $pdo->prepare("

    SELECT

        student_id,
        firstname,
        lastname,
        group_id

    FROM student

    WHERE user_id = ?

    ");

    $stmt->execute([
        $user['user_id']
    ]);

    $student = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($student) {

        $_SESSION['student_id'] = $student['student_id'];

        $_SESSION['fullname'] =
            $student['firstname'] . " " .
            $student['lastname'];

        $_SESSION['group_id'] =
            $student['group_id'];

    }

    header("Location: ../student/dashboard.php");
    exit;
}


/* ===============================
   Teacher
================================ */

if ($user['role'] == "teacher") {

    header("Location: ../teacher/dashboard.php");

    exit;

}


/* ===============================
   Staff
================================ */

if ($user['role'] == "staff") {

    header("Location: ../staff/dashboard.php");

    exit;

}


/* ===============================
   Admin
================================ */

if ($user['role'] == "admin") {

    header("Location: ../admin/dashboard.php");

    exit;

}


/* ===============================
   Default
================================ */

session_destroy();

header("Location: login.php");

exit;
