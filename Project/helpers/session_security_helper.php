<?php

function issueUserSessionToken(PDO $pdo, int $userId): string
{
    $token = bin2hex(random_bytes(32));
    $tokenHash = hash('sha256', $token);
    $statement = $pdo->prepare(
        'UPDATE users
         SET active_session_token_hash = ?, active_session_at = NOW()
         WHERE user_id = ? AND status = "active"'
    );
    $statement->execute([$tokenHash, $userId]);
    if ($statement->rowCount() !== 1) {
        throw new RuntimeException('ไม่สามารถสร้าง Session สำหรับบัญชีนี้ได้');
    }
    return $token;
}

function isCurrentUserSession(PDO $pdo, int $userId, string $token): bool
{
    if ($userId < 1 || $token === '') {
        return false;
    }
    $statement = $pdo->prepare(
        'SELECT active_session_token_hash
         FROM users
         WHERE user_id = ? AND status = "active" LIMIT 1'
    );
    $statement->execute([$userId]);
    $storedHash = (string) $statement->fetchColumn();
    return $storedHash !== '' && hash_equals($storedHash, hash('sha256', $token));
}

function revokeUserSessionToken(PDO $pdo, int $userId, string $token): void
{
    if ($userId < 1 || $token === '') {
        return;
    }
    $statement = $pdo->prepare(
        'UPDATE users
         SET active_session_token_hash = NULL, active_session_at = NULL
         WHERE user_id = ? AND active_session_token_hash = ?'
    );
    $statement->execute([$userId, hash('sha256', $token)]);
}

function destroyLocalUserSession(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }
    session_destroy();
}
