<?php

require_once __DIR__ . "/../../middleware/admin.php";
require_once __DIR__ . "/../../config/database.php";

header('Content-Type: application/json; charset=utf-8');

$faculty_id = $_GET['faculty_id'] ?? '';

if (!$faculty_id) {
    echo json_encode([]);
    exit;
}

try {

    $stmt = $pdo->prepare("
        SELECT
            department_id,
            department_name,
            department_abbreviation
        FROM department
        WHERE faculty_id = ?
        ORDER BY department_name ASC
    ");

    $stmt->execute([$faculty_id]);

    $departments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(
        $departments,
        JSON_UNESCAPED_UNICODE
    );

} catch (PDOException $e) {

    http_response_code(500);

    echo json_encode([
        "error" => $e->getMessage()
    ]);

}