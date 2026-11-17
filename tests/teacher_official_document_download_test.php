<?php
$root = dirname(__DIR__);
$page = file_get_contents($root . '/teacher/official_documents.php');
$word = file_get_contents($root . '/teacher/generate_official_word.php');
$pdf = file_get_contents($root . '/teacher/generate_official_pdf.php');
$sharedWord = file_get_contents($root . '/staff/generate_official_word.php');
$sharedPdf = file_get_contents($root . '/staff/generate_official_pdf.php');
$sidebar = file_get_contents($root . '/teacher/sidebar.php');
$checks = [
    'coordinator_only_page' => str_contains($page, 'teacherCoordinatorProfile'),
    'download_only_page' => !str_contains($page, 'action="delete"') && !str_contains($page, 'method="post"'),
    'teacher_word_context' => str_contains($word, "'teacher_coordinator'"),
    'teacher_pdf_context' => str_contains($pdf, "'teacher_coordinator'"),
    'coordinator_cannot_download_response_letter' => str_contains($sharedWord, '$isCoordinatorDownload && !in_array($requestedType') && str_contains($sharedPdf, '$isCoordinatorDownload&&!in_array($requestedType'),
    'sidebar_link' => str_contains($sidebar, 'official_documents.php'),
];
$failed = array_keys(array_filter($checks, static fn(bool $ok): bool => !$ok));
if ($failed) { fwrite(STDERR, 'Failed checks: ' . implode(', ', $failed) . PHP_EOL); exit(1); }
echo "Teacher official document download checks passed.\n";
