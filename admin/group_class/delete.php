<?php

require_once __DIR__ . "/../../middleware/admin.php";
require_once __DIR__ . "/../../config/database.php";

header('Location: /Project/admin/group_class/index.php');
exit;

$group_id = $_GET['id'] ?? null;

if (!$group_id) {

    header("Location: index.php");
    exit;
}


/*
    ตรวจสอบก่อนว่ามีนักศึกษา
    อยู่ในกลุ่มเรียนหรือไม่
*/

$stmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM student
    WHERE group_id = ?
");

$stmt->execute([$group_id]);

$student_count = $stmt->fetchColumn();


if ($student_count > 0) {

    die(
        "ไม่สามารถลบกลุ่มเรียนนี้ได้
        เนื่องจากยังมีนักศึกษาอยู่ในกลุ่มเรียน"
    );

}


$stmt = $pdo->prepare("
    DELETE FROM group_class
    WHERE group_id = ?
");

$stmt->execute([$group_id]);


header("Location: index.php");
exit;
