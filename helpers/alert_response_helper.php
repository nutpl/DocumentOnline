<?php
/**
 * Renders an error/success response for browser-initiated actions.
 *
 * Download and document-generation endpoints cannot redirect once an error
 * occurs, because the browser is waiting for a file. Errors are queued in the
 * session and shown over the return page, so a separate blank error page is
 * never shown to the user.
 */
function renderSweetAlertResponse(
    string $title,
    string $message,
    string $icon = 'error',
    ?string $returnUrl = null,
    int $statusCode = 400
): void {
    $_SESSION['app_sweet_alert'] = [
        'title' => $title,
        'text' => $message,
        'icon' => in_array($icon, ['success', 'error', 'warning', 'info', 'question'], true) ? $icon : 'info',
    ];
    http_response_code(302);
    header('Location: ' . ($returnUrl ?: '/Project/auth/login.php'));
    exit;
}

/** Place this in an authenticated page layout to show its queued alert. */
function renderQueuedSweetAlert(): void
{
    $alert = $_SESSION['app_sweet_alert'] ?? null;
    unset($_SESSION['app_sweet_alert']);
    if (!is_array($alert)) {
        return;
    }
    $payload = json_encode($alert, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    echo '<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>'
        . '<script>window.addEventListener("DOMContentLoaded",function(){Swal.fire(Object.assign({confirmButtonText:"ตกลง",allowOutsideClick:false},' . ($payload ?: '{}') . '));});</script>';
}

/**
 * Replacement for legacy plain-text exit messages in browser pages.
 * It keeps the current HTTP status when the caller already set one.
 */
function renderPlainErrorAlert(string $message, ?string $returnUrl = null): void
{
    $role = (string) ($_SESSION['role'] ?? '');
    $fallback = $returnUrl ?: match ($role) {
        'student' => '/Project/student/dashboard.php',
        'teacher' => '/Project/teacher/dashboard.php',
        'staff' => '/Project/staff/dashboard.php',
        default => '/Project/index.php',
    };
    $currentStatus = http_response_code();
    $statusCode = $currentStatus >= 400 ? $currentStatus : 400;
    $title = str_contains($message, 'ไม่พบเอกสาร') ? 'ไม่พบเอกสาร'
        : (str_contains($message, 'สิทธิ์') ? 'ไม่มีสิทธิ์ใช้งาน' : 'ไม่สามารถดำเนินการได้');

    renderSweetAlertResponse($title, $message, 'error', $fallback, $statusCode);
}
