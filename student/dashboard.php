<?php
require_once 'auth.php';

$stmt=$pdo->prepare('SELECT s.student_id,s.student_number,s.firstname,s.lastname,pr.prefix_short,g.group_name,d.department_name,f.faculty_name
    FROM student s LEFT JOIN prefixes pr ON pr.prefix_id=s.prefix_id LEFT JOIN group_class g ON g.group_id=s.group_id
    LEFT JOIN department d ON d.department_id=g.department_id LEFT JOIN faculty f ON f.faculty_id=d.faculty_id
    WHERE s.user_id=? LIMIT 1');
$stmt->execute([$_SESSION['user_id']]);
$student=$stmt->fetch(PDO::FETCH_ASSOC);
if (!$student) { http_response_code(403); renderPlainErrorAlert('ไม่พบข้อมูลนักศึกษา'); }

$countStmt=$pdo->prepare('SELECT COUNT(*) AS total,
    SUM(document_status="draft") AS drafts,
    SUM(document_status IN ("submitted","in_review","approved")) AS submitted
    FROM documents WHERE student_id=?');
$countStmt->execute([$student['student_id']]);
$documentStats=$countStmt->fetch(PDO::FETCH_ASSOC) ?: [];
$student_document_count=(int)($documentStats['total'] ?? 0);
$student_draft_count=(int)($documentStats['drafts'] ?? 0);
$student_submitted_count=(int)($documentStats['submitted'] ?? 0);

$pageHeading='แผงควบคุมนักศึกษา';
$studentName=trim(($student['prefix_short'] ?? '').' '.$student['firstname'].' '.$student['lastname']);
?>
<!doctype html>
<html lang="th">
<head>
    <link rel="icon" type="image/png" href="/Project/assets/images/fet-logo-transparent.png?v=20260815-3">
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>นักศึกษา</title><script src="https://cdn.tailwindcss.com"></script><link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css"></head>
<body class="min-h-screen bg-gray-100 text-gray-900">
<?php include 'sidebar.php'; ?>
<div class="min-h-screen lg:ml-72">
<?php include 'navbar.php'; ?>
<main class="student-page-content p-4 sm:p-6 lg:p-8">
    <section class="student-welcome overflow-hidden rounded-lg bg-gray-950 text-white shadow-sm">
        <div class="grid gap-8 p-6 sm:p-8 lg:grid-cols-[1fr_auto] lg:items-center">
            <div><p class="text-sm font-semibold text-red-400">ยินดีต้อนรับ</p><h2 class="mt-2 text-2xl font-bold sm:text-3xl"><?= htmlspecialchars($studentName) ?></h2>
                <div class="mt-5 grid gap-2 text-sm text-gray-300 sm:grid-cols-2"><p><i class="fa-solid fa-building-columns mr-2 w-5 text-red-400"></i><?= htmlspecialchars($student['faculty_name'] ?? '-') ?></p><p><i class="fa-solid fa-graduation-cap mr-2 w-5 text-red-400"></i><?= htmlspecialchars($student['department_name'] ?? '-') ?></p><p><i class="fa-solid fa-users mr-2 w-5 text-red-400"></i><?= htmlspecialchars($student['group_name'] ?? '-') ?></p><p><i class="fa-solid fa-id-card mr-2 w-5 text-red-400"></i><?= htmlspecialchars($student['student_number']) ?></p></div>
            </div><img src="../assets/images/logo-transparent.png" alt="RMUTI" class="hidden h-auto w-56 object-contain opacity-90 lg:block">
        </div>
    </section>

    <section class="mt-6 border-t border-gray-200 pt-6"><div class="mb-4"><h2 class="text-xl font-bold">เริ่มต้นใช้งาน</h2><p class="text-sm text-gray-500">เลือกงานที่ต้องการดำเนินการ</p></div>
        <div class="grid gap-3 md:grid-cols-3"><a href="documents.php" class="flex items-center gap-4 rounded-lg bg-white p-4 shadow-sm transition hover:shadow"><i class="fa-solid fa-file-circle-plus text-2xl text-red-600"></i><div><strong>สร้างคำร้อง (R)</strong><p class="text-sm text-gray-500">เลือกและกรอกแบบคำร้อง</p></div></a><a href="internship.php" class="flex items-center gap-4 rounded-lg bg-white p-4 shadow-sm transition hover:shadow"><i class="fa-solid fa-briefcase text-2xl text-blue-600"></i><div><strong>เอกสารสหกิจ</strong><p class="text-sm text-gray-500">จัดการข้อมูลการฝึกงาน</p></div></a><a href="history.php" class="flex items-center gap-4 rounded-lg bg-white p-4 shadow-sm transition hover:shadow"><i class="fa-solid fa-clock-rotate-left text-2xl text-amber-600"></i><div><strong>ประวัติเอกสาร</strong><p class="text-sm text-gray-500">ติดตามเอกสารของฉัน</p></div></a></div>
    </section>

</main></div>
</body></html>
