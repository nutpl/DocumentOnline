<?php

date_default_timezone_set('Asia/Bangkok');
require_once __DIR__ . '/../helpers/alert_response_helper.php';

/*
 * XAMPP ใช้ค่าเริ่มต้นด้านล่าง ส่วน Docker กำหนดค่า DB_* ผ่าน environment
 * โดยห้ามใช้ localhost ใน container ของเว็บ เพราะ MySQL อยู่ที่ service "db".
 */
$host = getenv('DB_HOST') ?: (is_file('/.dockerenv') ? 'db' : 'localhost');
$port = getenv('DB_PORT') ?: '3306';
$dbname = getenv('DB_NAME') ?: 'online_request';
$username = getenv('DB_USER') ?: 'root';
$password = getenv('DB_PASSWORD');
if ($password === false) {
    $password = '';
}

try {

    $pdo = new PDO(
        "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4",
        $username,
        $password
    );

    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $pdo->exec("SET time_zone = '+07:00'");

} catch (PDOException $e) {

    die("Database Error : " . $e->getMessage());

}
