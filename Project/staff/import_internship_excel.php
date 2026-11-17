<?php
declare(strict_types=1);

session_start();
require_once __DIR__ . '/../middleware/staff.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/csrf_helper.php';
require_once __DIR__ . '/../helpers/official_send_eligibility_helper.php';
require_once __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

function h(mixed $value): string { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); }
function importHeader(string $value): string { return mb_strtolower(preg_replace('/[\s_\-]+/u', '', trim($value)), 'UTF-8'); }
function importText(mixed $value): string { return trim(preg_replace('/\s+/u', ' ', (string) $value)); }
function importError(string $message): never { throw new RuntimeException($message); }

$staffStmt = $pdo->prepare('SELECT employee_id, department_id FROM employee WHERE user_id=? AND employee_type IN ("staff","teacher") LIMIT 1');
$staffStmt->execute([(int) $_SESSION['user_id']]);
$staff = $staffStmt->fetch(PDO::FETCH_ASSOC);
if (!$staff) { http_response_code(403); renderPlainErrorAlert('ไม่มีสิทธิ์นำเข้าข้อมูลฝึกงาน'); }
$departmentId = (int) $staff['department_id'];

if (($_GET['action'] ?? '') === 'download_template') {
    $book = new Spreadsheet();
    $sheet = $book->getActiveSheet();
    $sheet->setTitle('นำเข้าฝึกงาน');
    $headers = ['รหัสนักศึกษา', 'คำนำหน้า', 'ชื่อ', 'นามสกุล', 'เบอร์นักศึกษา', 'ชื่อสถานประกอบการ', 'ชื่อผู้รับหนังสือ', 'ตำแหน่งผู้รับหนังสือ', 'ชื่อผู้ติดต่อ', 'ตำแหน่งผู้ติดต่อ', 'เบอร์บริษัท', 'ที่อยู่บริษัท', 'ตำแหน่งฝึก', 'รายละเอียดงาน'];
    $sheet->fromArray($headers, null, 'A1');
    $sheet->freezePane('A2');
    $sheet->getStyle('A1:N1')->getFont()->setBold(true);
    $sheet->getStyle('A1:N1')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setRGB('E2F0D9');
    foreach (range('A', 'N') as $column) { $sheet->getColumnDimension($column)->setWidth(22); }
    $sheet->setCellValue('A2', '67172310000-0');
    $sheet->setCellValue('B2', 'นาย');
    $sheet->setCellValue('C2', 'ตัวอย่าง');
    $sheet->setCellValue('D2', 'นักศึกษา');
    $sheet->setCellValue('F2', 'บริษัทตัวอย่าง จำกัด');
    $sheet->setCellValue('G2', 'นายตัวอย่าง ผู้รับหนังสือ');
    $sheet->setCellValue('H2', 'ผู้จัดการ');
    $sheet->getComment('A1')->getText()->createTextRun('จำเป็น');
    $sheet->getComment('C1')->getText()->createTextRun('จำเป็น');
    $sheet->getComment('D1')->getText()->createTextRun('จำเป็น');
    $sheet->getComment('F1')->getText()->createTextRun('จำเป็น');
    $sheet->getComment('G1')->getText()->createTextRun('จำเป็น เพื่อใช้คำว่า เรียน ในหนังสือ');
    $sheet->getComment('H1')->getText()->createTextRun('จำเป็น');
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="internship_direct_send_template.xlsx"');
    header('Cache-Control: max-age=0');
    (new Xlsx($book))->save('php://output');
    exit;
}

$groupsStmt = $pdo->prepare('SELECT group_id, group_name FROM group_class WHERE department_id=? ORDER BY group_name');
$groupsStmt->execute([$departmentId]);
$groups = $groupsStmt->fetchAll(PDO::FETCH_ASSOC);

