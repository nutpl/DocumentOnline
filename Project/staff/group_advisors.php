<?php
session_start();
require_once '../middleware/staff.php';
require_once '../config/database.php';
require_once '../helpers/csrf_helper.php';
require_once '../helpers/pagination_helper.php';

$staffStmt = $pdo->prepare('SELECT employee_id,user_id,department_id,firstname,lastname FROM employee WHERE user_id=? AND employee_type IN ("staff","teacher") LIMIT 1');
$staffStmt->execute([$_SESSION['user_id']]);
$staff = $staffStmt->fetch(PDO::FETCH_ASSOC);
if (!$staff) { http_response_code(403); renderPlainErrorAlert('ไม่มีสิทธิ์'); }

$currentPage = max(1, (int) ($_GET['page'] ?? 1));
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_or_abort();
    $assignments = (array) ($_POST['advisor_employee_id'] ?? []);
    $returnPage = max(1, (int) ($_POST['return_page'] ?? 1));
    try {
        $pdo->beginTransaction();
        $groupStmt = $pdo->prepare('SELECT group_id FROM group_class WHERE department_id=? FOR UPDATE');
        $groupStmt->execute([$staff['department_id']]);
        $validGroups = array_flip(array_map('intval', $groupStmt->fetchAll(PDO::FETCH_COLUMN)));
        $teacherStmt = $pdo->prepare('SELECT employee_id FROM employee WHERE department_id=? AND employee_type="teacher"');
        $teacherStmt->execute([$staff['department_id']]);
        $validTeachers = array_flip(array_map('intval', $teacherStmt->fetchAll(PDO::FETCH_COLUMN)));
        $update = $pdo->prepare('UPDATE group_class SET advisor_employee_id=? WHERE group_id=? AND department_id=?');
        $saved = 0;
        foreach ($assignments as $groupId => $teacherId) {
            $groupId = (int) $groupId;
            $teacherId = (int) $teacherId;
            if (!isset($validGroups[$groupId])) throw new RuntimeException('พบกลุ่มเรียนที่อยู่นอกสาขาของคุณ');
            if ($teacherId > 0 && !isset($validTeachers[$teacherId])) throw new RuntimeException('พบอาจารย์ที่อยู่นอกสาขาของคุณ');
            $update->execute([$teacherId ?: null, $groupId, $staff['department_id']]);
            $saved++;
        }
        $pdo->commit();
        header('Location: group_advisors.php?saved='.$saved.'&page='.$returnPage.'#advisor-list');
        exit;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $error = $e->getMessage();
    }
}

$teachersStmt = $pdo->prepare('SELECT e.employee_id,e.firstname,e.lastname,COALESCE(p.prefix_short,p.prefix_name,"") prefix_name FROM employee e LEFT JOIN prefixes p ON p.prefix_id=e.prefix_id WHERE e.department_id=? AND e.employee_type="teacher" ORDER BY e.firstname,e.lastname');
$teachersStmt->execute([$staff['department_id']]);
$teachers = $teachersStmt->fetchAll(PDO::FETCH_ASSOC);
$groupsStmt = $pdo->prepare('SELECT g.group_id,g.group_name,g.advisor_employee_id,COUNT(s.student_id) student_count FROM group_class g LEFT JOIN student s ON s.group_id=g.group_id WHERE g.department_id=? GROUP BY g.group_id ORDER BY g.group_name');
$groupsStmt->execute([$staff['department_id']]);
$allGroups = $groupsStmt->fetchAll(PDO::FETCH_ASSOC);
$perPage = selected_per_page(10);
$groupTotal = count($allGroups);
$totalPages = max(1, (int) ceil($groupTotal / $perPage));
$currentPage = min($currentPage, $totalPages);
$groups = array_slice($allGroups, ($currentPage - 1) * $perPage, $perPage);

$staff_name = trim($staff['firstname'].' '.$staff['lastname']);
$pageHeading = 'กำหนดอาจารย์ที่ปรึกษา';
$document_count = 0;
function h($value): string { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); }
function teacherName(array $teacher): string {
    $prefix = trim((string) $teacher['prefix_name']);
    if (in_array($prefix, ['', 'นาย', 'นาง', 'นางสาว'], true)) $prefix = 'อาจารย์';
    return trim($prefix.' '.$teacher['firstname'].' '.$teacher['lastname']);
}
?>
<!doctype html>
<html lang="th">
<head>
    <link rel="icon" type="image/png" href="/Project/assets/images/fet-logo-transparent.png?v=20260815-3">
    <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
    <title>กำหนดอาจารย์ที่ปรึกษา</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <link rel="stylesheet" href="/Project/assets/css/app-ui.css?v=20260902-2">
