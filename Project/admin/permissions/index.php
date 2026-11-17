<?php
require_once __DIR__ . '/../../middleware/admin.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/csrf_helper.php';
require_once __DIR__ . '/../../helpers/pagination_helper.php';

function h(string $value): string { return htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); }
function permissionsReturnUrl(): string { $query = $_GET; return 'index.php' . ($query ? '?' . http_build_query($query) : ''); }

$roleMeta = [
    'admin' => ['label' => 'ผู้ดูแลระบบ', 'class' => 'bg-red-50 text-red-700'],
    'staff' => ['label' => 'เจ้าหน้าที่สาขา', 'class' => 'bg-amber-50 text-amber-800'],
    'teacher' => ['label' => 'อาจารย์', 'class' => 'bg-blue-50 text-blue-700'],
    'student' => ['label' => 'นักศึกษา', 'class' => 'bg-emerald-50 text-emerald-700'],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_or_abort();
    $userId = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT) ?: 0;
    $newRole = (string) ($_POST['role'] ?? '');
    try {
        $profileStmt = $pdo->prepare("SELECT u.user_id, u.username, s.student_id, e.employee_id, e.employee_type
            FROM users u LEFT JOIN student s ON s.user_id = u.user_id LEFT JOIN employee e ON e.user_id = u.user_id
            WHERE u.user_id = ? LIMIT 1");
        $profileStmt->execute([$userId]);
        $profile = $profileStmt->fetch(PDO::FETCH_ASSOC);
        if (!$profile) throw new RuntimeException('ไม่พบบัญชีผู้ใช้ที่ต้องการปรับสิทธิ์');
        $currentRoleStmt = $pdo->prepare('SELECT role FROM user_roles WHERE user_id = ? LIMIT 1');
        $currentRoleStmt->execute([$userId]);
        $currentRole = (string) ($currentRoleStmt->fetchColumn() ?: '');
        if ($profile['student_id'] !== null || $profile['employee_id'] === null) throw new RuntimeException('บัญชีนักศึกษาและผู้ดูแลระบบกำหนดสิทธิ์ตามประเภทบัญชี จึงเปลี่ยนจากหน้านี้ไม่ได้');
        if (!in_array($newRole, ['teacher', 'staff'], true)) throw new RuntimeException('เลือกสิทธิ์ไม่ถูกต้อง');

        $pdo->beginTransaction();
        $pdo->prepare('UPDATE employee SET employee_type = ? WHERE employee_id = ?')->execute([$newRole, $profile['employee_id']]);
        $pdo->prepare('DELETE FROM user_roles WHERE user_id = ?')->execute([$userId]);
        $pdo->prepare('INSERT INTO user_roles (user_id, role) VALUES (?, ?)')->execute([$userId, $newRole]);
        $actorId = (int) ($_SESSION['user_id'] ?? 0);
        $detail = 'ปรับสิทธิ์บัญชี ' . $profile['username'] . ' จาก ' . ($roleMeta[$currentRole]['label'] ?? 'ไม่ระบุ') . ' เป็น ' . $roleMeta[$newRole]['label'];
        $pdo->prepare('INSERT INTO activity_log (user_id, activity, detail) VALUES (?, ?, ?)')->execute([$actorId, 'role_updated', $detail]);
        $pdo->commit();
        $_SESSION['admin_success'] = 'บันทึกสิทธิ์ของ ' . $profile['username'] . ' แล้ว';
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $_SESSION['admin_error'] = $e->getMessage();
    }
    header('Location: ' . permissionsReturnUrl());
    exit;
}

