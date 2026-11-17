<?php

require_once "../config/database.php";

header("Content-Type: application/json; charset=utf-8");

$group_id = $_GET['group_id'] ?? 0;

$stmt = $pdo->prepare("
SELECT

    g.group_id,

    CONCAT(
        e.firstname,
        ' ',
        e.lastname
    ) AS advisor_name

FROM group_class g

LEFT JOIN employee e
ON g.advisor_employee_id = e.employee_id

WHERE g.group_id = ?
");

$stmt->execute([$group_id]);

echo json_encode(
    $stmt->fetch(PDO::FETCH_ASSOC),
    JSON_UNESCAPED_UNICODE
);