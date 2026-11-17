<?php

require_once __DIR__ . "/../../middleware/admin.php";
require_once __DIR__ . "/../../config/database.php";
require_once __DIR__ . "/../../helpers/csrf_helper.php";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_or_abort();
}

$employeeId = filter_input(INPUT_POST, 'employee_id', FILTER_VALIDATE_INT);
$employeeType = $_POST['employee_type'] ?? '';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$employeeId || !in_array($employeeType, ['teacher', 'staff'], true)) {
    $_SESSION['admin_error'] = 'ข้อมูลบุคลากรไม่ถูกต้อง';
    header('Location: ../teachers/index.php');
    exit;
}

$redirect = $employeeType === 'teacher' ? '../teachers/index.php' : '../staffs/index.php';

try {
    $pdo->beginTransaction();

    $employeeStmt = $pdo->prepare('
        SELECT employee_id, user_id
        FROM employee
        WHERE employee_id = ? AND employee_type = ?
        FOR UPDATE
    ');
    $employeeStmt->execute([$employeeId, $employeeType]);
    $employee = $employeeStmt->fetch(PDO::FETCH_ASSOC);

    if (!$employee) {
        throw new Exception('ไม่พบข้อมูลบุคลากร');
    }

    if (!empty($employee['user_id']) && (int) $employee['user_id'] === (int) ($_SESSION['user_id'] ?? 0)) {
        throw new Exception('ไม่สามารถลบบัญชีของตนเองได้');
    }

    if (!empty($employee['user_id'])) {
        $adminRoleStmt = $pdo->prepare("SELECT 1 FROM user_roles WHERE user_id = ? AND role = 'admin' LIMIT 1");
        $adminRoleStmt->execute([$employee['user_id']]);

        if ($adminRoleStmt->fetchColumn()) {
            throw new Exception('ไม่สามารถลบบัญชีผู้ดูแลระบบได้');
        }
    }

    $pdo->prepare('DELETE FROM employee_position WHERE employee_id = ?')->execute([$employeeId]);
    $pdo->prepare('UPDATE group_class SET advisor_employee_id = NULL WHERE advisor_employee_id = ?')->execute([$employeeId]);
    $pdo->prepare('DELETE FROM employee WHERE employee_id = ?')->execute([$employeeId]);

    if (!empty($employee['user_id'])) {
        $pdo->prepare('DELETE FROM user_roles WHERE user_id = ?')->execute([$employee['user_id']]);
        $pdo->prepare('DELETE FROM users WHERE user_id = ?')->execute([$employee['user_id']]);
    }

    $pdo->commit();
    $_SESSION['admin_success'] = 'ลบข้อมูลบุคลากรเรียบร้อยแล้ว';
} catch (Exception $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    $_SESSION['admin_error'] = $exception->getMessage();
}

header("Location: {$redirect}");
exit;
