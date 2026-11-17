<?php
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
require_once __DIR__ . '/../middleware/staff.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/official_internship_word_helper.php';
require_once __DIR__ . '/../helpers/official_template_settings_helper.php';
require_once __DIR__ . '/../helpers/official_internship_pdf_helper.php';
require_once __DIR__ . '/../helpers/official_response_form_pdf_helper.php';
require_once __DIR__ . '/../helpers/official_internship_batch_helper.php';
require_once __DIR__ . '/../helpers/word_to_pdf_converter_helper.php';
require_once __DIR__ . '/../helpers/alert_response_helper.php';

$staffStmt = $pdo->prepare('SELECT department_id FROM employee WHERE user_id=? AND employee_type IN ("staff","teacher") LIMIT 1');
$staffStmt->execute([$_SESSION['user_id']]);
$departmentId = (int)$staffStmt->fetchColumn();
$type = (string)($_GET['type'] ?? '');
$format = (string)($_GET['format'] ?? '');
$fontOption = strtolower((string)($_GET['font'] ?? 'psk')) === 'new' ? 'new' : 'psk';
$batch = $_SESSION['official_batch_download'] ?? null;
// A response form belongs to a request-letter batch and is deliberately
// available only as PDF so its faculty-approved layout stays fixed.
$responseForms = $type === 'response';
$batchType = $responseForms ? 'request' : $type;
if ($responseForms && $format === 'word') {
    renderSweetAlertResponse('ดาวน์โหลดได้เฉพาะ PDF', 'แบบตอบรับสำหรับพิมพ์รองรับการดาวน์โหลดเป็น PDF เท่านั้น', 'info', 'official_documents.php?type=request', 422);
}
if (!$departmentId || !in_array($type, ['request', 'send', 'response'], true) || !in_array($format, ['pdf', 'word'], true)
    || !is_array($batch) || ($batch['type'] ?? '') !== $batchType
    || (int)($batch['department_id'] ?? 0) !== $departmentId
    || (time() - (int)($batch['created_at'] ?? 0)) >= 7200) {
    renderSweetAlertResponse('ไม่พบชุดเอกสาร', 'ลิงก์ดาวน์โหลดหมดอายุหรือไม่มีสิทธิ์เข้าถึง กรุณาสร้างชุดเอกสารใหม่อีกครั้ง', 'warning', 'official_documents.php?type=' . ($type === 'send' ? 'send' : 'request'), 404);
}

