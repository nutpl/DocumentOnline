<?php
session_start();
require_once __DIR__ . '/../middleware/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/csrf_helper.php';
require_once __DIR__ . '/../helpers/workspace_presence_helper.php';

header('Content-Type: application/json; charset=utf-8');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(['ok'=>false]); exit; }
verify_csrf_or_abort();
$pageKey = trim((string) ($_POST['page_key'] ?? ''));
if (!str_starts_with($pageKey, 'staff/') || strlen($pageKey) > 120) { http_response_code(422); echo json_encode(['ok'=>false]); exit; }
touchWorkspacePresence($pdo, $pageKey);
echo json_encode(['ok'=>true]);
