<?php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/password_helper.php';

// Disabled by default while the project is in plaintext-password test mode.
// This explicit opt-in prevents the historical migration from being run by mistake.
if (getenv('ENABLE_PASSWORD_HASH_MIGRATION') !== '1') {
    fwrite(STDERR, 'password_hash_migration_disabled_in_test_mode' . PHP_EOL);
    exit(1);
}

$pdo->beginTransaction();
try {
    $users = $pdo->query('SELECT user_id,password FROM users FOR UPDATE')->fetchAll(PDO::FETCH_ASSOC);
    $update = $pdo->prepare('UPDATE users SET password = ? WHERE user_id = ?');
    $updated = 0;

    foreach ($users as $user) {
        $stored = (string)($user['password'] ?? '');
        $isPending = $stored === '' || str_starts_with($stored, 'pending-pass');
        if ($isPending || storedPasswordIsHash($stored)) {
            continue;
        }

        $update->execute([password_hash($stored, PASSWORD_DEFAULT), (int)$user['user_id']]);
        $updated++;
    }

    $pdo->commit();
    echo 'passwords_hashed=' . $updated . PHP_EOL;
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    fwrite(STDERR, 'password_migration_failed' . PHP_EOL);
    exit(1);
}
