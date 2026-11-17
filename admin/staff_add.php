<?php

if(isset($_POST['save'])){

    require_once __DIR__ . "/../../config/mail.php";

    $staff_code = trim($_POST['staff_code']);
    $firstname = trim($_POST['firstname']);
    $lastname = trim($_POST['lastname']);
    $email = trim($_POST['email']);

    $department = $_POST['department'];
    $position = $_POST['position'];

    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || !preg_match('/@(?:[a-z0-9-]+\.)*rmuti\.ac\.th$/i', $email)) {
        exit('กรุณาใช้อีเมล @rmuti.ac.th เท่านั้น');
    }

    try{

        $pdo->beginTransaction();

        // ตรวจสอบ Email ซ้ำ
        $stmt = $pdo->prepare("SELECT user_id FROM users WHERE email=?");
        $stmt->execute([$email]);

        if($stmt->fetch()){
            throw new Exception("Email นี้ถูกใช้งานแล้ว");
        }

        // สร้าง users โดยยังไม่ตั้งรหัสผ่าน และกำหนด first_login = 1
        $stmt = $pdo->prepare(
            "INSERT INTO users (username, email, password, first_login, status) VALUES (?, ?, 'pending-password', 1, 'active')"
        );

        $stmt->execute([
            $staff_code,
            $email
        ]);

        $user_id = $pdo->lastInsertId();

        // บันทึก role ลงใน user_roles
        $stmt = $pdo->prepare("INSERT INTO user_roles (user_id, role) VALUES (?, 'staff')");
        $stmt->execute([$user_id]);

        // เพิ่ม staff
        $stmt = $pdo->prepare(
            "INSERT INTO staff (user_id, staff_code, firstname, lastname, email, department_id, position_id) VALUES (?, ?, ?, ?, ?, ?, ?)"
        );

        $stmt->execute([
            $user_id,
            $staff_code,
            $firstname,
            $lastname,
            $email,
            $department,
            $position
        ]);

        $pdo->commit();

        // สร้าง OTP และส่งอีเมลให้ผู้ใช้ เพื่อให้ตั้งรหัสผ่านผ่านระบบ 'ลืมรหัสผ่าน'
        $otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $expire_at = date('Y-m-d H:i:s', time() + 300);

        // ทำให้ OTP เก่าใช้ไม่ได้
        $stmt = $pdo->prepare("UPDATE password_reset_otp SET used = 1 WHERE user_id = ? AND used = 0");
        $stmt->execute([$user_id]);

        // บันทึก OTP ใหม่
        $stmt = $pdo->prepare("INSERT INTO password_reset_otp (user_id, otp_code, expire_at, used) VALUES (?, ?, ?, 0)");
        $stmt->execute([$user_id, $otp, $expire_at]);

        // ส่ง OTP ทางอีเมล
        $sent = sendOTPEmail($email, $otp, $expire_at);

        if (!$sent) {
            // ส่งอีเมลไม่สำเร็จ แต่บัญชีถูกสร้างแล้ว — แจ้งผู้ดูแล
            $_SESSION['error'] = 'สร้างบัญชีสำเร็จ แต่ไม่สามารถส่งอีเมล OTP ได้';
        } else {
            $_SESSION['success'] = 'สร้างบัญชีสำเร็จ ระบบได้ส่ง OTP ไปยังอีเมลของผู้ใช้งานเพื่อให้ตั้งรหัสผ่านแล้ว';
        }

        header('Location: admin.php?page=staff');
        exit;

    }catch(Exception $e){

        if ($pdo->inTransaction()) $pdo->rollBack();

        echo "<div class='bg-red-600 p-4 rounded mb-5'>" . htmlspecialchars($e->getMessage()) . "</div>";

    }

}

$departments = $pdo->query("
SELECT *
FROM department
ORDER BY department_name
")->fetchAll();

$positions = $pdo->query("
SELECT *
FROM positions
ORDER BY position_name
")->fetchAll();

?>

<div class="bg-gray-800 rounded-xl p-8">

<h2 class="text-3xl font-bold mb-6">

เพิ่มเจ้าหน้าที่

</h2>

<form method="post">

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
document.addEventListener('DOMContentLoaded', function(){
    const form = document.querySelector('form');
    form.addEventListener('submit', function(e){
        e.preventDefault();

        // เก็บข้อมูลจากฟอร์มเพื่อแสดงสรุป
        const data = new FormData(form);
        const summary = [];
        summary.push('รหัส: ' + (data.get('staff_code') || '-'));
        summary.push('ชื่อ: ' + (data.get('firstname') || '-') + ' ' + (data.get('lastname') || '-'));
        summary.push('Email: ' + (data.get('email') || '-'));
        summary.push('สาขา: ' + (form.querySelector('select[name=department] option:checked')?.textContent || '-'));
        summary.push('ตำแหน่ง: ' + (form.querySelector('select[name=position] option:checked')?.textContent || '-'));

        Swal.fire({
            title: 'ยืนยันข้อมูล',
            html: summary.join('<br>'),
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'ยืนยัน',
            cancelButtonText: 'ยกเลิก',
            confirmButtonColor: '#dc2626'
        }).then((res) => {
            if (res.isConfirmed) form.submit();
        });

    });
});
</script>

<div class="grid grid-cols-1 gap-5 sm:grid-cols-2">

<div>

<label>รหัสเจ้าหน้าที่</label>

<input
type="text"
name="staff_code"
required
class="w-full p-3 rounded text-black">

</div>

<div>

<label>Email</label>

<input
type="email"
name="email"
required
pattern=".+@rmuti\.ac\.th$"
class="w-full p-3 rounded text-black">

</div>

<div>

<label>ชื่อ</label>

<input
type="text"
name="firstname"
required
class="w-full p-3 rounded text-black">

</div>

<div>

<label>นามสกุล</label>

<input
type="text"
name="lastname"
required
class="w-full p-3 rounded text-black">

</div>

<div>

<label>สาขา</label>

<select
name="department"
class="w-full p-3 rounded text-black">

<?php foreach($departments as $d){ ?>

<option value="<?=$d['department_id']?>">

<?=$d['department_name']?>

</option>

<?php } ?>

</select>

</div>

<div>

<label>ตำแหน่ง</label>

<select
name="position"
class="w-full p-3 rounded text-black">

<?php foreach($positions as $p){ ?>

<option value="<?=$p['position_id']?>">

<?=$p['position_name']?>

</option>

<?php } ?>

</select>

</div>

</div>

<br>

<button
name="save"
class="bg-red-600 px-6 py-3 rounded">

บันทึก

</button>

<a
href="admin.php?page=staff"
class="bg-gray-600 px-6 py-3 rounded">

ยกเลิก

</a>

</form>

</div>
