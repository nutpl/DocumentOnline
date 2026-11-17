<?php
require_once 'auth.php';

$stmt = $pdo->prepare('SELECT s.student_id,s.student_number,s.firstname,s.lastname,u.email,
        COALESCE(sp.prefix_short,sp.prefix_name,"") AS student_prefix,
        g.group_name,d.department_name,f.faculty_name,
        TRIM(CONCAT(
            CASE WHEN COALESCE(ap.prefix_short,ap.prefix_name,"") IN ("นาย","นาง","นางสาว")
                THEN "อาจารย์" ELSE COALESCE(ap.prefix_short,ap.prefix_name,"") END,
            CASE WHEN ae.employee_id IS NOT NULL THEN " " ELSE "" END,
            ae.firstname," ",ae.lastname
	        )) AS advisor_name,
	        ae.phone AS advisor_phone
    FROM student s
    LEFT JOIN prefixes sp ON sp.prefix_id=s.prefix_id
    LEFT JOIN group_class g ON g.group_id=s.group_id
    LEFT JOIN department d ON d.department_id=g.department_id
    LEFT JOIN faculty f ON f.faculty_id=d.faculty_id
    LEFT JOIN users u ON u.user_id=s.user_id
    LEFT JOIN employee ae ON ae.employee_id=g.advisor_employee_id
    LEFT JOIN prefixes ap ON ap.prefix_id=ae.prefix_id
    WHERE s.user_id=? LIMIT 1');
$stmt->execute([$_SESSION['user_id']]);
$student = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$student) {
    http_response_code(404);
    renderPlainErrorAlert('ไม่พบข้อมูลนักศึกษา');
}

$studentName = trim(($student['student_prefix'] ?? '').$student['firstname'].' '.$student['lastname']);
$countStmt = $pdo->prepare('SELECT COUNT(*) AS total,SUM(document_status IN ("submitted","in_review","approved")) AS submitted FROM documents WHERE student_id=?');
$countStmt->execute([$student['student_id']]);
$counts = $countStmt->fetch(PDO::FETCH_ASSOC) ?: [];
$student_document_count = (int) ($counts['total'] ?? 0);
$student_submitted_count = (int) ($counts['submitted'] ?? 0);
$pageHeading = 'ข้อมูลส่วนตัว';
$profileItems = [
    ['fa-id-card', 'รหัสนักศึกษา', $student['student_number'] ?: '-'],
    ['fa-envelope', 'อีเมล', $student['email'] ?: '-'],
    ['fa-users', 'กลุ่มเรียน', $student['group_name'] ?: '-'],
	    ['fa-user-tie', 'อาจารย์ที่ปรึกษา', $student['advisor_name'] ?: 'ยังไม่ได้กำหนดอาจารย์ที่ปรึกษา'],
	    ['fa-phone', 'เบอร์โทรศัพท์อาจารย์ที่ปรึกษา', $student['advisor_phone'] ?: '-'],
    ['fa-code-branch', 'สาขาวิชา', $student['department_name'] ?: '-'],
    ['fa-building-columns', 'คณะ', $student['faculty_name'] ?: '-'],
];
?>
<!doctype html>
<html lang="th">
<head>
    <link rel="icon" type="image/png" href="/Project/assets/images/fet-logo-transparent.png?v=20260815-3">

    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>ข้อมูลส่วนตัวนักศึกษา</title>
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
                <div class="profile-hero relative overflow-hidden bg-slate-950 px-6 py-8 text-white sm:px-9">
                    <div class="absolute -right-12 -top-16 h-52 w-52 rounded-full bg-red-600/20 blur-3xl"></div>
                    <div class="absolute -bottom-20 right-32 h-44 w-44 rounded-full bg-white/5 blur-2xl"></div>
                    <div class="relative flex flex-col items-center gap-5 text-center sm:flex-row sm:text-left">
                        <div class="flex h-24 w-24 shrink-0 items-center justify-center rounded-2xl border border-red-100 bg-white shadow-xl">
                            <i class="fa-solid fa-user-graduate text-4xl text-red-600"></i>
                        </div>
                        <div class="min-w-0">
                            <span class="profile-role inline-flex rounded-full px-3 py-1 text-xs font-semibold">นักศึกษา</span>
                            <h2 class="mt-3 break-words text-2xl font-bold sm:text-3xl"><?= htmlspecialchars($studentName) ?></h2>
                            <p class="profile-student-number mt-2 text-sm"><i class="fa-solid fa-graduation-cap mr-2"></i><?= htmlspecialchars($student['student_number'] ?: '-') ?></p>
                        </div>
                    </div>
                </div>

                <div class="p-5 sm:p-8">
                    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
                        <div><h3 class="text-xl font-bold">ข้อมูลการศึกษา</h3><p class="mt-1 text-sm text-slate-500">ข้อมูลที่ระบบใช้สำหรับจัดทำเอกสารคำร้อง</p></div>
                        <a href="../auth/change_password.php" class="inline-flex items-center gap-2 rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:border-red-300 hover:bg-red-50 hover:text-red-700"><i class="fa-solid fa-key"></i>เปลี่ยนรหัสผ่าน</a>
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
                </div>
            </section>
        </div>
    </main>
</div>
</body>
</html>
