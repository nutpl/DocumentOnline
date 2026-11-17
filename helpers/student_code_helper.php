<?php

/**
 * Accept twelve digits with or without the display hyphen and return the
 * canonical university format: eleven digits, a hyphen, and one check digit.
 */
function normalizeStudentCode(string $studentCode): ?string
{
    $studentCode = trim($studentCode);
    if (preg_match('/^\d{11}-\d$/', $studentCode) === 1) {
        return $studentCode;
    }

    if (preg_match('/^\d{12}$/', $studentCode) === 1) {
        return substr($studentCode, 0, 11) . '-' . substr($studentCode, 11, 1);
    }

    return null;
}
