<?php
session_start();
require "db.php";

if(!isset($_SESSION['user_id'])){
    header("Location: login.php");
    exit;
}

$user_id=$_SESSION['user_id'];

$stmt=$pdo->prepare("
SELECT *
FROM users
WHERE user_id=?
");

$stmt->execute([$user_id]);

$user=$stmt->fetch(PDO::FETCH_ASSOC);

if(!$user){
    session_destroy();
    header("Location: login.php");
    exit;
}

// ถ้าเปลี่ยนแล้ว ไม่ต้องเข้าหน้านี้
if($user['first_login']==0){

    switch($user['role']){

        case "admin":
            header("Location: admin.php");
        break;

        case "teacher":
            header("Location: teacher/index.php");
        break;

        case "staff":
            header("Location: staff/index.php");
        break;

        case "student":
            header("Location: student/index.php");
        break;
    }

    exit;
}

if(isset($_POST['save'])){

    $password=$_POST['password'];
    $confirm=$_POST['confirm_password'];

    if(strlen($password)<8){

        $error="รหัสผ่านต้องมีอย่างน้อย 8 ตัว";

    }elseif($password!=$confirm){

        $error="รหัสผ่านไม่ตรงกัน";

    }else{

        $stmt=$pdo->prepare("
        UPDATE users
        SET
            password=?,
            first_login=0
        WHERE user_id=?
        ");

        $stmt->execute([
            $password,
            $user_id
        ]);

        $stmt=$pdo->prepare("
        INSERT INTO activity_log
        (
        user_id,
        activity,
        detail
        )
        VALUES
        (
        ?,?,?
        )
        ");

        $stmt->execute([
            $user_id,
            'เปลี่ยนรหัสผ่าน',
            'เปลี่ยนรหัสผ่านครั้งแรก'
        ]);

        switch($_SESSION['role']){

            case "admin":
                header("Location: admin.php");
            break;

            case "teacher":
                header("Location: teacher/index.php");
            break;

            case "staff":
                header("Location: staff/index.php");
            break;

            case "student":
                header("Location: student/index.php");
            break;

        }

        exit;

    }

}

?>

<!DOCTYPE html>

<html lang="th">

<head>
    <link rel="icon" type="image/png" href="/Project/assets/images/fet-logo-transparent.png?v=20260815-3">


<meta charset="UTF-8">

<title>เปลี่ยนรหัสผ่าน</title>

<script src="https://cdn.tailwindcss.com"></script>

</head>

<body class="bg-gray-900">

<div class="flex justify-center items-center h-screen">

<div class="bg-gray-800 p-8 rounded-xl w-[420px]">

<h2 class="text-3xl text-center text-red-500 font-bold">

เปลี่ยนรหัสผ่าน

</h2>

<p class="text-center text-gray-300 mt-2">

เข้าสู่ระบบครั้งแรก กรุณาเปลี่ยนรหัสผ่าน

</p>

<?php
if(isset($error)){
?>

<div class="bg-red-600 p-3 rounded mt-5 text-white">

<?= $error ?>

</div>

<?php
}
?>

<form method="post">

<div class="mt-5">

<label class="text-white">

รหัสผ่านใหม่

</label>

<input
type="password"
name="password"
required
class="w-full mt-2 p-3 rounded bg-gray-700 text-white">

</div>

<div class="mt-5">

<label class="text-white">

ยืนยันรหัสผ่าน

</label>

<input
type="password"
name="confirm_password"
required
class="w-full mt-2 p-3 rounded bg-gray-700 text-white">

</div>

<button
name="save"
class="w-full mt-6 bg-red-600 hover:bg-red-700 p-3 rounded text-white font-bold">

บันทึกรหัสผ่าน

</button>

</form>

</div>

</div>

</body>

</html>
