<?php
session_start();
require_once __DIR__ . '/../../middleware/admin.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/csrf_helper.php';

$pageTitle = 'เพิ่มกลุ่มเรียน';
$isEmbedded = (($_GET['embed'] ?? $_POST['embed'] ?? '') === '1');
$error = '';
$facultyId = (int) ($_POST['faculty_id'] ?? $_GET['faculty_id'] ?? 0);
$departmentId = (int) ($_POST['department_id'] ?? $_GET['department_id'] ?? 0);
$advisorId = (int) ($_POST['advisor_employee_id'] ?? 0);
$groupNumber = trim((string) ($_POST['group_number'] ?? ''));
$faculties = $pdo->query('SELECT faculty_id, faculty_name, faculty_abbreviation FROM faculty ORDER BY faculty_name')->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_or_abort();
    try {
        if ($facultyId < 1 || $departmentId < 1) throw new RuntimeException('กรุณาเลือกคณะและสาขาวิชา');
        if (!preg_match('/^\d{5}$/', $groupNumber)) throw new RuntimeException('รหัสกลุ่มเรียนต้องเป็นตัวเลข 5 หลัก');

        $departmentStmt = $pdo->prepare('SELECT department_id, department_abbreviation FROM department WHERE department_id=? AND faculty_id=? LIMIT 1');
        $departmentStmt->execute([$departmentId, $facultyId]);
        $department = $departmentStmt->fetch(PDO::FETCH_ASSOC);
        if (!$department || trim((string) $department['department_abbreviation']) === '') throw new RuntimeException('ไม่พบตัวย่อของสาขาวิชาที่เลือก');

        if ($advisorId > 0) {
            $advisorStmt = $pdo->prepare('SELECT employee_id FROM employee WHERE employee_id=? AND employee_type="teacher" AND department_id=? LIMIT 1');
            $advisorStmt->execute([$advisorId, $departmentId]);
            if (!$advisorStmt->fetchColumn()) throw new RuntimeException('อาจารย์ที่ปรึกษาต้องสังกัดสาขาวิชาที่เลือก');
        }
        $groupName = rtrim(strtoupper(trim((string) $department['department_abbreviation'])), '.') . '.' . $groupNumber;
        $duplicateStmt = $pdo->prepare('SELECT COUNT(*) FROM group_class WHERE group_name=?');
        $duplicateStmt->execute([$groupName]);
        if ((int) $duplicateStmt->fetchColumn() > 0) throw new RuntimeException('กลุ่มเรียน ' . $groupName . ' มีอยู่แล้ว');

        $insertStmt = $pdo->prepare('INSERT INTO group_class (group_name, department_id, advisor_employee_id) VALUES (?, ?, ?)');
        $insertStmt->execute([$groupName, $departmentId, $advisorId ?: null]);
        $_SESSION['admin_success'] = 'เพิ่มกลุ่มเรียนเรียบร้อยแล้ว';
        header('Location: index.php?department_id=' . $departmentId);
        exit;
    } catch (Throwable $exception) {
        $error = $exception->getMessage();
    }
}

$departments = [];
if ($facultyId > 0) {
    $departmentStmt = $pdo->prepare('SELECT department_id, department_name FROM department WHERE faculty_id=? ORDER BY department_name');
    $departmentStmt->execute([$facultyId]);
    $departments = $departmentStmt->fetchAll(PDO::FETCH_ASSOC);
}
$teachers = [];
if ($departmentId > 0) {
    $teacherStmt = $pdo->prepare('SELECT e.employee_id, e.firstname, e.lastname, COALESCE(NULLIF(p.prefix_short, ""), p.prefix_name, "อาจารย์") AS prefix_name FROM employee e LEFT JOIN prefixes p ON p.prefix_id=e.prefix_id WHERE e.employee_type="teacher" AND e.department_id=? ORDER BY e.firstname, e.lastname');
    $teacherStmt->execute([$departmentId]);
    $teachers = $teacherStmt->fetchAll(PDO::FETCH_ASSOC);
}

