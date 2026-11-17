<?php
session_start();
require_once '../middleware/teacher.php';
require_once '../config/database.php';
require_once '../helpers/teacher_coordinator_helper.php';

$stmt = $pdo->prepare('SELECT e.employee_id,e.department_id,e.firstname,e.lastname,u.email,e.employee_type,d.department_name,f.faculty_name,
    (SELECT GROUP_CONCAT(DISTINCT p.position_name ORDER BY p.position_name SEPARATOR ", ")
     FROM employee_position ep JOIN positions p ON p.position_id=ep.position_id
     WHERE ep.employee_id=e.employee_id) AS position_names
    FROM employee e
    JOIN users u ON u.user_id=e.user_id
    LEFT JOIN department d ON d.department_id=e.department_id
    LEFT JOIN faculty f ON f.faculty_id=d.faculty_id
    WHERE e.user_id=? AND e.employee_type="teacher" LIMIT 1');
$stmt->execute([$_SESSION['user_id']]);
$teacher = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$teacher) {
    http_response_code(403);
    renderPlainErrorAlert('ไม่พบข้อมูลอาจารย์');
}

$teacher_name = trim($teacher['firstname'] . ' ' . $teacher['lastname']);
$teacher_position = trim((string)($teacher['position_names'] ?? '')) ?: 'อาจารย์';
$pendingStmt = $pdo->prepare('
    SELECT DISTINCT d.document_id,d.document_status,d.submitted_at,s.student_number,s.firstname,s.lastname,dt.document_code,dt.document_name,
           current_step.role_code AS current_role_code,current_step.step_name AS current_step_name
    FROM documents d
    JOIN student s ON s.student_id=d.student_id
    JOIN document_template t ON t.template_id=d.template_id
    JOIN document_type dt ON dt.document_type_id=t.document_type_id
    LEFT JOIN document_review_processes drp ON drp.document_id=d.document_id
    LEFT JOIN document_review_step_instances current_step ON current_step.process_id=drp.process_id AND current_step.step_order=drp.current_step_order
    WHERE drp.process_status="in_review"
      AND current_step.step_status="current"
      AND current_step.reviewer_user_id=?
    ORDER BY d.submitted_at DESC,d.document_id DESC');
$pendingStmt->execute([$_SESSION['user_id']]);
$all_documents = $pendingStmt->fetchAll(PDO::FETCH_ASSOC);
$teacherCoordinatorProfile = teacherCoordinatorProfile($pdo, (int) $_SESSION['user_id']);
$coordinatorDocuments = array_values(array_filter($all_documents, static fn(array $row): bool =>
    strtoupper((string)$row['document_code']) === 'INTERNSHIP_INFO'
    && ((string)$row['current_role_code'] === 'internship_coordinator'
        || (string)$row['current_step_name'] === 'อาจารย์ประสานงานฝึกประสบการณ์วิชาชีพ')
));
$waitingDocuments = array_values(array_filter($all_documents, static fn(array $row): bool =>
    !(strtoupper((string)$row['document_code']) === 'INTERNSHIP_INFO'
      && ((string)$row['current_role_code'] === 'internship_coordinator'
          || (string)$row['current_step_name'] === 'อาจารย์ประสานงานฝึกประสบการณ์วิชาชีพ'))
));
$pending_document_count = count($waitingDocuments);
$pending_student_count = count(array_unique(array_column($waitingDocuments, 'student_number')));
$pageHeading = 'แผงควบคุมอาจารย์';
?>
<!doctype html>
<html lang="th">
<head>
    <link rel="icon" type="image/png" href="/Project/assets/images/fet-logo-transparent.png?v=20260815-3">

    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>อาจารย์</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
</head>
<body class="min-h-screen bg-gray-100 text-gray-900">
<?php include 'sidebar.php'; ?>
<div class="min-h-screen lg:ml-72">
    <?php include 'navbar.php'; ?>
    <main class="p-4 sm:p-6 lg:p-8">
        <section class="overflow-hidden rounded-lg bg-gray-950 text-white shadow-sm">
            <div class="grid gap-8 p-6 sm:p-8 lg:grid-cols-[1fr_auto] lg:items-center">
                <div>
                    <p class="text-sm font-semibold text-red-400"><?= htmlspecialchars($teacher_position) ?></p>
                    <div class="mt-5 grid gap-2 text-sm text-gray-300 sm:grid-cols-2">
                        <p><i class="fa-solid fa-building-columns mr-2 w-5 text-red-400"></i><?= htmlspecialchars($teacher['faculty_name'] ?? '-') ?></p>
                        <p><i class="fa-solid fa-building mr-2 w-5 text-red-400"></i><?= htmlspecialchars($teacher['department_name'] ?? '-') ?></p>
                        <p><i class="fa-solid fa-envelope mr-2 w-5 text-red-400"></i><?= htmlspecialchars($teacher['email'] ?? '-') ?></p>
                    </div>
                </div>
                <img src="../assets/images/logo-transparent.png" alt="RMUTI" class="hidden h-auto w-56 object-contain opacity-90 lg:block">
            </div>
        </section>

        <section class="mt-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
            <a href="requests.php" class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm transition hover:border-red-300 hover:shadow">
                <div class="flex items-center justify-between"><span class="inline-flex h-10 w-10 items-center justify-center rounded-lg bg-red-100 text-red-700"><i class="fa-solid fa-file-signature text-lg"></i></span><i class="fa-solid fa-arrow-right text-gray-300"></i></div>
                <p class="mt-3 text-sm text-gray-500">เอกสารรอพิจารณา</p><strong class="mt-0.5 block text-2xl text-gray-950"><?= $pending_document_count ?></strong>
            </a>
            <a href="requests.php" class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm transition hover:border-blue-300 hover:shadow">
                <div class="flex items-center justify-between"><span class="inline-flex h-10 w-10 items-center justify-center rounded-lg bg-blue-100 text-blue-700"><i class="fa-solid fa-user-graduate text-lg"></i></span><i class="fa-solid fa-arrow-right text-gray-300"></i></div>
                <p class="mt-3 text-sm text-gray-500">นักศึกษาที่รอพิจารณา</p><strong class="mt-0.5 block text-2xl text-gray-950"><?= $pending_student_count ?></strong>
            </a>
            <?php if ($teacherCoordinatorProfile): ?>
            <a href="coordinator_requests.php" class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm transition hover:border-amber-300 hover:shadow">
                <div class="flex items-center justify-between"><span class="inline-flex h-10 w-10 items-center justify-center rounded-lg bg-amber-100 text-amber-700"><i class="fa-solid fa-briefcase text-lg"></i></span><i class="fa-solid fa-arrow-right text-gray-300"></i></div>
                <p class="mt-3 text-sm text-gray-500">คำร้องสหกิจสำหรับอาจารย์ประสานงาน</p><strong class="mt-0.5 block text-2xl text-gray-950"><?= count($coordinatorDocuments) ?></strong>
            </a>
            <?php endif; ?>
        </section>

        <section class="mt-6 border-t border-gray-200 pt-6">
            <div class="mb-4"><h2 class="text-xl font-bold">การทำงานหลัก</h2><p class="text-sm text-gray-500">ทางลัดสำหรับงานที่ใช้งานเป็นประจำ</p></div>
            <div class="grid gap-3 md:grid-cols-3">
                <a href="requests.php" class="flex items-center gap-4 rounded-lg bg-white p-4 shadow-sm"><i class="fa-solid fa-file-signature text-2xl text-red-600"></i><div><strong>ตรวจเอกสาร</strong><p class="text-sm text-gray-500">ดูเอกสารที่รอพิจารณา</p></div></a>
                <a href="profile.php" class="flex items-center gap-4 rounded-lg bg-white p-4 shadow-sm"><i class="fa-solid fa-user-pen text-2xl text-blue-600"></i><div><strong>ข้อมูลส่วนตัว</strong><p class="text-sm text-gray-500">ตรวจสอบข้อมูลอาจารย์</p></div></a>
                <a href="../auth/forgot_password.php" class="flex items-center gap-4 rounded-lg bg-white p-4 shadow-sm"><i class="fa-solid fa-envelope-circle-check text-2xl text-amber-600"></i><div><strong>เปลี่ยนผ่าน OTP</strong><p class="text-sm text-gray-500">ยืนยันผ่านอีเมลของคุณ</p></div></a>
            </div>
        </section>

    </main>
</div>
</body>
</html>
