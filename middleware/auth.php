<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {

    header("Location: /Project/auth/login.php");

    exit;

}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/session_security_helper.php';
require_once __DIR__ . '/../helpers/alert_response_helper.php';

/** Render application access/data errors consistently instead of plain text. */
function renderApplicationAlert(string $message, ?string $returnUrl = null, int $statusCode = 403): void
{
    $role = (string) ($_SESSION['role'] ?? '');
    $defaultUrl = match ($role) {
        'student' => '/Project/student/dashboard.php',
        'teacher' => '/Project/teacher/dashboard.php',
        'staff' => '/Project/staff/dashboard.php',
        default => '/Project/index.php',
    };
    $title = str_contains($message, 'ไม่พบเอกสาร') ? 'ไม่พบเอกสาร'
        : (str_contains($message, 'สิทธิ์') ? 'ไม่มีสิทธิ์ใช้งาน' : 'ไม่สามารถดำเนินการได้');

    renderSweetAlertResponse($title, $message, 'error', $returnUrl ?: $defaultUrl, $statusCode);
}

$sessionUserId = (int) $_SESSION['user_id'];
$sessionToken = (string) ($_SESSION['session_token'] ?? '');
if (!isCurrentUserSession($pdo, $sessionUserId, $sessionToken)) {
    destroyLocalUserSession();
    header('Location: /Project/auth/login.php?reason=session_replaced');
    exit;
}
