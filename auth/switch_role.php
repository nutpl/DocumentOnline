<?php
session_start();

require_once __DIR__ . '/../middleware/auth.php';
require_once __DIR__ . '/../helpers/csrf_helper.php';
require_once __DIR__ . '/../helpers/assistant_workspace_helper.php';

verify_csrf_or_abort();

$role = (string) ($_POST['role'] ?? '');
$roles = array_values(array_unique(array_filter($_SESSION['roles'] ?? [], static fn($item): bool => is_string($item))));
if (!in_array($role, $roles, true)) {
    $_SESSION['error'] = 'คุณไม่มีสิทธิ์สลับไปยังบทบาทที่เลือก';
    header('Location: /Project/index.php');
    exit;
}

$_SESSION['role'] = $role;
$_SESSION['active_role'] = $role;
$_SESSION['assistant_workspace'] = isAssistantWorkspaceUser() && in_array($role, ['admin', 'staff'], true);
$routes = ['admin' => '/Project/admin/dashboard.php', 'staff' => '/Project/staff/dashboard.php', 'teacher' => '/Project/teacher/dashboard.php', 'student' => '/Project/student/dashboard.php'];
$destination = !empty($_SESSION['assistant_workspace']) && in_array($role, ['admin', 'staff'], true)
    ? assistantWorkspaceRoute($role)
    : ($routes[$role] ?? '/Project/index.php');
header('Location: ' . $destination);
exit;
