<?php
session_start();
require_once __DIR__ . '/../helpers/assistant_workspace_helper.php';
$activeRole = (string) ($_SESSION['role'] ?? '');
if ($activeRole === 'teacher') {
    header('Location: /Project/teacher/dashboard.php');
    exit;
}

requireAssistantWorkspace($activeRole);
header('Location: ' . assistantWorkspaceRoute($activeRole));
exit;
