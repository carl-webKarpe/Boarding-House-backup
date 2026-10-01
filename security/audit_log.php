<?php

declare(strict_types=1);

require_once __DIR__ . '/database.php';

function auditLog(string $action, string $details, ?int $userId = null): void {
    try {
        $pdo = getDb();
        $stmt = $pdo->prepare('INSERT INTO audit_logs (user_id, ip_address, action, details, created_at) VALUES (:user_id, :ip_address, :action, :details, NOW())');
        $stmt->execute([
            ':user_id' => $userId,
            ':ip_address' => getClientIp(),
            ':action' => $action,
            ':details' => $details,
        ]);
    } catch (Throwable $e) {
        writeLog('Audit log failed: ' . $e->getMessage(), 'ERROR');
    }
}
