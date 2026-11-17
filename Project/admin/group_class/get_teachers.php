<?php

require_once __DIR__ . "/../../middleware/admin.php";
require_once __DIR__ . "/../../config/database.php";

$department_id=$_GET['department_id'] ?? 0;

$stmt=$pdo->prepare("

SELECT

e.employee_id,
e.firstname,
e.lastname,
CASE
    WHEN COALESCE(p.prefix_short,p.prefix_name,'') IN ('นาย','นาง','นางสาว') THEN 'อาจารย์'
    ELSE COALESCE(p.prefix_short,p.prefix_name,'')
END AS prefix_name

FROM employee e

LEFT JOIN prefixes p ON p.prefix_id=e.prefix_id

WHERE

e.employee_type='teacher'

AND

e.department_id=?

ORDER BY e.firstname,e.lastname

");

$stmt->execute([$department_id]);

echo json_encode(

$stmt->fetchAll(PDO::FETCH_ASSOC)

);
