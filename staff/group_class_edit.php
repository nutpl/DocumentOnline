<?php
session_start();
require_once '../middleware/staff.php';
require_once '../config/database.php';
require_once '../helpers/csrf_helper.php';

$staffStmt = $pdo->prepare('SELECT department_id FROM employee WHERE user_id=? AND employee_type IN ("staff","teacher") LIMIT 1');
$staffStmt->execute([(int) $_SESSION['user_id']]);
$departmentId = (int) $staffStmt->fetchColumn();
$groupId = (int) ($_GET['group_id'] ?? $_POST['group_id'] ?? 0);
$returnPage = max(1, (int) ($_GET['page'] ?? 1));
$returnSearch = trim((string) ($_GET['search'] ?? ''));
if (!$departmentId || !$groupId) { header('Location: group_classes.php'); exit; }

$departmentStmt = $pdo->prepare('SELECT department_abbreviation, department_name FROM department WHERE department_id=?');
$departmentStmt->execute([$departmentId]);
$department = $departmentStmt->fetch(PDO::FETCH_ASSOC);
$groupStmt = $pdo->prepare('SELECT group_id,group_name FROM group_class WHERE group_id=? AND department_id=?');
$groupStmt->execute([$groupId, $departmentId]);
$group = $groupStmt->fetch(PDO::FETCH_ASSOC);
if (!$group || !$department) { $_SESSION['group_error'] = 'ไม่พบกลุ่มเรียนในสาขาของคุณ'; header('Location: group_classes.php'); exit; }
$departmentCode = rtrim(strtoupper(trim((string) $department['department_abbreviation'])), '.');

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_or_abort();
    $groupNumber = trim((string) ($_POST['group_number'] ?? ''));
    try {
        if (!preg_match('/^\d{5}$/', $groupNumber)) throw new RuntimeException('รหัสกลุ่มเรียนต้องเป็นตัวเลข 5 หลัก');
        $groupName = $departmentCode . '.' . $groupNumber;
        $duplicateStmt = $pdo->prepare('SELECT COUNT(*) FROM group_class WHERE group_name=? AND group_id<>?');
        $duplicateStmt->execute([$groupName, $groupId]);
        if ((int) $duplicateStmt->fetchColumn()) throw new RuntimeException('กลุ่มเรียน ' . $groupName . ' มีอยู่ในระบบแล้ว');
        $updateStmt = $pdo->prepare('UPDATE group_class SET group_name=? WHERE group_id=? AND department_id=?');
        $updateStmt->execute([$groupName, $groupId, $departmentId]);
        header('Location: group_classes.php?updated=1&page='.$returnPage.'&search='.urlencode($returnSearch)); exit;
    } catch (Throwable $exception) {
        $error = $exception->getMessage();
        $group['group_name'] = $departmentCode . '.' . $groupNumber;
    }
}

$groupNumber = preg_replace('/^' . preg_quote($departmentCode, '/') . '\.+/', '', (string) $group['group_name']);
$pageHeading = 'แก้ไขกลุ่มเรียน'; $document_count = 0;
function h($value): string { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); }
?>
<!doctype html><html lang="th"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>แก้ไขกลุ่มเรียน</title><script src="https://cdn.tailwindcss.com"></script><link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css"></head><body class="min-h-screen bg-slate-100 text-slate-900"><?php include 'sidebar.php'; ?><div class="min-h-screen lg:ml-72"><?php include 'navbar.php'; ?><main class="mx-auto max-w-3xl p-4 sm:p-6 lg:p-8"><a href="group_classes.php" class="inline-flex items-center gap-2 text-sm font-bold text-slate-600 hover:text-red-700"><i class="fa-solid fa-arrow-left"></i>กลับหน้าจัดการกลุ่มเรียน</a><?php if($error):?><div class="mt-5 rounded-xl border border-red-200 bg-red-50 p-4 text-red-800"><?=h($error)?></div><?php endif;?><section class="mt-5 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm"><div class="bg-slate-950 px-6 py-6 text-white"><p class="text-sm font-bold text-red-300">เฉพาะสาขาของคุณ</p><h1 class="mt-1 text-2xl font-black">แก้ไขกลุ่มเรียน <?=h($group['group_name'])?></h1><p class="mt-2 text-sm text-slate-300">สาขา<?=h($department['department_name'])?></p></div><form method="post" class="space-y-5 p-6"><?=csrf_field()?><input type="hidden" name="group_id" value="<?=$groupId?>"><label class="block text-sm font-bold text-slate-700">รหัสกลุ่มเรียน 5 หลัก<div class="mt-2 flex"><span class="inline-flex items-center rounded-l-xl border border-r-0 border-slate-300 bg-slate-100 px-4 text-slate-600"><?=h($departmentCode)?>.</span><input name="group_number" required maxlength="5" pattern="\d{5}" inputmode="numeric" value="<?=h($groupNumber)?>" class="min-w-0 flex-1 rounded-r-xl border border-slate-300 px-4 py-3"></div></label><p class="rounded-xl bg-slate-50 p-3 text-sm text-slate-500"><i class="fa-solid fa-circle-info mr-1.5 text-red-600"></i>กำหนดอาจารย์ที่ปรึกษาได้จากเมนู “กำหนดอาจารย์ที่ปรึกษา”</p><div class="flex justify-end gap-3 border-t pt-5"><a href="group_classes.php" class="rounded-xl border border-slate-300 px-5 py-3 font-bold text-slate-700 hover:bg-slate-50">ยกเลิก</a><button class="rounded-xl bg-red-700 px-5 py-3 font-bold text-white hover:bg-red-800"><i class="fa-solid fa-floppy-disk mr-2"></i>บันทึกการแก้ไข</button></div></form></section></main></div></body></html>
