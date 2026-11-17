<?php

session_start();

require_once "../../middleware/admin.php";
require_once "../../config/database.php";

$id = filter_input(
    INPUT_GET,
    'id',
    FILTER_VALIDATE_INT
);

if(!$id){

    $_SESSION['error']="ไม่พบข้อมูล";

    header("Location:index.php");

    exit;

}

try{

    $pdo->beginTransaction();

    /* ==========================
       ตรวจสอบว่ามีกลุ่มเรียนหรือไม่
    ========================== */

    $stmt=$pdo->prepare("

    SELECT COUNT(*)

    FROM group_class

    WHERE department_id=?

    ");

    $stmt->execute([$id]);

    if($stmt->fetchColumn()>0){

        $_SESSION['error']="ไม่สามารถลบได้ เนื่องจากสาขาวิชานี้ยังมีกลุ่มเรียนอยู่";

        $pdo->rollBack();

        header("Location:index.php");

        exit;

    }

    /* ==========================
       ลบข้อมูล
    ========================== */

    $stmt=$pdo->prepare("

    DELETE FROM department

    WHERE department_id=?

    ");

    $stmt->execute([$id]);

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

        'ลบสาขาวิชา',

        'ลบ Department ID : '.$id

    ]);

    $pdo->commit();

    $_SESSION['success']="ลบข้อมูลเรียบร้อยแล้ว";

}catch(Exception $e){

    $pdo->rollBack();

    $_SESSION['error']="เกิดข้อผิดพลาด : ".$e->getMessage();

}

header("Location:index.php");

exit;