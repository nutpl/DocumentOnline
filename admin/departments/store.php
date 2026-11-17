<?php

session_start();

require_once "../../middleware/admin.php";
require_once "../../config/database.php";

if ($_SERVER["REQUEST_METHOD"] != "POST") {

    header("Location: index.php");
    exit;

}

/* ===========================
   รับข้อมูล
=========================== */

$department_abbreviation = trim($_POST['department_abbreviation'] ?? '');
$department_name = trim($_POST['department_name'] ?? '');
$faculty_id = filter_input(INPUT_POST, 'faculty_id', FILTER_VALIDATE_INT) ?: 0;

/* ===========================
   Validation
=========================== */

if (
    empty($department_abbreviation) ||
    empty($department_name) ||
    $faculty_id <= 0
) {

    $_SESSION['error'] = "กรุณากรอกข้อมูลให้ครบถ้วน";

    header("Location: create.php");
    exit;

}

$facultyStmt = $pdo->prepare('SELECT COUNT(*) FROM faculty WHERE faculty_id = ?');
$facultyStmt->execute([$faculty_id]);
if (!(int) $facultyStmt->fetchColumn()) {
    $_SESSION['error'] = 'ไม่พบคณะที่เลือก กรุณาเลือกคณะใหม่';
    header('Location: create.php');
    exit;
}

/* ===========================
   ตรวจสอบรหัสสาขาซ้ำ
=========================== */

$stmt = $pdo->prepare("
SELECT COUNT(*)
FROM department
WHERE department_abbreviation = ?
");

$stmt->execute([$department_abbreviation]);

if ($stmt->fetchColumn() > 0) {

    $_SESSION['error'] = "ตัวย่อสาขานี้มีอยู่แล้ว";

    header("Location: create.php");
    exit;

}

/* ===========================
   ตรวจสอบชื่อสาขาซ้ำ
=========================== */

$stmt = $pdo->prepare("
SELECT COUNT(*)
FROM department
WHERE department_name = ?
");

$stmt->execute([$department_name]);

if ($stmt->fetchColumn() > 0) {

    $_SESSION['error'] = "ชื่อสาขาวิชานี้มีอยู่แล้ว";

    header("Location: create.php");
    exit;

}

try {

    $pdo->beginTransaction();

    /* ===========================
       เพิ่มข้อมูลสาขาวิชา
    =========================== */

    $stmt = $pdo->prepare("
    INSERT INTO department
    (
        faculty_id,
        department_abbreviation,
        department_name
    )
    VALUES
    (
        ?,
        ?,
        ?
    )
    ");

    $stmt->execute([
        $faculty_id,
        $department_abbreviation,
        $department_name
    ]);

    $departmentId = $pdo->lastInsertId();

    /* ===========================
       Activity Log
    =========================== */

    $stmt = $pdo->prepare("
    INSERT INTO activity_log
    (
        user_id,
        activity,
        detail
    )
    VALUES
    (
        ?,
        ?,
        ?
    )
    ");

    $stmt->execute([
        $_SESSION['user_id'],
        'เพิ่มสาขาวิชา',
        'เพิ่มสาขาวิชา ID : ' . $departmentId
    ]);

    $pdo->commit();

    $_SESSION['success'] = "เพิ่มสาขาวิชาสำเร็จ";

    header("Location: index.php");
    exit;

} catch (Exception $e) {

    $pdo->rollBack();

    $_SESSION['error'] = "เกิดข้อผิดพลาด : " . $e->getMessage();

    header("Location: create.php");
    exit;

}
