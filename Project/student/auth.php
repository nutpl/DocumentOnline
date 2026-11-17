<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once "../config/database.php";


if (!isset($_SESSION['user_id'])) {

    header("Location: ../auth/login.php");
    exit();

}


$user_id = $_SESSION['user_id'];



$stmt = $pdo->prepare("

    SELECT 
        s.firstname,
        s.lastname,
        s.student_number,
        u.email

    FROM student s
    INNER JOIN users u ON u.user_id = s.user_id

    WHERE s.user_id = ?

");


$stmt->execute([$user_id]);


$user = $stmt->fetch(PDO::FETCH_ASSOC);



if (!$user) {

    die("ไม่พบข้อมูลนักศึกษา");

}

?>
