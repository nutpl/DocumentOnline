<?php
session_start();
require_once '../middleware/staff.php';
require_once '../config/database.php';
require_once '../helpers/csrf_helper.php';

function h($value): string { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); }

$staffStmt = $pdo->prepare("SELECT employee_id,department_id,firstname,lastname FROM employee WHERE user_id=? AND employee_type IN ('staff','teacher') LIMIT 1");
$staffStmt->execute([$_SESSION['user_id']]);
$staff = $staffStmt->fetch(PDO::FETCH_ASSOC);
if (!$staff) { http_response_code(403); renderPlainErrorAlert('ไม่พบข้อมูลเจ้าหน้าที่'); }

$teacherId = (int) ($_GET['teacher'] ?? $_POST['teacher_id'] ?? 0);
if ($teacherId < 1) { header('Location: teachers.php'); exit; }

$teacherStmt = $pdo->prepare(
    'SELECT e.employee_id,e.employee_code,e.firstname,e.lastname,e.phone,e.department_id,u.email,u.status,u.first_login,
            COALESCE(p.prefix_short,p.prefix_name,"") AS prefix_name,
            GROUP_CONCAT(DISTINCT pos.position_name ORDER BY ep.is_primary DESC,pos.position_name SEPARATOR ", ") AS position_names
     FROM employee e
     INNER JOIN users u ON u.user_id=e.user_id
     LEFT JOIN prefixes p ON p.prefix_id=e.prefix_id
     LEFT JOIN employee_position ep ON ep.employee_id=e.employee_id
     LEFT JOIN positions pos ON pos.position_id=ep.position_id
     WHERE e.employee_id=? AND e.department_id=? AND e.employee_type="teacher"
     GROUP BY e.employee_id'
);
$teacherStmt->execute([$teacherId, $staff['department_id']]);
$teacher = $teacherStmt->fetch(PDO::FETCH_ASSOC);
if (!$teacher) { http_response_code(404); renderPlainErrorAlert('ไม่พบอาจารย์ในสาขาของคุณ'); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_or_abort();
    $selectedGroupIds = array_values(array_unique(array_filter(array_map('intval', (array) ($_POST['advisor_group_ids'] ?? [])), static fn(int $id): bool => $id > 0)));
    try {
        $pdo->beginTransaction();
        $groupsStmt = $pdo->prepare('SELECT group_id FROM group_class WHERE department_id=? FOR UPDATE');
        $groupsStmt->execute([$staff['department_id']]);
        $validGroupIds = array_map('intval', $groupsStmt->fetchAll(PDO::FETCH_COLUMN));
        foreach ($selectedGroupIds as $groupId) {
            if (!in_array($groupId, $validGroupIds, true)) {
                throw new RuntimeException('พบกลุ่มเรียนที่อยู่นอกสาขาของคุณ');
            }
        }

        $pdo->prepare('UPDATE group_class SET advisor_employee_id=NULL WHERE department_id=? AND advisor_employee_id=?')
            ->execute([$staff['department_id'], $teacherId]);
        if ($selectedGroupIds) {
            $marks = implode(',', array_fill(0, count($selectedGroupIds), '?'));
            $assignStmt = $pdo->prepare("UPDATE group_class SET advisor_employee_id=? WHERE department_id=? AND group_id IN ($marks)");
            $assignStmt->execute([$teacherId, $staff['department_id'], ...$selectedGroupIds]);
        }
        $pdo->commit();
        if (($_POST['from_modal'] ?? '') === '1') {
            $_SESSION['teacher_groups_success'] = 'บันทึกการกำหนดกลุ่มเรียน ' . count($selectedGroupIds) . ' กลุ่มเรียนเรียบร้อยแล้ว';
            header('Location: teachers.php');
        } else {
            header('Location: teacher_detail.php?teacher='.$teacherId.'&saved='.count($selectedGroupIds));
        }
        exit;
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        $error = $exception->getMessage();
    }
}

