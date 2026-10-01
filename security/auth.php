<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/sanitize.php';
require_once __DIR__ . '/validation.php';
require_once __DIR__ . '/session.php';
require_once __DIR__ . '/audit_log.php';
require_once __DIR__ . '/rate_limit.php';

function loginUser(string $email, string $password, bool $rememberMe = false): array {
    startSecureSession();

    if (!checkRateLimit('login', getClientIp(), 5, 15)) {
        writeLog('Rate limit exceeded for login from ' . getClientIp(), 'WARN');
        return ['success' => false, 'message' => 'Too many login attempts. Please try again later.'];
    }

    $email = sanitizeEmail($email);
    $validation = validateEmailValue($email);
    if (!$validation['valid']) {
        return ['success' => false, 'message' => $validation['message']];
    }

    if (trim($password) === '') {
        return ['success' => false, 'message' => 'Password is required.'];
    }

    try {
        $pdo = getDb();
        $stmt = $pdo->prepare('SELECT id, username, email, password_hash, role, is_active, failed_login_attempts, locked_until FROM users WHERE email = :email LIMIT 1');
        $stmt->execute([':email' => $validation['value']]);
        $user = $stmt->fetch();

        if (!$user) {
            recordFailedLogin($email);
            return ['success' => false, 'message' => 'Invalid credentials.'];
        }

        if (!empty($user['locked_until']) && strtotime((string) $user['locked_until']) > time()) {
            return ['success' => false, 'message' => 'Account is temporarily locked. Please try again later.'];
        }

        if (!password_verify($password, (string) $user['password_hash'])) {
            recordFailedLogin($email);
            return ['success' => false, 'message' => 'Invalid credentials.'];
        }

        if ((int) $user['is_active'] !== 1) {
            return ['success' => false, 'message' => 'Account is not active.'];
        }

        $stmt = $pdo->prepare('UPDATE users SET failed_login_attempts = 0, locked_until = NULL WHERE id = :id');
        $stmt->execute([':id' => $user['id']]);

        session_regenerate_id(true);
        $_SESSION['user_id'] = (int) $user['id'];
        $_SESSION['username'] = (string) $user['username'];
        $_SESSION['email'] = (string) $user['email'];
        $_SESSION['role'] = (string) $user['role'];
        $_SESSION['is_logged_in'] = true;

        // "Remember me" only pre-fills the email on the client; clear the old
        // insecure cookie that stored the email in plain base64.
        setcookie('remember_me', '', time() - 3600, '/', '', isHttps(), true);

        auditLog('login', 'User logged in', (int) $user['id']);

        return ['success' => true, 'message' => 'Login successful.', 'username' => (string) $user['username'], 'role' => (string) $user['role']];
    } catch (Throwable $e) {
        writeLog('Login error: ' . $e->getMessage(), 'ERROR');
        return ['success' => false, 'message' => 'Login failed.'];
    }
}

function registerUser(array $data): array {
    startSecureSession();
    $emailValidation = validateEmailValue($data['email'] ?? '');
    if (!$emailValidation['valid']) {
        return ['success' => false, 'message' => $emailValidation['message']];
    }

    $usernameValidation = validateUsernameValue($data['username'] ?? '');
    if (!$usernameValidation['valid']) {
        return ['success' => false, 'message' => $usernameValidation['message']];
    }

    $passwordValidation = validatePasswordValue($data['password'] ?? '');
    if (!$passwordValidation['valid']) {
        return ['success' => false, 'message' => $passwordValidation['message']];
    }

    if (($data['password'] ?? '') !== ($data['confirmPassword'] ?? '')) {
        return ['success' => false, 'message' => 'Passwords do not match.'];
    }

    try {
        $pdo = getDb();
        $stmt = $pdo->prepare('SELECT id FROM users WHERE email = :email OR username = :username LIMIT 1');
        $stmt->execute([':email' => $emailValidation['value'], ':username' => $usernameValidation['value']]);
        if ($stmt->fetch()) {
            return ['success' => false, 'message' => 'Email or username already exists.'];
        }

        $passwordHash = password_hash((string) $data['password'], PASSWORD_DEFAULT);
        $stmt = $pdo->prepare('INSERT INTO users (username, email, password_hash, role, is_active, created_at) VALUES (:username, :email, :password_hash, :role, 1, NOW())');
        $allowedRoles = ['user', 'tenant', 'landlord'];
        $role = in_array($data['role'] ?? 'user', $allowedRoles, true) ? $data['role'] : 'user';

        $stmt->execute([
            ':username' => $usernameValidation['value'],
            ':email' => $emailValidation['value'],
            ':password_hash' => $passwordHash,
            ':role' => $role,
        ]);

        auditLog('create', 'User registered', (int) $pdo->lastInsertId());
        return ['success' => true, 'message' => 'Registration successful.'];
    } catch (Throwable $e) {
        writeLog('Registration error: ' . $e->getMessage(), 'ERROR');
        return ['success' => false, 'message' => 'Registration failed.'];
    }
}

function changePassword(int $userId, string $newPassword): bool {
    $passwordValidation = validatePasswordValue($newPassword);
    if (!$passwordValidation['valid']) {
        return false;
    }

    try {
        $pdo = getDb();
        $stmt = $pdo->prepare('UPDATE users SET password_hash = :password_hash WHERE id = :id');
        $stmt->execute([':password_hash' => password_hash($newPassword, PASSWORD_DEFAULT), ':id' => $userId]);
        auditLog('password_change', 'Password changed', $userId);
        return true;
    } catch (Throwable $e) {
        writeLog('Password update failed: ' . $e->getMessage(), 'ERROR');
        return false;
    }
}
