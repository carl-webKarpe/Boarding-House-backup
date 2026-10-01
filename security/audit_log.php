<?php

declare(strict_types=1);

require_once __DIR__ . '/database.php';

/**
 * Records an entry in activity_logs. These entries feed the admin
 * dashboard's "Recent Activities" timeline and the Activity Log page.
 * Logging must never break the request, so errors are only written to the log file.
 */
function auditLog(string $action, string $description, ?int $userId = null, ?string $entityType = null, ?int $entityId = null): void {
    try {
        $pdo = getDb();
        $stmt = $pdo->prepare('INSERT INTO activity_logs (user_id, action, description, entity_type, entity_id, ip_address) VALUES (:user_id, :action, :description, :entity_type, :entity_id, :ip_address)');
        $stmt->execute([
            ':user_id' => $userId,
            ':action' => substr($action, 0, 50),
            ':description' => substr($description, 0, 500),
            ':entity_type' => $entityType,
            ':entity_id' => $entityId,
            ':ip_address' => getClientIp(),
        ]);
    } catch (Throwable $e) {
        writeLog('Activity log failed: ' . $e->getMessage(), 'ERROR');
    }
}

/**
 * Creates a notification shown to every administrator.
 */
function notifyAdmins(string $type, string $title, string $message, ?string $link = null): void {
    try {
        $stmt = getDb()->prepare('INSERT INTO notifications (user_id, audience, type, title, message, link) VALUES (NULL, \'admin\', :type, :title, :message, :link)');
        $stmt->execute([':type' => $type, ':title' => $title, ':message' => $message, ':link' => $link]);
    } catch (Throwable $e) {
        writeLog('Admin notification failed: ' . $e->getMessage(), 'ERROR');
    }
}

/**
 * Creates a notification for a single user.
 */
function notifyUser(int $userId, string $type, string $title, string $message, ?string $link = null): void {
    try {
        $stmt = getDb()->prepare('INSERT INTO notifications (user_id, audience, type, title, message, link) VALUES (:user_id, \'user\', :type, :title, :message, :link)');
        $stmt->execute([':user_id' => $userId, ':type' => $type, ':title' => $title, ':message' => $message, ':link' => $link]);
    } catch (Throwable $e) {
        writeLog('User notification failed: ' . $e->getMessage(), 'ERROR');
    }
}
