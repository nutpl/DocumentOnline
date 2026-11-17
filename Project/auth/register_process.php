<?php

session_start();
require_once '../config/database.php';
require_once '../helpers/csrf_helper.php';
require_once '../helpers/password_helper.php';
require_once '../helpers/person_name_validation_helper.php';
require_once '../helpers/student_number_helper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: register.php');
    exit;
}

verify_csrf_or_abort();

$firstname = trim($_POST['firstname'] ?? '');
$lastname = trim($_POST['lastname'] ?? '');
$studentCodeInput = trim($_POST['student_number'] ?? '');
$studentCode = normalizeStudentCode($studentCodeInput);
$email = strtolower(trim($_POST['email'] ?? ''));
$password = $_POST['password'] ?? '';
$confirm = $_POST['confirm_password'] ?? '';
$prefixId = (int) ($_POST['prefix_id'] ?? 0);
$facultyId = (int) ($_POST['faculty_id'] ?? 0);
$departmentId = (int) ($_POST['department_id'] ?? 0);
$groupId = (int) ($_POST['group_id'] ?? 0);

$_SESSION['register_old'] = [
    'prefix_id' => $prefixId,
    'firstname' => $firstname,
    'lastname' => $lastname,
    'student_number' => $studentCode ?? $studentCodeInput,
    'email' => $email,
];

function registerError(string $message): void
{
    $_SESSION['error'] = $message;
    header('Location: register.php');
    exit;
}

if (!isValidPersonName($firstname) || !isValidPersonName($lastname)) {
    registerError('ชื่อและนามสกุลต้องเป็นตัวอักษรเท่านั้น และมีความยาวไม่เกิน 100 ตัวอักษร');
}
if ($studentCode === null) {
    registerError('รหัสนักศึกษาต้องเป็นตัวเลข 12 หลัก ระบบจะจัดรูปแบบขีดให้อัตโนมัติ');
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || !preg_match('/@(?:[a-z0-9-]+\.)*rmuti\.ac\.th$/i', $email)) {
    registerError('กรุณาใช้อีเมลของมหาวิทยาลัย (@rmuti.ac.th)');
}
if (strlen($password) < 8 || strlen($password) > 72) {
    registerError('รหัสผ่านต้องมีความยาว 8 ถึง 72 ตัวอักษร');
}
if (!hash_equals($password, $confirm)) {
    registerError('รหัสผ่านและยืนยันรหัสผ่านไม่ตรงกัน');
}

$stmt = $pdo->prepare("SELECT prefix_id FROM prefixes WHERE prefix_id=? AND prefix_short IN ('นาย','นางสาว') AND status='active'");
$stmt->execute([$prefixId]);
if (!$stmt->fetchColumn()) {
    registerError('กรุณาเลือกคำนำหน้า นาย หรือ นางสาว');
}

$stmt = $pdo->prepare('SELECT department_id FROM department WHERE department_id=? AND faculty_id=?');
$stmt->execute([$departmentId, $facultyId]);
if (!$stmt->fetchColumn()) {
    registerError('กรุณาเลือกคณะและสาขาให้ถูกต้อง');
}

$stmt = $pdo->prepare('SELECT group_id FROM group_class WHERE group_id=? AND department_id=?');
$stmt->execute([$groupId, $departmentId]);
if (!$stmt->fetchColumn()) {
    registerError('กรุณาเลือกกลุ่มเรียนให้ถูกต้อง');
}

$stmt = $pdo->prepare('SELECT email, username FROM users WHERE email=? OR username=?');
$stmt->execute([$email, $studentCode]);
$existingAccounts = $stmt->fetchAll(PDO::FETCH_ASSOC);
$emailExists = false;
$studentNumberExists = false;
foreach ($existingAccounts as $account) {
    $emailExists = $emailExists || strtolower((string) $account['email']) === $email;
    $studentNumberExists = $studentNumberExists || (string) $account['username'] === $studentCode;
}
if ($emailExists && $studentNumberExists) {
    registerError('อีเมลและรหัสนักศึกษานี้ถูกใช้งานแล้ว');
}
if ($emailExists) {
    registerError('อีเมลนี้ถูกใช้งานแล้ว');
}
if ($studentNumberExists) {
    registerError('รหัสนักศึกษานี้ถูกใช้งานแล้ว');
}

try {
    $pdo->beginTransaction();
    $stmt = $pdo->prepare("INSERT INTO users (username,email,password,first_login,status) VALUES (?,?,?,0,'active')");
    $stmt->execute([$studentCode, $email, prepareUserPasswordForStorage($password)]);
    $userId = (int) $pdo->lastInsertId();
    $pdo->prepare("INSERT INTO user_roles (user_id,role) VALUES (?,'student')")->execute([$userId]);
    $pdo->prepare('INSERT INTO student (user_id,student_number,prefix_id,firstname,lastname,group_id) VALUES (?,?,?,?,?,?)')
        ->execute([$userId, $studentCode, $prefixId, $firstname, $lastname, $groupId]);
    $pdo->commit();
    unset($_SESSION['register_old']);
    $_SESSION['success'] = 'สมัครสมาชิกสำเร็จ กรุณาเข้าสู่ระบบ';
    header('Location: login.php');
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    registerError('ไม่สามารถสมัครสมาชิกได้ กรุณาลองใหม่อีกครั้ง');
}
