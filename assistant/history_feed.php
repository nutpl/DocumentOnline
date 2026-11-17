<?php
declare(strict_types=1);

session_start();
require_once __DIR__ . '/../middleware/auth.php';
require_once __DIR__ . '/../helpers/assistant_workspace_helper.php';
requireAssistantWorkspace((string) ($_SESSION['role'] ?? ''));

header('Content-Type: application/json; charset=utf-8');
$statement = $pdo->query('SELECT al.activity,al.detail,al.activity_time,
    COALESCE(CONCAT(
        CASE WHEN e.employee_type="teacher" AND COALESCE(p.prefix_short,p.prefix_name,"") IN ("","นาย","นาง","นางสาว") THEN "อาจารย์" ELSE COALESCE(p.prefix_short,p.prefix_name,"") END,
        e.firstname," ",e.lastname
    ),u.username,"ระบบ") AS actor_name
    FROM activity_log al
    LEFT JOIN users u ON u.user_id=al.user_id
    LEFT JOIN employee e ON e.user_id=u.user_id
    LEFT JOIN prefixes p ON p.prefix_id=e.prefix_id
    ORDER BY al.activity_time DESC,al.log_id DESC LIMIT 200');
echo json_encode(['items' => $statement->fetchAll(PDO::FETCH_ASSOC)], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