function parseImportRows(string $file): array
{
    $book = IOFactory::load($file);
    $sheet = $book->getActiveSheet();
    $allRows = $sheet->toArray(null, true, true, false);
    $book->disconnectWorksheets();
    if (!$allRows || count($allRows) < 2) importError('ไฟล์ Excel ไม่มีรายการนักศึกษา');
    $headers = array_shift($allRows);
    $aliases = [
        'student_number' => ['รหัสนักศึกษา', 'studentnumber'],
        'prefix' => ['คำนำหน้า', 'prefix'],
        'firstname' => ['ชื่อ', 'firstname', 'first_name'],
        'lastname' => ['นามสกุล', 'lastname', 'last_name'],
        'student_phone' => ['เบอร์นักศึกษา', 'เบอร์โทรนักศึกษา', 'studentphone', 'phone'],
        'company_name' => ['ชื่อสถานประกอบการ', 'ชื่อบริษัท', 'companyname'],
        'recipient_name' => ['ชื่อผู้รับหนังสือ', 'ผู้รับหนังสือ', 'recipientname'],
        'recipient_position' => ['ตำแหน่งผู้รับหนังสือ', 'ตำแหน่งผู้รับ', 'recipientposition'],
        'contact_name' => ['ชื่อผู้ติดต่อ', 'contactname'],
        'contact_position' => ['ตำแหน่งผู้ติดต่อ', 'contactposition'],
        'company_phone' => ['เบอร์บริษัท', 'โทรศัพท์บริษัท', 'companyphone'],
        'company_address' => ['ที่อยู่บริษัท', 'companyaddress'],
        'job_position' => ['ตำแหน่งฝึก', 'ตำแหน่งงาน', 'jobposition'],
        'job_description' => ['รายละเอียดงาน', 'jobdescription'],
    ];
    $index = [];
    foreach ($headers as $column => $header) {
        $normalized = importHeader((string) $header);
        foreach ($aliases as $field => $names) {
            if (in_array($normalized, array_map('importHeader', $names), true)) $index[$field] = $column;
        }
    }
    foreach (['student_number', 'firstname', 'lastname', 'company_name', 'recipient_name', 'recipient_position'] as $required) {
        if (!array_key_exists($required, $index)) importError('ไม่พบหัวคอลัมน์ที่จำเป็น: ' . $aliases[$required][0]);
    }
    $rows = [];
    $studentNumbers = [];
    foreach ($allRows as $offset => $row) {
        $rowNumber = $offset + 2;
        $get = static fn(string $field): string => array_key_exists($field, $index) ? importText($row[$index[$field]] ?? '') : '';
        $studentNumber = $get('student_number');
        if ($studentNumber === '') continue;
        if (mb_strlen($studentNumber) > 50 || !preg_match('/^[0-9A-Za-z\-]+$/', $studentNumber)) importError("แถว {$rowNumber}: รหัสนักศึกษาไม่ถูกต้อง");
        if (isset($studentNumbers[$studentNumber])) importError("แถว {$rowNumber}: รหัสนักศึกษาซ้ำในไฟล์");
        $studentNumbers[$studentNumber] = true;
        $record = ['row_number' => $rowNumber];
        foreach (array_keys($aliases) as $field) $record[$field] = $get($field);
        foreach (['firstname', 'lastname', 'company_name', 'recipient_name', 'recipient_position'] as $required) {
            if ($record[$required] === '') importError("แถว {$rowNumber}: {$aliases[$required][0]} ต้องไม่ว่าง");
        }
        if (mb_strlen($record['company_name']) > 255 || mb_strlen($record['recipient_name']) > 255 || mb_strlen($record['recipient_position']) > 180) importError("แถว {$rowNumber}: ข้อมูลบริษัทหรือผู้รับยาวเกินกำหนด");
        $rows[] = $record;
    }
    if (!$rows) importError('ไม่พบรายการนักศึกษาที่พร้อมนำเข้า');
    if (count($rows) > 200) importError('นำเข้าได้ครั้งละไม่เกิน 200 รายการ');
    return $rows;
}

