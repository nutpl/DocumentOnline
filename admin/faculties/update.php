<?php

session_start();

require_once "../../middleware/admin.php";
require_once "../../config/database.php";

if ($_SERVER['REQUEST_METHOD'] != 'POST') {

    header("Location: index.php");
    exit;

}

$faculty_id   = (int)$_POST['faculty_id'];
$faculty_abbreviation = trim($_POST['faculty_abbreviation']);
$faculty_name = trim($_POST['faculty_name']);
$embedSuffix = ($_POST['embed'] ?? '') === '1' ? '&embed=1' : '';


// ===========================
// Validation
// ===========================

if (
    empty($faculty_abbreviation) ||
    empty($faculty_name)
) {

    $_SESSION['error'] = "กรุณากรอกข้อมูลให้ครบ";

    header("Location: edit.php?id=".$faculty_id.$embedSuffix);
    exit;

}


// ===========================
// ตรวจสอบตัวย่อคณะซ้ำ
// ===========================

$stmt = $pdo->prepare("
SELECT COUNT(*)
FROM faculty
WHERE faculty_abbreviation=?
AND faculty_id<>?
");

$stmt->execute([
    $faculty_abbreviation,
    $faculty_id
]);

if($stmt->fetchColumn()>0){

    $_SESSION['error']="ตัวย่อคณะนี้มีอยู่แล้ว";

    header("Location: edit.php?id=".$faculty_id.$embedSuffix);
    exit;

}


// ===========================
// ตรวจสอบชื่อคณะซ้ำ
// ===========================

$stmt = $pdo->prepare("
SELECT COUNT(*)
FROM faculty
WHERE faculty_name=?
AND faculty_id<>?
");

$stmt->execute([
    $faculty_name,
    $faculty_id
]);

if($stmt->fetchColumn()>0){

    $_SESSION['error']="ชื่อคณะนี้มีอยู่แล้ว";

    header("Location: edit.php?id=".$faculty_id.$embedSuffix);
    exit;

}


try{

    $pdo->beginTransaction();

    // ===========================
    // Update Faculty
    // ===========================

    $stmt=$pdo->prepare("
    UPDATE faculty
    SET
        faculty_abbreviation=?,
        faculty_name=?
    WHERE faculty_id=?
    ");

    $stmt->execute([
        $faculty_abbreviation,
        $faculty_name,
        $faculty_id
    ]);


    // ===========================
    // Activity Log
    // ===========================

    $stmt=$pdo->prepare("
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
        "แก้ไขคณะ",
        "แก้ไข ".$faculty_name
    ]);


    $pdo->commit();

    $_SESSION['success']="แก้ไขข้อมูลเรียบร้อยแล้ว";

    header("Location:index.php");
    exit;

}catch(Exception $e){

    $pdo->rollBack();

    $_SESSION['error']=$e->getMessage();

    header("Location:edit.php?id=".$faculty_id.$embedSuffix);
    exit;

}