$pageTitle = 'จัดการสิทธิ์ใช้งาน';
$search = trim((string) ($_GET['search'] ?? ''));
$roleFilter = (string) ($_GET['role'] ?? '');
if (!array_key_exists($roleFilter, $roleMeta)) $roleFilter = '';
$conditions = []; $params = [];
if ($search !== '') {
    $conditions[] = '(u.username LIKE ? OR u.email LIKE ? OR s.student_number LIKE ? OR e.employee_code LIKE ? OR CONCAT(COALESCE(s.firstname, e.firstname, \'\'), \' \', COALESCE(s.lastname, e.lastname, \'\')) LIKE ?)';
    $keyword = '%' . $search . '%'; $params = [$keyword, $keyword, $keyword, $keyword, $keyword];
}
if ($roleFilter !== '') {
    $conditions[] = "COALESCE(ur.role, CASE WHEN s.student_id IS NOT NULL THEN 'student' WHEN e.employee_type IS NOT NULL THEN e.employee_type ELSE 'admin' END) = ?";
    $params[] = $roleFilter;
}
$where = $conditions ? ' WHERE ' . implode(' AND ', $conditions) : '';
$countStmt = $pdo->prepare("SELECT COUNT(DISTINCT u.user_id) FROM users u LEFT JOIN user_roles ur ON ur.user_id = u.user_id LEFT JOIN student s ON s.user_id = u.user_id LEFT JOIN employee e ON e.user_id = u.user_id" . $where);
$countStmt->execute($params); $totalUsers = (int) $countStmt->fetchColumn();
$perPage = selected_per_page(10); $currentPage = max(1, (int) ($_GET['page'] ?? 1));
$totalPages = max(1, (int) ceil($totalUsers / $perPage)); $currentPage = min($currentPage, $totalPages); $offset = ($currentPage - 1) * $perPage;
$listSql = "SELECT u.user_id, u.username, u.email, u.status, ur.role, s.student_id, s.student_number,
        s.firstname AS student_firstname, s.lastname AS student_lastname, e.employee_id, e.employee_code,
        e.employee_type, e.firstname AS employee_firstname, e.lastname AS employee_lastname, d.department_name
    FROM users u LEFT JOIN user_roles ur ON ur.user_id = u.user_id LEFT JOIN student s ON s.user_id = u.user_id
    LEFT JOIN group_class gc ON gc.group_id = s.group_id LEFT JOIN employee e ON e.user_id = u.user_id
    LEFT JOIN department d ON d.department_id = COALESCE(gc.department_id, e.department_id)"
    . $where . ' ORDER BY u.user_id DESC LIMIT ' . (int) $perPage . ' OFFSET ' . (int) $offset;
$listStmt = $pdo->prepare($listSql); $listStmt->execute($params); $users = $listStmt->fetchAll(PDO::FETCH_ASSOC);
$historyStmt = $pdo->query("SELECT al.detail, al.activity_time, u.username FROM activity_log al LEFT JOIN users u ON u.user_id = al.user_id WHERE al.activity = 'role_updated' ORDER BY al.activity_time DESC LIMIT 5");
$history = $historyStmt->fetchAll(PDO::FETCH_ASSOC);

