<?php
declare(strict_types=1);

/**
 * Staff choose the running number every semester.  The visible official
 * suffix is always rendered as "ว." plus Thai numerals, e.g. ว.๔๖๐.
 */
function officialFormatDocumentNumber(string $number): string
{
    $number = trim($number);
    if ($number === '') return '';

    $slashPosition = mb_strrpos($number, '/');
    if ($slashPosition === false) return officialDocumentNumberThaiDigits($number);

    $prefix = mb_substr($number, 0, $slashPosition + 1);
    $suffix = trim(mb_substr($number, $slashPosition + 1));
    $arabicSuffix = strtr($suffix, [
        '๐' => '0', '๑' => '1', '๒' => '2', '๓' => '3', '๔' => '4',
        '๕' => '5', '๖' => '6', '๗' => '7', '๘' => '8', '๙' => '9',
    ]);
    $arabicSuffix = preg_replace('/^ว\s*\.\s*/u', '', $arabicSuffix) ?? $arabicSuffix;

    if (preg_match('/^\d+$/', $arabicSuffix)) {
        return officialDocumentNumberThaiDigits($prefix) . 'ว.' . officialDocumentNumberThaiDigits($arabicSuffix);
    }

    // Preserve an intentionally non-standard suffix rather than losing data.
    return officialDocumentNumberThaiDigits($prefix . $suffix);
}

function officialDocumentNumberThaiDigits(string $text): string
{
    return strtr($text, [
        '0' => '๐', '1' => '๑', '2' => '๒', '3' => '๓', '4' => '๔',
        '5' => '๕', '6' => '๖', '7' => '๗', '8' => '๘', '9' => '๙',
    ]);
}
