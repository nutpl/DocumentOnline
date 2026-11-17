<?php

session_start();

require_once "../../middleware/admin.php";
require_once "../../config/database.php";

$pageTitle = "เพิ่มสาขาวิชา";

include "../layouts/admin_header.php";
include "../layouts/admin_sidebar.php";
include "../layouts/admin_navbar.php";

$faculties = $pdo->query('SELECT faculty_id, faculty_abbreviation, faculty_name FROM faculty ORDER BY faculty_name ASC')
    ->fetchAll(PDO::FETCH_ASSOC);
$selectedFacultyId = filter_input(INPUT_GET, 'faculty_id', FILTER_VALIDATE_INT) ?: 0;

?>

<div class="w-full p-4 sm:p-6">
<div class="mx-auto max-w-3xl">
<section class="mb-5 border-b border-slate-200 pb-5"><h1 class="mt-1 text-2xl font-black text-slate-900">เพิ่มสาขาวิชา</h1><p class="mt-1 text-sm text-slate-600">ระบุตัวย่อ ชื่อสาขา และเลือกคณะที่สังกัด</p></section>

<div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
<div class="border-b border-slate-100 px-5 py-4"><h2 class="font-black text-slate-900">ข้อมูลสาขาวิชา</h2><p class="mt-0.5 text-sm text-slate-500">กรอกข้อมูลให้ครบก่อนบันทึก</p></div>

<?php if(isset($_SESSION['error'])): ?>

<div class="mx-5 mt-5 rounded-xl border border-red-200 bg-red-50 p-4 text-red-800">

<?= $_SESSION['error']; ?>

</div>

<?php unset($_SESSION['error']); ?>

<?php endif; ?>

<form action="store.php" method="POST" class="p-5 sm:p-6">

<div class="grid grid-cols-1 gap-5">

<div>

<label class="block mb-2 font-semibold">

ตัวย่อสาขา

</label>

<input
type="text"
name="department_abbreviation"
data-label="ตัวย่อสาขา"
required
placeholder="เช่น CPE."
class="h-11 w-full rounded-xl border border-slate-300 px-3 text-slate-900 outline-none focus:border-red-500 focus:ring-4 focus:ring-red-100">

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
placeholder="เช่น สาขาวิชาวิศวกรรมคอมพิวเตอร์"
class="h-11 w-full rounded-xl border border-slate-300 px-3 text-slate-900 outline-none focus:border-red-500 focus:ring-4 focus:ring-red-100">

</div>

<div>

<label class="block mb-2 font-semibold">

คณะ

</label>

<select name="faculty_id" required class="h-11 w-full rounded-xl border border-slate-300 bg-white px-3 text-slate-900 outline-none focus:border-red-500 focus:ring-4 focus:ring-red-100">
    <option value="">เลือกคณะ</option>
    <?php foreach ($faculties as $faculty): ?><option value="<?= (int) $faculty['faculty_id'] ?>" <?= (int)$faculty['faculty_id'] === $selectedFacultyId ? 'selected' : '' ?>><?= htmlspecialchars($faculty['faculty_abbreviation'] . ' — ' . $faculty['faculty_name']) ?></option><?php endforeach; ?>
</select>

</div>

</div>

<div class="mt-6 flex flex-wrap justify-end gap-3 border-t border-slate-100 pt-5">

<button
type="submit"
class="inline-flex h-11 items-center justify-center rounded-xl bg-red-700 px-5 text-sm font-bold text-white transition hover:bg-red-800">

<i class="fa-solid fa-floppy-disk mr-2"></i>

บันทึก

</button>

<a
href="index.php"
class="inline-flex h-11 items-center justify-center rounded-xl border border-slate-300 px-5 text-sm font-bold text-slate-600 transition hover:bg-slate-50">

ยกเลิก

</a>

</div>

</form>

</div>

</div>

</div>

<?php

include "../layouts/admin_footer.php";

?>
