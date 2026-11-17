<?php
$root = dirname(__DIR__);
$history = $root . '/staff/official_history.php';
$teacherDocuments = $root . '/teacher/official_documents.php';
$successAlert = $root . '/assets/js/success-alert.js';

foreach ([$history, $teacherDocuments, $successAlert] as $file) {
    if (!is_file($file)) {
        fwrite(STDERR, "Missing file: {$file}\n");
        exit(1);
    }
}

$historySource = file_get_contents($history);
$teacherSource = file_get_contents($teacherDocuments);
$successSource = file_get_contents($successAlert);
$checks = [
    'history_downloads_pdf_directly' => str_contains($historySource, 'generate_official_pdf.php') && !str_contains($historySource, 'data-official-pdf-pull'),
    'teacher_downloads_pdf_directly' => str_contains($teacherSource, 'generate_official_pdf.php') && !str_contains($teacherSource, 'data-official-pdf-pull'),
    'created_document_downloads_directly' => str_contains($successSource, 'window.location.href = actionUrl') && !str_contains($successSource, 'officialPdfPullDownload'),
];
foreach ($checks as $name => $passed) {
    if (!$passed) {
        fwrite(STDERR, "Failed: {$name}\n");
        exit(1);
    }
}
echo "Official server-PDF download UI structure checks passed.\n";
