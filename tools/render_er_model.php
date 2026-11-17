<?php

require_once __DIR__ . '/../config/database.php';

$output = __DIR__ . '/../docs/er-model-latest.png';
if (!is_dir(dirname($output))) {
    mkdir(dirname($output), 0775, true);
}

$font = 'C:/Windows/Fonts/tahoma.ttf';
$boldFont = 'C:/Windows/Fonts/tahomabd.ttf';
if (!is_file($font) || !is_file($boldFont)) {
    throw new RuntimeException('ไม่พบฟอนต์ Tahoma');
}

$columns = [80, 760, 1440, 2120, 2800, 3480, 4160];
$rows = [300, 700, 1100, 1500, 1900, 2300, 2700];
$positions = [
    'faculty' => [0,0,'org'], 'department' => [0,1,'org'], 'users' => [0,2,'org'],
    'user_roles' => [0,3,'org'], 'password_reset_otp' => [0,4,'org'], 'activity_log' => [0,5,'org'],
    'prefixes' => [1,0,'people'], 'employee' => [1,1,'people'], 'positions' => [1,2,'people'],
    'employee_position' => [1,3,'people'], 'group_class' => [1,4,'people'], 'student' => [1,5,'people'],
    'office_holders' => [1,6,'people'],
    'document_type' => [2,0,'docs'], 'document_template' => [2,1,'docs'], 'template_fields' => [2,2,'docs'],
    'documents' => [2,3,'docs'], 'document_field_values' => [2,4,'docs'], 'document_attachments' => [2,5,'docs'],
    'notifications' => [2,6,'docs'],
    'r01_request' => [3,0,'forms'], 'r12_request' => [3,1,'forms'], 'r12_request_courses' => [3,2,'forms'],
    'r14_request' => [3,3,'forms'], 'r18_request' => [3,5,'forms'],
    'r24_request' => [3,6,'forms'],
    'review_workflows' => [4,0,'workflow'], 'review_workflow_steps' => [4,1,'workflow'],
    'document_review_processes' => [4,2,'workflow'], 'document_review_step_instances' => [4,3,'workflow'],
    'document_events' => [4,4,'workflow'],
    'internship_calendar' => [5,0,'internship'], 'internship_profile' => [5,1,'internship'],
    'internship_application' => [5,2,'internship'], 'internship_supervision' => [5,3,'internship'],
    'internship_tracking' => [5,4,'internship'],
    'internship_official_document' => [6,1,'official'], 'internship_official_student' => [6,2,'official'],
    'internship_excel_sync' => [6,3,'official'],
];

$width = 4800;
$height = 3150;
$boxWidth = 560;
$boxHeight = 300;
$image = imagecreatetruecolor($width, $height);
imageantialias($image, true);
$white = imagecolorallocate($image, 255, 255, 255);
$ink = imagecolorallocate($image, 25, 33, 46);
$muted = imagecolorallocate($image, 84, 98, 116);
$line = imagecolorallocatealpha($image, 90, 105, 125, 72);
$border = imagecolorallocate($image, 160, 174, 192);
imagefill($image, 0, 0, $white);

$palettes = [
    'org' => [[219,234,254],[37,99,235]], 'people' => [[224,242,254],[2,132,199]],
    'docs' => [[254,226,226],[220,38,38]], 'forms' => [[255,237,213],[234,88,12]],
    'workflow' => [[237,233,254],[124,58,237]], 'internship' => [[220,252,231],[22,163,74]],
    'official' => [[254,249,195],[202,138,4]],
];

function color(array $rgb, $image): int { return imagecolorallocate($image, $rgb[0], $rgb[1], $rgb[2]); }
function label($image, string $font, int $size, int $x, int $y, int $color, string $text): void
{
    imagettftext($image, $size, 0, $x, $y, $color, $font, $text);
}

label($image, $boldFont, 42, 80, 70, $ink, 'ER Model — ระบบจัดการเอกสารคำร้องและฝึกประสบการณ์วิชาชีพ');
label($image, $font, 24, 80, 112, $muted, 'ฐานข้อมูล online_request_system • โครงสร้างล่าสุด • document_events เป็นประวัติกลาง');

$zoneNames = ['ข้อมูลพื้นฐาน','บุคลากร','เอกสาร','แบบคำร้อง R','Workflow','ข้อมูลสหกิจ','หนังสือสหกิจ'];
$zoneKeys = ['org','people','docs','forms','workflow','internship','official'];
foreach ($columns as $index => $x) {
    $headerColor = color($palettes[$zoneKeys[$index]][1], $image);
    label($image, $boldFont, 24, $x, 205, $headerColor, $zoneNames[$index]);
}

$node = [];
foreach ($positions as $table => [$column, $row, $group]) {
    $node[$table] = ['x' => $columns[$column], 'y' => $rows[$row], 'group' => $group];
}