$groupsStmt = $pdo->prepare(
    'SELECT g.group_id,g.group_name,g.advisor_employee_id,
            COUNT(s.student_id) AS student_count,
            CONCAT(
                CASE
                    WHEN COALESCE(ap.prefix_short,ap.prefix_name,"") IN ("", "นาย", "นาง", "นางสาว") THEN "อาจารย์ "
                    ELSE CONCAT(COALESCE(ap.prefix_short,ap.prefix_name,""), " ")
                END,
                COALESCE(ae.firstname,""), " ", COALESCE(ae.lastname,"")
            ) AS current_advisor_name
     FROM group_class g
     LEFT JOIN student s ON s.group_id=g.group_id
     LEFT JOIN employee ae ON ae.employee_id=g.advisor_employee_id
     LEFT JOIN prefixes ap ON ap.prefix_id=ae.prefix_id
     WHERE g.department_id=?
     GROUP BY g.group_id
     ORDER BY g.group_name'
);
$groupsStmt->execute([$staff['department_id']]);
$groups = $groupsStmt->fetchAll(PDO::FETCH_ASSOC);
$assignedCount = count(array_filter($groups, static fn(array $group): bool => (int) $group['advisor_employee_id'] === $teacherId));

$documentCountStmt = $pdo->prepare('SELECT COUNT(*) FROM documents d JOIN student s ON s.student_id=d.student_id JOIN group_class g ON g.group_id=s.group_id JOIN document_template t ON t.template_id=d.template_id JOIN document_type dt ON dt.document_type_id=t.document_type_id WHERE g.department_id=? AND d.document_status="submitted" AND dt.document_code IN ("INTERNSHIP_REQUEST","INTERNSHIP_SEND")');
$documentCountStmt->execute([$staff['department_id']]);
$document_count = (int) $documentCountStmt->fetchColumn();
$prefix = trim((string) $teacher['prefix_name']);
if (in_array($prefix, ['นาย', 'นาง', 'นางสาว'], true) || $prefix === '') { $prefix = 'อาจารย์'; }
$teacherDisplayName = trim($prefix.' '.$teacher['firstname'].' '.$teacher['lastname']);
$staff_name = trim($staff['firstname'].' '.$staff['lastname']);
$pageHeading = 'รายละเอียดอาจารย์';
?>
<!doctype html>
<html lang="th">
<head>
    <link rel="icon" type="image/png" href="/Project/assets/images/fet-logo-transparent.png?v=20260815-3">
    <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
    <title>รายละเอียดอาจารย์</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
