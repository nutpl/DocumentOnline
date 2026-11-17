<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . "/../vendor/autoload.php";

/**
 * Read a boolean environment option without treating an unknown value as true.
 */
function mailEnvironmentFlag(string $name, bool $default = false): bool
{
    $value = getenv($name);
    if ($value === false || trim($value) === '') {
        return $default;
    }

    return in_array(strtolower(trim($value)), ['1', 'true', 'yes', 'on'], true);
}

// Keep document-notification email disabled while the system is under test.
// In-system notifications continue to be recorded normally. Production must
// explicitly set NOTIFICATION_EMAIL_ENABLED=true before email is sent.
function notificationEmailEnabled(): bool
{
    if (!mailEnvironmentFlag('NOTIFICATION_EMAIL_ENABLED', false)) {
        return false;
    }

    if (PHP_SAPI === 'cli' && !mailEnvironmentFlag('NOTIFICATION_EMAIL_ALLOW_CLI')) {
        return false;
    }

    return true;
}

function sendOTPEmail(
    string $receiverEmail,
    string $otp,
    string $expireAt,
    ?string $customSubject = null,
    ?string $customHtmlBody = null
): bool {

    $mail = new PHPMailer(true);

    try {

        $mail->isSMTP();

        $mail->Host = "smtp.gmail.com";

        $mail->SMTPAuth = true;

        $mail->Username = "mewwwtnr46@gmail.com";

        $mail->Password = "ggmtmpnpoiwmzqcp";

        $mail->SMTPSecure =
            PHPMailer::ENCRYPTION_STARTTLS;

        $mail->Port = 587;

        $mail->CharSet = "UTF-8";

        $mail->setFrom(
            "mewwwtnr46@gmail.com",
            "RMUTI Document Management System"
        );

        $mail->addAddress($receiverEmail);

        $mail->isHTML(true);

        $mail->Subject = $customSubject
            ?? "รหัส OTP สำหรับเปลี่ยนรหัสผ่าน";

         $mail->Body = $customHtmlBody ?? "
    <h2>RMUTI Document Management System</h2>

    <p>รหัส OTP สำหรับเปลี่ยนรหัสผ่านของคุณคือ</p>

    <h1>{$otp}</h1>

    <p>
        OTP นี้จะหมดอายุเวลา:
        <strong>{$expireAt}</strong>
    </p>

    <p>
        กรุณาใช้รหัสนี้ภายใน 5 นาที
    </p>
";

        $mail->send();

        return true;

    } catch (Exception $e) {

        error_log('OTP email failed: ' . $mail->ErrorInfo);
        return false;
    }

}

function sendNotificationEmail(
    string $receiverEmail,
    string $title,
    string $message,
    ?string $targetUrl = null
): bool {
    if (!notificationEmailEnabled()) {
        error_log('Notification email skipped because NOTIFICATION_EMAIL_ENABLED is disabled.');
        return true;
    }

    $safeTitle = htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $safeMessage = nl2br(htmlspecialchars($message, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
    $actionHtml = '';

    if ($targetUrl !== null && trim($targetUrl) !== '') {
        $targetUrl = trim($targetUrl);
        if (!preg_match('~^https?://~i', $targetUrl)) {
            $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
            $targetUrl = $scheme . '://' . $host . '/' . ltrim($targetUrl, '/');
        }
        $safeUrl = htmlspecialchars($targetUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $actionHtml = '<p style="margin-top:24px"><a href="' . $safeUrl . '" style="display:inline-block;padding:10px 18px;border-radius:8px;background:#b91c1c;color:#ffffff;text-decoration:none;font-weight:bold">เปิดดูในระบบ</a></p>';
    }

    $body = '<div style="font-family:Tahoma,Arial,sans-serif;color:#1f2937;line-height:1.7">'
        . '<h2 style="color:#991b1b">ระบบจัดการเอกสารคำร้อง</h2>'
        . '<h3>' . $safeTitle . '</h3>'
        . '<p>' . $safeMessage . '</p>'
        . $actionHtml
        . '<p style="margin-top:28px;color:#6b7280;font-size:13px">อีเมลนี้ส่งโดยอัตโนมัติ กรุณาเข้าสู่ระบบเพื่อตรวจสอบรายละเอียด</p>'
        . '</div>';

    return sendOTPEmail(
        $receiverEmail,
        '',
        '',
        '[ระบบจัดการเอกสาร] ' . $title,
        $body
    );
}
