<?php

declare(strict_types=1);

function source(string $path): string
{
    $contents = file_get_contents(__DIR__ . '/../' . $path);
    if ($contents === false) {
        throw new RuntimeException('Cannot read ' . $path);
    }
    return $contents;
}

$index = source('admin/group_class/index.php');
$create = source('admin/group_class/create.php');
$edit = source('admin/group_class/edit.php');

$checks = [
    'admin group list explains its support role' => str_contains($index, 'แอดมินช่วยเพิ่มหรือแก้ไขข้อมูลกลุ่มเมื่อจำเป็น'),
    'admin can open a support create form' => str_contains($index, 'href="create.php"') && str_contains($index, 'ช่วยเพิ่มกลุ่มเรียน'),
    'admin can open an edit form' => str_contains($index, 'href="edit.php?id='),
    'admin create form is CSRF protected' => str_contains($create, 'verify_csrf_or_abort()') && str_contains($create, 'csrf_field()'),
    'admin create form does not provide deletion' => !str_contains($create, 'DELETE FROM group_class'),
    'admin edit prevents moving a group with students' => str_contains($edit, 'ไม่สามารถย้ายกลุ่มที่มีนักศึกษาไปยังสาขาอื่นได้'),
    'admin edit form is CSRF protected' => str_contains($edit, 'verify_csrf_or_abort()') && str_contains($edit, 'csrf_field()'),
];

foreach ($checks as $label => $passed) {
    if (!$passed) {
        fwrite(STDERR, "FAIL: {$label}\n");
        exit(1);
    }
    echo "PASS: {$label}\n";
}
