<?php

session_start();

require_once "../../middleware/admin.php";
require_once "../../config/database.php";

$pageTitle="เพิ่มคณะ";

include "../layouts/admin_header.php";
include "../layouts/admin_sidebar.php";
include "../layouts/admin_navbar.php";

?>

<div class="max-w-3xl mx-auto">

<div class="bg-white rounded-xl shadow-lg p-8">

<div class="mb-8">

<h1 class="text-3xl font-bold">

เพิ่มคณะ

</h1>

<p class="text-gray-500 mt-2">

กรอกข้อมูลคณะใหม่

</p>

</div>

<?php if(isset($_SESSION['error'])): ?>

<div class="bg-red-100 text-red-700 p-4 rounded mb-5">

<?= $_SESSION['error']; ?>

</div>

<?php unset($_SESSION['error']); ?>

<?php endif; ?>

<form action="store.php" method="POST">
    <div class="mb-5">

<label class="block mb-2 font-semibold">

ตัวย่อคณะ

</label>

<input

type="text"

name="faculty_abbreviation"

required

maxlength="20"

placeholder="เช่น ENG"

class="w-full border rounded-lg p-3">

</div>

<div class="mb-5">

<label class="block mb-2 font-semibold">

ชื่อคณะ

</label>

<input

type="text"

name="faculty_name"

required

class="w-full border rounded-lg p-3">

</div>
<div class="flex gap-3 mt-8">

<button

type="submit"

class="bg-red-600 hover:bg-red-700 text-white px-6 py-3 rounded-lg">

<i class="fa-solid fa-floppy-disk"></i>

บันทึก

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

include "../layouts/admin_footer.php";

?>
