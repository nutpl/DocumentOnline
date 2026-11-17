<?php

session_start();
require_once '../middleware/staff.php';
require_once '../config/database.php';
require_once '../helpers/pagination_helper.php';
require_once '../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

$staffStmt = $pdo->prepare("SELECT employee_id,department_id,employee_code,firstname,lastname FROM employee WHERE user_id=? AND employee_type IN ('staff','teacher') LIMIT 1");
$staffStmt->execute([$_SESSION['user_id']]);
$staff = $staffStmt->fetch(PDO::FETCH_ASSOC);
if (!$staff) {
    http_response_code(403);
    renderPlainErrorAlert('ไม่พบข้อมูลเจ้าหน้าที่');
}

if (($_GET['action'] ?? '') === 'download_template') {
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('รายชื่ออาจารย์');
    $sheet->fromArray([['รหัสบุคลากร', 'คำนำหน้า', 'ชื่อ', 'นามสกุล', 'อีเมล', 'ตำแหน่ง', 'เบอร์โทรศัพท์']]);
    $sheet->getStyle('A1:G1')->getFont()->setBold(true);
    $sheet->getStyle('G:G')->getNumberFormat()->setFormatCode('@');
    foreach (range('A', 'G') as $column) {
        $sheet->getColumnDimension($column)->setAutoSize(true);
    }
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="teacher_import_template.xlsx"');
    header('Cache-Control: max-age=0');
    (new Xlsx($spreadsheet))->save('php://output');
    $spreadsheet->disconnectWorksheets();
    exit;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$prefixes = $pdo->query("SELECT prefix_id,prefix_short FROM prefixes WHERE status='active' ORDER BY prefix_id")->fetchAll(PDO::FETCH_ASSOC);
$positions = $pdo->query('SELECT position_id,position_name FROM positions ORDER BY position_name')->fetchAll(PDO::FETCH_ASSOC);

$error = '';
$success = '';
$importResult = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf_token'], (string) ($_POST['csrf_token'] ?? ''))) {
        http_response_code(419);
        renderPlainErrorAlert('คำขอหมดอายุ กรุณาลองใหม่');
    }

    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'import') {
            if (!isset($_FILES['teacher_excel']) || (int) $_FILES['teacher_excel']['error'] !== UPLOAD_ERR_OK) {
                throw new RuntimeException('กรุณาเลือกไฟล์ Excel ที่ต้องการนำเข้า');
            }
            $upload = $_FILES['teacher_excel'];
            $extension = strtolower(pathinfo((string) $upload['name'], PATHINFO_EXTENSION));
            if (!in_array($extension, ['xlsx', 'xls'], true)) {
                throw new RuntimeException('รองรับเฉพาะไฟล์ .xlsx และ .xls เท่านั้น');
            }
            if ((int) $upload['size'] > 5 * 1024 * 1024) {
                throw new RuntimeException('ไฟล์ Excel ต้องมีขนาดไม่เกิน 5 MB');
            }

            $spreadsheet = IOFactory::load($upload['tmp_name']);
            $sheet = $spreadsheet->getActiveSheet();
            $highestRow = $sheet->getHighestDataRow();
            $highestColumn = $sheet->getHighestDataColumn();
            $rows = $sheet->rangeToArray("A1:{$highestColumn}{$highestRow}", null, true, true, false);
            if (!$rows) {
                throw new RuntimeException('ไม่พบข้อมูลในไฟล์ Excel');
            }

            $normalizeHeader = static function ($value): string {
                $value = preg_replace('/^\xEF\xBB\xBF/', '', trim((string) $value));
                return mb_strtolower(preg_replace('/\s+/u', '', $value), 'UTF-8');
            };
            $headerAliases = [
                'employee_code' => ['รหัสบุคลากร', 'รหัสอาจารย์', 'employeecode'],
                'prefix' => ['คำนำหน้า', 'prefix'],
                'firstname' => ['ชื่อ', 'firstname'],
                'lastname' => ['นามสกุล', 'lastname'],
                'email' => ['อีเมล', 'email'],
                'position' => ['ตำแหน่ง', 'position'],
                'phone' => ['เบอร์โทรศัพท์', 'โทรศัพท์', 'phone'],
            ];
            $templateHeaders = ['รหัสบุคลากร', 'คำนำหน้า', 'ชื่อ', 'นามสกุล', 'อีเมล', 'ตำแหน่ง', 'เบอร์โทรศัพท์'];
            $uploadedHeaders = array_map($normalizeHeader, array_slice($rows[0], 0, count($templateHeaders)));
            if ($uploadedHeaders !== array_map($normalizeHeader, $templateHeaders)) {
                throw new RuntimeException('หัวคอลัมน์ไม่ตรงกับไฟล์แม่แบบ กรุณาดาวน์โหลดไฟล์แม่แบบและกรอกข้อมูลลงในไฟล์เดิม');
            }
            $normalizedAliases = [];
            foreach ($headerAliases as $field => $aliases) {
                $normalizedAliases[$field] = array_map($normalizeHeader, $aliases);
            }
            $columns = [];
            foreach ($rows[0] as $index => $heading) {
                $normalized = $normalizeHeader($heading);
                foreach ($normalizedAliases as $field => $aliases) {
                    if (in_array($normalized, $aliases, true)) {
                        $columns[$field] = $index;
                    }
                }
            }
            $missing = array_diff(array_keys($headerAliases), array_keys($columns));
            if ($missing) {
                throw new RuntimeException('หัวตารางไม่ครบ กรุณาใช้คอลัมน์: รหัสบุคลากร, คำนำหน้า, ชื่อ, นามสกุล, อีเมล, ตำแหน่ง, เบอร์โทรศัพท์');
            }

            $prefixMap = [];
            foreach ($prefixes as $prefix) {
                $prefixMap[$normalizeHeader($prefix['prefix_short'])] = (int) $prefix['prefix_id'];
            }
            $positionMap = [];
            foreach ($positions as $position) {
                $positionMap[$normalizeHeader($position['position_name'])] = (int) $position['position_id'];
            }

            $duplicateStmt = $pdo->prepare('SELECT COUNT(*) FROM users u LEFT JOIN employee e ON e.user_id=u.user_id WHERE u.email=? OR u.username=? OR e.employee_code=?');
            $insertUserStmt = $pdo->prepare("INSERT INTO users(username,email,password,first_login,status) VALUES (?,?,'pending-password',1,'active')");
            $insertRoleStmt = $pdo->prepare("INSERT INTO user_roles(user_id,role) VALUES (?,'teacher')");
            $insertEmployeeStmt = $pdo->prepare("INSERT INTO employee(user_id,employee_code,prefix_id,firstname,lastname,phone,department_id,employee_type) VALUES (?,?,?,?,?,?,?,'teacher')");
            $createPositionMasterStmt = $pdo->prepare('INSERT IGNORE INTO positions(position_name) VALUES (?)');
            $findPositionMasterStmt = $pdo->prepare('SELECT position_id FROM positions WHERE position_name=? LIMIT 1');
            $insertPositionStmt = $pdo->prepare('INSERT INTO employee_position(employee_id,position_id,is_primary) VALUES (?,?,1)');
            $imported = 0;
            $importedRows = [];
            $existingRows = [];
            $invalidRows = [];
            $seenCodes = [];
            $seenEmails = [];

            foreach (array_slice($rows, 1) as $offset => $row) {
                $excelRow = $offset + 2;
                $values = [];
                foreach ($columns as $field => $index) {
                    $values[$field] = trim((string) ($row[$index] ?? ''));
                }
                if (!array_filter($values, static fn($value) => $value !== '')) {
                    continue;
                }
                try {
                    $employeeCode = $values['employee_code'];
                    $email = mb_strtolower($values['email'], 'UTF-8');
                    $prefixId = $prefixMap[$normalizeHeader($values['prefix'])] ?? 0;
                    $positionName = trim($values['position']);
                    $positionKey = $normalizeHeader($positionName);
                    $positionId = $positionMap[$positionKey] ?? 0;
                    if ($employeeCode === '' || $values['firstname'] === '' || $values['lastname'] === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                        throw new RuntimeException('ข้อมูลไม่ครบหรืออีเมลไม่ถูกต้อง');
                    }
                    if (!$prefixId) {
                        throw new RuntimeException('ไม่พบคำนำหน้า “' . $values['prefix'] . '” ในระบบ');
                    }
                    if ($positionName === '') {
                        throw new RuntimeException('กรุณาระบุตำแหน่ง');
                    }
                    if (mb_strlen($values['phone']) > 30) {
                        throw new RuntimeException('เบอร์โทรศัพท์ยาวเกิน 30 ตัวอักษร');
                    }
                    $codeKey = mb_strtolower($employeeCode, 'UTF-8');
                    $personLabel = trim($values['prefix'] . $values['firstname'] . ' ' . $values['lastname']) . ' (' . $employeeCode . ')';
                    if (isset($seenCodes[$codeKey]) || isset($seenEmails[$email])) {
                        $existingRows[] = 'แถว ' . $excelRow . ': ' . $personLabel . ' — ซ้ำภายในไฟล์';
                        continue;
                    }
                    $duplicateStmt->execute([$email, $employeeCode, $employeeCode]);
                    if ((int) $duplicateStmt->fetchColumn() > 0) {
                        $existingRows[] = 'แถว ' . $excelRow . ': ' . $personLabel . ' — มีข้อมูลอยู่ในระบบแล้ว';
                        continue;
                    }

                    $pdo->beginTransaction();
                    if (!$positionId) {
                        $createPositionMasterStmt->execute([$positionName]);
                        $findPositionMasterStmt->execute([$positionName]);
                        $positionId = (int) $findPositionMasterStmt->fetchColumn();
                        if (!$positionId) {
                            throw new RuntimeException('ไม่สามารถเพิ่มตำแหน่ง “' . $positionName . '” ได้');
                        }
                        $positionMap[$positionKey] = $positionId;
                    }
                    $insertUserStmt->execute([$employeeCode, $email]);
                    $userId = (int) $pdo->lastInsertId();
                    $insertRoleStmt->execute([$userId]);
                    $insertEmployeeStmt->execute([$userId, $employeeCode, $prefixId, $values['firstname'], $values['lastname'], $values['phone'] !== '' ? $values['phone'] : null, $staff['department_id']]);
                    $employeeId = (int) $pdo->lastInsertId();
                    $insertPositionStmt->execute([$employeeId, $positionId]);
                    $pdo->commit();
                    $seenCodes[$codeKey] = true;
                    $seenEmails[$email] = true;
                    $imported++;
                    $importedRows[] = 'แถว ' . $excelRow . ': ' . $personLabel;
                } catch (Throwable $rowError) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    $invalidRows[] = 'แถว ' . $excelRow . ': ' . $rowError->getMessage();
                }
            }
            $spreadsheet->disconnectWorksheets();
            if ($imported === 0 && !$existingRows && !$invalidRows) {
                throw new RuntimeException('ไม่พบรายการข้อมูลสำหรับนำเข้า');
            }

            $importResult = [
                'imported' => $importedRows,
                'existing' => $existingRows,
                'invalid' => $invalidRows,
            ];

            if ($imported > 0) {
                $success = 'นำเข้าอาจารย์ใหม่สำเร็จ ' . $imported . ' รายการ';
            }
	        } elseif ($action === 'create') {
            $employeeCode = trim($_POST['employee_code'] ?? '');
            $firstname = trim($_POST['firstname'] ?? '');
            $lastname = trim($_POST['lastname'] ?? '');
	            $email = strtolower(trim($_POST['email'] ?? ''));
	            $phone = trim($_POST['phone'] ?? '');
            $prefixId = (int) ($_POST['prefix_id'] ?? 0);
            $positionId = (int) ($_POST['position_id'] ?? 0);
            if ($employeeCode === '' || $firstname === '' || $lastname === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || !$prefixId || !$positionId) {
                throw new RuntimeException('กรุณากรอกข้อมูลอาจารย์ให้ครบและใช้อีเมลที่ถูกต้อง');
            }

            $validPrefix = $pdo->prepare("SELECT COUNT(*) FROM prefixes WHERE prefix_id=? AND status='active'");
            $validPrefix->execute([$prefixId]);
            $validPosition = $pdo->prepare('SELECT COUNT(*) FROM positions WHERE position_id=?');
            $validPosition->execute([$positionId]);
            if (!(int) $validPrefix->fetchColumn() || !(int) $validPosition->fetchColumn()) {
                throw new RuntimeException('คำนำหน้าหรือตำแหน่งไม่ถูกต้อง');
            }

            $duplicate = $pdo->prepare('SELECT COUNT(*) FROM users u LEFT JOIN employee e ON e.user_id=u.user_id WHERE u.email=? OR u.username=? OR e.employee_code=?');
            $duplicate->execute([$email, $employeeCode, $employeeCode]);
            if ((int) $duplicate->fetchColumn() > 0) {
                throw new RuntimeException('อีเมลหรือรหัสบุคลากรนี้มีอยู่ในระบบแล้ว');
            }

            $pdo->beginTransaction();
            $stmt = $pdo->prepare("INSERT INTO users(username,email,password,first_login,status) VALUES (?,?,'pending-password',1,'active')");
            $stmt->execute([$employeeCode, $email]);
            $userId = (int) $pdo->lastInsertId();
            $pdo->prepare("INSERT INTO user_roles(user_id,role) VALUES (?,'teacher')")->execute([$userId]);
	            $stmt = $pdo->prepare("INSERT INTO employee(user_id,employee_code,prefix_id,firstname,lastname,phone,department_id,employee_type) VALUES (?,?,?,?,?,?,?,'teacher')");
	            $stmt->execute([$userId, $employeeCode, $prefixId, $firstname, $lastname, $phone !== '' ? $phone : null, $staff['department_id']]);
            $employeeId = (int) $pdo->lastInsertId();
            $pdo->prepare('INSERT INTO employee_position(employee_id,position_id,is_primary) VALUES (?,?,1)')->execute([$employeeId, $positionId]);
            $pdo->commit();
            $success = 'เพิ่มข้อมูลอาจารย์แล้ว อาจารย์สามารถรับ OTP เพื่อตั้งรหัสผ่านได้';
        } elseif ($action === 'update') {
            $employeeId = (int) ($_POST['employee_id'] ?? 0);
            $firstname = trim($_POST['firstname'] ?? '');
            $lastname = trim($_POST['lastname'] ?? '');
	            $email = strtolower(trim($_POST['email'] ?? ''));
	            $phone = trim($_POST['phone'] ?? '');
            $prefixId = (int) ($_POST['prefix_id'] ?? 0);
            $positionId = (int) ($_POST['position_id'] ?? 0);
            if (!$employeeId || $firstname === '' || $lastname === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || !$prefixId || !$positionId) {
                throw new RuntimeException('ข้อมูลที่แก้ไขไม่ถูกต้อง');
            }

            $validPrefix = $pdo->prepare("SELECT COUNT(*) FROM prefixes WHERE prefix_id=? AND status='active'");
            $validPrefix->execute([$prefixId]);
            $validPosition = $pdo->prepare('SELECT COUNT(*) FROM positions WHERE position_id=?');
            $validPosition->execute([$positionId]);
            if (!(int) $validPrefix->fetchColumn() || !(int) $validPosition->fetchColumn()) {
                throw new RuntimeException('คำนำหน้าหรือตำแหน่งไม่ถูกต้อง');
            }
            $owner = $pdo->prepare("SELECT user_id FROM employee WHERE employee_id=? AND department_id=? AND employee_type='teacher'");
            $owner->execute([$employeeId, $staff['department_id']]);
            $userId = (int) $owner->fetchColumn();
            if (!$userId) {
                throw new RuntimeException('ไม่พบอาจารย์ในสาขาของคุณ');
            }
            $duplicate = $pdo->prepare('SELECT COUNT(*) FROM users WHERE email=? AND user_id<>?');
            $duplicate->execute([$email, $userId]);
            if ((int) $duplicate->fetchColumn() > 0) {
                throw new RuntimeException('อีเมลนี้มีบัญชีอื่นใช้งานแล้ว');
            }
            $pdo->beginTransaction();
            $pdo->prepare('UPDATE users SET email=? WHERE user_id=?')->execute([$email, $userId]);
	            $pdo->prepare('UPDATE employee SET prefix_id=?,firstname=?,lastname=?,phone=? WHERE employee_id=?')->execute([$prefixId, $firstname, $lastname, $phone !== '' ? $phone : null, $employeeId]);
            $pdo->prepare('UPDATE employee_position SET is_primary=0 WHERE employee_id=?')->execute([$employeeId]);
            $pdo->prepare('INSERT INTO employee_position(employee_id,position_id,is_primary) VALUES (?,?,1) ON DUPLICATE KEY UPDATE is_primary=1')->execute([$employeeId, $positionId]);
            $pdo->commit();
            $success = 'แก้ไขข้อมูลอาจารย์แล้ว';
        } elseif ($action === 'toggle') {
            $employeeId = (int) ($_POST['employee_id'] ?? 0);
            $owner = $pdo->prepare("SELECT e.user_id,u.status FROM employee e JOIN users u ON u.user_id=e.user_id WHERE e.employee_id=? AND e.department_id=? AND e.employee_type='teacher'");
            $owner->execute([$employeeId, $staff['department_id']]);
            $teacher = $owner->fetch(PDO::FETCH_ASSOC);
            if (!$teacher) {
                throw new RuntimeException('ไม่พบอาจารย์ในสาขาของคุณ');
            }
            $nextStatus = $teacher['status'] === 'active' ? 'inactive' : 'active';
            $pdo->prepare('UPDATE users SET status=? WHERE user_id=?')->execute([$nextStatus, $teacher['user_id']]);
            $success = $nextStatus === 'active' ? 'เปิดใช้งานบัญชีแล้ว' : 'ปิดใช้งานบัญชีแล้ว';
        }
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error = $e->getMessage();
    }
}

