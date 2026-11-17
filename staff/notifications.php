<?php
session_start();
require_once '../middleware/staff.php';
require_once '../config/database.php';
require_once '../helpers/csrf_helper.php';
if($_SERVER['REQUEST_METHOD']==='POST'){
    verify_csrf_or_abort();
    $pdo->prepare('UPDATE notifications SET is_read=1,read_at=COALESCE(read_at,NOW()) WHERE recipient_user_id=?')->execute([$_SESSION['user_id']]);
    header('Location: notifications.php');exit;
}
$staffStmt=$pdo->prepare("SELECT employee_id,employee_code,department_id,firstname,lastname FROM employee WHERE user_id=? AND employee_type IN ('staff','teacher') LIMIT 1");$staffStmt->execute([$_SESSION['user_id']]);$staff=$staffStmt->fetch(PDO::FETCH_ASSOC);if(!$staff){http_response_code(403);renderPlainErrorAlert('ไม่พบข้อมูลเจ้าหน้าที่');}$staff_name=trim($staff['firstname'].' '.$staff['lastname']);
$stmt=$pdo->prepare('SELECT notification_id,document_id,notification_type,title,message,target_url,is_read,created_at FROM notifications WHERE recipient_user_id=? ORDER BY created_at DESC LIMIT 100');$stmt->execute([$_SESSION['user_id']]);$notifications=$stmt->fetchAll(PDO::FETCH_ASSOC);$pageHeading='การแจ้งเตือน';$document_count=0;
function h($v):string{return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
?>
<!doctype html><html lang="th"><head>
    <link rel="icon" type="image/png" href="/Project/assets/images/fet-logo-transparent.png?v=20260815-3">
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>การแจ้งเตือน</title><script src="https://cdn.tailwindcss.com"></script><link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css"></head><body class="min-h-screen bg-gray-100 text-gray-900"><?php include 'sidebar.php'; ?><div class="min-h-screen lg:ml-72"><?php include 'navbar.php'; ?><main class="p-4 sm:p-6 lg:p-8"><div class="flex flex-wrap items-center justify-between gap-3"><div><h1 class="text-2xl font-bold">การแจ้งเตือน</h1><p class="mt-1 text-sm text-gray-500">เอกสารและงานฝึกประสบการณ์ที่ต้องดำเนินการ</p></div><form method="post"><?= csrf_field() ?><button class="rounded-lg border bg-white px-4 py-2 text-sm font-bold"><i class="fa-solid fa-check-double mr-2"></i>อ่านทั้งหมด</button></form></div><section class="mt-5 overflow-hidden rounded-xl border bg-white shadow-sm"><div class="divide-y"><?php foreach($notifications as $item): ?><a href="<?= h($item['target_url']?:'documents.php') ?>" class="block p-5 hover:bg-red-50/40 <?= !$item['is_read']?'border-l-4 border-red-600 bg-red-50/30':'' ?>"><div class="flex items-start gap-3"><i class="fa-regular fa-bell mt-1 text-red-700"></i><div><strong><?= h($item['title']) ?></strong><p class="mt-1 text-sm text-gray-600"><?= h($item['message']) ?></p><p class="mt-2 text-xs text-gray-400"><?= h($item['created_at']) ?></p></div></div></a><?php endforeach; ?><?php if(!$notifications): ?><div class="p-12 text-center text-gray-500"><i class="fa-regular fa-bell-slash mb-3 text-4xl text-gray-300"></i><p>ยังไม่มีการแจ้งเตือน</p></div><?php endif; ?></div></section></main></div></body></html>
