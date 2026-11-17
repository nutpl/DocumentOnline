<?php

session_start();

require_once "../../middleware/admin.php";
require_once "../../config/database.php";

if ($_SERVER["REQUEST_METHOD"] != "POST") {

    header("Location:index.php");
    exit;

}

/* ==========================
   รับข้อมูล
========================== */

$department_id = intval($_POST['department_id'] ?? 0);

$department_abbreviation = trim($_POST['department_abbreviation'] ?? '');

$department_name = trim($_POST['department_name'] ?? '');

$faculty_id = intval($_POST['faculty_id'] ?? 0);
$embedSuffix = ($_POST['embed'] ?? '') === '1' ? '&embed=1' : '';


/* ==========================
   Validation
========================== */

if(

    $department_id<=0 ||

    empty($department_abbreviation) ||

    empty($department_name) ||

    $faculty_id<=0

){

    $_SESSION['error']="กรุณากรอกข้อมูลให้ครบ";

    header("Location:edit.php?id=".$department_id.$embedSuffix);

    exit;

}


/* ==========================
   ตรวจสอบรหัสซ้ำ
========================== */

$stmt=$pdo->prepare("

SELECT COUNT(*)

FROM department

WHERE department_abbreviation=?

AND department_id<>?

");

$stmt->execute([

    $department_abbreviation,

    $department_id

]);

if($stmt->fetchColumn()>0){

    $_SESSION['error']="รหัสสาขาวิชานี้มีอยู่แล้ว";

    header("Location:edit.php?id=".$department_id.$embedSuffix);

    exit;

}


/* ==========================
   ตรวจสอบชื่อซ้ำ
========================== */

$stmt=$pdo->prepare("

SELECT COUNT(*)

FROM department

WHERE department_name=?

AND department_id<>?

");

$stmt->execute([

    $department_name,

    $department_id

]);

if($stmt->fetchColumn()>0){

    $_SESSION['error']="ชื่อสาขาวิชานี้มีอยู่แล้ว";

    header("Location:edit.php?id=".$department_id.$embedSuffix);

    exit;

}


try{

    $pdo->beginTransaction();

    /* ==========================
       Update
    ========================== */

    $stmt=$pdo->prepare("

    UPDATE department

    SET

        department_abbreviation=?,

        department_name=?,

        faculty_id=?

    WHERE department_id=?

    ");

    $stmt->execute([

        $department_abbreviation,

        $department_name,

        $faculty_id,

        $department_id

    ]);


    /* ==========================
       Activity Log
    ========================== */

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

        'แก้ไขสาขาวิชา',

        'แก้ไข Department ID : '.$department_id

    ]);


    $pdo->commit();

    $_SESSION['success']="แก้ไขข้อมูลสาขาวิชาเรียบร้อยแล้ว";

    header("Location:index.php");

    exit;

}catch(Exception $e){

    $pdo->rollBack();

    $_SESSION['error']="เกิดข้อผิดพลาด : ".$e->getMessage();

    header("Location:edit.php?id=".$department_id.$embedSuffix);

    exit;

}
