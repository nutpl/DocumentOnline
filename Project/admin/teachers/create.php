<?php
require_once __DIR__ . '/../../middleware/admin.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/csrf_helper.php';

$pageTitle = 'เพิ่มอาจารย์';
$prefixes = $pdo->query("SELECT prefix_id,prefix_short FROM prefixes WHERE status='active' ORDER BY prefix_id")->fetchAll(PDO::FETCH_ASSOC);
$positions = $pdo->query('SELECT position_id,position_name FROM positions ORDER BY position_name')->fetchAll(PDO::FETCH_ASSOC);
$departments = $pdo->query('SELECT department_id,department_name FROM department ORDER BY department_name')->fetchAll(PDO::FETCH_ASSOC);
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_or_abort();
    $code = trim($_POST['employee_code'] ?? '');
    $prefixId = (int) ($_POST['prefix_id'] ?? 0);
    $firstname = trim($_POST['firstname'] ?? '');
    $lastname = trim($_POST['lastname'] ?? '');
    $email = strtolower(trim($_POST['email'] ?? ''));
    $phone = trim($_POST['phone'] ?? '');
    $departmentId = (int) ($_POST['department_id'] ?? 0);
    $positionId = (int) ($_POST['position_id'] ?? 0);
    try {
        if ($code === '' || $firstname === '' || $lastname === '' || !$prefixId || !$departmentId || !$positionId || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('กรุณากรอกข้อมูลอาจารย์ให้ครบถ้วน');
        }
        if (!preg_match('/@(?:[a-z0-9-]+\.)*rmuti\.ac\.th$/i', $email)) throw new RuntimeException('กรุณาใช้อีเมล @rmuti.ac.th เท่านั้น');
        if (mb_strlen($phone) > 30) throw new RuntimeException('เบอร์โทรศัพท์ยาวเกิน 30 ตัวอักษร');
        $check = $pdo->prepare('SELECT COUNT(*) FROM users u LEFT JOIN employee e ON e.user_id=u.user_id WHERE u.email=? OR u.username=? OR e.employee_code=?');
        $check->execute([$email, $code, $code]);
        if ((int) $check->fetchColumn()) throw new RuntimeException('รหัสบุคลากรหรืออีเมลนี้มีอยู่ในระบบแล้ว');
        $valid = $pdo->prepare("SELECT (SELECT COUNT(*) FROM prefixes WHERE prefix_id=? AND status='active') + (SELECT COUNT(*) FROM positions WHERE position_id=?) + (SELECT COUNT(*) FROM department WHERE department_id=?)");
        $valid->execute([$prefixId, $positionId, $departmentId]);
        if ((int) $valid->fetchColumn() !== 3) throw new RuntimeException('คำนำหน้า ตำแหน่ง หรือสาขาวิชาไม่ถูกต้อง');
        $pdo->beginTransaction();
        $pdo->prepare("INSERT INTO users(username,email,password,first_login,status) VALUES (?,?,'pending-password',1,'active')")->execute([$code, $email]);
        $userId = (int) $pdo->lastInsertId();
        $pdo->prepare("INSERT INTO user_roles(user_id,role) VALUES (?,'teacher')")->execute([$userId]);
        $pdo->prepare("INSERT INTO employee(user_id,employee_code,prefix_id,firstname,lastname,phone,department_id,employee_type) VALUES (?,?,?,?,?,?,?,'teacher')")->execute([$userId, $code, $prefixId, $firstname, $lastname, $phone !== '' ? $phone : null, $departmentId]);
        $employeeId = (int) $pdo->lastInsertId();
        $pdo->prepare('INSERT INTO employee_position(employee_id,position_id,is_primary) VALUES (?,?,1)')->execute([$employeeId, $positionId]);
        $pdo->commit();
        $_SESSION['admin_success'] = 'เพิ่มข้อมูลอาจารย์แล้ว อาจารย์สามารถตั้งรหัสผ่านผ่าน OTP ได้';
        header('Location: index.php'); exit;
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $error = $exception->getMessage();
    }
}
include __DIR__ . '/../layouts/admin_header.php';
include __DIR__ . '/../layouts/admin_sidebar.php';
include __DIR__ . '/../layouts/admin_navbar.php';
?>
<main class="w-full p-4 sm:p-6"><div class="mx-auto max-w-4xl"><a href="index.php" class="inline-flex items-center gap-2 text-sm font-bold text-slate-600 hover:text-red-700"><i class="fa-solid fa-arrow-left"></i>กลับหน้าจัดการอาจารย์</a><section class="mt-4 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm"><div class="border-b border-slate-100 px-5 py-4"><p class="text-sm font-bold text-red-700">สิทธิ์ช่วยเหลือกรณีฉุกเฉิน</p><h1 class="mt-1 text-2xl font-black text-slate-900">เพิ่มอาจารย์</h1><p class="mt-1 text-sm text-slate-600">ข้อมูลนี้ใช้สร้างบัญชีอาจารย์ใหม่ในสาขาที่เลือก</p></div><form method="post" class="p-5 sm:p-6"><?= csrf_field() ?><?php if ($error): ?><div class="mb-4 rounded-xl border border-red-200 bg-red-50 p-4 text-red-800"><?= htmlspecialchars($error) ?></div><?php endif; ?><div class="grid gap-4 sm:grid-cols-2"><label class="text-sm font-bold">รหัสบุคลากร<input required name="employee_code" value="<?= htmlspecialchars($_POST['employee_code'] ?? '') ?>" class="mt-1.5 w-full rounded-xl border border-slate-300 px-3 py-2.5 font-normal"></label><label class="text-sm font-bold">อีเมล @rmuti.ac.th<input required type="email" name="email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" class="mt-1.5 w-full rounded-xl border border-slate-300 px-3 py-2.5 font-normal"></label><label class="text-sm font-bold">คำนำหน้า<select required name="prefix_id" class="mt-1.5 w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 font-normal"><option value="">เลือกคำนำหน้า</option><?php foreach ($prefixes as $item): ?><option value="<?= (int)$item['prefix_id'] ?>" <?= (int)($_POST['prefix_id'] ?? 0) === (int)$item['prefix_id'] ? 'selected' : '' ?>><?= htmlspecialchars($item['prefix_short']) ?></option><?php endforeach; ?></select></label><label class="text-sm font-bold">ตำแหน่ง<select required name="position_id" class="mt-1.5 w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 font-normal"><option value="">เลือกตำแหน่ง</option><?php foreach ($positions as $item): ?><option value="<?= (int)$item['position_id'] ?>" <?= (int)($_POST['position_id'] ?? 0) === (int)$item['position_id'] ? 'selected' : '' ?>><?= htmlspecialchars($item['position_name']) ?></option><?php endforeach; ?></select></label><label class="text-sm font-bold">ชื่อ<input required name="firstname" value="<?= htmlspecialchars($_POST['firstname'] ?? '') ?>" class="mt-1.5 w-full rounded-xl border border-slate-300 px-3 py-2.5 font-normal"></label><label class="text-sm font-bold">นามสกุล<input required name="lastname" value="<?= htmlspecialchars($_POST['lastname'] ?? '') ?>" class="mt-1.5 w-full rounded-xl border border-slate-300 px-3 py-2.5 font-normal"></label><label class="text-sm font-bold">สาขาวิชา<select required name="department_id" class="mt-1.5 w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 font-normal"><option value="">เลือกสาขาวิชา</option><?php foreach ($departments as $item): ?><option value="<?= (int)$item['department_id'] ?>" <?= (int)($_POST['department_id'] ?? 0) === (int)$item['department_id'] ? 'selected' : '' ?>><?= htmlspecialchars($item['department_name']) ?></option><?php endforeach; ?></select></label><label class="text-sm font-bold">เบอร์โทรศัพท์ <span class="font-normal text-slate-400">(ถ้ามี)</span><input name="phone" maxlength="30" value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>" class="mt-1.5 w-full rounded-xl border border-slate-300 px-3 py-2.5 font-normal"></label></div><div class="mt-6 flex justify-end"><button class="rounded-xl bg-red-700 px-5 py-2.5 font-bold text-white hover:bg-red-800"><i class="fa-solid fa-user-plus mr-2"></i>เพิ่มอาจารย์</button></div></form></section></div></main>
<?php include __DIR__ . '/../layouts/admin_footer.php'; ?>
