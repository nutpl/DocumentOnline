<?php

require_once __DIR__ . '/../../middleware/admin.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/csrf_helper.php';
require_once __DIR__ . '/../../helpers/activity_log_helper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['admin_error'] = 'ต้องยืนยันการลบนักศึกษาจากหน้าจัดการนักศึกษา';
    header('Location: index.php');
    exit;
}

verify_csrf_or_abort();
$studentId = filter_input(INPUT_POST, 'student_id', FILTER_VALIDATE_INT) ?: 0;
if ($studentId <= 0) {
    $_SESSION['admin_error'] = 'ข้อมูลนักศึกษาไม่ถูกต้อง';
    header('Location: index.php');
    exit;
}

try {
    $pdo->beginTransaction();

    $studentStatement = $pdo->prepare(
        'SELECT student_id, user_id, student_number, firstname, lastname
         FROM student WHERE student_id=? LIMIT 1 FOR UPDATE'
    );
    $studentStatement->execute([$studentId]);
    $student = $studentStatement->fetch(PDO::FETCH_ASSOC);
    if (!$student) {
        throw new RuntimeException('ไม่พบข้อมูลนักศึกษาที่ต้องการลบ');
    }

    // Student documents are the official history of requests. Never remove a
    // student record through this screen after that history exists.
    $documentStatement = $pdo->prepare('SELECT COUNT(*) FROM documents WHERE student_id=?');
    $documentStatement->execute([$studentId]);
    if ((int) $documentStatement->fetchColumn() > 0) {
        throw new RuntimeException('ไม่สามารถลบนักศึกษาที่มีเอกสารในระบบได้ เพื่อคงประวัติคำร้องไว้');
    }

    $internshipStatement = $pdo->prepare('SELECT COUNT(*) FROM internship_profile WHERE student_id=?');
    $internshipStatement->execute([$studentId]);
    if ((int) $internshipStatement->fetchColumn() > 0) {
        throw new RuntimeException('ไม่สามารถลบนักศึกษาที่มีประวัติข้อมูลสหกิจได้ เพื่อคงประวัติไว้');
    }

    if (!empty($student['user_id'])) {
        // The student row is deleted by the user foreign-key cascade.
        $pdo->prepare('DELETE FROM users WHERE user_id=?')->execute([(int) $student['user_id']]);
    } else {
        $pdo->prepare('DELETE FROM student WHERE student_id=?')->execute([$studentId]);
    }

    logActivity(
        $pdo,
        (int) ($_SESSION['user_id'] ?? 0),
        'student_deleted',
        'ลบนักศึกษา ' . trim((string) $student['student_number'] . ' ' . $student['firstname'] . ' ' . $student['lastname'])
    );
    $pdo->commit();
    $_SESSION['admin_success'] = 'ลบข้อมูลนักศึกษาเรียบร้อยแล้ว';
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $_SESSION['admin_error'] = $exception->getMessage();
}

header('Location: index.php');
exit;