$ids = array_values(array_unique(array_filter(array_map('intval', $batch['ids'] ?? []))));
if (!$ids) {
    renderSweetAlertResponse('ข้อมูลชุดเอกสารไม่ถูกต้อง', 'ไม่พบหนังสือสำหรับสร้างไฟล์รวม', 'warning', 'official_documents.php?type=' . $type, 422);
}
$marks = implode(',', array_fill(0, count($ids), '?'));
$documentStmt = $pdo->prepare("SELECT DISTINCT od.*
    FROM internship_official_document od
    JOIN internship_official_student os ON os.official_document_id=od.official_document_id
    JOIN internship_profile ip ON ip.internship_profile_id=os.internship_profile_id
    JOIN student s ON s.student_id=ip.student_id
    JOIN group_class g ON g.group_id=s.group_id
    WHERE od.official_document_id IN ($marks) AND od.document_type=? AND g.department_id=?");
$documentStmt->execute([...$ids, $batchType, $departmentId]);
$documentsById = [];
foreach ($documentStmt->fetchAll(PDO::FETCH_ASSOC) as $document) {
    $documentsById[(int)$document['official_document_id']] = $document;
}
if (count($documentsById) !== count($ids)) {
    renderSweetAlertResponse('ไม่พบหนังสือ', 'มีหนังสือบางฉบับไม่อยู่ในสิทธิ์ของคุณ', 'error', 'official_documents.php?type=' . $type, 403);
}

$studentsStmt = $pdo->prepare('SELECT ip.*,ic.academic_year,ic.semester,ic.start_date,ic.end_date,s.student_number,s.firstname,s.lastname,p.prefix_short,d.department_name
    FROM internship_official_student os
    JOIN internship_profile ip ON ip.internship_profile_id=os.internship_profile_id
    JOIN internship_calendar ic ON ic.calendar_id=ip.calendar_id
    JOIN student s ON s.student_id=ip.student_id
    LEFT JOIN prefixes p ON p.prefix_id=s.prefix_id
    JOIN group_class g ON g.group_id=s.group_id
    JOIN department d ON d.department_id=g.department_id
    WHERE os.official_document_id=? AND g.department_id=?
    ORDER BY os.sequence_no');

$temporaryFiles = [];
try {
    $letters = [];
    foreach ($ids as $id) {
        $studentsStmt->execute([$id, $departmentId]);
        $students = $studentsStmt->fetchAll(PDO::FETCH_ASSOC);
        if (!$students) {
            throw new RuntimeException('ไม่พบรายชื่อนักศึกษาในหนังสือที่เลือก');
        }
        $letters[] = ['document' => officialTemplateApplySettings($pdo, $departmentId, $documentsById[$id]), 'students' => $students];
    }
    $baseName = $responseForms
        ? 'แบบตอบรับนักศึกษารวมหลายบริษัท'
        : ($type === 'request'
        ? 'หนังสือขอความอนุเคราะห์รับนักศึกษารวมหลายบริษัท'
        : 'ใบส่งตัวนักศึกษารวมหลายบริษัท');
    if ($format === 'word') {
        $wordFiles = [];
        $multiStudentSourceIndexes = [];
        foreach ($letters as $letterIndex => $letter) {
            if (count($letter['students']) > 1) {
                $multiStudentSourceIndexes[] = $letterIndex;
            }
            $temporaryBase = tempnam(sys_get_temp_dir(), 'official-batch-word-');
            if ($temporaryBase === false) throw new RuntimeException('ไม่สามารถเตรียมไฟล์ Word ได้');
            $wordFile = $temporaryBase . '.docx';
            @unlink($temporaryBase);
            saveOfficialInternshipWordFile($letter['document'], $letter['students'], $responseForms ? 'response' : $type, $wordFile, false, $fontOption);
            $temporaryFiles[] = $wordFile;
            $wordFiles[] = $wordFile;
        }
        $temporaryBase = tempnam(sys_get_temp_dir(), 'official-batch-merged-');
        if ($temporaryBase === false) throw new RuntimeException('ไม่สามารถเตรียมไฟล์ Word รวมได้');
        $mergedWord = $temporaryBase . '.docx';
        @unlink($temporaryBase);
        $temporaryFiles[] = $mergedWord;
        mergeOfficialInternshipWordFiles($wordFiles, $mergedWord, $multiStudentSourceIndexes);
        header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
        header('Content-Disposition: attachment; filename*=UTF-8\'\''.rawurlencode($baseName . '.docx'));
        header('Content-Length: ' . filesize($mergedWord));
        header('X-Content-Type-Options: nosniff');
        readfile($mergedWord);
        exit;
    }

    if ($responseForms) {
        $pdfPages = [];
        foreach ($letters as $letter) {
            $pdfPages[] = createOfficialResponseFormPdf($pdo, $letter['document'], $letter['students'])->Output('', 'S');
        }
        $pdfContents = mergeOfficialInternshipPdfContents($pdfPages);
    } else {
        // Generate and convert every company separately before merging PDFs.
        // Merging DOCX files can reflow a final contact line to a second,
        // nearly blank page even when each individual letter is one page.
        // Merging the finished PDFs preserves the approved one-page layout
        // for every letter on Windows and on the Server.
        // A multi-company batch can contain many letters. Allow the request
        // enough time for Word to export every letter before the PDFs merge.
        @set_time_limit(300);
        $pdfPages = [];
        foreach ($letters as $letter) {
            $temporaryBase = tempnam(sys_get_temp_dir(), 'official-batch-pdf-');
            if ($temporaryBase === false) throw new RuntimeException('ไม่สามารถเตรียมไฟล์ PDF ได้');
            $wordFile = $temporaryBase . '.docx';
            $pdfFile = $temporaryBase . '.pdf';
            @unlink($temporaryBase);
            // LibreOffice needs its conversion-specific template on Server.
            // Windows retains its existing Word-COM PDF path unchanged.
            // Start from exactly the same DOCX layout as the Word download.
            // Each file is converted independently, so its fixed QR/contact
            // footer remains at the approved one-page position in the PDF.
            saveOfficialInternshipWordFile($letter['document'], $letter['students'], $type, $wordFile, false);
            $temporaryFiles[] = $wordFile;
            $temporaryFiles[] = $pdfFile;
            // Use the proven per-document Word conversion path. The extended
            // request limit above lets a large batch complete safely.
            convertWordDocumentToPdf($wordFile, $pdfFile);
            $pdfPage = file_get_contents($pdfFile);
            if ($pdfPage === false || $pdfPage === '') throw new RuntimeException('ไม่สามารถสร้างไฟล์ PDF ของบริษัทได้');
            $pdfPages[] = $pdfPage;
        }
        $pdfContents = mergeOfficialInternshipPdfContents($pdfPages);
    }
    if ($pdfContents === false || $pdfContents === '') {
        throw new RuntimeException('ไม่สามารถสร้างไฟล์ PDF รวมได้');
    }
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename*=UTF-8\'\''.rawurlencode($baseName . '.pdf'));
    header('Content-Length: ' . strlen($pdfContents));
    header('X-Content-Type-Options: nosniff');
    echo $pdfContents;
} catch (Throwable $error) {
    error_log('Official batch export failed: ' . $error->getMessage());
    renderSweetAlertResponse('สร้างไฟล์รวมไม่สำเร็จ', 'ระบบไม่สามารถรวมเอกสารได้ กรุณาลองใหม่อีกครั้ง', 'error', 'official_documents.php?type=' . $type, 500);
} finally {
    foreach ($temporaryFiles as $temporaryFile) {
        @unlink($temporaryFile);
    }
}
