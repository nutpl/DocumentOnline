<?php
require_once __DIR__ . '/../../middleware/admin.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/csrf_helper.php';
require_once __DIR__ . '/../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

$employeeType = ($_GET['employee_type'] ?? '') === 'staff' ? 'staff' : 'teacher';
$entityLabel = $employeeType === 'staff' ? 'เจ้าหน้าที่' : 'อาจารย์';
$returnIndex = $employeeType === 'staff' ? '/Project/admin/staffs/index.php' : '/Project/admin/teachers/index.php';
$pageTitle = 'นำเข้าข้อมูล' . $entityLabel;
$departments = $pdo->query('SELECT department_id,department_name FROM department ORDER BY department_name')->fetchAll(PDO::FETCH_ASSOC);
$prefixes = $pdo->query("SELECT prefix_id,prefix_short FROM prefixes WHERE status='active' ORDER BY prefix_id")->fetchAll(PDO::FETCH_ASSOC);
$positions = $pdo->query('SELECT position_id,position_name FROM positions ORDER BY position_name')->fetchAll(PDO::FETCH_ASSOC);
$error = '';
$success = '';
$importResult = null;

if (($_GET['action'] ?? '') === 'download_template') {
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('รายชื่อ' . $entityLabel);
    $sheet->fromArray([['รหัสบุคลากร', 'คำนำหน้า', 'ชื่อ', 'นามสกุล', 'อีเมล', 'ตำแหน่ง', 'เบอร์โทรศัพท์']]);
    $sheet->getStyle('A1:G1')->getFont()->setBold(true);
    $sheet->getStyle('G:G')->getNumberFormat()->setFormatCode('@');
    foreach (range('A', 'G') as $column) {
        $sheet->getColumnDimension($column)->setAutoSize(true);
    }
    $filename = $employeeType === 'staff' ? 'staff_import_template.xlsx' : 'teacher_import_template.xlsx';
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: max-age=0');
    (new Xlsx($spreadsheet))->save('php://output');
    $spreadsheet->disconnectWorksheets();
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_or_abort();
    $departmentId = (int) ($_POST['department_id'] ?? 0);
    $validDepartment = $pdo->prepare('SELECT COUNT(*) FROM department WHERE department_id=?');
    $validDepartment->execute([$departmentId]);
    if ($departmentId < 1 || !(int) $validDepartment->fetchColumn()) {
        $error = 'กรุณาเลือกสาขาวิชาที่ต้องการนำเข้ารายชื่ออาจารย์';
    } elseif (!isset($_FILES['teacher_excel']) || (int) $_FILES['teacher_excel']['error'] !== UPLOAD_ERR_OK) {
        $error = 'กรุณาเลือกไฟล์ Excel ที่ต้องการนำเข้า';
    } else {
        try {
            $upload = $_FILES['teacher_excel'];
            $extension = strtolower(pathinfo((string) $upload['name'], PATHINFO_EXTENSION));
            if (!in_array($extension, ['xlsx', 'xls'], true)) throw new RuntimeException('รองรับเฉพาะไฟล์ .xlsx และ .xls เท่านั้น');
            if ((int) $upload['size'] > 5 * 1024 * 1024) throw new RuntimeException('ไฟล์ Excel ต้องมีขนาดไม่เกิน 5 MB');

            $spreadsheet = IOFactory::load($upload['tmp_name']);
            $sheet = $spreadsheet->getActiveSheet();
            $rows = $sheet->rangeToArray('A1:' . $sheet->getHighestDataColumn() . $sheet->getHighestDataRow(), null, true, true, false);
            if (!$rows) throw new RuntimeException('ไม่พบข้อมูลในไฟล์');

            $normalize = static function ($value): string {
                $value = preg_replace('/^\xEF\xBB\xBF/', '', trim((string) $value));
                return mb_strtolower(preg_replace('/\s+/u', '', $value), 'UTF-8');
            };
            $aliases = [
                'employee_code' => ['รหัสบุคลากร', 'รหัสอาจารย์', 'employeecode'],
                'prefix' => ['คำนำหน้า', 'prefix'],
                'firstname' => ['ชื่อ', 'firstname'],
                'lastname' => ['นามสกุล', 'lastname'],
                'email' => ['อีเมล', 'email'],
                'position' => ['ตำแหน่ง', 'position'],
                'phone' => ['เบอร์โทรศัพท์', 'โทรศัพท์', 'phone'],
            ];
            $templateHeaders = ['รหัสบุคลากร', 'คำนำหน้า', 'ชื่อ', 'นามสกุล', 'อีเมล', 'ตำแหน่ง', 'เบอร์โทรศัพท์'];
            $uploadedHeaders = array_map($normalize, array_slice($rows[0], 0, count($templateHeaders)));
            if ($uploadedHeaders !== array_map($normalize, $templateHeaders)) {
                throw new RuntimeException('หัวคอลัมน์ไม่ตรงกับไฟล์แม่แบบ กรุณาดาวน์โหลดไฟล์แม่แบบและกรอกข้อมูลลงในไฟล์เดิม');
            }
            $columns = [];
            foreach ($rows[0] as $index => $heading) {
                foreach ($aliases as $field => $names) {
                    if (in_array($normalize($heading), array_map($normalize, $names), true)) $columns[$field] = $index;
                }
            }
            if (array_diff(array_keys($aliases), array_keys($columns))) {
                throw new RuntimeException('หัวตารางไม่ครบ: รหัสบุคลากร | คำนำหน้า | ชื่อ | นามสกุล | อีเมล | ตำแหน่ง | เบอร์โทรศัพท์');
            }

            $prefixMap = [];
            foreach ($prefixes as $prefix) $prefixMap[$normalize($prefix['prefix_short'])] = (int) $prefix['prefix_id'];
            $positionMap = [];
            foreach ($positions as $position) $positionMap[$normalize($position['position_name'])] = (int) $position['position_id'];

            $duplicateStmt = $pdo->prepare('SELECT COUNT(*) FROM users u LEFT JOIN employee e ON e.user_id=u.user_id WHERE u.email=? OR u.username=? OR e.employee_code=?');
            $insertUser = $pdo->prepare("INSERT INTO users(username,email,password,first_login,status) VALUES (?,?,'pending-password',1,'active')");
            $insertRole = $pdo->prepare('INSERT INTO user_roles(user_id,role) VALUES (?,?)');
            $insertEmployee = $pdo->prepare('INSERT INTO employee(user_id,employee_code,prefix_id,firstname,lastname,phone,department_id,employee_type) VALUES (?,?,?,?,?,?,?,?)');
            $createPositionMaster = $pdo->prepare('INSERT IGNORE INTO positions(position_name) VALUES (?)');
            $findPositionMaster = $pdo->prepare('SELECT position_id FROM positions WHERE position_name=? LIMIT 1');
            $insertPosition = $pdo->prepare('INSERT INTO employee_position(employee_id,position_id,is_primary) VALUES (?,?,1)');
            $imported = 0; $importedRows = []; $existingRows = []; $invalidRows = []; $seenCodes = []; $seenEmails = [];

            foreach (array_slice($rows, 1) as $offset => $row) {
                $excelRow = $offset + 2;
                $values = [];
                foreach ($columns as $field => $index) $values[$field] = trim((string) ($row[$index] ?? ''));
                if (!array_filter($values, static fn($value): bool => $value !== '')) continue;
                try {
                    $code = $values['employee_code'];
                    $email = mb_strtolower($values['email'], 'UTF-8');
                    $prefixId = $prefixMap[$normalize($values['prefix'])] ?? 0;
                    $positionName = trim($values['position']);
                    $positionKey = $normalize($positionName);
                    $positionId = $positionMap[$positionKey] ?? 0;
                    if ($code === '' || $values['firstname'] === '' || $values['lastname'] === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) throw new RuntimeException('ข้อมูลไม่ครบหรืออีเมลไม่ถูกต้อง');
                    if (!$prefixId) throw new RuntimeException('ไม่พบคำนำหน้าในข้อมูลพื้นฐานของระบบ');
                    if ($positionName === '') throw new RuntimeException('กรุณาระบุตำแหน่ง');
                    if (mb_strlen($values['phone']) > 30) throw new RuntimeException('เบอร์โทรศัพท์ยาวเกิน 30 ตัวอักษร');
                    $codeKey = mb_strtolower($code, 'UTF-8');
                    $personLabel = trim($values['prefix'] . $values['firstname'] . ' ' . $values['lastname']) . ' (' . $code . ')';
                    if (isset($seenCodes[$codeKey]) || isset($seenEmails[$email])) {
                        $existingRows[] = 'แถว ' . $excelRow . ': ' . $personLabel . ' — ซ้ำภายในไฟล์';
                        continue;
                    }
                    $duplicateStmt->execute([$email, $code, $code]);
                    if ((int) $duplicateStmt->fetchColumn()) {
                        $existingRows[] = 'แถว ' . $excelRow . ': ' . $personLabel . ' — มีข้อมูลอยู่ในระบบแล้ว';
                        continue;
                    }
                    $pdo->beginTransaction();
                    if (!$positionId) {
                        $createPositionMaster->execute([$positionName]);
                        $findPositionMaster->execute([$positionName]);
                        $positionId = (int) $findPositionMaster->fetchColumn();
                        if (!$positionId) throw new RuntimeException('ไม่สามารถเพิ่มตำแหน่ง “' . $positionName . '” ได้');
                        $positionMap[$positionKey] = $positionId;
                    }
                    $insertUser->execute([$code, $email]); $userId = (int) $pdo->lastInsertId();
                    $insertRole->execute([$userId, $employeeType]);
                    $insertEmployee->execute([$userId, $code, $prefixId, $values['firstname'], $values['lastname'], $values['phone'] !== '' ? $values['phone'] : null, $departmentId, $employeeType]); $employeeId = (int) $pdo->lastInsertId();
                    $insertPosition->execute([$employeeId, $positionId]);
                    $pdo->commit();
                    $seenCodes[$codeKey] = true; $seenEmails[$email] = true; $imported++;
                    $importedRows[] = 'แถว ' . $excelRow . ': ' . $personLabel;
                } catch (Throwable $rowError) {
                    if ($pdo->inTransaction()) $pdo->rollBack();
                    $invalidRows[] = 'แถว ' . $excelRow . ': ' . $rowError->getMessage();
                }
            }
            $spreadsheet->disconnectWorksheets();
            if ($imported === 0 && !$existingRows && !$invalidRows) throw new RuntimeException('ไม่พบรายการข้อมูลสำหรับนำเข้า');
            if ($imported > 0) $success = 'นำเข้า' . $entityLabel . 'ใหม่สำเร็จ ' . $imported . ' รายการ';
            $importResult = ['imported' => $importedRows, 'existing' => $existingRows, 'invalid' => $invalidRows];
        } catch (Throwable $exception) {
            if (isset($spreadsheet)) $spreadsheet->disconnectWorksheets();
            $error = $exception->getMessage();
        }
    }
}

