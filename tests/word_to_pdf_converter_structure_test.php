<?php

$root = dirname(__DIR__);
$helper = file_get_contents($root . '/helpers/word_to_pdf_converter_helper.php');
$generator = file_get_contents($root . '/staff/generate_official_pdf.php');

$checks = [
    'legacy_converter_remains_available_for_uploaded_word_files' => str_contains($helper, 'libreoffice') && str_contains($helper, '--headless'),
    'server_uses_local_libreoffice_only' => str_contains($helper, 'libreoffice') && !str_contains($helper, 'WORD_PDF_SERVICE_URL') && !str_contains($helper, 'convertWordDocumentToPdfRemotely'),
    'official_letters_start_from_word_template' => str_contains($generator, 'saveOfficialInternshipWordFile($document,$students,$requestedType,$docxFile)'),
    'official_letters_do_not_queue_windows_worker' => !str_contains($generator, 'wordPdfPullCreateJob('),
    'official_letters_convert_the_generated_docx' => str_contains($generator, 'convertWordDocumentToPdf($docxFile,$pdfFile)'),
    'pdf_generator_no_direct_powershell' => !str_contains($generator, 'powershell.exe'),
];

$failed = array_keys(array_filter($checks, static fn(bool $passed): bool => !$passed));
if ($failed) {
    fwrite(STDERR, 'Failed checks: ' . implode(', ', $failed) . PHP_EOL);
    exit(1);
}

echo 'Official server-PDF renderer structure checks passed.' . PHP_EOL;
