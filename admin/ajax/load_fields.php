<?php

session_start();
require_once "../../config/database.php";

if (($_SESSION['role'] ?? '') !== 'admin') {
    http_response_code(401);
    exit;
}

$template_id = isset($_GET['template_id']) ? (int)$_GET['template_id'] : 0;

$stmt = $pdo->prepare("
SELECT
    field_id,
    field_name,
    field_label,
    field_type,
    page_no,
    pos_x,
    pos_y,
    width,
    height,
    font_size,
    required_field
FROM template_fields
WHERE template_id=?
ORDER BY field_id
");

$stmt->execute([$template_id]);

echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC), JSON_UNESCAPED_UNICODE);
