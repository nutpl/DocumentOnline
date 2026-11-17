<?php

require_once "../config/database.php";

header("Content-Type: application/json; charset=utf-8");

$faculty_id = $_GET['faculty_id'] ?? 0;

$stmt = $pdo->prepare("
SELECT
    department_id,
    department_name,
    department_abbreviation
FROM department
WHERE faculty_id = ?
ORDER BY department_name
");

$stmt->execute([$faculty_id]);

echo json_encode(
    $stmt->fetchAll(PDO::FETCH_ASSOC),
    JSON_UNESCAPED_UNICODE
);