function ensureImportUsername(PDO $pdo, string $studentNumber): string
{
    $base = 'import_' . preg_replace('/[^A-Za-z0-9]/', '', $studentNumber);
    $candidate = $base;
    $check = $pdo->prepare('SELECT COUNT(*) FROM users WHERE username=?');
    for ($attempt = 1; $attempt <= 100; $attempt++) {
        $check->execute([$candidate]);
        if (!(int) $check->fetchColumn()) return $candidate;
        $candidate = $base . '_' . $attempt;
    }
    throw new RuntimeException('ไม่สามารถสร้างชื่อผู้ใช้สำหรับรหัส ' . $studentNumber);
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        verify_csrf_or_abort();
        $mode = (string) ($_POST['mode'] ?? '');
        if ($mode === 'preview') {
            $file = $_FILES['excel_file'] ?? null;
            if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) importError('กรุณาเลือกไฟล์ Excel');
            if (($file['size'] ?? 0) > 10 * 1024 * 1024) importError('ไฟล์ต้องมีขนาดไม่เกิน 10 MB');
            if (mb_strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION)) !== 'xlsx') importError('รองรับเฉพาะไฟล์ .xlsx');
            $groupId = (int) ($_POST['group_id'] ?? 0);
            $validGroupIds = array_map('intval', array_column($groups, 'group_id'));
            if (!in_array($groupId, $validGroupIds, true)) importError('กรุณาเลือกกลุ่มเรียน');
            $academicYear = (int) ($_POST['academic_year'] ?? 0);
            $semester = (int) ($_POST['semester'] ?? 0);
            $startDate = (string) ($_POST['start_date'] ?? '');
            $endDate = (string) ($_POST['end_date'] ?? '');
            if ($academicYear < 2500 || $academicYear > 2700 || !in_array($semester, [1, 2, 3], true) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDate) || $startDate > $endDate) importError('กรุณาระบุปี ภาคเรียน และช่วงวันฝึกให้ถูกต้อง');
            $_SESSION['internship_excel_import_preview'] = [
                'rows' => parseImportRows((string) $file['tmp_name']),
                'source_filename' => basename((string) $file['name']),
                'source_sha256' => hash_file('sha256', (string) $file['tmp_name']),
                'group_id' => $groupId,
                'academic_year' => $academicYear,
                'semester' => $semester,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'created_at' => time(),
            ];
            header('Location: import_internship_excel.php?preview=1');
            exit;
        }
        if ($mode === 'confirm') {
            $preview = $_SESSION['internship_excel_import_preview'] ?? null;
            if (!is_array($preview) || time() - (int) ($preview['created_at'] ?? 0) > 1800) importError('ข้อมูลตัวอย่างหมดอายุ กรุณาอัปโหลดไฟล์ใหม่');
            $pdo->exec(file_get_contents(__DIR__ . '/../database/migrations/20260915_direct_send_import.sql'));
            $prefixMap = [];
            foreach ($pdo->query('SELECT prefix_id, prefix_short FROM prefixes') as $prefix) $prefixMap[importHeader((string) $prefix['prefix_short'])] = (int) $prefix['prefix_id'];
            $calendar = $pdo->prepare('SELECT calendar_id FROM internship_calendar WHERE academic_year=? AND semester=? AND start_date=? AND end_date=? LIMIT 1');
            $calendar->execute([(int) $preview['academic_year'], (int) $preview['semester'], $preview['start_date'], $preview['end_date']]);
            $calendarId = (int) $calendar->fetchColumn();
            $pdo->beginTransaction();
            if (!$calendarId) {
                $addCalendar = $pdo->prepare('INSERT INTO internship_calendar (academic_year,semester,start_date,end_date,status,created_by) VALUES (?,?,?,?,"active",?)');
                $addCalendar->execute([(int) $preview['academic_year'], (int) $preview['semester'], $preview['start_date'], $preview['end_date'], (int) $_SESSION['user_id']]);
                $calendarId = (int) $pdo->lastInsertId();
            }
            $findStudent = $pdo->prepare('SELECT student_id, firstname, lastname FROM student WHERE student_number=? LIMIT 1');
            $findActive = $pdo->prepare('SELECT internship_profile_id FROM internship_profile WHERE student_id=? AND calendar_id=? AND request_status="active" LIMIT 1');
            $addUser = $pdo->prepare('INSERT INTO users (username,email,password,first_login,status) VALUES (?,?,?,1,"inactive")');
            $addRole = $pdo->prepare('INSERT INTO user_roles (user_id,role) VALUES (?,"student")');
            $addStudent = $pdo->prepare('INSERT INTO student (user_id,student_number,prefix_id,firstname,lastname,group_id) VALUES (?,?,?,?,?,?)');
            $addProfile = $pdo->prepare('INSERT INTO internship_profile (student_id,calendar_id,student_phone,company_name,recipient_name,recipient_position,contact_name,contact_position,company_phone,company_address,job_position,job_description) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)');
            $addImport = $pdo->prepare('INSERT INTO internship_direct_send_import (internship_profile_id,source_filename,source_sha256,source_row,import_note) VALUES (?,?,?,?,?)');
            $added = 0;
            foreach ($preview['rows'] as $row) {
                $findStudent->execute([$row['student_number']]);
                $student = $findStudent->fetch(PDO::FETCH_ASSOC);
                if ($student) {
                    if ($student['firstname'] !== $row['firstname'] || $student['lastname'] !== $row['lastname']) importError('แถว ' . $row['row_number'] . ': ชื่อไม่ตรงกับรหัสนักศึกษาที่มีอยู่');
                    $studentId = (int) $student['student_id'];
                } else {
                    $username = ensureImportUsername($pdo, $row['student_number']);
                    $email = $username . '@example.invalid';
                    $addUser->execute([$username, $email, password_hash(bin2hex(random_bytes(24)), PASSWORD_DEFAULT)]);
                    $userId = (int) $pdo->lastInsertId();
                    $addRole->execute([$userId]);
                    $prefixId = $row['prefix'] === '' ? null : ($prefixMap[importHeader($row['prefix'])] ?? null);
                    if ($row['prefix'] !== '' && !$prefixId) importError('แถว ' . $row['row_number'] . ': ไม่พบคำนำหน้า “' . $row['prefix'] . '” ในระบบ');
                    $addStudent->execute([$userId, $row['student_number'], $prefixId, $row['firstname'], $row['lastname'], (int) $preview['group_id']]);
                    $studentId = (int) $pdo->lastInsertId();
                }
                $findActive->execute([$studentId, $calendarId]);
                if ($findActive->fetchColumn()) importError('แถว ' . $row['row_number'] . ': นักศึกษามีข้อมูลฝึกงานที่ยังใช้งานอยู่ในช่วงนี้แล้ว');
                $addProfile->execute([$studentId, $calendarId, $row['student_phone'] ?: null, $row['company_name'], $row['recipient_name'], $row['recipient_position'], $row['contact_name'] ?: null, $row['contact_position'] ?: null, $row['company_phone'] ?: null, $row['company_address'] ?: null, $row['job_position'] ?: null, $row['job_description'] ?: null]);
                $profileId = (int) $pdo->lastInsertId();
                $addImport->execute([$profileId, $preview['source_filename'], $preview['source_sha256'], (int) $row['row_number'], 'นำเข้าจากหน้าเว็บเพื่อออกใบส่งตัวโดยตรง']);
                $added++;
            }
            $pdo->commit();
            unset($_SESSION['internship_excel_import_preview']);
            header('Location: official_documents.php?type=send&imported=' . $added);
            exit;
        }
        importError('คำสั่งไม่ถูกต้อง');
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $error = $exception->getMessage();
    }
}

