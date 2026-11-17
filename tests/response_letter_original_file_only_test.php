<?php

declare(strict_types=1);

$responsePage = file_get_contents(__DIR__ . '/../staff/response_letters.php');
$downloadEndpoint = file_get_contents(__DIR__ . '/../staff/download_response_letter.php');
$historyPage = file_get_contents(__DIR__ . '/../staff/official_history.php');

if ($responsePage === false || $downloadEndpoint === false || $historyPage === false) {
    fwrite(STDERR, "FAIL: Cannot read response-letter files\n");
    exit(1);
}

$checks = [
    'response page has no generated Word action' => !str_contains($responsePage, "['word','fa-file-word','Word'"),
    'response page does not append format=word' => !str_contains($responsePage, "+'&format='+format"),
    'legacy PDF-to-Word request is rejected' => str_contains($downloadEndpoint, "ไม่รองรับการสร้างไฟล์ Word"),
    'original Word uploads remain supported' => str_contains($downloadEndpoint, "in_array(\$extension, ['doc', 'docx'], true)"),
    'response download uses the student filename' => str_contains($downloadEndpoint, '$downloadFilename') && str_contains($downloadEndpoint, 'หนังสือตอบรับ_'),
    'official history does not offer response exports' => str_contains($historyPage, 'link.remove());'),
    'official history uses the university favicon' => str_contains($historyPage, 'rmuti-university-seal.png'),
];

foreach ($checks as $label => $passed) {
    if (!$passed) {
        fwrite(STDERR, "FAIL: {$label}\n");
        exit(1);
    }
    echo "PASS: {$label}\n";
}
