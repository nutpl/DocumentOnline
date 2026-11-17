<?php

$projectRoot = dirname(__DIR__);
$checks = [
    'admin_shared_responsive_css' => ['admin/layouts/admin_header.php', 'responsive.css'],
    'student_shared_responsive_css' => ['student/sidebar.php', 'responsive.css'],
    'staff_shared_responsive_css' => ['staff/sidebar.php', 'responsive.css'],
    'teacher_shared_responsive_css' => ['teacher/sidebar.php', 'responsive.css'],
    'admin_mobile_sidebar' => ['admin/layouts/admin_sidebar.php', '-translate-x-full'],
    'student_mobile_sidebar' => ['student/sidebar.php', '-translate-x-full'],
    'staff_mobile_sidebar' => ['staff/sidebar.php', '-translate-x-full'],
    'teacher_mobile_sidebar' => ['teacher/sidebar.php', '-translate-x-full'],
    'admin_tablet_sidebar_close' => ['admin/layouts/admin_sidebar.php', 'admin-sidebar-close'],
    'tablet_sidebar_close_rule' => ['assets/css/responsive.css', '@media (min-width: 1024px) and (max-width: 1100px)'],
    'safe_area_sidebar_spacing' => ['assets/css/responsive.css', 'safe-area-inset-bottom'],
    'student_document_preview_scroll' => ['student/document_form.php', 'document-preview-scroll'],
    'student_internship_responsive_grid' => ['student/internship_form.php', 'xl:grid-cols-'],
    'staff_wide_table_scroll' => ['staff/teachers.php', 'overflow-x-auto'],
    'admin_wide_table_scroll' => ['admin/employees/index.php', 'overflow-x-auto'],
    'login_viewport' => ['auth/login.php', 'name="viewport"'],
    'register_viewport' => ['auth/register.php', 'name="viewport"'],
    'first_login_viewport' => ['auth/first_login.php', 'name="viewport"'],
    'create_password_viewport' => ['auth/create_password.php', 'name="viewport"'],
    'login_shared_responsive_css' => ['auth/login.php', 'responsive.css'],
    'change_password_shared_responsive_css' => ['auth/change_password.php', 'responsive.css'],
    'mobile_hero_wraps_badges' => ['assets/css/responsive.css', 'flex-wrap: wrap'],
    'mobile_hero_title_keeps_width' => ['assets/css/responsive.css', 'calc(100% - 3.3rem)'],
    'staff_tracking_actions_wrap' => ['assets/css/responsive.css', '#tracking-list details > div > .mt-4.flex'],
];

$passed = true;
foreach ($checks as $name => [$relativePath, $expectedText]) {
    $contents = file_get_contents($projectRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath));
    $ok = $contents !== false && str_contains($contents, $expectedText);
    echo $name . '=' . ($ok ? 'passed' : 'failed') . PHP_EOL;
    $passed = $passed && $ok;
}

exit($passed ? 0 : 1);
