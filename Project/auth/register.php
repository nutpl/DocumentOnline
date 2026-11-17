<?php
session_start();
require_once '../config/database.php';
require_once '../helpers/csrf_helper.php';

$faculties = $pdo->query('SELECT faculty_id, faculty_name FROM faculty ORDER BY faculty_name')->fetchAll(PDO::FETCH_ASSOC);
$studentPrefixes = $pdo->query("SELECT prefix_id, prefix_short FROM prefixes WHERE prefix_short IN ('นาย', 'นางสาว') AND status='active' ORDER BY FIELD(prefix_short, 'นาย', 'นางสาว')")->fetchAll(PDO::FETCH_ASSOC);
$old = $_SESSION['register_old'] ?? [];
unset($_SESSION['register_old']);
?>
<!doctype html>
<html lang="th">
<head>
    <link rel="icon" type="image/png" href="/Project/assets/images/fet-logo-transparent.png?v=20260815-3">
  <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
  <title>สมัครสมาชิกนักศึกษา | ระบบจัดการเอกสารคำร้องของคณะผ่านเว็บแอพพลิเคชั่น</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="/Project/assets/css/app-ui.css?v=20260902-2">
  <link rel="stylesheet" href="/Project/assets/css/responsive.css?v=20260910-1">
  <link rel="stylesheet" href="/Project/assets/css/auth-campus-background.css?v=20260917-1">
  <link rel="stylesheet" href="/Project/assets/css/auth-index-modal.css?v=20260918-1">
  <script src="/Project/assets/js/unified-page-title.js?v=20260902-2" defer></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
  <script src="/Project/assets/js/password-visibility.js?v=20260901-1" defer></script>
  <style>
    #registerForm > * + * { margin-top: 1rem !important; }
    #registerForm section h2 { margin-bottom: .75rem !important; }
    #registerForm .grid { gap: .75rem !important; }
    #registerForm input, #registerForm select { padding-top: .5rem !important; padding-bottom: .5rem !important; }
  </style>
