<?php

require_once "../config/database.php";

header("Content-Type: application/json; charset=utf-8");

$department_id = $_GET['department_id'] ?? 0;

$stmt = $pdo->prepare("
SELECT
    group_id,
    group_name
FROM group_class
WHERE department_id = ?
ORDER BY group_name
");

$stmt->execute([$department_id]);

echo json_encode(
    $stmt->fetchAll(PDO::FETCH_ASSOC),
    JSON_UNESCAPED_UNICODE
);