<?php

session_start();

require_once "../../middleware/admin.php";
require_once "../../config/database.php";

if ($_SERVER['REQUEST_METHOD'] != 'POST') {

    header("Location: index.php");
    exit;

}

$faculty_abbreviation = trim($_POST['faculty_abbreviation']);
$faculty_name = trim($_POST['faculty_name']);


// =========================
// Validation
// =========================

if (
    empty($faculty_abbreviation) ||
    empty($faculty_name)
) {

    $_SESSION['error'] = "กรุณากรอกข้อมูลให้ครบ";

    header("Location: create.php");
    exit;

}


// =========================
// ตรวจสอบตัวย่อคณะซ้ำ
// =========================

$stmt = $pdo->prepare("
SELECT COUNT(*)
FROM faculty
WHERE faculty_abbreviation=?
");

$stmt->execute([
    $faculty_abbreviation
]);

if ($stmt->fetchColumn() > 0) {

    $_SESSION['error'] = "ตัวย่อคณะนี้มีอยู่แล้ว";

    header("Location: create.php");
    exit;

}


// =========================
// ตรวจสอบชื่อคณะซ้ำ
// =========================

$stmt = $pdo->prepare("
SELECT COUNT(*)
FROM faculty
WHERE faculty_name=?
");

$stmt->execute([
    $faculty_name
]);

if ($stmt->fetchColumn() > 0) {

    $_SESSION['error'] = "ชื่อคณะนี้มีอยู่แล้ว";

    header("Location: create.php");
    exit;

}


try {

    $pdo->beginTransaction();


    // =========================
    // เพิ่มข้อมูล
    // =========================

    $stmt = $pdo->prepare("
    INSERT INTO faculty
    (
        faculty_abbreviation,
        faculty_name
    )
    VALUES
    (
        ?,
        ?
    )
    ");

    $stmt->execute([
        $faculty_abbreviation,
        $faculty_name
    ]);


    // =========================
    // Activity Log
    // =========================

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
        "เพิ่มคณะ",
        "เพิ่มคณะ : ".$faculty_name
    ]);


    $pdo->commit();

    $_SESSION['success'] = "เพิ่มคณะเรียบร้อยแล้ว";

    header("Location: index.php");
    exit;

} catch (Exception $e) {

    $pdo->rollBack();

    $_SESSION['error'] = $e->getMessage();

    header("Location: create.php");
    exit;

}