include __DIR__ . '/../layouts/admin_header.php';
include __DIR__ . '/../layouts/admin_sidebar.php';
include __DIR__ . '/../layouts/admin_navbar.php';
?>
<link rel="stylesheet" href="/Project/assets/css/management-hero.css">
<div class="w-full p-0">
    <a href="<?= htmlspecialchars($returnIndex) ?>" class="inline-flex items-center gap-2 text-sm font-bold text-slate-600 hover:text-red-700"><i class="fa-solid fa-arrow-left"></i>กลับหน้าจัดการ<?= htmlspecialchars($entityLabel) ?></a>
    <section class="mt-4 overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-200">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 px-5 py-4"><div><h2 class="text-xl font-black text-slate-900">นำเข้ารายชื่อ<?= htmlspecialchars($entityLabel) ?>จาก Excel</h2><p class="mt-1 text-sm text-slate-500">เลือกสาขา แล้วอัปโหลดไฟล์ที่กรอกจากแม่แบบ</p></div><i class="fa-solid fa-file-excel text-2xl text-emerald-600"></i></div>
        <div class="p-5">
            <?php if ($error): ?><div class="mb-5 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800"><?= htmlspecialchars($error) ?></div><?php endif; ?>
            <?php if ($success): ?><div hidden data-success-alert data-success-title="ดำเนินการสำเร็จ" data-success-text="<?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?>"></div><?php endif; ?>
            <form method="post" enctype="multipart/form-data" class="space-y-4">
                <?= csrf_field() ?>
                <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-950"><div><span class="font-bold">1. ดาวน์โหลดแม่แบบ</span><span class="mx-1 text-blue-300">→</span><span class="font-bold">2. กรอกข้อมูล</span><span class="mx-1 text-blue-300">→</span><span class="font-bold">3. อัปโหลดไฟล์เดิม</span><p class="mt-1 text-xs text-blue-700">หัวคอลัมน์: รหัสบุคลากร | คำนำหน้า | ชื่อ | นามสกุล | อีเมล | ตำแหน่ง | เบอร์โทรศัพท์</p></div><a href="?employee_type=<?= htmlspecialchars($employeeType) ?>&amp;action=download_template" class="inline-flex shrink-0 items-center gap-2 rounded-lg bg-emerald-600 px-4 py-2 font-bold text-white transition hover:bg-emerald-700"><i class="fa-solid fa-download"></i>ดาวน์โหลดแม่แบบ</a></div>
                <div class="grid gap-4 lg:grid-cols-[minmax(220px,1fr)_minmax(300px,1.4fr)_auto] lg:items-end"><label class="block text-sm font-bold text-slate-700">สาขาวิชา<select name="department_id" required class="mt-2 w-full rounded-xl border border-slate-300 bg-white px-4 py-3"><option value="">เลือกสาขาวิชา</option><?php foreach ($departments as $department): ?><option value="<?= (int) $department['department_id'] ?>" <?= (int) ($departmentId ?? 0) === (int) $department['department_id'] ? 'selected' : '' ?>><?= htmlspecialchars($department['department_name']) ?></option><?php endforeach; ?></select></label><label class="block text-sm font-bold text-slate-700">ไฟล์ Excel<input type="file" name="teacher_excel" accept=".xlsx,.xls,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/vnd.ms-excel" required class="mt-2 block w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm file:mr-4 file:rounded-lg file:border-0 file:bg-emerald-50 file:px-4 file:py-2 file:font-bold file:text-emerald-700"></label><button class="inline-flex h-12 items-center justify-center gap-2 rounded-xl bg-emerald-600 px-5 font-bold text-white hover:bg-emerald-700"><i class="fa-solid fa-file-import"></i>นำเข้ารายชื่อ</button></div>
            </form>
        </div>
    </section>
