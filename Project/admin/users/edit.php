<?php

session_start();

require_once "../../middleware/admin.php";
require_once "../../config/database.php";
require_once "../../helpers/csrf_helper.php";


// =============================
// ตรวจสอบ ID
// =============================

$user_id = intval($_GET['id'] ?? 0);

if ($user_id <= 0) {

    header("Location: index.php");
    exit;

}


// =============================
// ดึงข้อมูล User
// =============================

$stmt = $pdo->prepare("

    SELECT
        u.user_id,
        u.username,
        u.email,
        u.status,

        ur.role,

        s.student_id,
        s.firstname AS student_firstname,
        s.lastname AS student_lastname,

        e.employee_id,
        e.employee_type,
        e.firstname AS employee_firstname,
        e.lastname AS employee_lastname

    FROM users u

    LEFT JOIN user_roles ur
        ON u.user_id = ur.user_id

    LEFT JOIN student s
        ON u.user_id = s.user_id

    LEFT JOIN employee e
        ON u.user_id = e.user_id

    WHERE u.user_id = ?

    LIMIT 1

");

$stmt->execute([

    $user_id

]);

$user = $stmt->fetch(PDO::FETCH_ASSOC);


if (!$user) {

    $_SESSION['admin_error'] =
        "ไม่พบข้อมูลผู้ใช้งาน";

    header("Location: index.php");

    exit;

}

$backDestination = match ($_GET['from'] ?? 'view') {
    'teachers' => '../teachers/index.php',
    'staffs' => '../staffs/index.php',
    'students' => '../students/index.php',
    'users' => 'index.php',
    default => 'view.php?id=' . (int) $user['user_id'],
};
$returnSource = in_array($_GET['from'] ?? '', ['teachers', 'staffs', 'students', 'users'], true)
    ? (string) $_GET['from']
    : 'users';


// =============================
// กำหนด Role จากข้อมูลจริง
// =============================

if (!empty($user['student_id'])) {

    $current_role = 'student';

    $user_type = 'นักศึกษา';

} elseif (

    !empty($user['employee_id'])

    &&

    $user['employee_type'] === 'teacher'

) {

    $current_role = 'teacher';

    $user_type = 'อาจารย์';

} elseif (

    !empty($user['employee_id'])

    &&

    $user['employee_type'] === 'staff'

) {

    $current_role = 'staff';

    $user_type = 'เจ้าหน้าที่';

} else {

    $current_role = 'admin';

    $user_type = 'ผู้ดูแลระบบ';

}


// =============================
// Page
// =============================

$pageTitle = "แก้ไขผู้ใช้งาน";
$isEmbedded = ($_GET['embed'] ?? '') === '1';

$displayName = trim((string) ($user['student_id']
    ? ($user['student_firstname'] . ' ' . $user['student_lastname'])
    : ($user['employee_firstname'] . ' ' . $user['employee_lastname'])));
$displayName = $displayName !== '' ? $displayName : $user['username'];
$roleIcon = match ($current_role) {
    'teacher' => 'fa-chalkboard-user',
    'staff' => 'fa-user-gear',
    'student' => 'fa-user-graduate',
    default => 'fa-user-shield',
};

if ($isEmbedded) {
    ?><!doctype html><html lang="th"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><script src="https://cdn.tailwindcss.com"></script><link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css"></head><body class="min-h-screen bg-slate-50 text-slate-900"><?php
} else {
    include __DIR__ . "/../layouts/admin_header.php";
    include __DIR__ . "/../layouts/admin_sidebar.php";
    include __DIR__ . "/../layouts/admin_navbar.php";
}

?>

<style>
    main > section > header { border-left: 6px solid #dc2626; background: linear-gradient(115deg, #fff1f2, #ffffff) !important; color: #0f172a !important; }
    main > section > header h1 { color: #0f172a !important; }
    main > section > header p:first-child { color: #b91c1c !important; }
    main > section > header p:last-child { color: #64748b !important; }
    main > section > header > div > div > span:first-child { background: #fee2e2 !important; color: #b91c1c !important; }
    main > section > header span:has(.fa-circle-check) { background: #dcfce7 !important; color: #15803d !important; }
    main > section > header span:has(.fa-circle-pause) { background: #e2e8f0 !important; color: #475569 !important; }
</style>
<main class="mx-auto max-w-5xl p-4 sm:p-6 lg:p-8">
    <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <header class="border-b border-slate-100 bg-gradient-to-r from-slate-900 to-slate-700 px-5 py-6 text-white sm:px-7">
            <div class="flex flex-col gap-5 sm:flex-row sm:items-center sm:justify-between"><div class="flex min-w-0 items-center gap-4"><span class="inline-flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl bg-white/15 text-2xl"><i class="fa-solid <?= $roleIcon ?>"></i></span><div class="min-w-0"><p class="text-sm font-semibold text-red-200">จัดการบัญชีผู้ใช้งาน</p><h1 class="mt-1 truncate text-2xl font-black"><?= htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8') ?></h1><p class="mt-1 text-sm text-slate-300">แก้ไขอีเมลและสถานะการเข้าใช้งานของบัญชีนี้</p></div></div><div class="flex flex-wrap gap-2"><span class="rounded-full bg-white/15 px-3 py-1.5 text-xs font-bold"><i class="fa-solid fa-user-tag mr-1.5"></i><?= htmlspecialchars($user_type, ENT_QUOTES, 'UTF-8') ?></span><span class="rounded-full px-3 py-1.5 text-xs font-bold <?= $user['status'] === 'active' ? 'bg-emerald-400/20 text-emerald-100' : 'bg-slate-500/40 text-slate-100' ?>"><i class="fa-solid <?= $user['status'] === 'active' ? 'fa-circle-check' : 'fa-circle-pause' ?> mr-1.5"></i><?= $user['status'] === 'active' ? 'เปิดใช้งาน' : 'ปิดใช้งาน' ?></span></div></div>
        </header>

        <form action="update.php" method="POST" class="p-5 sm:p-7">
            <?= csrf_field() ?>
            <input type="hidden" name="user_id" value="<?= (int)$user['user_id'] ?>">
            <input type="hidden" name="from" value="<?= htmlspecialchars($returnSource, ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="embed" value="<?= $isEmbedded ? '1' : '0' ?>">
            <input type="hidden" name="role" value="<?= htmlspecialchars($current_role, ENT_QUOTES, 'UTF-8') ?>">

            <div class="grid gap-5 lg:grid-cols-[minmax(0,1fr)_minmax(0,1.35fr)]">
                <section class="rounded-2xl border border-slate-200 bg-slate-50/70 p-5"><div class="mb-4 flex items-center gap-2"><span class="inline-flex h-9 w-9 items-center justify-center rounded-xl bg-slate-200 text-slate-700"><i class="fa-solid fa-id-card"></i></span><div><h2 class="font-black text-slate-900">ข้อมูลบัญชี</h2><p class="text-xs text-slate-500">ข้อมูลส่วนนี้กำหนดจากการสมัครและสิทธิ์</p></div></div><dl class="space-y-3"><div class="rounded-xl border border-slate-200 bg-white px-4 py-3"><dt class="text-xs font-bold text-slate-500">ชื่อผู้ใช้</dt><dd class="mt-1 break-all font-bold text-slate-900"><?= htmlspecialchars($user['username'], ENT_QUOTES, 'UTF-8') ?></dd></div><div class="rounded-xl border border-slate-200 bg-white px-4 py-3"><dt class="text-xs font-bold text-slate-500">ประเภทผู้ใช้งาน</dt><dd class="mt-1 font-bold text-slate-900"><?= htmlspecialchars($user_type, ENT_QUOTES, 'UTF-8') ?></dd></div><div class="rounded-xl border border-slate-200 bg-white px-4 py-3"><dt class="text-xs font-bold text-slate-500">สิทธิ์ในระบบ</dt><dd class="mt-1 font-bold uppercase tracking-wide text-slate-900"><?= htmlspecialchars($current_role, ENT_QUOTES, 'UTF-8') ?></dd></div></dl><p class="mt-4 flex gap-2 text-xs leading-5 text-slate-500"><i class="fa-solid fa-circle-info mt-0.5 text-red-600"></i><span>ชื่อผู้ใช้และสิทธิ์ถูกกำหนดตามประเภทผู้ใช้งาน จึงไม่สามารถแก้ไขจากหน้านี้ได้</span></p></section>

                <section class="rounded-2xl border border-slate-200 bg-white p-5"><div class="mb-5 flex items-center gap-2"><span class="inline-flex h-9 w-9 items-center justify-center rounded-xl bg-red-50 text-red-700"><i class="fa-solid fa-pen-to-square"></i></span><div><h2 class="font-black text-slate-900">ข้อมูลที่แก้ไขได้</h2><p class="text-xs text-slate-500">บันทึกเมื่อปรับอีเมลหรือสถานะบัญชีเรียบร้อย</p></div></div><div class="space-y-5"><label class="block text-sm font-bold text-slate-700">อีเมลมหาวิทยาลัย<input type="email" name="email" value="<?= htmlspecialchars($user['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required autocomplete="email" class="mt-2 w-full rounded-xl border border-slate-300 px-4 py-3 text-slate-900 outline-none transition placeholder:text-slate-400 focus:border-red-500 focus:ring-4 focus:ring-red-100"><span class="mt-2 block text-xs font-normal text-slate-500"><i class="fa-solid fa-envelope mr-1"></i>ใช้อีเมลที่ลงท้ายด้วย @rmuti.ac.th</span></label><label class="block text-sm font-bold text-slate-700">สถานะบัญชี<select name="status" required class="mt-2 w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-slate-900 outline-none transition focus:border-red-500 focus:ring-4 focus:ring-red-100"><option value="active" <?= $user['status'] === 'active' ? 'selected' : '' ?>>เปิดใช้งาน — เข้าสู่ระบบได้</option><option value="inactive" <?= $user['status'] === 'inactive' ? 'selected' : '' ?>>ปิดใช้งาน — ไม่อนุญาตให้เข้าสู่ระบบ</option></select></label></div></section>
            </div>

            <div class="mt-6 flex flex-col-reverse gap-3 border-t border-slate-100 pt-5 sm:flex-row sm:items-center sm:justify-between"><a href="<?= htmlspecialchars($backDestination, ENT_QUOTES, 'UTF-8') ?>" class="inline-flex items-center justify-center gap-2 rounded-xl border border-slate-300 px-5 py-3 text-sm font-bold text-slate-600 transition hover:bg-slate-50"><i class="fa-solid fa-xmark"></i>ยกเลิก</a><button type="submit" class="inline-flex items-center justify-center gap-2 rounded-xl bg-red-700 px-6 py-3 text-sm font-bold text-white shadow-sm transition hover:bg-red-800 focus:outline-none focus:ring-4 focus:ring-red-100"><i class="fa-solid fa-floppy-disk"></i>บันทึกการแก้ไข</button></div>
        </form>
    </section>
</main>

<?php if ($isEmbedded): ?></body></html><?php else: include __DIR__ . "/../layouts/admin_footer.php"; endif; ?>
