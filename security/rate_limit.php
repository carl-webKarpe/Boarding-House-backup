<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/database.php';

function checkRateLimit(string $scope, string $identifier, int $maxAttempts, int $windowMinutes): bool {
    $file = BH_RATE_LIMIT_FILE_DIR . '/' . $scope . '_' . md5($identifier) . '.json';
    $now = time();

    $data = ['attempts' => 0, 'window_start' => $now];
    if (is_file($file)) {
        $decoded = json_decode((string) file_get_contents($file), true);
        if (is_array($decoded)) {
            $data = $decoded;
        }
    }

    if (($now - (int) ($data['window_start'] ?? $now)) > ($windowMinutes * 60)) {
        $data = ['attempts' => 0, 'window_start' => $now];
    }

    if ((int) ($data['attempts'] ?? 0) >= $maxAttempts) {
        return false;
    }

    $data['attempts'] = (int) ($data['attempts'] ?? 0) + 1;
    $data['window_start'] = (int) ($data['window_start'] ?? $now);
    @file_put_contents($file, json_encode($data));
    return true;
}

function recordFailedLogin(string $email): void {
    $file = BH_RATE_LIMIT_FILE_DIR . '/failed_login_' . md5($email) . '.json';
    $now = time();

    $data = ['attempts' => 0, 'window_start' => $now];
    if (is_file($file)) {
        $decoded = json_decode((string) file_get_contents($file), true);
        if (is_array($decoded)) {
            $data = $decoded;
        }
    }

    $data['attempts'] = (int) ($data['attempts'] ?? 0) + 1;
    $data['window_start'] = $now;
    @file_put_contents($file, json_encode($data));

    try {
        $pdo = getDb();
        $stmt = $pdo->prepare('SELECT id, failed_login_attempts, locked_until FROM users WHERE email = :email LIMIT 1');
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch();
        if (!$user) {
            return;
        }

        $previousAttempts = (int) $user['failed_login_attempts'];
        if (!empty($user['locked_until']) && strtotime((string) $user['locked_until']) <= time()) {
            // The previous lock has expired, so start counting again.
            $previousAttempts = 0;
        }
        $newAttempts = $previousAttempts + 1;
        $lockedUntil = null;
        if ($newAttempts >= BH_MAX_LOGIN_ATTEMPTS) {
            $lockedUntil = (new DateTimeImmutable('+' . BH_LOCKOUT_MINUTES . ' minutes'))->format('Y-m-d H:i:s');
        }

        $stmt = $pdo->prepare('UPDATE users SET failed_login_attempts = :attempts, locked_until = :locked_until WHERE id = :id');
        $stmt->execute([
            ':attempts' => $newAttempts,
            ':locked_until' => $lockedUntil,
            ':id' => $user['id'],
        ]);
    } catch (Throwable $e) {
        writeLog('Failed-login persistence failed: ' . $e->getMessage(), 'ERROR');
    }
}
