<?php

$id = $_GET['id'] ?? 0;

try{

    $pdo->beginTransaction();

    // หา user_id
    $stmt = $pdo->prepare("
    SELECT user_id
    FROM staff
    WHERE staff_id=?
    ");

    $stmt->execute([$id]);

    $staff = $stmt->fetch(PDO::FETCH_ASSOC);

    if(!$staff){

        throw new Exception("ไม่พบข้อมูลเจ้าหน้าที่");

    }

    // ลบข้อมูล staff
    $stmt = $pdo->prepare("
    DELETE
    FROM staff
    WHERE staff_id=?
    ");

    $stmt->execute([$id]);

    // ลบ users
    $stmt = $pdo->prepare("
    DELETE
    FROM users
    WHERE user_id=?
    ");

    $stmt->execute([

        $staff['user_id']

    ]);

    $pdo->commit();

    echo "

    <script>

    alert('ลบข้อมูลเรียบร้อย');

    location='admin.php?page=staff';

    </script>

    ";

}catch(Exception $e){

    $pdo->rollBack();

    echo "

    <div class='bg-red-600 p-4 rounded'>

    ".$e->getMessage()."

    </div>

    ";

}