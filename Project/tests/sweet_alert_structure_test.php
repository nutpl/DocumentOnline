<?php

$projectRoot = dirname(__DIR__);
$checks = [
    'shared_alert_response_helper' => ['helpers/alert_response_helper.php', 'renderSweetAlertResponse'],
    'csrf_uses_sweet_alert' => ['helpers/csrf_helper.php', 'renderSweetAlertResponse'],
    'flash_errors_use_sweet_alert' => ['helpers/flash.php', "data-success-icon='error'"],
    'official_document_errors_use_sweet_alert' => ['staff/official_documents.php', 'renderSweetAlertResponse'],
    'official_word_errors_use_sweet_alert' => ['staff/generate_official_word.php', 'renderSweetAlertResponse'],
    'official_pdf_errors_use_sweet_alert' => ['staff/generate_official_pdf.php', 'renderSweetAlertResponse'],
    'response_review_errors_use_sweet_alert' => ['staff/response_letters.php', 'renderSweetAlertResponse'],
    'student_upload_errors_use_sweet_alert' => ['student/upload_response_letter.php', 'renderSweetAlertResponse'],
    'teacher_review_errors_use_sweet_alert' => ['teacher/process_review.php', 'renderSweetAlertResponse'],
    'response_upload_accepts_word_and_pdf' => ['student/upload_response_letter.php', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
    'response_page_word_file_picker' => ['student/response_letter.php', 'application/msword,.doc'],
    'response_download_uses_file_type' => ['staff/download_response_letter.php', '$responseMimeTypes'],
    'no_document_response_has_manual_followup' => ['student/declare_response_outcome.php', 'awaiting_result'],
];

$passed = true;
foreach ($checks as $name => [$relativePath, $expectedText]) {
    $contents = file_get_contents($projectRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath));
    $ok = $contents !== false && str_contains($contents, $expectedText);
    echo $name . '=' . ($ok ? 'passed' : 'failed') . PHP_EOL;
    $passed = $passed && $ok;
}

exit($passed ? 0 : 1);
