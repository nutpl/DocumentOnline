<?php

session_start();
require_once '../middleware/staff.php';
require_once '../config/database.php';
require_once '../vendor/autoload.php';
require_once '../helpers/pdf_font_helper.php';

$staffStmt = $pdo->prepare("SELECT department_id FROM employee WHERE user_id=? AND employee_type IN ('staff','teacher') LIMIT 1");
$staffStmt->execute([$_SESSION['user_id']]);
$departmentId = (int) $staffStmt->fetchColumn();
if (!$departmentId) {
    http_response_code(403);
    renderPlainErrorAlert('ไม่มีสิทธิ์ส่งออกข้อมูล');
}

$requestedFormat = $_GET['format'] ?? 'excel';
$format = in_array($requestedFormat, ['pdf', 'word', 'excel'], true) ? $requestedFormat : 'excel';
$keyword = trim($_GET['search'] ?? '');
$conditions = ['g.department_id=?', 'd.document_status="submitted"', 'dt.document_code IN ("INTERNSHIP_REQUEST","INTERNSHIP_SEND")'];
$params = [$departmentId];
if ($keyword !== '') {
    $conditions[] = '(s.student_number LIKE ? OR s.firstname LIKE ? OR s.lastname LIKE ? OR dt.document_name LIKE ?)';
    $term = '%' . $keyword . '%';
    array_push($params, $term, $term, $term, $term);
}
$stmt = $pdo->prepare('SELECT s.student_number,s.firstname,s.lastname,dt.document_code,dt.document_name,d.submitted_at
    FROM documents d JOIN student s ON s.student_id=d.student_id JOIN group_class g ON g.group_id=s.group_id
    JOIN document_template t ON t.template_id=d.template_id JOIN document_type dt ON dt.document_type_id=t.document_type_id
    WHERE ' . implode(' AND ', $conditions) . ' ORDER BY d.submitted_at DESC');
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

if ($format === 'pdf') {
    $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
    $pdf->SetCreator('RMUTI Document Management System');
    $pdf->SetTitle('รายการเอกสารสหกิจ');
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    $pdf->SetMargins(12, 12, 12);
    $pdf->AddPage();
    $pdf->SetFont(thSarabunPskFont(), '', 10);

    $body = '';
    foreach ($rows as $index => $row) {
        $body .= '<tr>'
            . '<td width="7%" align="center">' . ($index + 1) . '</td>'
            . '<td width="18%">' . htmlspecialchars($row['student_number']) . '</td>'
            . '<td width="25%">' . htmlspecialchars($row['firstname'] . ' ' . $row['lastname']) . '</td>'
            . '<td width="17%">' . htmlspecialchars($row['document_code']) . '</td>'
            . '<td width="33%">' . htmlspecialchars($row['document_name']) . '<br>' . htmlspecialchars($row['submitted_at']) . '</td>'
            . '</tr>';
    }
    if ($body === '') {
        $body = '<tr><td colspan="5" align="center">ไม่พบเอกสารสหกิจที่ส่งแล้ว</td></tr>';
    }
    $html = '<h2 align="center">รายการเอกสารสหกิจของนักศึกษา</h2>'
        . '<table border="1" cellpadding="5"><thead><tr style="background-color:#e5e7eb;font-weight:bold">'
        . '<th width="7%">ลำดับ</th><th width="18%">รหัสนักศึกษา</th><th width="25%">ชื่อ-นามสกุล</th><th width="17%">รหัสเอกสาร</th><th width="33%">เอกสาร / วันที่ส่ง</th>'
        . '</tr></thead><tbody>' . $body . '</tbody></table>';
    $pdf->writeHTML($html, true, false, true, false, '');
    $pdf->Output('internship-documents-' . date('Ymd-His') . '.pdf', 'D');
    exit;
}

$extension = $format === 'word' ? 'doc' : 'xls';
$mime = $format === 'word' ? 'application/msword' : 'application/vnd.ms-excel';
header('Content-Type: ' . $mime . '; charset=UTF-8');
header('Content-Disposition: attachment; filename="documents-' . date('Ymd-His') . '.' . $extension . '"');
echo "\xEF\xBB\xBF";
?>
<!doctype html><html lang="th"><head>
    <link rel="icon" type="image/png" href="/Project/assets/images/fet-logo-transparent.png?v=20260815-3">
<meta charset="utf-8"><style>body{font-family:Tahoma,sans-serif}table{border-collapse:collapse;width:100%}th,td{border:1px solid #555;padding:7px}th{background:#ddd}</style></head><body>
<h2>รายการเอกสารนักศึกษาในสาขา</h2>
<table><thead><tr><th>ลำดับ</th><th>รหัสนักศึกษา</th><th>ชื่อ-นามสกุล</th><th>รหัสเอกสาร</th><th>ชื่อเอกสาร</th><th>วันที่ส่ง</th></tr></thead><tbody>
<?php foreach ($rows as $index => $row): ?><tr><td><?= $index + 1 ?></td><td><?= htmlspecialchars($row['student_number']) ?></td><td><?= htmlspecialchars($row['firstname'] . ' ' . $row['lastname']) ?></td><td><?= htmlspecialchars($row['document_code']) ?></td><td><?= htmlspecialchars($row['document_name']) ?></td><td><?= htmlspecialchars($row['submitted_at']) ?></td></tr><?php endforeach; ?>
</tbody></table></body></html>
