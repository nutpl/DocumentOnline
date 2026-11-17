<?php
require_once __DIR__ . "/../config/database.php";

header('Content-Type: application/json; charset=UTF-8');

$email = trim($_POST['email'] ?? '');
$firstname = trim($_POST['firstname'] ?? '');
$lastname = trim($_POST['lastname'] ?? '');
$type = $_POST['type'] ?? 'teacher';

if (!$email || !$firstname || !$lastname) {
    echo json_encode(['found' => false, 'message' => 'กรุณากรอก Email, ชื่อ และนามสกุล']);
    exit;
}

$stmt = $pdo->prepare("SELECT employee_id, firstname, lastname, email, department_id FROM employee WHERE email = ? AND firstname = ? AND lastname = ? AND employee_type = ? AND user_id IS NULL LIMIT 1");
$stmt->execute([$email, $firstname, $lastname, $type]);
$emp = $stmt->fetch(PDO::FETCH_ASSOC);

if ($emp) {
    echo json_encode([
        'found' => true,
        'employee_id' => $emp['employee_id'],
        'department_id' => $emp['department_id'],
        'message' => 'พบบัญชีในระบบ สามารถสมัครได้'
    ], JSON_UNESCAPED_UNICODE);
} else {
    echo json_encode(['found' => false, 'message' => 'ไม่พบข้อมูลบุคลากรที่ตรงกัน หรือบัญชีถูกใช้งานแล้ว'], JSON_UNESCAPED_UNICODE);
}
