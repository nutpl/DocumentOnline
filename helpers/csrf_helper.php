<?php

require_once __DIR__ . '/alert_response_helper.php';

function csrf_token(): string
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="'
        . htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8')
        . '">';
}

function verify_csrf_or_abort(): void
{
    $submittedToken = (string) ($_POST['csrf_token'] ?? '');

    if ($submittedToken === '' || !hash_equals(csrf_token(), $submittedToken)) {
        renderSweetAlertResponse(
            'คำขอไม่ถูกต้อง',
            'คำขอหมดอายุหรือไม่ถูกต้อง กรุณาโหลดหน้าใหม่แล้วลองอีกครั้ง',
            'warning',
            null,
            403
        );
    }
}