$teachersPerPage = selected_per_page(10);
$teacherPage = max(1, (int) ($_GET['page'] ?? 1));
$teacherSearch = trim((string) ($_GET['search'] ?? ''));
$teacherStatus = in_array(($_GET['status'] ?? ''), ['active', 'inactive'], true) ? $_GET['status'] : '';
$teacherWhere = " WHERE e.department_id=:department_id AND e.employee_type='teacher'";
$teacherParameters = [':department_id' => (int) $staff['department_id']];
if ($teacherSearch !== '') {
	    $teacherWhere .= ' AND (e.employee_code LIKE :teacher_search OR e.firstname LIKE :teacher_search OR e.lastname LIKE :teacher_search OR u.email LIKE :teacher_search OR e.phone LIKE :teacher_search OR p.position_name LIKE :teacher_search)';
    $teacherParameters[':teacher_search'] = '%'.$teacherSearch.'%';
}
if ($teacherStatus !== '') {
    $teacherWhere .= ' AND u.status=:teacher_status';
    $teacherParameters[':teacher_status'] = $teacherStatus;
}
$teacherCountStmt = $pdo->prepare("SELECT COUNT(DISTINCT e.employee_id) FROM employee e JOIN users u ON u.user_id=e.user_id LEFT JOIN employee_position ep ON ep.employee_id=e.employee_id AND ep.is_primary=1 LEFT JOIN positions p ON p.position_id=ep.position_id".$teacherWhere);
$teacherCountStmt->execute($teacherParameters);
$teacherTotal = (int) $teacherCountStmt->fetchColumn();
$teacherTotalPages = max(1, (int) ceil($teacherTotal / $teachersPerPage));
$teacherPage = min($teacherPage, $teacherTotalPages);
$teacherOffset = ($teacherPage - 1) * $teachersPerPage;

