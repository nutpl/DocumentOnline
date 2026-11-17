<?php
session_start();
require_once '../middleware/teacher.php';
require_once '../config/database.php';

$stmt = $pdo->prepare('SELECT e.employee_id,e.employee_code,e.firstname,e.lastname,e.phone,u.email,
        COALESCE(p.prefix_short,p.prefix_name,"") AS prefix_name,
        d.department_name,f.faculty_name,
        (SELECT GROUP_CONCAT(DISTINCT pos.position_name ORDER BY ep.is_primary DESC, pos.position_name SEPARATOR ", ")
         FROM employee_position ep
         JOIN positions pos ON pos.position_id=ep.position_id
         WHERE ep.employee_id=e.employee_id) AS position_names
    FROM employee e
    JOIN users u ON u.user_id=e.user_id
    LEFT JOIN prefixes p ON p.prefix_id=e.prefix_id
    LEFT JOIN department d ON d.department_id=e.department_id
    LEFT JOIN faculty f ON f.faculty_id=d.faculty_id
    WHERE e.user_id=? AND e.employee_type="teacher" LIMIT 1');
$stmt->execute([$_SESSION['user_id']]);
$teacher = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$teacher) {
    http_response_code(403);
    renderPlainErrorAlert('ไม่พบข้อมูลอาจารย์');
}

$advisorGroupsStmt = $pdo->prepare(
    'SELECT g.group_name, COUNT(s.student_id) AS student_count
     FROM group_class g
     LEFT JOIN student s ON s.group_id=g.group_id
     WHERE g.advisor_employee_id=?
     GROUP BY g.group_id, g.group_name
     ORDER BY g.group_name'
);
$advisorGroupsStmt->execute([(int) $teacher['employee_id']]);
$advisorGroups = $advisorGroupsStmt->fetchAll(PDO::FETCH_ASSOC);

$teacher_name = trim($teacher['firstname'].' '.$teacher['lastname']);
$pageHeading = 'ข้อมูลส่วนตัว';
$profileItems = [
    ['fa-id-card', 'รหัสบุคลากร', $teacher['employee_code'] ?: '-'],
    ['fa-envelope', 'อีเมล', $teacher['email'] ?: '-'],
    ['fa-phone', 'โทรศัพท์ติดต่อ', $teacher['phone'] ?: '-'],
    ['fa-briefcase', 'ตำแหน่ง', $teacher['position_names'] ?: 'อาจารย์'],
    ['fa-code-branch', 'สาขาวิชา', $teacher['department_name'] ?: '-'],
    ['fa-building-columns', 'คณะ', $teacher['faculty_name'] ?: '-'],
];
?>
<!doctype html>
<html lang="th">
<head>
    <link rel="icon" type="image/png" href="/Project/assets/images/fet-logo-transparent.png?v=20260815-3">

    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>ข้อมูลส่วนตัวอาจารย์</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
</head>
<body class="min-h-screen bg-slate-100 text-slate-900">
<?php include 'sidebar.php'; ?>
<div class="min-h-screen lg:ml-72">
    <?php include 'navbar.php'; ?>
    <main class="p-4 sm:p-6 lg:p-8">
        <div class="mx-auto max-w-6xl">

            <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="relative overflow-hidden bg-slate-950 px-6 py-8 text-white sm:px-9">
                    <div class="absolute -right-12 -top-16 h-52 w-52 rounded-full bg-red-600/20 blur-3xl"></div>
                    <div class="absolute -bottom-20 right-32 h-44 w-44 rounded-full bg-white/5 blur-2xl"></div>
                    <div class="relative flex flex-col items-center gap-5 text-center sm:flex-row sm:text-left">
                        <div class="flex h-24 w-24 shrink-0 items-center justify-center rounded-2xl border border-white/20 bg-white/10 shadow-xl backdrop-blur">
                            <i class="fa-solid fa-chalkboard-user text-4xl text-red-300"></i>
                        </div>
                        <div class="min-w-0">
                            <span class="inline-flex rounded-full bg-red-100 px-3 py-1 text-xs font-bold tracking-wide text-red-800 ring-1 ring-red-200">อาจารย์</span>
                            <h2 class="mt-3 break-words text-3xl font-black leading-tight tracking-[0.01em] text-white drop-shadow-sm sm:text-4xl"><?= htmlspecialchars($teacher_name) ?></h2>
                            <p class="mt-3 inline-flex items-center rounded-lg bg-white/10 px-3 py-1.5 text-sm font-semibold text-slate-100 ring-1 ring-white/10"><i class="fa-solid fa-id-badge mr-2 text-red-300"></i><?= htmlspecialchars($teacher['employee_code'] ?: '-') ?></p>
                        </div>
                    </div>
                </div>

                <div class="p-5 sm:p-8">
                    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <h3 class="text-xl font-bold">ข้อมูลบุคลากร</h3>
                            <p class="mt-1 text-sm text-slate-500">ข้อมูลที่บันทึกไว้ในระบบ</p>
                        </div>
                        <a href="../auth/change_password.php" class="inline-flex items-center gap-2 rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:border-red-300 hover:bg-red-50 hover:text-red-700">
                            <i class="fa-solid fa-key"></i>เปลี่ยนรหัสผ่าน
                        </a>
                    </div>
                    <div class="grid gap-4 md:grid-cols-2">
                        <?php foreach ($profileItems as [$icon, $label, $value]): ?>
                        <article class="rounded-xl border border-slate-200 bg-slate-50/70 p-5 transition hover:border-red-200 hover:bg-red-50/40">
                            <div class="flex items-start gap-4">
                                <span class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-white text-red-600 shadow-sm"><i class="fa-solid <?= htmlspecialchars($icon) ?>"></i></span>
                                <div class="min-w-0"><p class="text-sm text-slate-500"><?= htmlspecialchars($label) ?></p><p class="mt-1 break-words font-semibold leading-6"><?= htmlspecialchars($value) ?></p></div>
                            </div>
                        </article>
                        <?php endforeach; ?>
                    </div>

                    <section class="mt-6 rounded-xl border border-slate-200 bg-slate-50/70 p-5">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <div><h4 class="font-bold text-slate-900"><i class="fa-solid fa-user-graduate mr-2 text-red-600"></i>กลุ่มเรียนที่เป็นอาจารย์ที่ปรึกษา</h4><p class="mt-1 text-sm text-slate-500">กลุ่มเรียนที่ได้รับมอบหมายในสาขา</p></div>
                            <span class="rounded-full bg-white px-3 py-1 text-sm font-semibold text-slate-600 shadow-sm"><?= count($advisorGroups) ?> กลุ่ม</span>
                        </div>
                        <?php if ($advisorGroups): ?>
                        <div class="mt-4 flex flex-wrap gap-2">
                            <?php foreach ($advisorGroups as $group): ?>
                            <span class="inline-flex items-center gap-2 rounded-lg border border-red-100 bg-white px-3 py-2 text-sm font-semibold text-slate-700"><i class="fa-solid fa-users text-red-500"></i><?= htmlspecialchars($group['group_name']) ?><small class="font-normal text-slate-400"><?= (int) $group['student_count'] ?> คน</small></span>
                            <?php endforeach; ?>
                        </div>
                        <?php else: ?>
                        <p class="mt-4 rounded-lg border border-dashed border-slate-300 bg-white px-4 py-3 text-sm text-slate-500">ยังไม่ได้รับมอบหมายให้เป็นอาจารย์ที่ปรึกษากลุ่มเรียน</p>
                        <?php endif; ?>
                    </section>
                </div>
            </section>
        </div>
    </main>
</div>
</body>
</html>
