<?php

require_once __DIR__ . '/../helpers/document_download_access_helper.php';

$cases = [
    ['R01', 'draft', null, false],
    ['R14', 'submitted', null, false],
    ['R24', 'in_review', null, false],
    ['R12', 'revision_requested', null, false],
    ['R18', 'approved', null, true],
    ['INTERNSHIP_INFO', 'draft', 'waiting_signature', true],
    ['INTERNSHIP_INFO', 'in_review', 'submitted', false],
    ['INTERNSHIP_INFO', 'approved', 'submitted', true],
];

$passed = true;
foreach ($cases as [$code, $documentStatus, $internshipStatus, $expected]) {
    $actual = studentCanDownloadDocument($code, $documentStatus, $internshipStatus);
    $ok = $actual === $expected;
    echo implode('_', [$code, $documentStatus, $internshipStatus ?? 'none']) . '=' . ($ok ? 'passed' : 'failed') . PHP_EOL;
    $passed = $passed && $ok;
}

exit($passed ? 0 : 1);
