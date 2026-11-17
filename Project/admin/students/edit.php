<?php

require_once __DIR__ . '/../../middleware/admin.php';
require_once __DIR__ . '/../../config/database.php';

$studentId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$studentId) {
    header('Location: index.php');
    exit;
}

$stmt = $pdo->prepare('SELECT user_id FROM student WHERE student_id = ? LIMIT 1');
$stmt->execute([$studentId]);
$userId = (int) $stmt->fetchColumn();

if (!$userId) {
    $_SESSION['admin_error'] = 'ไม่พบข้อมูลนักศึกษา';
    header('Location: index.php');
    exit;
}

header('Location: ../users/edit.php?id=' . $userId . '&from=students');
exit;
