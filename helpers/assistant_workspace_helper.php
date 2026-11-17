<?php

function isAssistantWorkspaceUser(): bool
{
    $roles = array_values(array_unique(array_filter(
        $_SESSION['roles'] ?? [],
        static fn($role): bool => is_string($role)
    )));

    return count(array_intersect(['admin', 'staff', 'teacher'], $roles)) === 3;
}

function assistantWorkspaceRoute(string $role): string
{
    $routes = [
        'admin' => '/Project/admin/dashboard.php',
        'staff' => '/Project/staff/dashboard.php',
        'teacher' => '/Project/teacher/dashboard.php',
    ];

    return $routes[$role] ?? '/Project/index.php';
}

function requireAssistantWorkspace(string $role): void
{
    require_once __DIR__ . '/../middleware/auth.php';

    if (!in_array($role, ['admin', 'staff'], true) || !isAssistantWorkspaceUser() || ($_SESSION['role'] ?? '') !== $role) {
        $activeRole = (string) ($_SESSION['role'] ?? '');
        $standardRoutes = [
            'admin' => '/Project/admin/dashboard.php',
            'staff' => '/Project/staff/dashboard.php',
            'teacher' => '/Project/teacher/dashboard.php',
            'student' => '/Project/student/dashboard.php',
        ];
        $returnUrl = isAssistantWorkspaceUser()
            ? assistantWorkspaceRoute($activeRole)
            : ($standardRoutes[$activeRole] ?? '/Project/index.php');
        renderSweetAlertResponse(
            'ไม่มีสิทธิ์ใช้งาน',
            'กรุณาเลือกบทบาทให้ตรงกับพื้นที่งานที่ต้องการก่อนเข้าใช้งาน',
            'error',
            $returnUrl,
            403
        );
    }
}

function assistantWorkspaceRoleLabel(string $role): string
{
    return [
        'admin' => 'ผู้ช่วยผู้ดูแลระบบ',
        'staff' => 'ผู้ช่วยเจ้าหน้าที่',
        'teacher' => 'ผู้ช่วยอาจารย์',
    ][$role] ?? 'พื้นที่ช่วยจัดการ';
}
