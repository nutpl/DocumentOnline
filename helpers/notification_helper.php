<?php

require_once __DIR__ . '/../config/mail.php';

function createNotification(
    PDO $pdo,
    int $recipientUserId,
    ?int $documentId,
    string $type,
    string $title,
    string $message,
    ?string $targetUrl = null
): void {
    $stmt = $pdo->prepare('INSERT INTO notifications
        (recipient_user_id,document_id,notification_type,title,message,target_url)
        VALUES (?,?,?,?,?,?)');
    $stmt->execute([
        $recipientUserId,
        $documentId,
        mb_substr(trim($type), 0, 50),
        mb_substr(trim($title), 0, 180),
        trim($message),
        $targetUrl !== null ? mb_substr(trim($targetUrl), 0, 500) : null,
    ]);

    try {
        $emailStmt = $pdo->prepare('SELECT email FROM users WHERE user_id=? AND status="active" LIMIT 1');
        $emailStmt->execute([$recipientUserId]);
        $receiverEmail = trim((string)$emailStmt->fetchColumn());
        if ($receiverEmail !== '' && filter_var($receiverEmail, FILTER_VALIDATE_EMAIL)) {
            sendNotificationEmail($receiverEmail, $title, $message, $targetUrl);
        }
    } catch (Throwable $e) {
        error_log('Notification email failed for user ' . $recipientUserId . ': ' . $e->getMessage());
    }
}

function notifyDepartmentStaffForInternship(
    PDO $pdo,
    int $departmentId,
    ?int $documentId,
    string $studentCode,
    string $studentName
): void {
    $stmt = $pdo->prepare('SELECT DISTINCT e.user_id
        FROM employee e
        JOIN users u ON u.user_id=e.user_id
        WHERE e.department_id=?
          AND (e.employee_type="staff" OR EXISTS (
              SELECT 1 FROM user_roles ur WHERE ur.user_id=e.user_id AND ur.role="staff"
          ))
          AND e.user_id IS NOT NULL AND u.status="active"');
    $stmt->execute([$departmentId]);

    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $userId) {
        createNotification(
            $pdo,
            (int)$userId,
            $documentId,
            'internship_document_submitted',
            'มีเอกสารฝึกประสบการณ์ฉบับลงนามครบแล้ว',
            'นักศึกษา ' . $studentCode . ' ' . $studentName . ' ส่ง RMUTI_Pro-Ex_02 ที่อาจารย์ที่ปรึกษาและอาจารย์ประสานงานลงนามแล้ว',
            '/Project/staff/documents.php'
        );
    }
}

function notifyDepartmentStaffForResponseLetter(PDO $pdo, int $departmentId, string $studentCode, string $studentName): void
{
    $stmt = $pdo->prepare('SELECT DISTINCT e.user_id
        FROM employee e JOIN users u ON u.user_id=e.user_id
        WHERE e.department_id=?
          AND (e.employee_type="staff" OR EXISTS (
              SELECT 1 FROM user_roles ur WHERE ur.user_id=e.user_id AND ur.role="staff"
          ))
          AND e.user_id IS NOT NULL AND u.status="active"');
    $stmt->execute([$departmentId]);
    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $userId) {
        createNotification($pdo, (int)$userId, null, 'internship_response_uploaded',
            'มีหนังสือตอบรับรอตรวจสอบ',
            'นักศึกษา ' . $studentCode . ' ' . $studentName . ' อัปโหลดหนังสือตอบรับจากสถานประกอบการแล้ว',
            '/Project/staff/response_letters.php');
    }
}

function notifyDepartmentStaffForResponseFollowup(PDO $pdo, int $departmentId, string $studentCode, string $studentName): void
{
    $stmt = $pdo->prepare('SELECT DISTINCT e.user_id
        FROM employee e JOIN users u ON u.user_id=e.user_id
        WHERE e.department_id=?
          AND (e.employee_type="staff" OR EXISTS (
              SELECT 1 FROM user_roles ur WHERE ur.user_id=e.user_id AND ur.role="staff"
          ))
          AND e.user_id IS NOT NULL AND u.status="active"');
    $stmt->execute([$departmentId]);
    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $userId) {
        createNotification($pdo, (int)$userId, null, 'internship_response_followup',
            'มีรายการผลตอบกลับที่ต้องติดตาม',
            'นักศึกษา ' . $studentCode . ' ' . $studentName . ' แจ้งว่ายังไม่มีเอกสารตอบกลับจากสถานประกอบการ',
            '/Project/staff/response_letters.php');
    }
}

/**
 * แจ้งอาจารย์หรือผู้ดำรงตำแหน่งที่เกี่ยวข้อง หลังอาจารย์ที่ปรึกษาอนุมัติแล้ว
 * บุคคลเหล่านี้เปิดดูเอกสารได้ แต่ไม่ใช่ผู้อนุมัติใน Workflow
 */
function notifyRelatedTeachersAfterAdvisorApproval(PDO $pdo, int $documentId, int $advisorUserId): void
{
    $metaStatement = $pdo->prepare(
        'SELECT REPLACE(UPPER(dt.document_code),".","") document_code,g.department_id
         FROM documents d
         INNER JOIN student s ON s.student_id=d.student_id
         LEFT JOIN group_class g ON g.group_id=s.group_id
         INNER JOIN document_template t ON t.template_id=d.template_id
         INNER JOIN document_type dt ON dt.document_type_id=t.document_type_id
         WHERE d.document_id=?
         LIMIT 1'
    );
    $metaStatement->execute([$documentId]);
    $meta = $metaStatement->fetch(PDO::FETCH_ASSOC);
    $departmentId = (int) ($meta['department_id'] ?? 0);
    $code = (string) ($meta['document_code'] ?? '');
    if ($departmentId <= 0) {
        return;
    }

    $employeeIds = [];
    $positionNames = match ($code) {
        'R01' => ['ประธานหลักสูตร', 'หัวหน้าสาขา%'],
        'R12' => ['ประธานหลักสูตร'],
        'R14' => ['ประธานหลักสูตร', 'หัวหน้าสาขา%'],
        'R18', 'R24' => ['หัวหน้าสาขา%'],
        'INTERNSHIP_INFO' => ['อาจารย์ประสานงานฝึกประสบการณ์วิชาชีพ'],
        default => [],
    };
    $positionStatement = $pdo->prepare(
        'SELECT DISTINCT e.employee_id
         FROM employee e
         INNER JOIN employee_position ep ON ep.employee_id=e.employee_id
         INNER JOIN positions p ON p.position_id=ep.position_id
         WHERE e.department_id=? AND e.employee_type="teacher"
           AND p.position_name LIKE ?'
    );
    foreach ($positionNames as $positionName) {
        $positionStatement->execute([$departmentId, $positionName]);
        $employeeIds = array_merge($employeeIds, array_map('intval', $positionStatement->fetchAll(PDO::FETCH_COLUMN)));
    }

    if (in_array($code, ['R18', 'R24'], true)) {
        $requestTable = $code === 'R18' ? 'r18_request' : 'r24_request';
        $teacherStatement = $pdo->prepare('SELECT teacher_employee_id FROM ' . $requestTable . ' WHERE document_id=? LIMIT 1');
        $teacherStatement->execute([$documentId]);
        $teacherEmployeeId = (int) $teacherStatement->fetchColumn();
        if ($teacherEmployeeId > 0) {
            $employeeIds[] = $teacherEmployeeId;
        }
    }

    $employeeIds = array_values(array_unique(array_filter($employeeIds)));
    if (!$employeeIds) {
        return;
    }

    $placeholders = implode(',', array_fill(0, count($employeeIds), '?'));
    $recipientStatement = $pdo->prepare(
        'SELECT DISTINCT e.user_id FROM employee e
         INNER JOIN users u ON u.user_id=e.user_id AND u.status="active"
         WHERE e.employee_type="teacher" AND e.user_id IS NOT NULL
           AND e.user_id<>? AND e.employee_id IN (' . $placeholders . ')'
    );
    $recipientStatement->execute(array_merge([$advisorUserId], $employeeIds));

    $isInternship = $code === 'INTERNSHIP_INFO';
    foreach ($recipientStatement->fetchAll(PDO::FETCH_COLUMN) as $userId) {
        createNotification(
            $pdo,
            (int) $userId,
            $documentId,
            'document_advisor_approved',
            $isInternship ? 'คำร้องสหกิจผ่านอาจารย์ที่ปรึกษาแล้ว' : 'คำร้อง ' . $code . ' ผ่านอาจารย์ที่ปรึกษาแล้ว',
            $isInternship
                ? 'คำร้องสหกิจเลขที่ #' . $documentId . ' ผ่านอาจารย์ที่ปรึกษาแล้ว นักศึกษาจะนำเอกสารฉบับพิมพ์มาให้อาจารย์ประสานงานลงนาม'
                : 'คำร้อง ' . $code . ' เลขที่ #' . $documentId . ' ผ่านการพิจารณาจากอาจารย์ที่ปรึกษาแล้ว สามารถตรวจสอบเอกสารได้',
            '/Project/student/download_document.php?document=' . $documentId
        );
    }
}

function unreadNotificationCount(PDO $pdo, int $userId): int
{
    try {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM notifications WHERE recipient_user_id=? AND is_read=0');
        $stmt->execute([$userId]);
        return (int)$stmt->fetchColumn();
    } catch (Throwable $e) {
        return 0;
    }
}