</div>
<script src="/Project/assets/js/success-alert.js" defer></script>
<?php if ($importResult !== null): ?>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const result = <?= json_encode($importResult, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    const escapeHtml = (value) => String(value).replace(/[&<>'"]/g, (character) => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#039;', '"': '&quot;'
    }[character]));
    const section = (title, rows, color) => rows.length ? `<div class="mt-3 rounded-lg border border-${color}-200 bg-${color}-50 p-3 text-left"><p class="font-bold text-${color}-800">${title} (${rows.length} รายการ)</p><ul class="mt-1 max-h-36 list-disc overflow-y-auto pl-5 text-sm text-${color}-800">${rows.map((row) => `<li>${escapeHtml(row)}</li>`).join('')}</ul></div>` : '';
    Swal.fire({
        icon: result.imported.length ? (result.existing.length || result.invalid.length ? 'warning' : 'success') : 'info',
        title: 'ผลการนำเข้ารายชื่อ<?= htmlspecialchars($entityLabel) ?>',
        html: `<p>เพิ่มข้อมูลใหม่ได้ <b>${result.imported.length}</b> รายการ</p>` + section('เพิ่มเข้าระบบแล้ว', result.imported, 'green') + section('มีข้อมูลอยู่แล้ว (ไม่นำเข้าซ้ำ)', result.existing, 'amber') + section('ข้อมูลไม่ถูกต้อง', result.invalid, 'red'),
        confirmButtonText: 'ตกลง', confirmButtonColor: '#dc2626', width: 720,
    });
});
</script>
<?php endif; ?>
<?php include __DIR__ . '/../layouts/admin_footer.php'; ?>
