<?php

declare(strict_types=1);

/**
 * The logged-in landlord's notifications (new reservations, messages, approvals).
 *
 *   GET notifications.php                   ?per_page=
 *   PUT notifications.php?id=3              mark one read
 *   PUT notifications.php?action=read_all   mark all read
 */

require_once __DIR__ . '/_landlord.php';

$pdo = getDb();

function landlordUnread(): int {
    $stmt = getDb()->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = :me AND audience = 'user' AND is_read = 0");
    $stmt->execute([':me' => landlordUserId()]);
    return (int) $stmt->fetchColumn();
}

switch (requestMethod()) {
    case 'GET':
        $limit = queryInt('per_page', 8, 1, 50);
        $stmt = $pdo->prepare("SELECT id, type, title, message, is_read, created_at FROM notifications
            WHERE user_id = :me AND audience = 'user' ORDER BY created_at DESC, id DESC LIMIT {$limit}");
        $stmt->execute([':me' => landlordUserId()]);
        jsonResponse(array_map(fn ($r) => ['is_read' => (bool) $r['is_read']] + castRow($r, ['id']), $stmt->fetchAll()), '', 200, ['unread' => landlordUnread()]);

    case 'PUT':
    case 'PATCH':
        if (queryString('action') === 'read_all') {
            $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = :me AND audience = 'user'")->execute([':me' => landlordUserId()]);
        } else {
            $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = :id AND user_id = :me")->execute([':id' => requireId(), ':me' => landlordUserId()]);
        }
        jsonResponse(null, '', 200, ['unread' => landlordUnread()]);

    default:
        requireMethod('GET', 'PUT', 'PATCH');
}
