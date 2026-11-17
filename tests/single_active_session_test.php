<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/session_security_helper.php';

$userId = (int) $pdo->query("SELECT user_id FROM users WHERE status='active' ORDER BY user_id LIMIT 1")->fetchColumn();
if ($userId < 1) {
    throw new RuntimeException('ไม่พบบัญชี active สำหรับทดสอบ');
}

$pdo->beginTransaction();
try {
    $firstToken = issueUserSessionToken($pdo, $userId);
    if (!isCurrentUserSession($pdo, $userId, $firstToken)) {
        throw new RuntimeException('Session แรกไม่ผ่านการตรวจสอบ');
    }

    $secondToken = issueUserSessionToken($pdo, $userId);
    if (isCurrentUserSession($pdo, $userId, $firstToken)) {
        throw new RuntimeException('Session แรกยังใช้งานได้หลัง Login ครั้งที่สอง');
    }
    if (!isCurrentUserSession($pdo, $userId, $secondToken)) {
        throw new RuntimeException('Session ล่าสุดไม่ผ่านการตรวจสอบ');
    }

    revokeUserSessionToken($pdo, $userId, $firstToken);
    if (!isCurrentUserSession($pdo, $userId, $secondToken)) {
        throw new RuntimeException('Session เก่าสามารถล้าง Session ล่าสุดได้');
    }

    revokeUserSessionToken($pdo, $userId, $secondToken);
    if (isCurrentUserSession($pdo, $userId, $secondToken)) {
        throw new RuntimeException('Logout ไม่ได้ล้าง Session ล่าสุด');
    }

    echo "single_active_session_test=passed\n";
} finally {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
}