include __DIR__ . '/../layouts/admin_header.php';
include __DIR__ . '/../layouts/admin_sidebar.php';
include __DIR__ . '/../layouts/admin_navbar.php';
?>
<div class="w-full p-4 sm:p-6">
    <section class="flex flex-wrap items-end justify-between gap-4 border-b border-slate-200 pb-5">
        <div><p class="text-sm font-bold text-red-700">ผู้ดูแลระบบ</p><h1 class="mt-1 text-2xl font-black text-slate-900">จัดการสิทธิ์ใช้งาน</h1><p class="mt-1 text-sm text-slate-600">ตรวจสอบและปรับบทบาทของบัญชีอาจารย์และเจ้าหน้าที่</p></div>
        <span class="rounded-xl bg-slate-100 px-4 py-2 text-sm font-bold text-slate-700"><i class="fa-solid fa-users mr-1"></i><?= $totalUsers ?> บัญชี</span>
    </section>
    <section class="mt-5 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
        <form method="get" class="grid gap-3 md:grid-cols-[minmax(0,1fr)_220px_auto] md:items-end">
            <label class="block text-sm font-bold text-slate-700">ค้นหาบัญชี<input name="search" value="<?= h($search) ?>" class="mt-1.5 h-11 w-full rounded-xl border border-slate-300 px-3 text-slate-900" placeholder="ชื่อผู้ใช้ อีเมล หรือรหัสบุคลากร"></label>
            <label class="block text-sm font-bold text-slate-700">บทบาท<select name="role" class="mt-1.5 h-11 w-full rounded-xl border border-slate-300 bg-white px-3 text-slate-900"><option value="">ทุกบทบาท</option><?php foreach ($roleMeta as $key => $meta): ?><option value="<?= $key ?>" <?= $roleFilter === $key ? 'selected' : '' ?>><?= h($meta['label']) ?></option><?php endforeach; ?></select></label>
            <button class="h-11 rounded-xl bg-slate-900 px-5 font-bold text-white hover:bg-slate-700"><i class="fa-solid fa-magnifying-glass mr-1"></i>ค้นหา</button>
        </form>
    </section>

    <section id="permission-list" class="mt-5 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm"><div class="overflow-x-auto"><table class="min-w-[900px] w-full text-left text-sm">
        <thead class="bg-slate-50 text-slate-600"><tr><th class="px-5 py-4 font-bold">บัญชีผู้ใช้</th><th class="px-5 py-4 font-bold">สาขา</th><th class="px-5 py-4 font-bold">สถานะ</th><th class="px-5 py-4 font-bold">สิทธิ์ปัจจุบัน</th><th class="px-5 py-4 font-bold">เปลี่ยนสิทธิ์</th></tr></thead><tbody class="divide-y divide-slate-100">
        <?php foreach ($users as $user): $role = $user['role'] ?: ($user['student_id'] ? 'student' : ($user['employee_type'] ?: 'admin')); $isEmployee = $user['employee_id'] !== null && $user['student_id'] === null; $name = $user['student_id'] ? trim($user['student_firstname'] . ' ' . $user['student_lastname']) : trim($user['employee_firstname'] . ' ' . $user['employee_lastname']); ?>
            <tr><td class="px-5 py-4"><p class="font-bold text-slate-900"><?= h($name ?: $user['username']) ?></p><p class="mt-0.5 text-xs text-slate-500"><?= h($user['email']) ?> · <?= h($user['student_number'] ?: $user['employee_code'] ?: $user['username']) ?></p></td><td class="px-5 py-4 text-slate-700"><?= h($user['department_name'] ?: '-') ?></td><td class="px-5 py-4"><span class="rounded-full px-2.5 py-1 text-xs font-bold <?= $user['status'] === 'active' ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-600' ?>"><?= $user['status'] === 'active' ? 'ใช้งาน' : 'ปิดใช้งาน' ?></span></td><td class="px-5 py-4"><span class="rounded-full px-2.5 py-1 text-xs font-bold <?= $roleMeta[$role]['class'] ?? 'bg-slate-100 text-slate-700' ?>"><?= h($roleMeta[$role]['label'] ?? $role) ?></span></td><td class="px-5 py-4"><?php if ($isEmployee): ?><form method="post" class="flex items-center gap-2"><?= csrf_field() ?><input type="hidden" name="user_id" value="<?= (int) $user['user_id'] ?>"><select name="role" class="h-9 rounded-lg border border-slate-300 bg-white px-2 text-sm font-semibold text-slate-800"><option value="teacher" <?= $role === 'teacher' ? 'selected' : '' ?>>อาจารย์</option><option value="staff" <?= $role === 'staff' ? 'selected' : '' ?>>เจ้าหน้าที่สาขา</option></select><button class="h-9 rounded-lg bg-red-700 px-3 text-xs font-bold text-white hover:bg-red-800">บันทึก</button></form><?php else: ?><span class="text-xs text-slate-500">กำหนดอัตโนมัติตามประเภทบัญชี</span><?php endif; ?></td></tr>
        <?php endforeach; ?>
        <?php if (!$users): ?><tr><td colspan="5" class="px-5 py-12 text-center text-slate-500">ไม่พบบัญชีผู้ใช้ตามเงื่อนไข</td></tr><?php endif; ?>
        </tbody></table></div><?php render_pagination($totalUsers, $currentPage, $perPage, 'page', 'permission-list'); ?></section>

    <section class="mt-5 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"><h2 class="font-black text-slate-900">ประวัติการปรับสิทธิ์ล่าสุด</h2><div class="mt-3 space-y-3"><?php foreach ($history as $item): ?><div class="flex gap-3 rounded-xl bg-slate-50 px-4 py-3 text-sm"><i class="fa-solid fa-clock mt-0.5 text-slate-400"></i><div><p class="font-semibold text-slate-800"><?= h($item['detail']) ?></p><p class="mt-0.5 text-xs text-slate-500"><?= h($item['activity_time']) ?> โดย <?= h($item['username'] ?: 'ผู้ดูแลระบบ') ?></p></div></div><?php endforeach; ?><?php if (!$history): ?><p class="text-sm text-slate-500">ยังไม่มีประวัติการปรับสิทธิ์</p><?php endif; ?></div><p class="mt-4 border-t border-slate-100 pt-4 text-xs text-slate-500"><i class="fa-solid fa-circle-info mr-1"></i>นักศึกษาและผู้ดูแลระบบกำหนดสิทธิ์ตามประเภทบัญชี ส่วนอาจารย์และเจ้าหน้าที่สามารถสลับสิทธิ์ระหว่างกันได้</p></section>
</div>
<?php include __DIR__ . '/../layouts/admin_footer.php'; ?>