$preview = isset($_GET['preview']) && is_array($_SESSION['internship_excel_import_preview'] ?? null) ? $_SESSION['internship_excel_import_preview'] : null;
?>
<!doctype html><html lang="th"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>นำเข้าข้อมูลฝึกงานจาก Excel</title><script src="https://cdn.tailwindcss.com"></script><link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css"><link rel="stylesheet" href="/Project/assets/css/app-ui.css?v=20260918-1"></head>
<body class="bg-slate-100 text-slate-900"><?php include __DIR__ . '/sidebar.php'; ?><div class="min-h-screen lg:ml-72"><?php include __DIR__ . '/navbar.php'; ?><main class="mx-auto max-w-6xl p-4 sm:p-6 lg:p-8">
<section class="overflow-hidden rounded-2xl bg-white shadow-sm"><div class="bg-gradient-to-r from-emerald-700 to-emerald-600 px-6 py-7 text-white"><p class="text-sm font-bold text-emerald-100">นำเข้าข้อมูลสำหรับออกใบส่งตัวโดยตรง</p><h1 class="mt-1 text-2xl font-black">นำเข้าข้อมูลฝึกงานจาก Excel</h1><p class="mt-2 max-w-3xl text-sm text-emerald-50">ระบบตรวจสอบข้อมูลก่อนบันทึก และนำเฉพาะรายชื่อที่ผ่านการยืนยันไปแสดงในหน้าใบส่งตัว</p></div><div class="p-5 sm:p-6">
<?php if ($error): ?><div class="mb-5 rounded-xl border border-red-200 bg-red-50 p-4 text-red-800"><i class="fa-solid fa-triangle-exclamation mr-2"></i><?= h($error) ?></div><?php endif; ?>
<?php if (!$preview): ?><div class="mb-6 rounded-xl border border-blue-200 bg-blue-50 p-4 text-sm text-blue-900"><p class="font-bold">ขั้นตอน: 1. ดาวน์โหลดแม่แบบ 2. กรอกข้อมูล 3. อัปโหลดเพื่อตรวจสอบ 4. ยืนยันนำเข้า</p><p class="mt-1">ข้อมูลที่ต้องมี: รหัสนักศึกษา, ชื่อ, นามสกุล, บริษัท, ชื่อผู้รับหนังสือ และตำแหน่งผู้รับหนังสือ</p><a href="?action=download_template" class="mt-3 inline-flex rounded-lg bg-blue-700 px-4 py-2 font-bold text-white hover:bg-blue-800"><i class="fa-solid fa-download mr-2"></i>ดาวน์โหลดแม่แบบ Excel</a></div>
<form method="post" enctype="multipart/form-data" class="grid gap-5 md:grid-cols-2"><?= csrf_field() ?><input type="hidden" name="mode" value="preview"><label class="md:col-span-2 block text-sm font-bold">ไฟล์ Excel (.xlsx)<input required type="file" name="excel_file" accept=".xlsx" class="mt-2 block w-full rounded-xl border border-slate-300 bg-white px-3 py-3 font-normal"></label><label class="block text-sm font-bold">กลุ่มเรียนสำหรับนักศึกษาใหม่<select required name="group_id" class="mt-2 w-full rounded-xl border border-slate-300 bg-white px-3 py-3 font-normal"><option value="">เลือกกลุ่มเรียน</option><?php foreach ($groups as $group): ?><option value="<?= (int) $group['group_id'] ?>"><?= h($group['group_name']) ?></option><?php endforeach; ?></select></label><label class="block text-sm font-bold">ปีการศึกษา<input required name="academic_year" inputmode="numeric" value="<?= date('Y') + 543 ?>" class="mt-2 w-full rounded-xl border border-slate-300 px-3 py-3 font-normal"></label><label class="block text-sm font-bold">ภาคการศึกษา<select required name="semester" class="mt-2 w-full rounded-xl border border-slate-300 bg-white px-3 py-3 font-normal"><option value="1">1</option><option value="2">2</option><option value="3">3</option></select></label><label class="block text-sm font-bold">วันเริ่มฝึก<input required type="date" name="start_date" class="mt-2 w-full rounded-xl border border-slate-300 px-3 py-3 font-normal"></label><label class="block text-sm font-bold">วันสิ้นสุดฝึก<input required type="date" name="end_date" class="mt-2 w-full rounded-xl border border-slate-300 px-3 py-3 font-normal"></label><div class="md:col-span-2 flex justify-end border-t pt-5"><button class="rounded-xl bg-emerald-700 px-6 py-3 font-bold text-white hover:bg-emerald-800"><i class="fa-solid fa-magnifying-glass mr-2"></i>ตรวจสอบข้อมูล</button></div></form>
<?php else: ?><div class="mb-5 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-emerald-900"><div><strong>พบ <?= count($preview['rows']) ?> รายการ</strong><span class="ml-2 text-sm">ปี <?= (int) $preview['academic_year'] ?> ภาค <?= (int) $preview['semester'] ?> · <?= h($preview['start_date']) ?> ถึง <?= h($preview['end_date']) ?></span></div><a href="import_internship_excel.php" class="text-sm font-bold underline">เลือกไฟล์ใหม่</a></div><div class="overflow-x-auto rounded-xl border border-slate-200"><table class="min-w-full text-sm"><thead class="bg-slate-100 text-left text-slate-600"><tr><th class="px-4 py-3">แถว</th><th class="px-4 py-3">รหัสนักศึกษา</th><th class="px-4 py-3">ชื่อ-สกุล</th><th class="px-4 py-3">สถานประกอบการ</th><th class="px-4 py-3">เรียน</th></tr></thead><tbody class="divide-y divide-slate-100"><?php foreach ($preview['rows'] as $row): ?><tr><td class="px-4 py-3"><?= (int) $row['row_number'] ?></td><td class="px-4 py-3 font-semibold"><?= h($row['student_number']) ?></td><td class="px-4 py-3"><?= h(trim($row['prefix'] . ' ' . $row['firstname'] . ' ' . $row['lastname'])) ?></td><td class="px-4 py-3"><?= h($row['company_name']) ?></td><td class="px-4 py-3"><?= h($row['recipient_name'] . ' · ' . $row['recipient_position']) ?></td></tr><?php endforeach; ?></tbody></table></div><form method="post" class="mt-5 flex justify-end gap-3"><?= csrf_field() ?><input type="hidden" name="mode" value="confirm"><a href="import_internship_excel.php" class="rounded-xl border border-slate-300 px-5 py-3 font-bold text-slate-700">ยกเลิก</a><button class="rounded-xl bg-emerald-700 px-6 py-3 font-bold text-white hover:bg-emerald-800"><i class="fa-solid fa-file-import mr-2"></i>ยืนยันนำเข้า <?= count($preview['rows']) ?> รายการ</button></form><?php endif; ?>
</div></section></main></div></body></html>
