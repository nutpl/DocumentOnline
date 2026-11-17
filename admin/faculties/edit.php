<?php

session_start();

require_once "../../middleware/admin.php";
require_once "../../config/database.php";

$pageTitle = "แก้ไขคณะ";
$isEmbedded = ($_GET['embed'] ?? '') === '1';

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$id) {

    header("Location: index.php");
    exit;

}

$stmt = $pdo->prepare("
SELECT *
FROM faculty
WHERE faculty_id=?
LIMIT 1
");

$stmt->execute([$id]);

$faculty = $stmt->fetch(PDO::FETCH_ASSOC);

if(!$faculty){

    die("ไม่พบข้อมูลคณะ");

}

$structureStmt = $pdo->prepare('SELECT COUNT(DISTINCT d.department_id) AS department_count,COUNT(DISTINCT g.group_id) AS group_count
    FROM faculty f LEFT JOIN department d ON d.faculty_id=f.faculty_id
    LEFT JOIN group_class g ON g.department_id=d.department_id WHERE f.faculty_id=?');
$structureStmt->execute([$id]);
$structure = $structureStmt->fetch(PDO::FETCH_ASSOC) ?: ['department_count'=>0, 'group_count'=>0];

if ($isEmbedded) {
    ?><!doctype html><html lang="th"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><script src="https://cdn.tailwindcss.com"></script><link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css"></head><body class="min-h-screen bg-slate-50 p-4 text-slate-900"><?php
} else { include "../layouts/admin_header.php"; include "../layouts/admin_sidebar.php"; include "../layouts/admin_navbar.php"; }

?>
<?php if ($isEmbedded): ?><style>body > div.max-w-4xl{max-width:100%} body > div.max-w-4xl > div{box-shadow:0 10px 28px rgb(15 23 42 / .08)}</style><?php endif; ?>

<div class="mx-auto max-w-4xl">

<div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">

<div class="border-b border-slate-100 bg-gradient-to-r from-red-50 to-white px-5 py-5 sm:px-6">

<div class="flex flex-wrap items-start justify-between gap-4">
<div class="flex items-start gap-3"><span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-red-700 text-xl text-white shadow-lg shadow-red-200"><i class="fa-solid fa-building-columns"></i></span><div>
<p class="text-sm font-bold text-red-700">โครงสร้างการศึกษา</p>
<h1 class="mt-1 text-2xl font-black text-slate-900">

แก้ไขคณะ

</h1>

<p class="mt-1 text-sm text-slate-600">

ปรับตัวย่อและชื่อคณะ โดยข้อมูลสาขาและกลุ่มเรียนจะยังคงเชื่อมโยงเดิม

</p>
</div></div>
</div>
</div>

<div class="grid gap-3 border-b border-slate-100 bg-slate-50 p-5 sm:grid-cols-2"><div class="rounded-xl border border-slate-200 bg-white p-4"><p class="text-xs font-bold text-slate-400">สาขาในคณะนี้</p><p class="mt-1 text-xl font-black text-slate-900"><?= (int)$structure['department_count'] ?> <span class="text-sm font-semibold text-slate-500">สาขา</span></p></div><div class="rounded-xl border border-slate-200 bg-white p-4"><p class="text-xs font-bold text-slate-400">กลุ่มเรียนในคณะนี้</p><p class="mt-1 text-xl font-black text-slate-900"><?= (int)$structure['group_count'] ?> <span class="text-sm font-semibold text-slate-500">กลุ่มเรียน</span></p></div></div>

<?php if(isset($_SESSION['error'])): ?>

<div class="mx-5 mt-5 rounded-xl border border-red-200 bg-red-50 px-5 py-4 text-red-800 sm:mx-6">

<?= $_SESSION['error']; ?>

</div>

<?php unset($_SESSION['error']); ?>

<?php endif; ?>

<form action="update.php" method="POST" class="p-5 sm:p-6">

<input type="hidden" name="embed" value="<?= $isEmbedded ? '1' : '0' ?>">

<input
type="hidden"
name="faculty_id"
value="<?= $faculty['faculty_id'] ?>">

<div class="grid gap-5 sm:grid-cols-2">

<div>

<label class="block mb-2 font-semibold">

ตัวย่อคณะ

</label>

<input

type="text"

name="faculty_abbreviation"

required maxlength="20"

value="<?= htmlspecialchars($faculty['faculty_abbreviation']) ?>"

placeholder="เช่น ENG"
class="mt-2 w-full rounded-xl border border-slate-300 px-4 py-3 font-semibold text-slate-900 outline-none transition focus:border-red-500 focus:ring-4 focus:ring-red-100">

<p class="mt-1.5 text-xs font-normal text-slate-500">ใช้เป็นรหัสย่อสำหรับแสดงผลในระบบ เช่น ENG</p>

</div>

<div>

<label class="block mb-2 font-semibold">

ชื่อคณะ

</label>

<input

type="text"

name="faculty_name"

required

value="<?= htmlspecialchars($faculty['faculty_name']) ?>"

class="mt-2 w-full rounded-xl border border-slate-300 px-4 py-3 font-semibold text-slate-900 outline-none transition focus:border-red-500 focus:ring-4 focus:ring-red-100">

<p class="mt-1.5 text-xs font-normal text-slate-500">ระบุชื่อเต็มที่ใช้ในเอกสารและหน้าจอระบบ</p>

</div>

</div>

<div class="mt-6 flex flex-col-reverse gap-3 border-t border-slate-100 pt-5 sm:flex-row sm:justify-end">

<button
type="submit"
class="inline-flex items-center justify-center rounded-xl bg-red-700 px-5 py-3 text-sm font-bold text-white transition hover:bg-red-800 focus:outline-none focus:ring-4 focus:ring-red-200">

<i class="fa-solid fa-save mr-2"></i>

บันทึกการแก้ไข

</button>

<a
href="index.php"
class="inline-flex items-center justify-center rounded-xl border border-slate-300 px-5 py-3 text-sm font-bold text-slate-700 transition hover:bg-slate-50">

ยกเลิก

</a>

</div>

</form>

</div>

</div>

<?php

if ($isEmbedded): ?></body></html><?php else: include "../layouts/admin_footer.php"; endif;

?>