</head>
<body class="auth-campus-page auth-index-modal py-6 px-4">
<?php include __DIR__ . '/auth_index_modal_background.php'; ?>
<main class="max-w-2xl mx-auto">
  <form id="registerForm" action="register_process.php" method="post" data-confirm-show-password="true" class="auth-campus-card rounded-2xl p-5 sm:p-6 space-y-5">
    <?= csrf_field() ?>
    <div class="flex flex-col items-center text-center">
      <div class="flex h-[120px] w-[120px] items-center justify-center"><img src="/Project/assets/images/rmuti-university-seal.png" alt="ตรามหาวิทยาลัยเทคโนโลยีราชมงคล" class="h-full w-full object-contain"></div>
      <p class="mt-1 text-[11px] font-bold tracking-wide text-slate-500">มหาวิทยาลัยเทคโนโลยีราชมงคลอีสาน</p>
      <h1 class="mt-2 text-2xl font-bold text-slate-900">สมัครสมาชิกนักศึกษา</h1>
      <p class="mt-1 text-slate-500">ระบบจัดการเอกสารคำร้องของคณะผ่านเว็บแอปพลิเคชัน</p>
    </div>
    <?php if (!empty($_SESSION['error'])): ?><div class="rounded-lg bg-red-50 border border-red-200 text-red-700 px-4 py-3"><?= htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></div><?php endif; ?>
    <section><h2 class="font-semibold text-lg mb-4"><i class="fa-solid fa-user text-red-600 mr-2"></i>ข้อมูลส่วนตัว</h2><div class="grid sm:grid-cols-3 gap-4">
      <label class="block text-sm font-medium">คำนำหน้า<select name="prefix_id" required class="mt-1 w-full border rounded-lg px-3 py-2.5"><option value="">เลือกคำนำหน้า</option><?php foreach($studentPrefixes as $prefix): ?><option value="<?=$prefix['prefix_id']?>" <?=($old['prefix_id']??'')==$prefix['prefix_id']?'selected':''?>><?=htmlspecialchars($prefix['prefix_short'])?></option><?php endforeach;?></select></label>
      <label class="block text-sm font-medium">ชื่อ<input name="firstname" value="<?=htmlspecialchars($old['firstname']??'')?>" required minlength="1" maxlength="100" pattern="[\p{L}\p{M}]+(?:[ .'\-][\p{L}\p{M}]+)*" title="ใช้ตัวอักษรเท่านั้น ไม่อนุญาตตัวเลขหรือสัญลักษณ์พิเศษ" class="mt-1 w-full border rounded-lg px-3 py-2.5" autocomplete="given-name"></label>
      <label class="block text-sm font-medium">นามสกุล<input name="lastname" value="<?=htmlspecialchars($old['lastname']??'')?>" required minlength="1" maxlength="100" pattern="[\p{L}\p{M}]+(?:[ .'\-][\p{L}\p{M}]+)*" title="ใช้ตัวอักษรเท่านั้น ไม่อนุญาตตัวเลขหรือสัญลักษณ์พิเศษ" class="mt-1 w-full border rounded-lg px-3 py-2.5" autocomplete="family-name"></label>
      <label class="block text-sm font-medium">รหัสนักศึกษา<input id="studentCode" name="student_number" value="<?=htmlspecialchars($old['student_number']??'')?>" required minlength="13" maxlength="13" inputmode="numeric" pattern="[0-9]{11}-[0-9]" title="กรอกรหัสนักศึกษาเป็นตัวเลข 12 หลัก ระบบจะเติมขีดให้อัตโนมัติ" placeholder="67172310596-0" class="mt-1 w-full border rounded-lg px-3 py-2.5" autocomplete="username"><span class="mt-1 block text-xs font-normal text-slate-500"></span></label>
      <label class="block text-sm font-medium">อีเมลมหาวิทยาลัย<input name="email" type="email" value="<?=htmlspecialchars($old['email']??'')?>" required placeholder="example@rmuti.ac.th" class="mt-1 w-full border rounded-lg px-3 py-2.5" autocomplete="email"><span data-field-hint class="mt-1 block text-xs font-normal text-red-600"><i class="fa-solid fa-circle-info mr-1"></i>ต้องใช้อีเมล @rmuti.ac.th</span></label>
    </div></section>
    <section><h2 class="font-semibold text-lg mb-4"><i class="fa-solid fa-building-columns text-red-600 mr-2"></i>ข้อมูลการศึกษา</h2><div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-4">
      <label class="block text-sm font-medium">คณะ<select name="faculty_id" id="faculty" required class="mt-1 w-full border rounded-lg px-3 py-2.5"><option value="">เลือกคณะ</option><?php foreach($faculties as $faculty): ?><option value="<?=$faculty['faculty_id']?>"><?=htmlspecialchars($faculty['faculty_name'])?></option><?php endforeach;?></select></label>
      <label class="block text-sm font-medium">สาขา<select name="department_id" id="department" required disabled class="mt-1 w-full border rounded-lg px-3 py-2.5"><option value="">เลือกคณะก่อน</option></select></label>
      <label class="block text-sm font-medium">กลุ่มเรียน<select name="group_id" id="group" required disabled class="mt-1 w-full border rounded-lg px-3 py-2.5"><option value="">เลือกสาขาก่อน</option></select></label>
      <label class="block text-sm font-medium">อาจารย์ที่ปรึกษา<input id="advisor" readonly value="-" class="mt-1 w-full bg-slate-100 border rounded-lg px-3 py-2.5 text-slate-600"></label>
    </div><p id="groupHint" class="text-sm text-slate-500 mt-3"></p></section>
    <section><h2 class="font-semibold text-lg mb-4"><i class="fa-solid fa-lock text-red-600 mr-2"></i>รหัสผ่าน</h2><div class="grid sm:grid-cols-2 gap-4">
      <label class="block text-sm font-medium">รหัสผ่าน<input id="password" name="password" type="password" required minlength="8" maxlength="72" title="รหัสผ่านต้องมีอย่างน้อย 8 ตัวอักษร" class="mt-1 w-full border rounded-lg px-3 py-2.5" autocomplete="new-password"></label>
      <label class="block text-sm font-medium">ยืนยันรหัสผ่าน<input id="confirmPassword" name="confirm_password" type="password" required minlength="8" maxlength="72" class="mt-1 w-full border rounded-lg px-3 py-2.5" autocomplete="new-password"></label>
    </div><p class="text-xs text-slate-500 mt-2">รหัสผ่านต้องมีอย่างน้อย 8 ตัวอักษร</p></section>
    <button class="w-full bg-red-600 hover:bg-red-700 text-white font-semibold py-3 rounded-lg"><i class="fa-solid fa-user-plus mr-2"></i>สมัครสมาชิก</button>
    <p class="text-center text-sm text-slate-600">มีบัญชีแล้ว? <a class="text-red-600 font-medium hover:underline" href="login.php">เข้าสู่ระบบ</a></p>
  </form>