$foreignKeys = $pdo->query(
    "SELECT TABLE_NAME, COLUMN_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME
     FROM information_schema.KEY_COLUMN_USAGE
     WHERE TABLE_SCHEMA = DATABASE() AND REFERENCED_TABLE_NAME IS NOT NULL"
)->fetchAll(PDO::FETCH_ASSOC);

foreach ($foreignKeys as $foreignKey) {
    $childName = strtolower($foreignKey['TABLE_NAME']);
    $parentName = strtolower($foreignKey['REFERENCED_TABLE_NAME']);
    if (!isset($node[$childName], $node[$parentName])) { continue; }
    $child = $node[$childName];
    $parent = $node[$parentName];
    $parentX = $parent['x']; $parentY = $parent['y'];
    $childX = $child['x']; $childY = $child['y'];
    if ($parentX === $childX) {
        $offset = 20 + (crc32($childName . $parentName) % 45);
        $x = $parentX - $offset;
        imageline($image, $parentX, $parentY + $boxHeight / 2, $x, $parentY + $boxHeight / 2, $line);
        imageline($image, $x, $parentY + $boxHeight / 2, $x, $childY + $boxHeight / 2, $line);
        imageline($image, $x, $childY + $boxHeight / 2, $childX, $childY + $boxHeight / 2, $line);
    } else {
        $startX = $parentX < $childX ? $parentX + $boxWidth : $parentX;
        $endX = $parentX < $childX ? $childX : $childX + $boxWidth;
        $startY = $parentY + $boxHeight / 2;
        $endY = $childY + $boxHeight / 2;
        $midX = (int)(($startX + $endX) / 2);
        imageline($image, $startX, $startY, $midX, $startY, $line);
        imageline($image, $midX, $startY, $midX, $endY, $line);
        imageline($image, $midX, $endY, $endX, $endY, $line);
    }
}

$columnStatement = $pdo->prepare(
    "SELECT c.COLUMN_NAME,
            CASE WHEN c.COLUMN_KEY='PRI' THEN 'PK'
                 WHEN k.REFERENCED_TABLE_NAME IS NOT NULL THEN 'FK' ELSE '' END AS key_type,
            c.DATA_TYPE
     FROM information_schema.COLUMNS c
     LEFT JOIN information_schema.KEY_COLUMN_USAGE k
       ON k.TABLE_SCHEMA=c.TABLE_SCHEMA AND k.TABLE_NAME=c.TABLE_NAME
      AND k.COLUMN_NAME=c.COLUMN_NAME AND k.REFERENCED_TABLE_NAME IS NOT NULL
     WHERE c.TABLE_SCHEMA=DATABASE() AND c.TABLE_NAME=?
       AND (c.COLUMN_KEY='PRI' OR k.REFERENCED_TABLE_NAME IS NOT NULL)
     ORDER BY c.ORDINAL_POSITION"
);

foreach ($positions as $table => [$column, $row, $group]) {
    $x = $columns[$column]; $y = $rows[$row];
    $fill = color($palettes[$group][0], $image);
    $header = color($palettes[$group][1], $image);
    imagefilledrectangle($image, $x, $y, $x + $boxWidth, $y + $boxHeight, $white);
    imagerectangle($image, $x, $y, $x + $boxWidth, $y + $boxHeight, $border);
    imagefilledrectangle($image, $x, $y, $x + $boxWidth, $y + 58, $header);
    imagefilledrectangle($image, $x + 1, $y + 59, $x + $boxWidth - 1, $y + $boxHeight - 1, $fill);
    $tableLabel = $table;
    $headerSize = 21;
    while ($headerSize > 14) {
        $bounds = imagettfbbox($headerSize, 0, $boldFont, $tableLabel);
        if (($bounds[2] - $bounds[0]) <= $boxWidth - 32) { break; }
        $headerSize--;
    }
    label($image, $boldFont, $headerSize, $x + 16, $y + 39, $white, $tableLabel);
    $columnStatement->execute([$table]);
    $fields = $columnStatement->fetchAll(PDO::FETCH_ASSOC);
    $fieldY = $y + 94;
    foreach (array_slice($fields, 0, 7) as $field) {
        $key = $field['key_type'] !== '' ? '[' . $field['key_type'] . '] ' : '';
        label($image, $font, 18, $x + 18, $fieldY, $ink, $key . $field['COLUMN_NAME']);
        $fieldY += 29;
    }
    if (count($fields) > 7) {
        label($image, $font, 17, $x + 18, $fieldY, $muted, '+ คีย์อื่น ' . (count($fields) - 7) . ' รายการ');
    }
}

label($image, $font, 20, 80, 3085, $muted, 'PK = Primary Key   •   FK = Foreign Key   •   เส้นเชื่อมแสดงความสัมพันธ์จาก Foreign Key ไปยังตารางหลัก');
imagepng($image, $output, 7);
imagedestroy($image);
echo $output;
