<?php
session_start();
require_once "../../config/database.php";
header('Content-Type: application/json; charset=utf-8');

if (($_SESSION['role'] ?? '') !== 'admin') {
    http_response_code(403); echo json_encode(['success' => false]); exit;
}
$data = json_decode(file_get_contents('php://input'), true);
if (!$data || (int)($data['field_id'] ?? 0) < 1) {
    http_response_code(422); echo json_encode(['success' => false]); exit;
}
$stmt = $pdo->prepare('UPDATE template_fields SET pos_x=?, pos_y=?, width=?, height=? WHERE field_id=?');
$stmt->execute([
    max(0, (float)($data['pos_x'] ?? 0)), max(0, (float)($data['pos_y'] ?? 0)),
    max(40, (float)($data['width'] ?? 180)), max(20, (float)($data['height'] ?? 35)), (int)$data['field_id']
]);
echo json_encode(['success' => true]);
