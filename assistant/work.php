<?php
session_start();
require_once __DIR__ . '/../middleware/auth.php';
require_once __DIR__ . '/../helpers/assistant_workspace_helper.php';

$modules = [
    'admin-users' => ['admin', '/Project/admin/users/index.php', 'บัญชีและผู้ใช้งาน'],
    'admin-teachers' => ['admin', '/Project/admin/teachers/index.php', 'ข้อมูลอาจารย์และเจ้าหน้าที่'],
    'admin-structure' => ['admin', '/Project/admin/departments/index.php', 'โครงสร้างการศึกษา'],
    'admin-positions' => ['admin', '/Project/admin/positions/index.php', 'ตำแหน่งและผู้ดำรงตำแหน่ง'],
    'staff-documents' => ['staff', '/Project/staff/documents.php', 'เอกสารสหกิจ'],
    'staff-response-letters' => ['staff', '/Project/staff/response_letters.php', 'หนังสือตอบรับ'],
    'staff-official-documents' => ['staff', '/Project/staff/official_documents.php', 'หนังสือราชการ'],
    'staff-supervision' => ['staff', '/Project/staff/supervision.php', 'แผนการนิเทศ'],
    'staff-tracking' => ['staff', '/Project/staff/internship_tracking.php', 'ติดตามสถานะสหกิจ'],
];

$module = (string) ($_GET['module'] ?? '');
if ($module === 'history') {
    header('Location: /Project/assistant/history.php');
    exit;
}
if (!isset($modules[$module])) {
    renderSweetAlertResponse(
        'ไม่พบพื้นที่ทำงาน',
        'ไม่พบเมนูที่เลือก กรุณาเลือกเมนูจากศูนย์ช่วยจัดการอีกครั้ง',
        'error',
        isAssistantWorkspaceUser() ? assistantWorkspaceRoute((string) ($_SESSION['role'] ?? 'teacher')) : '/Project/index.php',
        404
    );
}

[$requiredRole, $sourceUrl, $title] = $modules[$module];
requireAssistantWorkspace($requiredRole);
header('Location: ' . $sourceUrl);
exit;
