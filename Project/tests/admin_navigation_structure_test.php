<?php
$root = dirname(__DIR__);
$sidebar = file_get_contents($root . '/admin/layouts/admin_sidebar.php');
$css = file_get_contents($root . '/assets/css/admin.css');
$checks = [
    'sidebar_groups_user_management' => str_contains($sidebar, 'ผู้ใช้งานและสิทธิ์'),
    'sidebar_groups_academic_structure' => str_contains($sidebar, 'โครงสร้างการศึกษา'),
    'sidebar_groups_document_workflow' => str_contains($sidebar, 'เอกสารและขั้นตอน'),
    'no_academic_structure_overview_link' => !str_contains($sidebar, 'academic_structure.php'),
    'sidebar_group_is_compact_when_collapsed' => str_contains($css, '.admin-menu-group__links{display:none!important;}'),
];
$failed = array_keys(array_filter($checks, static fn(bool $ok): bool => !$ok));
if ($failed) { fwrite(STDERR, 'Failed checks: ' . implode(', ', $failed) . PHP_EOL); exit(1); }
echo "Admin navigation structure checks passed.\n";
