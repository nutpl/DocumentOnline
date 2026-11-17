<?php

$id = $_GET['id'] ?? 0;

$stmt = $pdo->prepare("
SELECT *
FROM staff
WHERE staff_id=?
");

$stmt->execute([$id]);

$staff = $stmt->fetch(PDO::FETCH_ASSOC);

if(!$staff){
    die("ไม่พบข้อมูลเจ้าหน้าที่");
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

if(isset($_POST['save'])){

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

        // อัปเดต staff
        $stmt = $pdo->prepare("
        UPDATE staff
        SET
            staff_code=?,
            firstname=?,
            lastname=?,
            email=?,
            department_id=?,
            position_id=?
        WHERE staff_id=?
        ");

        $stmt->execute([
            $staff_code,
            $firstname,
            $lastname,
            $email,
            $department,
            $position,
            $id
        ]);

        // อัปเดต users
        $stmt = $pdo->prepare("
        UPDATE users
        SET
            username=?,
            email=?
        WHERE user_id=?
        ");

        $stmt->execute([
            $staff_code,
            $email,
            $staff['user_id']
        ]);

        $pdo->commit();

        echo "
        <script>
        alert('แก้ไขข้อมูลเรียบร้อย');
        location='admin.php?page=staff';
        </script>
        ";

        exit;

    }catch(Exception $e){

        $pdo->rollBack();

        echo "<div class='bg-red-600 p-4 rounded'>".$e->getMessage()."</div>";

    }

}

?>

<div class="bg-gray-800 rounded-xl p-8">

<h2 class="text-3xl font-bold mb-6">

แก้ไขเจ้าหน้าที่

</h2>

<form method="post">

<div class="grid grid-cols-1 gap-5 sm:grid-cols-2">

<div>

<label>รหัสเจ้าหน้าที่</label>

<input
type="text"
name="staff_code"
value="<?=htmlspecialchars($staff['staff_code'])?>"
class="w-full p-3 rounded text-black">

</div>

<div>

<label>Email</label>

<input
type="email"
name="email"
pattern=".+@rmuti\.ac\.th$"
value="<?=htmlspecialchars($staff['email'])?>"
class="w-full p-3 rounded text-black">

</div>

<div>

<label>ชื่อ</label>

<input
type="text"
name="firstname"
value="<?=htmlspecialchars($staff['firstname'])?>"
class="w-full p-3 rounded text-black">

</div>

<div>

<label>นามสกุล</label>

<input
type="text"
name="lastname"
value="<?=htmlspecialchars($staff['lastname'])?>"
class="w-full p-3 rounded text-black">

</div>

<div>

<label>สาขา</label>

<select
name="department"
class="w-full p-3 rounded text-black">

<?php foreach($departments as $d){ ?>

<option
value="<?=$d['department_id']?>"
<?=$staff['department_id']==$d['department_id']?'selected':''?>>

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

<option
value="<?=$p['position_id']?>"
<?=$staff['position_id']==$p['position_id']?'selected':''?>>

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