</head>
<body class="min-h-screen bg-slate-100 text-slate-900">
<?php include 'sidebar.php'; ?>
<div class="min-h-screen lg:ml-72">
<?php include 'navbar.php'; ?>
<main class="mx-auto max-w-6xl p-4 sm:p-6 lg:p-8">
    <?php if (isset($_GET['saved'])): ?><div hidden data-success-alert data-success-title="บันทึกสำเร็จ" data-success-text="กำหนดอาจารย์ที่ปรึกษา <?= (int) $_GET['saved'] ?> กลุ่มเรียนเรียบร้อยแล้ว" data-success-clean-param="saved"></div><?php endif; ?>
    <?php if ($error): ?><div class="mb-5 rounded-xl border border-red-200 bg-red-50 p-4 text-red-800"><?= h($error) ?></div><?php endif; ?>

    <section class="management-hero">
        <div class="management-hero__icon"><i class="fa-solid fa-user-graduate"></i></div>
        <div class="min-w-0 flex-1"><p class="management-hero__eyebrow">เฉพาะสาขาของคุณ</p><h1>กำหนดอาจารย์ที่ปรึกษากลุ่มเรียน</h1><p>อาจารย์หนึ่งท่านสามารถดูแลได้มากกว่าหนึ่งกลุ่มเรียน</p></div>
        <span class="inline-flex shrink-0 items-center gap-2 rounded-full border border-red-100 bg-red-50 px-3 py-2 text-sm font-bold text-red-700"><i class="fa-solid fa-people-group"></i><?= $groupTotal ?> กลุ่มเรียน</span>
    </section>

    <form id="advisor-list" method="post" class="mt-5 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <?= csrf_field() ?><input type="hidden" name="return_page" value="<?= $currentPage ?>">
        <div class="border-b border-slate-100 bg-slate-50 px-6 py-5"><p class="text-xs font-bold tracking-wide text-red-600">จัดการจากหน้าเดียว</p><h2 class="mt-1 font-black">รายชื่อกลุ่มเรียน</h2><p class="mt-1 text-sm text-slate-500">เลือกอาจารย์ที่ปรึกษาให้กลุ่มเรียนในหน้านี้ แล้วกดบันทึก</p></div>
        <div class="overflow-x-auto">
            <table class="w-full min-w-[860px] text-sm">
                <thead class="bg-slate-50 text-slate-600"><tr><th class="px-6 py-4 text-left">กลุ่มเรียน</th><th class="px-6 py-4 text-center">จำนวนนักศึกษา</th><th class="px-6 py-4 text-left">อาจารย์ที่ปรึกษา</th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                <?php foreach ($groups as $group): ?>
                    <tr class="transition hover:bg-red-50/40"><td class="px-6 py-4 font-bold"><?= h($group['group_name']) ?></td><td class="px-6 py-4 text-center"><span class="inline-flex min-w-9 items-center justify-center rounded-full bg-slate-100 px-3 py-1 font-semibold text-slate-700"><?= (int) $group['student_count'] ?></span></td><td class="px-6 py-4"><select name="advisor_employee_id[<?= (int) $group['group_id'] ?>]" class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5"><option value="0">ยังไม่ได้กำหนด</option><?php foreach ($teachers as $teacher): ?><option value="<?= (int) $teacher['employee_id'] ?>" <?= (int) $group['advisor_employee_id'] === (int) $teacher['employee_id'] ? 'selected' : '' ?>><?= h(teacherName($teacher)) ?></option><?php endforeach; ?></select></td></tr>
                <?php endforeach; ?>
                <?php if (!$groups): ?><tr><td colspan="3" class="px-6 py-14 text-center text-slate-400">ยังไม่มีกลุ่มเรียนในสาขา</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
        <div class="flex flex-col gap-3 border-t border-slate-100 bg-slate-50 px-6 py-4 sm:flex-row sm:items-center sm:justify-between"><p class="text-sm text-slate-500">หน้า <?= $currentPage ?> จาก <?= $totalPages ?></p><button class="rounded-xl bg-red-700 px-5 py-3 font-bold text-white transition hover:bg-red-800"><i class="fa-solid fa-floppy-disk mr-2"></i>บันทึกการกำหนด</button></div>
    </form>

    <?php if ($groupTotal > 0): ?><div class="mt-4 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm"><?php render_pagination($groupTotal, $currentPage, $perPage, 'page', 'advisor-list'); ?></div><?php endif; ?>
</main>
</div>
</body>
</html>
