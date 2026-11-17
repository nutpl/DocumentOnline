<?php

session_start();

require_once "../../middleware/admin.php";
require_once "../../config/database.php";

$pageTitle = "แก้ไขสาขาวิชา";
$isEmbedded = ($_GET['embed'] ?? '') === '1';

/* ==========================
   ตรวจสอบ ID
========================== */

$id = filter_input(
    INPUT_GET,
    'id',
    FILTER_VALIDATE_INT
);

if(!$id){

    header("Location:index.php");
    exit;

}

/* ==========================
   ดึงข้อมูลสาขา
========================== */

$stmt = $pdo->prepare("

SELECT
d.department_id,
d.department_abbreviation,
d.department_name,
d.faculty_id,
f.faculty_name

FROM department d

LEFT JOIN faculty f
ON d.faculty_id=f.faculty_id

WHERE d.department_id=?

LIMIT 1

");

$stmt->execute([$id]);

$department = $stmt->fetch(PDO::FETCH_ASSOC);

if(!$department){

    die("ไม่พบข้อมูลสาขาวิชา");

}

/* ==========================
   รายชื่อคณะ
========================== */

$faculties = $pdo->query("

SELECT
faculty_id,
faculty_name

FROM faculty

ORDER BY faculty_name

")->fetchAll(PDO::FETCH_ASSOC);

if ($isEmbedded) {
    ?><!doctype html><html lang="th"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><script src="https://cdn.tailwindcss.com"></script><link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css"></head><body class="min-h-screen bg-slate-50 p-4 text-slate-900"><?php
} else { include "../layouts/admin_header.php"; include "../layouts/admin_sidebar.php"; include "../layouts/admin_navbar.php"; }

?>
<?php if ($isEmbedded): ?>
<style>
    body > div.max-w-3xl > div { overflow: hidden; border: 1px solid #e2e8f0; border-radius: 1rem; box-shadow: 0 10px 28px rgb(15 23 42 / .08); padding: 0; }
    body > div.max-w-3xl > div > div:first-child { border-left: 6px solid #dc2626; background: linear-gradient(115deg, #fff1f2, #fff); margin: 0; padding: 1.5rem 1.75rem; }
    body > div.max-w-3xl > div > div:first-child h1 { color: #0f172a; font-size: 1.65rem; font-weight: 800; }
    body > div.max-w-3xl > div > form { padding: 1.5rem 1.75rem; }
    body > div.max-w-3xl input, body > div.max-w-3xl select { border-color: #cbd5e1; border-radius: .75rem; padding: .8rem 1rem; outline: none; }
    body > div.max-w-3xl input:focus, body > div.max-w-3xl select:focus { border-color: #dc2626; box-shadow: 0 0 0 4px #fee2e2; }
    body > div.max-w-3xl button[type="submit"] { background: #b91c1c; border-radius: .75rem; font-weight: 700; }
    body > div.max-w-3xl button[type="submit"]:hover { background: #991b1b; }
    body > div.max-w-3xl form > div:last-child { justify-content: space-between; border-top: 1px solid #f1f5f9; padding-top: 1.25rem; }
    body > div.max-w-3xl form > div:last-child a { background: #fff; color: #475569; border: 1px solid #cbd5e1; border-radius: .75rem; font-weight: 700; }
</style>
<?php endif; ?>

<div class="max-w-3xl mx-auto">

<div class="bg-white rounded-xl shadow-lg p-8">

<div class="mb-8">

<h1 class="text-3xl font-bold">

แก้ไขสาขาวิชา

</h1>

<p class="text-gray-500 mt-2">

แก้ไขข้อมูลสาขาวิชา

</p>

</div>

<?php if(isset($_SESSION['error'])): ?>

<div class="bg-red-100 text-red-700 p-4 rounded-lg mb-5">

<?= $_SESSION['error']; ?>

</div>

<?php unset($_SESSION['error']); ?>

<?php endif; ?>

<form action="update.php" method="POST">

<input type="hidden" name="embed" value="<?= $isEmbedded ? '1' : '0' ?>">

<input
type="hidden"
name="department_id"
value="<?= $department['department_id'] ?>">

<div class="grid grid-cols-1 gap-6">

<div>

<label class="block mb-2 font-semibold">

ตัวย่อสาขา

</label>

<input
type="text"
name="department_abbreviation"
data-label="ตัวย่อสาขา"
required
value="<?= htmlspecialchars($department['department_abbreviation']) ?>"
class="w-full border rounded-lg p-3">

</div>

<div>

<label class="block mb-2 font-semibold">

ชื่อสาขาวิชา

</label>

<input
type="text"
name="department_name"
data-label="ชื่อสาขาวิชา"
required
value="<?= htmlspecialchars($department['department_name']) ?>"
class="w-full border rounded-lg p-3">

</div>

<div>

<label class="block mb-2 font-semibold">

คณะ

</label>

<select
name="faculty_id"
required
class="w-full border rounded-lg p-3">

<?php foreach($faculties as $faculty): ?>

<option
value="<?= $faculty['faculty_id'] ?>"
<?= $department['faculty_id']==$faculty['faculty_id'] ? 'selected' : '' ?>>

<?= htmlspecialchars($faculty['faculty_name']) ?>

</option>

<?php endforeach; ?>

</select>

</div>

</div>

<div class="mt-8 flex gap-3">

<button
type="submit"
class="bg-yellow-500 hover:bg-yellow-600 text-white px-6 py-3 rounded-lg">

<i class="fa-solid fa-floppy-disk mr-2"></i>

บันทึกการแก้ไข

</button>

<a
href="index.php"
class="bg-gray-500 hover:bg-gray-600 text-white px-6 py-3 rounded-lg">

ยกเลิก

</a>

</div>

</form>

</div>

</div>

<?php

if ($isEmbedded): ?></body></html><?php else: include "../layouts/admin_footer.php"; endif;

?>
