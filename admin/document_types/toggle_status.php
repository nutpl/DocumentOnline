<?php
session_start(); require_once '../../config/database.php'; require_once '../../helpers/csrf_helper.php';
if (($_SESSION['role'] ?? '') !== 'admin') { header('Location: ../../auth/login.php'); exit; }
$_SESSION['admin_error'] = 'ระบบไม่อนุญาตให้เปลี่ยนสถานะแม่แบบเอกสาร';
header('Location: index.php');
exit;
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: index.php'); exit; }
verify_csrf_or_abort();
$id=(int)($_POST['id']??0); $status=$_POST['status']??'';
if(!$id || !in_array($status,['draft','active','inactive'],true)){header('Location: index.php');exit;}
if($status==='active'){
    $check=$pdo->prepare('SELECT COUNT(*) FROM template_fields WHERE template_id=?'); $check->execute([$id]);
    if(!(int)$check->fetchColumn()){header('Location: index.php?error=no-fields');exit;}
}
$stmt=$pdo->prepare('UPDATE document_template SET status=? WHERE template_id=?');$stmt->execute([$status,$id]);
header('Location: index.php?updated=1');
