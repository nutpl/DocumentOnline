<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/password_helper.php';
require_once __DIR__ . '/../helpers/person_name_validation_helper.php';
require_once __DIR__ . '/../helpers/student_number_helper.php';

$passed = true;
$nameCases = [
    ['พรรณวรา', true],
    ['ณัฐพล อาคเดช', true],
    ["Anne-Marie", true],
    ["O'Connor", true],
    ['12345', false],
    ['สมชาย123', false],
    ['!!!', false],
    ['', false],
];

foreach ($nameCases as [$name, $expected]) {
    $ok = isValidPersonName($name) === $expected;
    echo 'name_' . md5($name) . '=' . ($ok ? 'passed' : 'failed') . PHP_EOL;
    $passed = $passed && $ok;
}

$studentCodeCases = [
    ['671723105960', '67172310596-0'],
    ['67172310596-0', '67172310596-0'],
    ['67172310596A', null],
    ['67172-310596-0', null],
    ['12345', null],
];
foreach ($studentCodeCases as [$input, $expected]) {
    $ok = normalizeStudentCode($input) === $expected;
    echo 'student_number_' . md5($input) . '=' . ($ok ? 'passed' : 'failed') . PHP_EOL;
    $passed = $passed && $ok;
}

$plainPassword = 'LegacyPass123';
$testModeStored = prepareUserPasswordForStorage($plainPassword);
$plainStorageOk = $testModeStored === $plainPassword && !storedPasswordIsHash($testModeStored);
$plainVerifyOk = verifyStoredUserPassword($plainPassword, $testModeStored)
    && !verifyStoredUserPassword('WrongPass123', $testModeStored);

// Accounts hashed before test mode remain usable; logging in does not rewrite them.
$existingHash = password_hash($plainPassword, PASSWORD_DEFAULT);
$hashCompatibilityOk = verifyStoredUserPassword($plainPassword, $existingHash)
    && !verifyStoredUserPassword('WrongPass123', $existingHash);

echo 'password_plaintext_test_mode=' . ($plainStorageOk ? 'passed' : 'failed') . PHP_EOL;
echo 'plaintext_password_verify=' . ($plainVerifyOk ? 'passed' : 'failed') . PHP_EOL;
echo 'existing_hash_compatibility=' . ($hashCompatibilityOk ? 'passed' : 'failed') . PHP_EOL;
$passed = $passed && $plainStorageOk && $plainVerifyOk && $hashCompatibilityOk;

$csrfFiles = [
    'login' => __DIR__ . '/../auth/login.php',
    'login_process' => __DIR__ . '/../auth/login_process.php',
    'register' => __DIR__ . '/../auth/register.php',
    'register_process' => __DIR__ . '/../auth/register_process.php',
];
foreach ($csrfFiles as $label => $path) {
    $contents = (string)file_get_contents($path);
    $needle = str_ends_with($label, '_process') ? 'verify_csrf_or_abort()' : 'csrf_field()';
    $ok = str_contains($contents, $needle);
    echo 'csrf_' . $label . '=' . ($ok ? 'passed' : 'failed') . PHP_EOL;
    $passed = $passed && $ok;
}

exit($passed ? 0 : 1);
