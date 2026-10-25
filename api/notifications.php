<?php

declare(strict_types=1);

/**
 * The logged-in user's own notifications (the student's bell on Browse Rooms):
 * reservation approved / rejected, landlord replies, rent recorded, new chat messages.
 *
 *   GET api/notifications.php                       ?per_page=   latest first, + unread count
 *   PUT api/notifications.php?id=3                  mark one read
 *   PUT api/notifications.php?action=read_all       mark all read
 *   PUT api/notifications.php?action=read_type&type=chat   mark one kind read (e.g. after opening the chat)
 */

require_once __DIR__ . '/_common.php';
require_once __DIR__ . '/_queries.php';

apiBootstrap();

if (!isLoggedIn()) {
    jsonError('Please log in to continue.', 401);
}
if (requestMethod() !== 'GET') {
    requireCsrf();
}

$pdo = getDb();
$me = (int) currentUserId();

$unread = static function () use ($pdo, $me): int {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = :me AND audience = 'user' AND is_read = 0");
    $stmt->execute([':me' => $me]);
    return (int) $stmt->fetchColumn();
};

switch (requestMethod()) {
    case 'GET':
        $limit = queryInt('per_page', 10, 1, 50);
        $stmt = $pdo->prepare("SELECT id, type, title, message, is_read, created_at FROM notifications
            WHERE user_id = :me AND audience = 'user' ORDER BY created_at DESC, id DESC LIMIT {$limit}");
        $stmt->execute([':me' => $me]);
        $rows = array_map(static fn ($r) => ['is_read' => (bool) $r['is_read']] + castRow($r, ['id']), $stmt->fetchAll());
        jsonResponse($rows, '', 200, ['unread' => $unread()]);

    case 'PUT':
    case 'PATCH':
        $action = queryString('action');
        if ($action === 'read_all') {
            $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = :me AND audience = 'user'")->execute([':me' => $me]);
        } elseif ($action === 'read_type') {
            $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = :me AND audience = 'user' AND type = :type")
                ->execute([':me' => $me, ':type' => substr(queryString('type'), 0, 40)]);
        } else {
            $pdo->prepare('UPDATE notifications SET is_read = 1 WHERE id = :id AND user_id = :me')->execute([':id' => requireId(), ':me' => $me]);
        }
        jsonResponse(null, '', 200, ['unread' => $unread()]);

    default:
        requireMethod('GET', 'PUT', 'PATCH');
}