$teacherStmt = $pdo->prepare("SELECT e.employee_id,e.employee_code,e.prefix_id,e.firstname,e.lastname,u.email,e.phone,u.first_login,u.status,ep.position_id,p.position_name,
    (SELECT COUNT(*) FROM group_class g WHERE g.department_id=e.department_id AND g.advisor_employee_id=e.employee_id) AS advisor_group_count,
    (SELECT GROUP_CONCAT(g.group_name ORDER BY g.group_name SEPARATOR ', ') FROM group_class g WHERE g.department_id=e.department_id AND g.advisor_employee_id=e.employee_id) AS advisor_group_names
    FROM employee e JOIN users u ON u.user_id=e.user_id
    LEFT JOIN employee_position ep ON ep.employee_id=e.employee_id AND ep.is_primary=1
    LEFT JOIN positions p ON p.position_id=ep.position_id
    ".$teacherWhere."
    ORDER BY e.firstname,e.lastname LIMIT :teacher_limit OFFSET :teacher_offset");
foreach ($teacherParameters as $parameter => $value) {
    $teacherStmt->bindValue($parameter, $value, $parameter === ':department_id' ? PDO::PARAM_INT : PDO::PARAM_STR);
}
$teacherStmt->bindValue(':teacher_limit', $teachersPerPage, PDO::PARAM_INT);
$teacherStmt->bindValue(':teacher_offset', $teacherOffset, PDO::PARAM_INT);
$teacherStmt->execute();
$teachers = $teacherStmt->fetchAll(PDO::FETCH_ASSOC);
$advisorGroupsStmt = $pdo->prepare('SELECT g.group_id,g.group_name,g.advisor_employee_id,COUNT(s.student_id) AS student_count FROM group_class g LEFT JOIN student s ON s.group_id=g.group_id WHERE g.department_id=? GROUP BY g.group_id ORDER BY g.group_name');
$advisorGroupsStmt->execute([$staff['department_id']]);
$advisorGroups = $advisorGroupsStmt->fetchAll(PDO::FETCH_ASSOC);
$staff_name = trim($staff['firstname'] . ' ' . $staff['lastname']);
$documentCountStmt = $pdo->prepare('SELECT COUNT(*) FROM documents d JOIN student s ON s.student_id=d.student_id JOIN group_class g ON g.group_id=s.group_id JOIN document_template t ON t.template_id=d.template_id JOIN document_type dt ON dt.document_type_id=t.document_type_id WHERE g.department_id=? AND d.document_status="submitted" AND dt.document_code IN ("INTERNSHIP_REQUEST","INTERNSHIP_SEND")');
$documentCountStmt->execute([$staff['department_id']]);
$document_count = (int) $documentCountStmt->fetchColumn();
$pageHeading = 'จัดการข้อมูลอาจารย์';
$navbarActions = [
    ['target' => 'teacher-import-modal', 'label' => 'เพิ่มอาจารย์', 'icon' => 'fa-user-plus', 'tone' => 'green'],
];
?>
<!doctype html>
<html lang="th">
<head>
    <link rel="icon" type="image/png" href="/Project/assets/images/fet-logo-transparent.png?v=20260815-3">

    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>จัดการข้อมูลอาจารย์</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <link rel="stylesheet" href="/Project/assets/css/app-ui.css?v=20260902-1">
</head>
<body class="min-h-screen bg-slate-100 text-slate-900">
<?php include 'sidebar.php'; ?>
<div class="min-h-screen lg:ml-72">
<?php include 'navbar.php'; ?>
<main class="p-4 sm:p-6 lg:p-8">
    <section class="management-hero"><span class="management-hero__icon"><i class="fa-solid fa-chalkboard-user"></i></span><div class="min-w-0 flex-1"><p class="management-hero__eyebrow">เฉพาะสาขาของคุณ</p><h1>อาจารย์ในสาขา</h1><h6>ค้นหา ปรับปรุงข้อมูล และกำหนดกลุ่มเรียนที่อาจารย์ดูแล</h6></div><span class="inline-flex shrink-0 items-center gap-2 rounded-full border border-red-100 bg-red-50 px-3 py-2 text-sm font-bold text-red-700"><i class="fa-solid fa-users"></i><?= $teacherTotal ?> คน</span></section>
    <?php if ($error): ?><div class="mb-5 rounded-lg bg-red-100 p-4 text-red-700"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <?php if ($success): ?><div hidden data-success-alert data-success-title="ดำเนินการสำเร็จ" data-success-text="<?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?>"></div><?php endif; ?>
    <?php if (!empty($_SESSION['teacher_groups_success'])): ?><div hidden data-success-alert data-success-title="ดำเนินการสำเร็จ" data-success-text="<?= htmlspecialchars($_SESSION['teacher_groups_success'], ENT_QUOTES, 'UTF-8') ?>"></div><?php unset($_SESSION['teacher_groups_success']); endif; ?>

    <div id="teacher-import-modal" data-initial-mode="<?= ($_POST['action'] ?? '') === 'import' ? 'import' : 'manual' ?>" class="fixed inset-0 z-[70] <?= $error && in_array(($_POST['action'] ?? ''), ['import', 'create'], true) ? '' : 'hidden' ?> overflow-y-auto bg-slate-950/50 p-4 backdrop-blur-sm" role="dialog" aria-modal="true" aria-labelledby="teacher-import-title">
        <div class="flex min-h-full items-center justify-center">
        <section class="w-full max-w-3xl overflow-hidden rounded-2xl bg-white shadow-2xl">
            <div class="flex items-start justify-between gap-4 border-b border-slate-100 px-5 py-4 sm:px-6">
                <div class="flex min-w-0 items-center gap-3"><span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600"><i class="fa-solid fa-user-plus"></i></span><div><h2 id="teacher-import-title" class="text-xl font-bold text-slate-900">เพิ่มอาจารย์</h2><p class="mt-0.5 text-sm text-slate-500">เลือกกรอกข้อมูลทีละรายการ หรือนำเข้าหลายรายการจาก Excel</p></div></div>
                <button type="button" data-close-teacher-import class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl border border-slate-200 text-slate-500 transition hover:bg-slate-100 hover:text-slate-800" aria-label="ปิดหน้าต่าง"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <div class="border-b border-slate-100 bg-slate-50 px-5 pt-4 sm:px-6">
                <div class="flex gap-2" role="tablist" aria-label="รูปแบบการเพิ่มอาจารย์">
                    <button type="button" data-teacher-add-mode="manual" class="rounded-t-xl px-4 py-2.5 text-sm font-bold transition"><i class="fa-solid fa-pen-to-square mr-2"></i>กรอกข้อมูลเอง</button>
                    <button type="button" data-teacher-add-mode="import" class="rounded-t-xl px-4 py-2.5 text-sm font-bold transition"><i class="fa-solid fa-file-excel mr-2"></i>นำเข้า Excel</button>
                </div>
            </div>

            <form method="post" data-teacher-add-panel="manual" class="bg-slate-50/60 p-5 sm:p-6">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                <input type="hidden" name="action" value="create">
                <?php if ($error && ($_POST['action'] ?? '') === 'create'): ?><div class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-red-800"><?= htmlspecialchars($error) ?></div><?php endif; ?>
                <p class="mb-4 text-sm text-slate-600">ข้อมูลอาจารย์ที่เพิ่มจะอยู่ในสาขาของคุณโดยอัตโนมัติ</p>
                <div class="grid gap-4 sm:grid-cols-2">
                    <label class="block text-sm font-bold text-slate-700">รหัสบุคลากร<input type="text" name="employee_code" required value="<?= ($_POST['action'] ?? '') === 'create' ? htmlspecialchars($_POST['employee_code'] ?? '') : '' ?>" class="mt-1.5 w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 font-normal outline-none focus:border-emerald-500 focus:ring-4 focus:ring-emerald-100" placeholder="เช่น 65001"></label>
                    <label class="block text-sm font-bold text-slate-700">อีเมล<input type="email" name="email" required value="<?= ($_POST['action'] ?? '') === 'create' ? htmlspecialchars($_POST['email'] ?? '') : '' ?>" class="mt-1.5 w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 font-normal outline-none focus:border-emerald-500 focus:ring-4 focus:ring-emerald-100" placeholder="name@rmuti.ac.th"></label>
                    <label class="block text-sm font-bold text-slate-700">คำนำหน้า<select name="prefix_id" required class="mt-1.5 w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 font-normal outline-none focus:border-emerald-500 focus:ring-4 focus:ring-emerald-100"><option value="">เลือกคำนำหน้า</option><?php foreach ($prefixes as $prefix): ?><option value="<?= (int) $prefix['prefix_id'] ?>" <?= ($_POST['action'] ?? '') === 'create' && (int) ($_POST['prefix_id'] ?? 0) === (int) $prefix['prefix_id'] ? 'selected' : '' ?>><?= htmlspecialchars($prefix['prefix_short']) ?></option><?php endforeach; ?></select></label>
                    <label class="block text-sm font-bold text-slate-700">ตำแหน่ง<select name="position_id" required class="mt-1.5 w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 font-normal outline-none focus:border-emerald-500 focus:ring-4 focus:ring-emerald-100"><option value="">เลือกตำแหน่ง</option><?php foreach ($positions as $position): ?><option value="<?= (int) $position['position_id'] ?>" <?= ($_POST['action'] ?? '') === 'create' && (int) ($_POST['position_id'] ?? 0) === (int) $position['position_id'] ? 'selected' : '' ?>><?= htmlspecialchars($position['position_name']) ?></option><?php endforeach; ?></select></label>
                    <label class="block text-sm font-bold text-slate-700">ชื่อ<input type="text" name="firstname" required value="<?= ($_POST['action'] ?? '') === 'create' ? htmlspecialchars($_POST['firstname'] ?? '') : '' ?>" class="mt-1.5 w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 font-normal outline-none focus:border-emerald-500 focus:ring-4 focus:ring-emerald-100"></label>
                    <label class="block text-sm font-bold text-slate-700">นามสกุล<input type="text" name="lastname" required value="<?= ($_POST['action'] ?? '') === 'create' ? htmlspecialchars($_POST['lastname'] ?? '') : '' ?>" class="mt-1.5 w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 font-normal outline-none focus:border-emerald-500 focus:ring-4 focus:ring-emerald-100"></label>
                    <label class="block text-sm font-bold text-slate-700 sm:col-span-2">เบอร์โทรศัพท์ <span class="font-normal text-slate-400">(ถ้ามี)</span><input type="tel" name="phone" maxlength="30" value="<?= ($_POST['action'] ?? '') === 'create' ? htmlspecialchars($_POST['phone'] ?? '') : '' ?>" class="mt-1.5 w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 font-normal outline-none focus:border-emerald-500 focus:ring-4 focus:ring-emerald-100" placeholder="08x-xxx-xxxx"></label>
                </div>
                <div class="mt-5 flex justify-end"><button class="rounded-xl bg-emerald-600 px-5 py-2.5 font-bold text-white transition hover:bg-emerald-700"><i class="fa-solid fa-user-plus mr-2"></i>เพิ่มอาจารย์</button></div>
            </form>

            <form method="post" enctype="multipart/form-data" data-teacher-add-panel="import" class="hidden bg-slate-50/60 p-5 sm:p-6">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                <input type="hidden" name="action" value="import">
                <?php if ($error && ($_POST['action'] ?? '') === 'import'): ?><div class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-red-800"><?= htmlspecialchars($error) ?></div><?php endif; ?>
                <div class="mb-4 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-900"><div><span class="font-bold">1. ดาวน์โหลดแม่แบบ</span><span class="mx-1 text-blue-300">→</span><span class="font-bold">2. กรอกข้อมูล</span><span class="mx-1 text-blue-300">→</span><span class="font-bold">3. อัปโหลดไฟล์เดิม</span><p class="mt-1 text-xs text-blue-700">รหัสบุคลากร | คำนำหน้า | ชื่อ | นามสกุล | อีเมล | ตำแหน่ง | เบอร์โทรศัพท์</p></div><a href="teachers.php?action=download_template" class="inline-flex shrink-0 items-center gap-2 rounded-lg bg-emerald-600 px-4 py-2 font-bold text-white transition hover:bg-emerald-700"><i class="fa-solid fa-download"></i>ดาวน์โหลดแม่แบบ</a></div>
                <div class="flex flex-col gap-4 sm:flex-row sm:items-end"><label class="block min-w-0 flex-1 text-sm font-bold">เลือกไฟล์ Excel<input type="file" name="teacher_excel" accept=".xlsx,.xls,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/vnd.ms-excel" required class="mt-2 block w-full rounded-lg border bg-white px-3 py-2.5 text-sm"></label><button class="rounded-lg bg-emerald-600 px-5 py-2.5 font-bold text-white hover:bg-emerald-700"><i class="fa-solid fa-file-import mr-2"></i>เพิ่มรายชื่อ</button></div>
            </form>
        </section>
        </div>
    </div>

    <div id="teacher-view-summary-modal" class="hidden"></div>
    <div id="teacher-view-modal" class="fixed inset-0 z-[75] hidden overflow-y-auto bg-slate-950/50 p-3 backdrop-blur-sm sm:p-6" role="dialog" aria-modal="true"><div class="flex min-h-full items-center justify-center"><section class="w-full max-w-[1420px] overflow-hidden rounded-2xl bg-slate-100 shadow-2xl"><div class="flex justify-end px-5 pt-4"><button type="button" data-close-teacher-view class="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-500 hover:bg-slate-50" aria-label="ปิดหน้าต่าง"><i class="fa-solid fa-xmark"></i></button></div><div class="space-y-5 p-3 pt-1 sm:p-5 sm:pt-1"><section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm"><div class="flex flex-col gap-5 border-l-[6px] border-red-600 px-6 py-7 sm:flex-row sm:items-center"><span class="flex h-16 w-16 shrink-0 items-center justify-center rounded-2xl bg-red-600 text-2xl text-white shadow-lg"><i class="fa-solid fa-chalkboard-user"></i></span><div class="min-w-0 flex-1"><p class="text-sm font-semibold text-red-600">รายละเอียดอาจารย์ในสาขา</p><h2 class="mt-1 text-2xl font-black text-slate-900 sm:text-3xl" data-teacher-view-name></h2><p class="mt-2 text-sm text-slate-500" data-teacher-view-position></p></div><span class="self-start rounded-full bg-emerald-50 px-3 py-1.5 text-sm font-bold text-emerald-700" data-teacher-view-status></span></div><div class="grid border-t border-slate-100 sm:grid-cols-3"><div class="px-6 py-4"><p class="text-xs font-bold text-slate-500">อีเมล</p><p class="mt-1 break-all font-semibold text-slate-800" data-teacher-view-email></p></div><div class="border-t border-slate-100 px-6 py-4 sm:border-l sm:border-t-0"><p class="text-xs font-bold text-slate-500">เบอร์โทรศัพท์</p><p class="mt-1 font-semibold text-slate-800" data-teacher-view-phone></p></div><div class="border-t border-slate-100 px-6 py-4 sm:border-l sm:border-t-0"><p class="text-xs font-bold text-slate-500">กลุ่มเรียนที่เป็นที่ปรึกษา</p><p class="mt-1 font-semibold text-slate-800"><span data-teacher-view-group-count></span> กลุ่มเรียน</p></div></div></section><section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm"><div class="flex flex-col gap-3 border-b border-slate-200 px-6 py-5 sm:flex-row sm:items-center sm:justify-between"><div><h3 class="text-lg font-black text-slate-900">กำหนดกลุ่มเรียนที่อาจารย์เป็นที่ปรึกษา</h3><p class="mt-1 text-sm text-slate-500">เลือกได้มากกว่า 1 กลุ่มเรียน ระบบจะบันทึกให้กับอาจารย์ที่เลือก</p></div><span class="self-start rounded-full bg-red-50 px-3 py-1.5 text-xs font-bold text-red-700"><i class="fa-solid fa-lock mr-1"></i>เฉพาะสาขาของคุณ</span></div><form method="post" action="teacher_detail.php" class="p-5 sm:p-6"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>"><input type="hidden" name="teacher_id" data-teacher-view-id><input type="hidden" name="from_modal" value="1"><div class="grid gap-3 md:grid-cols-2 xl:grid-cols-3"><?php foreach ($advisorGroups as $group): ?><label data-teacher-group-card data-group-id="<?= (int)$group['group_id'] ?>" class="flex cursor-pointer items-start gap-3 rounded-xl border border-slate-200 bg-white p-4 transition hover:border-red-200"><input type="checkbox" name="advisor_group_ids[]" value="<?= (int)$group['group_id'] ?>" class="mt-1 h-4 w-4 rounded border-slate-300 text-red-700"><span><strong class="block text-slate-900"><?= htmlspecialchars($group['group_name']) ?></strong><span class="mt-1 block text-xs text-slate-500"><i class="fa-solid fa-user-graduate mr-1"></i><?= (int)$group['student_count'] ?> นักศึกษา</span></span></label><?php endforeach; ?></div><div class="mt-6 flex justify-end"><button class="rounded-xl bg-red-700 px-5 py-3 font-bold text-white hover:bg-red-800"><i class="fa-solid fa-floppy-disk mr-2"></i>บันทึกการกำหนดกลุ่มเรียน</button></div></form></section></div></section></div></div>

    <div id="teacher-edit-modal" class="fixed inset-0 z-[75] hidden overflow-y-auto bg-slate-950/50 p-4 backdrop-blur-sm" role="dialog" aria-modal="true" aria-labelledby="teacher-edit-title">
        <div class="flex min-h-full items-center justify-center"><section class="w-full max-w-2xl overflow-hidden rounded-2xl bg-white shadow-2xl"><header class="flex items-start justify-between gap-4 border-b border-slate-100 px-5 py-4"><div><p class="text-sm font-bold text-blue-700">แก้ไขข้อมูลอาจารย์</p><h2 id="teacher-edit-title" class="mt-1 text-xl font-black text-slate-900" data-teacher-edit-name></h2></div><button type="button" data-close-teacher-edit class="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-slate-200 text-slate-500 hover:bg-slate-100" aria-label="ปิดหน้าต่าง"><i class="fa-solid fa-xmark"></i></button></header><form method="post" class="p-5 sm:p-6"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>"><input type="hidden" name="action" value="update"><input type="hidden" name="employee_id" data-teacher-edit-id><div class="grid gap-4 sm:grid-cols-2"><label class="text-sm font-bold text-slate-700">คำนำหน้า<select required name="prefix_id" data-teacher-edit-prefix class="mt-1.5 w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 font-normal"><?php foreach ($prefixes as $prefix): ?><option value="<?= (int) $prefix['prefix_id'] ?>"><?= htmlspecialchars($prefix['prefix_short']) ?></option><?php endforeach; ?></select></label><label class="text-sm font-bold text-slate-700">ตำแหน่ง<select required name="position_id" data-teacher-edit-position class="mt-1.5 w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 font-normal"><?php foreach ($positions as $position): ?><option value="<?= (int) $position['position_id'] ?>"><?= htmlspecialchars($position['position_name']) ?></option><?php endforeach; ?></select></label><label class="text-sm font-bold text-slate-700">ชื่อ<input required name="firstname" data-teacher-edit-firstname class="mt-1.5 w-full rounded-xl border border-slate-300 px-3 py-2.5 font-normal"></label><label class="text-sm font-bold text-slate-700">นามสกุล<input required name="lastname" data-teacher-edit-lastname class="mt-1.5 w-full rounded-xl border border-slate-300 px-3 py-2.5 font-normal"></label><label class="text-sm font-bold text-slate-700">อีเมล<input required type="email" name="email" data-teacher-edit-email class="mt-1.5 w-full rounded-xl border border-slate-300 px-3 py-2.5 font-normal"></label><label class="text-sm font-bold text-slate-700">เบอร์โทรศัพท์<input type="tel" maxlength="30" name="phone" data-teacher-edit-phone class="mt-1.5 w-full rounded-xl border border-slate-300 px-3 py-2.5 font-normal" placeholder="08x-xxx-xxxx"></label></div><div class="mt-6 flex justify-end"><button class="rounded-xl bg-blue-600 px-5 py-2.5 font-bold text-white hover:bg-blue-700"><i class="fa-solid fa-floppy-disk mr-2"></i>บันทึกการแก้ไข</button></div></form></section></div>
    </div>

    <form id="staff-teacher-search" method="get" data-jquery-search-form data-search-results="#staff-teacher-results" class="mt-5 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm"><div class="mb-3 flex items-center gap-2 text-sm font-bold text-slate-700"><span class="inline-flex h-8 w-8 items-center justify-center rounded-lg bg-slate-100 text-slate-600"><i class="fa-solid fa-filter"></i></span>ค้นหาและกรองรายชื่อ</div><div class="grid gap-3 sm:grid-cols-[1fr_220px_auto]">
        <label><span class="mb-1 block text-xs font-bold text-slate-500">ค้นหาอาจารย์</span><input type="search" name="search" value="<?= htmlspecialchars($teacherSearch) ?>" placeholder="ชื่อ อีเมล หรือตำแหน่ง" class="w-full rounded-xl border border-slate-300 px-3 py-2.5 outline-none focus:border-red-500 focus:ring-4 focus:ring-red-100"></label>
        <label><span class="mb-1 block text-xs font-bold text-slate-500">สถานะบัญชี</span><select name="status" class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5"><option value="">ทุกสถานะ</option><option value="active" <?= $teacherStatus === 'active' ? 'selected' : '' ?>>เปิดใช้งาน</option><option value="inactive" <?= $teacherStatus === 'inactive' ? 'selected' : '' ?>>ปิดใช้งาน</option></select></label>
        <button class="self-end rounded-xl bg-slate-900 px-5 py-2.5 font-semibold text-white transition hover:bg-slate-700"><i class="fa-solid fa-magnifying-glass mr-2"></i>ค้นหา</button></div>
    </form>

    <div id="staff-teacher-results" data-jquery-search-result data-search-form="#staff-teacher-search">
    <section id="teacher-list" class="mt-5 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 bg-slate-50 px-5 py-4"><div><h2 class="font-bold text-slate-900">รายชื่ออาจารย์</h2><p class="mt-0.5 text-sm text-slate-500">เลือกอาจารย์ที่ต้องการ แล้วกดปุ่มแก้ไขเพื่อปรับข้อมูลในหน้าต่าง</p></div><span class="rounded-full border border-slate-200 bg-white px-3 py-1.5 text-sm font-bold text-slate-600 shadow-sm"><?= $teacherTotal ?> คน</span></div>
        <div class="overflow-x-auto">
	        <table class="w-full min-w-[1220px] border-separate border-spacing-0 text-sm">
	            <thead class="sticky top-0 z-10 bg-slate-50 text-slate-600"><tr><th scope="col" class="border-b border-slate-200 p-3 text-left font-bold">คำนำหน้า</th><th scope="col" class="border-b border-slate-200 p-3 text-left font-bold">ชื่อ-นามสกุล</th><th scope="col" class="border-b border-slate-200 p-3 text-left font-bold">ตำแหน่ง</th><th scope="col" class="border-b border-slate-200 p-3 text-left font-bold">อีเมล</th><th scope="col" class="border-b border-slate-200 p-3 text-left font-bold">เบอร์โทรศัพท์</th><th scope="col" class="border-b border-slate-200 p-3 text-center font-bold">การเข้าใช้</th><th scope="col" class="border-b border-slate-200 p-3 text-center font-bold">สถานะ</th><th scope="col" class="w-64 min-w-64 border-b border-slate-200 p-3 text-center font-bold">จัดการ</th></tr></thead>
            <tbody>
            <?php foreach ($teachers as $teacher): ?>
                <tr class="border-t align-top">
                        <td class="p-3 font-semibold text-slate-700"><?php foreach ($prefixes as $prefix) { if ((int) $teacher['prefix_id'] === (int) $prefix['prefix_id']) { echo htmlspecialchars($prefix['prefix_short']); break; } } ?></td>
                        <td class="p-3 font-semibold text-slate-900"><?= htmlspecialchars($teacher['firstname'] . ' ' . $teacher['lastname']) ?></td>
                        <td class="p-3 text-slate-700"><?= htmlspecialchars($teacher['position_name'] ?? '-') ?></td>
	                        <td class="p-3 text-slate-700"><?= htmlspecialchars($teacher['email']) ?></td>
	                        <td class="p-3 text-slate-700"><?= htmlspecialchars($teacher['phone'] ?: '-') ?></td>
                        <td class="p-3 text-center"><?= (int) $teacher['first_login'] === 1 ? '<span class="text-amber-700">รอตั้งรหัสผ่าน</span>' : '<span class="text-green-700">ตั้งแล้ว</span>' ?></td>
                        <td class="p-3 text-center"><span class="status-badge <?= $teacher['status'] === 'active' ? 'status-badge--active' : 'status-badge--inactive' ?>"><?= $teacher['status'] === 'active' ? 'เปิดใช้งาน' : 'ปิดใช้งาน' ?></span></td>
                        <td class="w-32 min-w-32 p-3">
                            <div class="flex items-center justify-center gap-2">
                                <button type="button" data-open-teacher-view data-id="<?= (int) $teacher['employee_id'] ?>" data-code="<?= htmlspecialchars($teacher['employee_code'], ENT_QUOTES, 'UTF-8') ?>" data-firstname="<?= htmlspecialchars($teacher['firstname'], ENT_QUOTES, 'UTF-8') ?>" data-lastname="<?= htmlspecialchars($teacher['lastname'], ENT_QUOTES, 'UTF-8') ?>" data-email="<?= htmlspecialchars($teacher['email'], ENT_QUOTES, 'UTF-8') ?>" data-phone="<?= htmlspecialchars($teacher['phone'] ?? '', ENT_QUOTES, 'UTF-8') ?>" data-position="<?= htmlspecialchars($teacher['position_name'] ?? '-', ENT_QUOTES, 'UTF-8') ?>" data-status="<?= $teacher['status'] === 'active' ? 'เปิดใช้งานบัญชี' : 'ปิดใช้งานบัญชี' ?>" data-group-count="<?= (int) $teacher['advisor_group_count'] ?>" data-group-ids="<?= htmlspecialchars(implode(',', array_column(array_filter($advisorGroups, static fn(array $group): bool => (int) $group['advisor_employee_id'] === (int) $teacher['employee_id']), 'group_id')), ENT_QUOTES, 'UTF-8') ?>" class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-slate-100 text-slate-700 transition hover:bg-slate-800 hover:text-white" title="ดูรายละเอียดและกำหนดกลุ่มเรียน" aria-label="ดูรายละเอียดและกำหนดกลุ่มเรียนของ <?= htmlspecialchars($teacher['firstname'].' '.$teacher['lastname']) ?>"><i class="fa-solid fa-eye" aria-hidden="true"></i></button>
                                <button type="button" data-open-teacher-edit data-id="<?= (int) $teacher['employee_id'] ?>" data-prefix-id="<?= (int) $teacher['prefix_id'] ?>" data-position-id="<?= (int) $teacher['position_id'] ?>" data-firstname="<?= htmlspecialchars($teacher['firstname'], ENT_QUOTES, 'UTF-8') ?>" data-lastname="<?= htmlspecialchars($teacher['lastname'], ENT_QUOTES, 'UTF-8') ?>" data-email="<?= htmlspecialchars($teacher['email'], ENT_QUOTES, 'UTF-8') ?>" data-phone="<?= htmlspecialchars($teacher['phone'] ?? '', ENT_QUOTES, 'UTF-8') ?>" class="inline-flex h-10 items-center gap-2 rounded-xl bg-blue-50 px-3 text-blue-700 transition hover:bg-blue-600 hover:text-white" title="แก้ไขข้อมูลอาจารย์"><i class="fa-solid fa-pen" aria-hidden="true"></i><span>แก้ไข</span></button>
                            </div>
                        </td>
                </tr>
            <?php endforeach; ?>
	            <?php if (!$teachers): ?><tr><td colspan="8" class="p-10 text-center text-gray-500">ยังไม่มีข้อมูลอาจารย์ในสาขา</td></tr><?php endif; ?>
            </tbody>
        </table>
        </div>
    </section>
    <?php if ($teacherTotal > 0): ?><div class="mt-4 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm"><?php render_pagination($teacherTotal, $teacherPage, $teachersPerPage, 'page', 'teacher-list'); ?></div><?php endif; ?>
    </div>
</main>
</div>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="/Project/assets/js/form-confirm.js" defer></script>
<script>
(() => {
    const modal = document.getElementById('teacher-import-modal');
    if (!modal) return;
    const modeButtons = [...modal.querySelectorAll('[data-teacher-add-mode]')];
    const panels = [...modal.querySelectorAll('[data-teacher-add-panel]')];
    const setMode = (mode) => {
        modeButtons.forEach((button) => {
            const active = button.dataset.teacherAddMode === mode;
            button.classList.toggle('bg-white', active);
            button.classList.toggle('text-emerald-700', active);
            button.classList.toggle('shadow-sm', active);
            button.classList.toggle('text-slate-500', !active);
            button.setAttribute('aria-selected', active ? 'true' : 'false');
        });
        panels.forEach((panel) => panel.classList.toggle('hidden', panel.dataset.teacherAddPanel !== mode));
    };
    const open = () => {
        modal.classList.remove('hidden');
        document.body.classList.add('overflow-hidden');
        setMode(modal.dataset.initialMode || 'manual');
        modal.querySelector('[data-teacher-add-panel]:not(.hidden) input, [data-teacher-add-panel]:not(.hidden) select')?.focus();
    };
    const close = () => {
        modal.classList.add('hidden');
        document.body.classList.remove('overflow-hidden');
    };
    document.querySelectorAll('[data-navbar-panel="teacher-import-modal"]').forEach((link) => {
        link.addEventListener('click', (event) => { event.preventDefault(); open(); });
    });
    modeButtons.forEach((button) => button.addEventListener('click', () => setMode(button.dataset.teacherAddMode)));
    modal.querySelectorAll('[data-close-teacher-import]').forEach((button) => button.addEventListener('click', close));
    modal.addEventListener('click', (event) => { if (event.target === modal) close(); });
    document.addEventListener('keydown', (event) => { if (event.key === 'Escape' && !modal.classList.contains('hidden')) close(); });
    if (!modal.classList.contains('hidden')) {
        setMode(modal.dataset.initialMode || 'manual');
        document.body.classList.add('overflow-hidden');
    }
})();
</script>
<script>
(() => {
    const modal = document.getElementById('teacher-view-modal');
    if (!modal) return;
    const fields = ['name','position','email','phone','status','groupCount'];
    const close = () => { modal.classList.add('hidden'); document.body.classList.remove('overflow-hidden'); };
    document.querySelectorAll('[data-open-teacher-view]').forEach((button) => button.addEventListener('click', () => {
        fields.forEach((field) => { const element = modal.querySelector(`[data-teacher-view-${field.replace(/[A-Z]/g, (char) => '-' + char.toLowerCase())}]`); if (element) element.textContent = button.dataset[field] || '-'; });
        modal.querySelector('[data-teacher-view-id]').value = button.dataset.id;
        const selectedGroups = new Set((button.dataset.groupIds || '').split(',').filter(Boolean));
        modal.querySelectorAll('[data-teacher-group-card]').forEach((card) => {
            const checked = selectedGroups.has(card.dataset.groupId);
            const checkbox = card.querySelector('input[type="checkbox"]');
            checkbox.checked = checked;
            card.classList.toggle('border-red-300', checked);
            card.classList.toggle('bg-red-50', checked);
        });
        modal.classList.remove('hidden'); document.body.classList.add('overflow-hidden');
        modal.querySelector('[data-close-teacher-view]')?.focus();
    }));
    modal.querySelectorAll('[data-close-teacher-view]').forEach((button) => button.addEventListener('click', close));
    modal.addEventListener('click', (event) => { if (event.target === modal) close(); });
    document.addEventListener('keydown', (event) => { if (event.key === 'Escape' && !modal.classList.contains('hidden')) close(); });
})();
</script>
<script>
(() => {
    const modal = document.getElementById('teacher-edit-modal');
    if (!modal) return;
    const close = () => { modal.classList.add('hidden'); document.body.classList.remove('overflow-hidden'); };
    document.querySelectorAll('[data-open-teacher-edit]').forEach((button) => button.addEventListener('click', () => {
        modal.querySelector('[data-teacher-edit-id]').value = button.dataset.id;
        modal.querySelector('[data-teacher-edit-prefix]').value = button.dataset.prefixId;
        modal.querySelector('[data-teacher-edit-position]').value = button.dataset.positionId;
        modal.querySelector('[data-teacher-edit-firstname]').value = button.dataset.firstname;
        modal.querySelector('[data-teacher-edit-lastname]').value = button.dataset.lastname;
        modal.querySelector('[data-teacher-edit-email]').value = button.dataset.email;
        modal.querySelector('[data-teacher-edit-phone]').value = button.dataset.phone;
        modal.querySelector('[data-teacher-edit-name]').textContent = `${button.dataset.firstname} ${button.dataset.lastname}`;
        modal.classList.remove('hidden'); document.body.classList.add('overflow-hidden');
        modal.querySelector('[data-teacher-edit-firstname]')?.focus();
    }));
    modal.querySelectorAll('[data-close-teacher-edit]').forEach((button) => button.addEventListener('click', close));
    modal.addEventListener('click', (event) => { if (event.target === modal) close(); });
    document.addEventListener('keydown', (event) => { if (event.key === 'Escape' && !modal.classList.contains('hidden')) close(); });
})();
</script>
<?php if ($importResult !== null): ?>
<script>
(() => {
    const result = <?= json_encode($importResult, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    const escapeHtml = (value) => String(value).replace(/[&<>'"]/g, (character) => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#039;', '"': '&quot;'
    }[character]));
    const section = (title, rows, color) => rows.length ? `
        <div class="mt-3 rounded-lg border border-${color}-200 bg-${color}-50 p-3 text-left">
            <p class="font-bold text-${color}-800">${title} (${rows.length} รายการ)</p>
            <ul class="mt-1 max-h-36 list-disc overflow-y-auto pl-5 text-sm text-${color}-800">${rows.map((row) => `<li>${escapeHtml(row)}</li>`).join('')}</ul>
        </div>` : '';
    const totalAdded = result.imported.length;
    Swal.fire({
        icon: totalAdded > 0 ? (result.existing.length || result.invalid.length ? 'warning' : 'success') : 'info',
        title: 'ผลการนำเข้ารายชื่ออาจารย์',
        html: `<p>เพิ่มข้อมูลใหม่ได้ <b>${totalAdded}</b> รายการ</p>`
            + section('เพิ่มเข้าระบบแล้ว', result.imported, 'green')
            + section('มีข้อมูลอยู่แล้ว (ไม่นำเข้าซ้ำ)', result.existing, 'amber')
            + section('ข้อมูลไม่ถูกต้อง', result.invalid, 'red'),
        confirmButtonText: 'ตกลง',
        confirmButtonColor: '#dc2626',
        width: 720,
    });
})();
</script>
<?php endif; ?>
</body>
</html>
