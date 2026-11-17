<?php
declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

$url = 'https://shorturl.asia/JYz5h';
$target = __DIR__ . '/../templates/official_internship/evaluation_steps_qr.png';
$barcode = new TCPDF2DBarcode($url, 'QRCODE,H');
$matrix = $barcode->getBarcodeArray();
$moduleSize = 8;
$quietZone = 4 * $moduleSize;
$width = ($matrix['num_cols'] * $moduleSize) + (2 * $quietZone);
$height = ($matrix['num_rows'] * $moduleSize) + (2 * $quietZone);
$image = imagecreatetruecolor($width, $height);
$white = imagecolorallocate($image, 255, 255, 255);
$black = imagecolorallocate($image, 0, 0, 0);
imagefill($image, 0, 0, $white);
for ($row = 0; $row < $matrix['num_rows']; $row++) {
    for ($column = 0; $column < $matrix['num_cols']; $column++) {
        if (empty($matrix['bcode'][$row][$column])) continue;
        $x = $quietZone + ($column * $moduleSize);
        $y = $quietZone + ($row * $moduleSize);
        imagefilledrectangle($image, $x, $y, $x + $moduleSize - 1, $y + $moduleSize - 1, $black);
    }
}
imagepng($image, $target, 9);
imagedestroy($image);
echo $target, PHP_EOL;
