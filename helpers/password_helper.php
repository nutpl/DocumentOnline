<?php

/**
 * Temporary test-mode storage. Password hashing is intentionally disabled
 * until the project moves out of the system-testing phase.
 */
function prepareUserPasswordForStorage(string $plainPassword): string
{
    return $plainPassword;
}

function storedPasswordIsHash(string $storedPassword): bool
{
    $info = password_get_info($storedPassword);
    return ($info['algoName'] ?? 'unknown') !== 'unknown';
}

/**
 * Verify test-mode plaintext passwords while retaining compatibility with
 * accounts that were already hashed before test mode was enabled.
 */
function verifyStoredUserPassword(
    string $plainPassword,
    string $storedPassword
): bool {
    if (storedPasswordIsHash($storedPassword)) {
        return password_verify($plainPassword, $storedPassword);
    }

    return $storedPassword !== '' && hash_equals($storedPassword, $plainPassword);
}
