<?php

/**
 * Register the bundled TH Sarabun PSK files with TCPDF and return the
 * concrete TCPDF font name for the requested style.
 */
function thSarabunPskFont(string $style = ''): string
{
    static $fonts = [];

    $style = strtoupper($style);
    $style = in_array($style, ['', 'B', 'I', 'BI'], true) ? $style : '';
    if (isset($fonts[$style])) {
        return $fonts[$style];
    }

    $files = [
        '' => 'THSarabun.ttf',
        'B' => 'THSarabun-Bold.ttf',
        'I' => 'THSarabun-Italic.ttf',
        'BI' => 'THSarabun-BoldItalic.ttf',
    ];
    $path = realpath(__DIR__ . '/../assets/fonts/thsarabunpsk/' . $files[$style]);
    if ($path === false) {
        throw new RuntimeException('ไม่พบไฟล์ฟอนต์ TH Sarabun PSK');
    }

    $fontName = TCPDF_FONTS::addTTFfont($path, 'TrueTypeUnicode', '', 96);
    if ($fontName === false) {
        throw new RuntimeException('ไม่สามารถลงทะเบียนฟอนต์ TH Sarabun PSK กับ TCPDF ได้');
    }
    $fonts[$style] = $fontName;
    return $fontName;
}

