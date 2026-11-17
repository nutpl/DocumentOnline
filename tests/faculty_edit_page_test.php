<?php
$root = dirname(__DIR__);
$page = file_get_contents($root . '/admin/faculties/edit.php');
$update = file_get_contents($root . '/admin/faculties/update.php');
$checks = [
    'uses_faculty_abbreviation_label' => str_contains($page, 'ตัวย่อคณะ') && !str_contains($page, '>รหัสคณะ<'),
    'shows_related_structure_summary' => str_contains($page, 'department_count') && str_contains($page, 'group_count'),
    'duplicate_error_uses_abbreviation' => str_contains($update, 'ตัวย่อคณะนี้มีอยู่แล้ว'),
];
$failed = array_keys(array_filter($checks, static fn(bool $ok): bool => !$ok));
if ($failed) { fwrite(STDERR, 'Failed checks: ' . implode(', ', $failed) . PHP_EOL); exit(1); }
echo "Faculty edit page checks passed.\n";
