<?php
session_start();
require_once "../../config/database.php";

header('Content-Type: application/json; charset=utf-8');

if (($_SESSION['role'] ?? '') !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'ไม่มีสิทธิ์ใช้งาน']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$allowedTypes = ['text', 'textarea', 'date', 'number', 'checkbox'];
$name = preg_replace('/[^a-zA-Z0-9_]/', '', $data['field_name'] ?? '');
$label = trim($data['field_label'] ?? '');

if (!$data || (int)($data['template_id'] ?? 0) < 1 || $name === '' || $label === '' || !in_array($data['field_type'] ?? '', $allowedTypes, true)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'ข้อมูลช่องกรอกไม่ถูกต้อง']);
    exit;
}

$pageNo = max(1, min(99, (int)($data['page_no'] ?? 1)));
$stmt = $pdo->prepare('INSERT INTO template_fields
    (template_id, field_name, field_label, field_type, page_no, pos_x, pos_y, width, height, font_size, required_field)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
$stmt->execute([
    (int)$data['template_id'], $name, $label, $data['field_type'], $pageNo,
    max(0, (float)($data['pos_x'] ?? 0)), max(0, (float)($data['pos_y'] ?? 0)),
    max(40, (float)($data['width'] ?? 180)), max(20, (float)($data['height'] ?? 35)),
    max(6, min(30, (float)($data['font_size'] ?? 12))), !empty($data['required']) ? 1 : 0,
]);

echo json_encode(['success' => true, 'field_id' => (int)$pdo->lastInsertId()]);
