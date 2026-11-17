<?php

session_start();

require_once "../../middleware/admin.php";
require_once "../../config/database.php";

header('Location: /Project/admin/group_class/index.php');
exit;

$group_id = $_POST['group_id'] ?? 0;

$department_id = $_POST['department_id'] ?? '';

$group_number = trim($_POST['group_number'] ?? '');

$advisor_employee_id = $_POST['advisor_employee_id'] ?? null;
if (
    !$group_id ||
    !$department_id ||
    !preg_match('/^\d{5}$/', $group_number) ||
    !$advisor_employee_id
) {

    $_SESSION['error'] = "กรุณากรอกข้อมูลให้ครบ";

    header("Location: edit.php?id=".$group_id);
    exit;
}
$stmt = $pdo->prepare("
SELECT department_abbreviation
FROM department
WHERE department_id = ?
");

$stmt->execute([$department_id]);

$department = $stmt->fetch(PDO::FETCH_ASSOC);

if(!$department){

    $_SESSION['error']="ไม่พบข้อมูลสาขา";

    header("Location:index.php");

    exit;
}
$group_name =
    $department['department_abbreviation']
    . "."
    . $group_number;
    $stmt = $pdo->prepare("

UPDATE group_class

SET

    department_id=?,

    group_name=?,

    advisor_employee_id=?

WHERE group_id=?

");

$stmt->execute([

    $department_id,

    $group_name,

    $advisor_employee_id,

    $group_id

]);
$_SESSION['success']="แก้ไขกลุ่มเรียนเรียบร้อยแล้ว";

header("Location:index.php");

exit;
