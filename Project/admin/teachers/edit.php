<?php
require_once __DIR__ . '/../../middleware/admin.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/csrf_helper.php';

$pageTitle = 'แก้ไขอาจารย์';
$isEmbedded = ($_GET['embed'] ?? '') === '1';
$employeeId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$employeeId) { $_SESSION['admin_error'] = 'ไม่พบข้อมูลอาจารย์'; header('Location: index.php'); exit; }
$prefixes = $pdo->query("SELECT prefix_id,prefix_short FROM prefixes WHERE status='active' ORDER BY prefix_id")->fetchAll(PDO::FETCH_ASSOC);
$positions = $pdo->query('SELECT position_id,position_name FROM positions ORDER BY position_name')->fetchAll(PDO::FETCH_ASSOC);
$departments = $pdo->query('SELECT department_id,department_name FROM department ORDER BY department_name')->fetchAll(PDO::FETCH_ASSOC);
$findTeacher = $pdo->prepare("SELECT e.*,u.email,u.status,COALESCE((SELECT ep.position_id FROM employee_position ep WHERE ep.employee_id=e.employee_id AND ep.is_primary=1 LIMIT 1), (SELECT ep.position_id FROM employee_position ep WHERE ep.employee_id=e.employee_id LIMIT 1)) AS position_id FROM employee e JOIN users u ON u.user_id=e.user_id WHERE e.employee_id=? AND e.employee_type='teacher' LIMIT 1");
$findTeacher->execute([$employeeId]);
$teacher = $findTeacher->fetch(PDO::FETCH_ASSOC);
if (!$teacher) { $_SESSION['admin_error'] = 'ไม่พบข้อมูลอาจารย์'; header('Location: index.php'); exit; }
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_or_abort();
    $prefixId = (int) ($_POST['prefix_id'] ?? 0);
    $firstname = trim($_POST['firstname'] ?? ''); $lastname = trim($_POST['lastname'] ?? '');
    $email = strtolower(trim($_POST['email'] ?? '')); $phone = trim($_POST['phone'] ?? '');
    $departmentId = (int) ($_POST['department_id'] ?? 0); $positionId = (int) ($_POST['position_id'] ?? 0);
    $status = $_POST['status'] ?? '';
    try {
        if (!$prefixId || $firstname === '' || $lastname === '' || !$departmentId || !$positionId || !filter_var($email, FILTER_VALIDATE_EMAIL) || !in_array($status, ['active','inactive'], true)) throw new RuntimeException('กรุณากรอกข้อมูลอาจารย์ให้ครบถ้วน');
        if (!preg_match('/@(?:[a-z0-9-]+\.)*rmuti\.ac\.th$/i', $email)) throw new RuntimeException('กรุณาใช้อีเมล @rmuti.ac.th เท่านั้น');
        if (mb_strlen($phone) > 30) throw new RuntimeException('เบอร์โทรศัพท์ยาวเกิน 30 ตัวอักษร');
        $duplicate = $pdo->prepare('SELECT COUNT(*) FROM users WHERE email=? AND user_id<>?'); $duplicate->execute([$email, $teacher['user_id']]);
        if ((int) $duplicate->fetchColumn()) throw new RuntimeException('อีเมลนี้มีบัญชีอื่นใช้งานแล้ว');
        $valid = $pdo->prepare("SELECT (SELECT COUNT(*) FROM prefixes WHERE prefix_id=? AND status='active') + (SELECT COUNT(*) FROM positions WHERE position_id=?) + (SELECT COUNT(*) FROM department WHERE department_id=?)");
        $valid->execute([$prefixId, $positionId, $departmentId]); if ((int) $valid->fetchColumn() !== 3) throw new RuntimeException('คำนำหน้า ตำแหน่ง หรือสาขาวิชาไม่ถูกต้อง');
        $pdo->beginTransaction();
        $pdo->prepare('UPDATE users SET email=?,status=? WHERE user_id=?')->execute([$email, $status, $teacher['user_id']]);
        $pdo->prepare('UPDATE employee SET prefix_id=?,firstname=?,lastname=?,phone=?,department_id=? WHERE employee_id=?')->execute([$prefixId, $firstname, $lastname, $phone !== '' ? $phone : null, $departmentId, $employeeId]);
        $pdo->prepare('UPDATE employee_position SET is_primary=0 WHERE employee_id=?')->execute([$employeeId]);
        $exists = $pdo->prepare('SELECT COUNT(*) FROM employee_position WHERE employee_id=? AND position_id=?'); $exists->execute([$employeeId, $positionId]);
        if ((int) $exists->fetchColumn()) $pdo->prepare('UPDATE employee_position SET is_primary=1 WHERE employee_id=? AND position_id=?')->execute([$employeeId, $positionId]);
        else $pdo->prepare('INSERT INTO employee_position(employee_id,position_id,is_primary) VALUES (?,?,1)')->execute([$employeeId, $positionId]);
        $pdo->commit(); $_SESSION['admin_success'] = 'แก้ไขข้อมูลอาจารย์เรียบร้อยแล้ว'; header('Location: index.php'); exit;
    } catch (Throwable $exception) { if ($pdo->inTransaction()) $pdo->rollBack(); $error = $exception->getMessage(); $teacher = array_merge($teacher, $_POST, ['prefix_id' => $prefixId, 'department_id' => $departmentId, 'position_id' => $positionId, 'email' => $email, 'phone' => $phone, 'status' => $status]); }
}
if ($isEmbedded) {
    ?><!doctype html><html lang="th"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><script src="https://cdn.tailwindcss.com"></script><link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css"></head><body class="min-h-screen bg-slate-50 text-slate-900"><?php
} else {
    include __DIR__ . '/../layouts/admin_header.php'; include __DIR__ . '/../layouts/admin_sidebar.php'; include __DIR__ . '/../layouts/admin_navbar.php';
}
?>
<style>
    main.w-full > div { max-width: 64rem; }
    main.w-full > div > section { overflow: hidden; border-color: #e2e8f0; box-shadow: 0 10px 30px rgb(15 23 42 / 0.08); }
    main.w-full > div > section > div:first-child { border: 0; border-left: 6px solid #dc2626; background: linear-gradient(115deg, #fff1f2, #ffffff); padding: 1.5rem 1.75rem; color: #0f172a; }
    main.w-full > div > section > div:first-child > p:first-child { color: #b91c1c; }
    main.w-full > div > section > div:first-child > h1 { color: #0f172a; font-size: 1.65rem; }
    main.w-full > div > section > div:first-child > p:last-child { color: #64748b; }
    main.w-full > div > section form { padding: 1.75rem; }
    main.w-full > div > section label { color: #334155; }
    main.w-full > div > section input, main.w-full > div > section select { background: #fff; border-radius: .75rem; }
    main.w-full > div > section input:focus, main.w-full > div > section select:focus { border-color: #dc2626; outline: none; box-shadow: 0 0 0 4px rgb(254 226 226); }
    @media (max-width: 640px) { main.w-full > div > section > div:first-child, main.w-full > div > section form { padding: 1.25rem; } }
</style>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const page = document.querySelector('main.w-full > div');
    const backLink = page?.querySelector(':scope > a');
    const card = page?.querySelector(':scope > section');
    const saveButton = card?.querySelector('form button[type="submit"], form button:not([type])');
    const actions = saveButton?.parentElement;
    backLink?.remove();
    card?.classList.remove('mt-4');
    if (!actions || actions.querySelector('[data-cancel-edit]')) return;
    actions.classList.remove('justify-end');
    actions.classList.add('justify-between', 'gap-3', 'flex-col-reverse', 'sm:flex-row', 'sm:items-center');
    const cancel = document.createElement('a');
    cancel.href = 'index.php';
    cancel.dataset.cancelEdit = '1';
    cancel.className = 'inline-flex items-center justify-center gap-2 rounded-xl border border-slate-300 px-5 py-2.5 text-sm font-bold text-slate-600 transition hover:bg-slate-50';
    cancel.innerHTML = '<i class="fa-solid fa-xmark"></i>ยกเลิก';
    actions.prepend(cancel);
});
</script>
<main class="w-full p-4 sm:p-6"><div class="mx-auto max-w-4xl"><a href="index.php" class="inline-flex items-center gap-2 text-sm font-bold text-slate-600 hover:text-red-700"><i class="fa-solid fa-arrow-left"></i>กลับหน้าจัดการอาจารย์</a><section class="mt-4 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm"><div class="border-b border-slate-100 px-5 py-4"><p class="text-sm font-bold text-red-700">สิทธิ์ช่วยเหลือกรณีฉุกเฉิน</p><h1 class="mt-1 text-2xl font-black text-slate-900">แก้ไขข้อมูลอาจารย์</h1><p class="mt-1 text-sm text-slate-600">รหัสบุคลากร <span class="font-bold text-slate-800"><?= htmlspecialchars($teacher['employee_code']) ?></span> ไม่สามารถแก้ไขได้</p></div><form method="post" class="p-5 sm:p-6"><?= csrf_field() ?><?php if ($error): ?><div class="mb-4 rounded-xl border border-red-200 bg-red-50 p-4 text-red-800"><?= htmlspecialchars($error) ?></div><?php endif; ?><div class="grid gap-4 sm:grid-cols-2"><label class="text-sm font-bold">อีเมล @rmuti.ac.th<input required type="email" name="email" value="<?= htmlspecialchars($teacher['email'] ?? '') ?>" class="mt-1.5 w-full rounded-xl border border-slate-300 px-3 py-2.5 font-normal"></label><label class="text-sm font-bold">สถานะบัญชี<select required name="status" class="mt-1.5 w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 font-normal"><option value="active" <?= $teacher['status'] === 'active' ? 'selected' : '' ?>>เปิดใช้งาน</option><option value="inactive" <?= $teacher['status'] === 'inactive' ? 'selected' : '' ?>>ปิดใช้งาน</option></select></label><label class="text-sm font-bold">คำนำหน้า<select required name="prefix_id" class="mt-1.5 w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 font-normal"><?php foreach ($prefixes as $item): ?><option value="<?= (int)$item['prefix_id'] ?>" <?= (int)$teacher['prefix_id'] === (int)$item['prefix_id'] ? 'selected' : '' ?>><?= htmlspecialchars($item['prefix_short']) ?></option><?php endforeach; ?></select></label><label class="text-sm font-bold">ตำแหน่ง<select required name="position_id" class="mt-1.5 w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 font-normal"><?php foreach ($positions as $item): ?><option value="<?= (int)$item['position_id'] ?>" <?= (int)$teacher['position_id'] === (int)$item['position_id'] ? 'selected' : '' ?>><?= htmlspecialchars($item['position_name']) ?></option><?php endforeach; ?></select></label><label class="text-sm font-bold">ชื่อ<input required name="firstname" value="<?= htmlspecialchars($teacher['firstname']) ?>" class="mt-1.5 w-full rounded-xl border border-slate-300 px-3 py-2.5 font-normal"></label><label class="text-sm font-bold">นามสกุล<input required name="lastname" value="<?= htmlspecialchars($teacher['lastname']) ?>" class="mt-1.5 w-full rounded-xl border border-slate-300 px-3 py-2.5 font-normal"></label><label class="text-sm font-bold">สาขาวิชา<select required name="department_id" class="mt-1.5 w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 font-normal"><?php foreach ($departments as $item): ?><option value="<?= (int)$item['department_id'] ?>" <?= (int)$teacher['department_id'] === (int)$item['department_id'] ? 'selected' : '' ?>><?= htmlspecialchars($item['department_name']) ?></option><?php endforeach; ?></select></label><label class="text-sm font-bold">เบอร์โทรศัพท์ <span class="font-normal text-slate-400">(ถ้ามี)</span><input name="phone" maxlength="30" value="<?= htmlspecialchars($teacher['phone'] ?? '') ?>" class="mt-1.5 w-full rounded-xl border border-slate-300 px-3 py-2.5 font-normal"></label></div><div class="mt-6 flex justify-end"><button class="rounded-xl bg-red-700 px-5 py-2.5 font-bold text-white hover:bg-red-800"><i class="fa-solid fa-floppy-disk mr-2"></i>บันทึกการแก้ไข</button></div></form></section></div></main>
<?php if ($isEmbedded): ?></body></html><?php else: include __DIR__ . '/../layouts/admin_footer.php'; endif; ?>
