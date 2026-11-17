<?php

function requireRole($roles){

if(!isset($_SESSION['active_role'])){

header("Location:/Project/auth/login.php");

exit;

}

if(!is_array($roles)){

$roles=[$roles];

}

if(!in_array($_SESSION['active_role'],$roles)){

die("ไม่มีสิทธิ์เข้าใช้งาน");

}

}