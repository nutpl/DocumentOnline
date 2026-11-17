<?php

session_start();

require_once "../../middleware/admin.php";
require_once "../../config/database.php";

// ==========================
// ตรวจสอบ ID
// ==========================

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$id) {

    $_SESSION['error'] = "ไม่พบข้อมูลคณะ";

    header("Location: index.php");

    exit;

}

try {

    // ==========================
    // ตรวจสอบว่ามีภาควิชาหรือไม่
    // ==========================

    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM department
        WHERE faculty_id = ?
    ");

    $stmt->execute([$id]);

    if ($stmt->fetchColumn() > 0) {

        $_SESSION['error'] = "ไม่สามารถลบคณะได้ เนื่องจากยังมีภาควิชาอยู่";

        header("Location: index.php");

        exit;

    }

    $pdo->beginTransaction();

    // ==========================
    // ลบข้อมูล
    // ==========================

    $stmt = $pdo->prepare("
        DELETE FROM faculty
        WHERE faculty_id = ?
    ");

    $stmt->execute([$id]);

    // ==========================
    // Activity Log
    // ==========================

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
        'ลบข้อมูลคณะ',
        'Faculty ID : '.$id
    ]);

    $pdo->commit();

    $_SESSION['success'] = "ลบข้อมูลสำเร็จ";

} catch (Exception $e) {

    $pdo->rollBack();

    $_SESSION['error'] = "เกิดข้อผิดพลาด : ".$e->getMessage();

}

header("Location: index.php");

exit;