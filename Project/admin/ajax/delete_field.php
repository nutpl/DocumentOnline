<?php
session_start();
require_once "../../config/database.php";
header('Content-Type: application/json; charset=utf-8');
if (($_SESSION['role'] ?? '') !== 'admin') { http_response_code(403); echo json_encode(['success'=>false]); exit; }
$data = json_decode(file_get_contents('php://input'), true);
$stmt = $pdo->prepare('DELETE FROM template_fields WHERE field_id=?');
$stmt->execute([(int)($data['field_id'] ?? 0)]);
echo json_encode(['success' => true]);
