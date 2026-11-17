<?php

function logActivity(PDO $pdo, ?int $userId, string $activity, ?string $detail = null): void
{
    try {
        $activity = mb_substr(trim($activity), 0, 100);
        if ($activity === '') return;
        if (!empty($_SESSION['assistant_workspace'])) {
            $role = (string) ($_SESSION['role'] ?? '');
            $roleLabel = ['admin' => 'ผู้ช่วยผู้ดูแลระบบ', 'staff' => 'ผู้ช่วยเจ้าหน้าที่', 'teacher' => 'ผู้ช่วยอาจารย์'][$role] ?? 'พื้นที่ช่วยจัดการ';
            $detail = '[' . $roleLabel . '] ' . trim((string) $detail);
        }
        $stmt = $pdo->prepare('INSERT INTO activity_log (user_id,activity,detail) VALUES (?,?,?)');
        $stmt->execute([$userId ?: null, $activity, $detail]);
    } catch (Throwable $e) {
        error_log('Activity log failed: ' . $e->getMessage());
    }
}
