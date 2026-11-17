<?php

function isValidPersonName(string $name, int $maximumLength = 100): bool
{
    $name = trim($name);
    if ($name === '' || mb_strlen($name, 'UTF-8') > $maximumLength) {
        return false;
    }

    // Unicode letters and combining marks; allow a single separator between
    // name parts, including spaces, periods, apostrophes, and hyphens.
    return preg_match("/^[\\p{L}\\p{M}]+(?:[ .'-][\\p{L}\\p{M}]+)*$/u", $name) === 1;
}