</head>
<body class="min-h-screen bg-slate-100 text-slate-900">
<?php include 'sidebar.php'; ?>
<div class="min-h-screen lg:ml-72">
<?php include 'navbar.php'; ?>
<main class="mx-auto w-full max-w-7xl p-4 sm:p-6 lg:p-8">
    <a href="teachers.php" class="inline-flex items-center gap-2 text-sm font-bold text-slate-600 transition hover:text-red-700"><i class="fa-solid fa-arrow-left"></i>กลับหน้าจัดการอาจารย์</a>

    <?php if (!empty($error)): ?><div class="mt-5 rounded-xl border border-red-200 bg-red-50 p-4 text-red-800"><i class="fa-solid fa-circle-exclamation mr-2"></i><?= h($error) ?></div><?php endif; ?>
    <?php if (isset($_GET['saved'])): ?><div hidden data-success-alert data-success-title="บันทึกสำเร็จ" data-success-text="กำหนดอาจารย์ที่ปรึกษา <?= (int) $_GET['saved'] ?> กลุ่มเรียนเรียบร้อยแล้ว"></div><?php endif; ?>

    <section class="mt-5 overflow-hidden rounded-2xl bg-slate-950 text-white shadow-xl">
        <div class="flex flex-col gap-5 px-6 py-7 sm:flex-row sm:items-center sm:px-8">
            <span class="flex h-16 w-16 shrink-0 items-center justify-center rounded-2xl bg-red-600 text-2xl text-white shadow-lg shadow-red-950/30"><i class="fa-solid fa-chalkboard-user"></i></span>
            <div class="min-w-0 flex-1"><p class="text-sm font-semibold text-red-300">รายละเอียดอาจารย์ในสาขา</p><h1 class="mt-1 break-words text-2xl font-black sm:text-3xl"><?= h($teacherDisplayName) ?></h1><p class="mt-2 text-sm text-slate-300"><?= h($teacher['position_names'] ?: 'ยังไม่ได้กำหนดตำแหน่ง') ?></p></div>
            <span class="inline-flex self-start rounded-full px-3 py-1.5 text-sm font-bold <?= $teacher['status']==='active' ? 'bg-emerald-400/15 text-emerald-200' : 'bg-slate-700 text-slate-200' ?>"><i class="fa-solid fa-circle mr-2 text-[9px]"></i><?= $teacher['status']==='active' ? 'เปิดใช้งานบัญชี' : 'ปิดใช้งานบัญชี' ?></span>
        </div>
        <div class="grid border-t border-white/10 sm:grid-cols-3"><div class="px-6 py-4"><p class="text-xs font-bold uppercase tracking-wide text-slate-400">อีเมล</p><p class="mt-1 break-all text-sm font-semibold"><?= h($teacher['email']) ?></p></div><div class="border-t border-white/10 px-6 py-4 sm:border-l sm:border-t-0"><p class="text-xs font-bold uppercase tracking-wide text-slate-400">เบอร์โทรศัพท์</p><p class="mt-1 text-sm font-semibold"><?= h($teacher['phone'] ?: '-') ?></p></div><div class="border-t border-white/10 px-6 py-4 sm:border-l sm:border-t-0"><p class="text-xs font-bold uppercase tracking-wide text-slate-400">กลุ่มเรียนที่เป็นที่ปรึกษา</p><p class="mt-1 text-sm font-semibold"><?= $assignedCount ?> กลุ่มเรียน</p></div></div>
    </section>

    <section class="mt-6 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="flex flex-col gap-3 border-b border-slate-200 px-6 py-5 sm:flex-row sm:items-center sm:justify-between"><div><h2 class="text-lg font-black">กำหนดกลุ่มเรียนที่อาจารย์เป็นที่ปรึกษา</h2><p class="mt-1 text-sm text-slate-500">เลือกได้มากกว่า 1 กลุ่มเรียน และเมื่อเลือกกลุ่มที่มีอาจารย์ที่ปรึกษาเดิม ระบบจะเปลี่ยนเป็นอาจารย์ท่านนี้</p></div><span class="inline-flex self-start rounded-full bg-red-50 px-3 py-1.5 text-xs font-bold text-red-700"><i class="fa-solid fa-lock mr-1.5"></i>เฉพาะสาขาของคุณ</span></div>
        <form method="post" class="p-5 sm:p-6" data-confirm-form data-confirm-title="ยืนยันการกำหนดอาจารย์ที่ปรึกษา?" data-confirm-text="ระบบจะบันทึกกลุ่มเรียนที่เลือกให้กับอาจารย์ท่านนี้">
            <?= csrf_field() ?><input type="hidden" name="teacher_id" value="<?= $teacherId ?>">
            <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
            <?php foreach ($groups as $group): $checked = (int) $group['advisor_employee_id'] === $teacherId; ?>
                <label class="flex cursor-pointer items-start gap-3 rounded-xl border p-4 transition <?= $checked ? 'border-red-300 bg-red-50/60' : 'border-slate-200 bg-white hover:border-red-200 hover:bg-slate-50' ?>">
                    <input type="checkbox" name="advisor_group_ids[]" value="<?= (int) $group['group_id'] ?>" <?= $checked ? 'checked' : '' ?> class="mt-1 h-4 w-4 rounded border-slate-300 text-red-700 focus:ring-red-500">
                    <span class="min-w-0"><strong class="block text-slate-900"><?= h($group['group_name']) ?></strong><span class="mt-1 block text-xs text-slate-500"><i class="fa-solid fa-user-graduate mr-1"></i><?= (int) $group['student_count'] ?> นักศึกษา</span><span class="mt-2 block text-xs <?= $checked ? 'font-bold text-red-700' : 'text-slate-500' ?>"><?= $checked ? 'อาจารย์ที่ปรึกษาปัจจุบัน: '.$teacherDisplayName : 'อาจารย์ที่ปรึกษาปัจจุบัน: '.h($group['current_advisor_name'] ?: 'ยังไม่ได้กำหนด') ?></span></span>
                </label>
            <?php endforeach; ?>
            <?php if (!$groups): ?><p class="col-span-full py-8 text-center text-sm text-slate-500">ยังไม่มีกลุ่มเรียนในสาขาของคุณ</p><?php endif; ?>
            </div>
            <div class="mt-6 flex justify-end"><button type="submit" class="inline-flex items-center gap-2 rounded-xl bg-red-700 px-5 py-3 font-bold text-white shadow-sm transition hover:bg-red-800"><i class="fa-solid fa-floppy-disk"></i>บันทึกการกำหนดกลุ่มเรียน</button></div>
        </form>
    </section>
</main>
</div>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="/Project/assets/js/form-confirm.js" defer></script>
</body>
</html>