if ($isEmbedded) {
    ?><!doctype html><html lang="th"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><script src="https://cdn.tailwindcss.com"></script><link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css"></head><body class="min-h-screen bg-slate-50 text-slate-900"><?php
} else {
    include __DIR__ . '/../layouts/admin_header.php';
    include __DIR__ . '/../layouts/admin_sidebar.php';
    include __DIR__ . '/../layouts/admin_navbar.php';
}
?>
<?php if ($isEmbedded): ?>
<style>
    main.w-full > div > a { display: none; }
    main.w-full > div > section { margin-top: 0 !important; box-shadow: 0 10px 28px rgb(15 23 42 / .08); }
    main.w-full > div > section > header { border-left: 6px solid #dc2626; background: linear-gradient(115deg, #fff1f2, #fff); }
    main.w-full > div > section > header > p:first-child { color: #b91c1c; }
</style>
<?php endif; ?>
<main class="w-full p-4 sm:p-6"><div class="mx-auto max-w-4xl">
    <a href="index.php" class="inline-flex items-center gap-2 text-sm font-bold text-slate-600 hover:text-red-700"><i class="fa-solid fa-arrow-left"></i> กลับหน้ารายการกลุ่มเรียน</a>
    <section class="mt-4 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <header class="border-b border-slate-100 px-5 py-5 sm:px-6"><p class="text-sm font-bold text-red-700">ข้อมูลโครงสร้างการศึกษา</p><h1 class="mt-1 text-2xl font-black text-slate-900">เพิ่มกลุ่มเรียน</h1><p class="mt-1 text-sm text-slate-600">เลือกคณะ สาขาวิชา และกำหนดอาจารย์ที่ปรึกษาให้กลุ่มเรียนได้จากหน้านี้</p></header>
        <?php if ($error): ?><div hidden data-success-alert data-success-icon="error" data-success-title="เพิ่มกลุ่มเรียนไม่ได้" data-success-text="<?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>"></div><?php endif; ?>
        <form method="post" class="p-5 sm:p-6"><?= csrf_field() ?><input type="hidden" name="embed" value="<?= $isEmbedded ? '1' : '0' ?>">
            <div class="grid gap-4 sm:grid-cols-2">
                <label class="text-sm font-bold text-slate-700">คณะ<select id="faculty_id" name="faculty_id" required class="mt-1.5 w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 font-normal"><option value="">เลือกคณะ</option><?php foreach ($faculties as $faculty): ?><option value="<?= (int) $faculty['faculty_id'] ?>" <?= $facultyId === (int) $faculty['faculty_id'] ? 'selected' : '' ?>><?= htmlspecialchars((($faculty['faculty_abbreviation'] ?? '') ? $faculty['faculty_abbreviation'].' — ' : '').$faculty['faculty_name']) ?></option><?php endforeach; ?></select></label>
                <label class="text-sm font-bold text-slate-700">สาขาวิชา<select id="department_id" name="department_id" required class="mt-1.5 w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 font-normal"><option value="">เลือกสาขาวิชา</option><?php foreach ($departments as $department): ?><option value="<?= (int) $department['department_id'] ?>" <?= $departmentId === (int) $department['department_id'] ? 'selected' : '' ?>><?= htmlspecialchars($department['department_name']) ?></option><?php endforeach; ?></select></label>
                <label class="text-sm font-bold text-slate-700">รหัสกลุ่มเรียน 5 หลัก<input name="group_number" required maxlength="5" pattern="\d{5}" inputmode="numeric" value="<?= htmlspecialchars($groupNumber, ENT_QUOTES, 'UTF-8') ?>" placeholder="เช่น 67231" class="mt-1.5 w-full rounded-xl border border-slate-300 px-3 py-2.5 font-normal"></label>
                <label class="text-sm font-bold text-slate-700">อาจารย์ที่ปรึกษา <span class="font-normal text-slate-400">(ถ้ามี)</span><select id="advisor_employee_id" name="advisor_employee_id" class="mt-1.5 w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 font-normal"><option value="">ยังไม่กำหนด</option><?php foreach ($teachers as $teacher): ?><option value="<?= (int) $teacher['employee_id'] ?>" <?= $advisorId === (int) $teacher['employee_id'] ? 'selected' : '' ?>><?= htmlspecialchars(trim($teacher['prefix_name'].' '.$teacher['firstname'].' '.$teacher['lastname'])) ?></option><?php endforeach; ?></select></label>
            </div>
            <p class="mt-4 rounded-xl bg-amber-50 px-4 py-3 text-sm text-amber-900"><i class="fa-solid fa-circle-info mr-1.5"></i>ระบบจะสร้างชื่อกลุ่มจากตัวย่อสาขาและรหัส 5 หลักโดยอัตโนมัติ</p>
            <div class="mt-6 flex justify-end gap-3 border-t border-slate-100 pt-5"><a href="index.php" class="rounded-xl border border-slate-300 px-5 py-2.5 font-bold text-slate-600 hover:bg-slate-50">ยกเลิก</a><button class="rounded-xl bg-red-700 px-5 py-2.5 font-bold text-white hover:bg-red-800"><i class="fa-solid fa-floppy-disk mr-2"></i>บันทึกกลุ่มเรียน</button></div>
        </form>
    </section>
</div></main>
<script>
(() => {
    const faculty = document.getElementById('faculty_id');
    const department = document.getElementById('department_id');
    const advisor = document.getElementById('advisor_employee_id');
    if (!faculty || !department || !advisor) return;
    const fill = (select, items, placeholder, label) => {
        select.innerHTML = `<option value="">${placeholder}</option>` + items.map(item => `<option value="${item[label.id]}">${item[label.text]}</option>`).join('');
    };
    faculty.addEventListener('change', async () => {
        fill(department, [], 'เลือกสาขาวิชา', {id:'department_id', text:'department_name'});
        fill(advisor, [], 'ยังไม่กำหนด', {id:'employee_id', text:'firstname'});
        if (!faculty.value) return;
        const response = await fetch(`get_departments.php?faculty_id=${encodeURIComponent(faculty.value)}`);
        if (response.ok) fill(department, await response.json(), 'เลือกสาขาวิชา', {id:'department_id', text:'department_name'});
    });
    department.addEventListener('change', async () => {
        fill(advisor, [], 'ยังไม่กำหนด', {id:'employee_id', text:'firstname'});
        if (!department.value) return;
        const response = await fetch(`get_teachers.php?department_id=${encodeURIComponent(department.value)}`);
        if (!response.ok) return;
        const teachers = await response.json();
        advisor.innerHTML = '<option value="">ยังไม่กำหนด</option>' + teachers.map(teacher => `<option value="${teacher.employee_id}">${[teacher.prefix_name, teacher.firstname, teacher.lastname].filter(Boolean).join(' ')}</option>`).join('');
    });
})();
</script>
<?php if ($isEmbedded): ?></body></html><?php else: include __DIR__ . '/../layouts/admin_footer.php'; endif; ?>