</main>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="/Project/assets/js/form-confirm.js?v=20261108-2" defer></script>
<script>
const faculty=document.querySelector('#faculty'), department=document.querySelector('#department'), group=document.querySelector('#group'), advisor=document.querySelector('#advisor'), hint=document.querySelector('#groupHint'), password=document.querySelector('#password'), confirmPassword=document.querySelector('#confirmPassword'), studentCode=document.querySelector('#studentCode');
function formatStudentCode(value){const digits=String(value).replace(/\D/g,'').slice(0,12);return digits.length>11?digits.slice(0,11)+'-'+digits.slice(11):digits;}
studentCode.addEventListener('input',()=>{const formatted=formatStudentCode(studentCode.value);if(studentCode.value!==formatted)studentCode.value=formatted;});
studentCode.value=formatStudentCode(studentCode.value);
function setOptions(select, placeholder, rows, valueKey, labelKey){select.innerHTML='';select.add(new Option(placeholder,''));rows.forEach(row=>select.add(new Option(row[labelKey],row[valueKey])));select.disabled=false;}
faculty.addEventListener('change',async()=>{department.disabled=true;group.disabled=true;advisor.value='-';hint.textContent='';setOptions(group,'เลือกสาขาก่อน',[],'group_id','group_name');if(!faculty.value){department.innerHTML='<option value="">เลือกคณะก่อน</option>';return;}const response=await fetch('get_departments.php?faculty_id='+encodeURIComponent(faculty.value));setOptions(department,'เลือกสาขา',await response.json(),'department_id','department_name');});
department.addEventListener('change',async()=>{group.disabled=true;advisor.value='-';hint.textContent='';if(!department.value){group.innerHTML='<option value="">เลือกสาขาก่อน</option>';return;}const response=await fetch('get_groups.php?department_id='+encodeURIComponent(department.value));const rows=await response.json();setOptions(group,'เลือกกลุ่มเรียน',rows,'group_id','group_name');if(!rows.length)hint.textContent='ยังไม่มีกลุ่มเรียนในสาขานี้ โปรดติดต่อผู้ดูแลระบบ';});
group.addEventListener('change',async()=>{advisor.value='-';if(!group.value)return;const response=await fetch('get_group_info.php?group_id='+encodeURIComponent(group.value));const data=await response.json();advisor.value=data.advisor_name||'ยังไม่ได้กำหนดอาจารย์ที่ปรึกษา';});
document.querySelector('#registerForm').addEventListener('submit',event=>{if(password.value!==confirmPassword.value){event.preventDefault();alert('รหัสผ่านและยืนยันรหัสผ่านไม่ตรงกัน');confirmPassword.focus();}});
</script>
</body></html>
