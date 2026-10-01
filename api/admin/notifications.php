<?php

declare(strict_types=1);

/**
 * Administrator notifications.
 *
 *   GET    notifications.php                   list ?filter=all|unread&type=&page=&per_page=
 *   PUT    notifications.php?id=3              { is_read: true|false }
 *   PUT    notifications.php?action=read_all   mark everything read
 *   DELETE notifications.php?id=3              delete one
 *   DELETE notifications.php?action=clear_read delete every read notification
 */

require_once __DIR__ . '/_admin.php';

$pdo = getDb();

/**
 * Notification types the admin switched off in Settings > Notification preferences.
 */
function mutedTypesSql(): string {
    $map = [
        'new_users' => ['user_registered'],
        'new_listings' => ['landlord_registered', 'listing_submitted', 'listing_pending'],
        'bookings' => ['booking_created', 'booking_cancelled'],
        'system' => ['system'],
    ];
    $stmt = getDb()->prepare('SELECT notification_prefs FROM users WHERE id = :id');
    $stmt->execute([':id' => adminId()]);
    $prefs = json_decode((string) $stmt->fetchColumn(), true);
    $muted = [];
    foreach ($map as $pref => $types) {
        if (is_array($prefs) && array_key_exists($pref, $prefs) && !$prefs[$pref]) {
            array_push($muted, ...$types);
        }
    }

    return $muted ? ' AND n.type NOT IN (' . implode(',', array_map(fn ($t) => getDb()->quote($t), $muted)) . ')' : '';
}

$scope = "(n.audience = 'admin' OR n.user_id = :me)" . mutedTypesSql();

function loadNotification(int $id): array {
    $stmt = getDb()->prepare("SELECT n.id, n.type, n.title, n.message, n.link, n.is_read, n.created_at FROM notifications n WHERE n.id = :id AND (n.audience = 'admin' OR n.user_id = :me)");
    $stmt->execute([':id' => $id, ':me' => adminId()]);
    $row = $stmt->fetch();
    if (!$row) {
        notFound('Notification');
    }

    return ['is_read' => (bool) $row['is_read']] + castRow($row, ['id']);
}

function unreadCount(): int {
    global $scope;
    $stmt = getDb()->prepare("SELECT COUNT(*) FROM notifications n WHERE {$scope} AND n.is_read = 0");
    $stmt->execute([':me' => adminId()]);
    return (int) $stmt->fetchColumn();
}

switch (requestMethod()) {
    case 'GET':
        $where = [$scope];
        $params = [':me' => adminId()];
        if (queryString('filter') === 'unread') {
            $where[] = 'n.is_read = 0';
        }
        $type = queryString('type');
        if ($type !== '') {
            $where[] = 'n.type = :type';
            $params[':type'] = $type;
        }

        [$rows, $meta] = pagedQuery(
            'SELECT n.id, n.type, n.title, n.message, n.link, n.is_read, n.created_at',
            'FROM notifications n WHERE ' . implode(' AND ', $where),
            $params,
            ' ORDER BY n.created_at DESC, n.id DESC',
            paginationParams(15)
        );

        jsonResponse(array_map(fn ($r) => ['is_read' => (bool) $r['is_read']] + castRow($r, ['id']), $rows), '', 200, [
            'meta' => $meta,
            'unread' => unreadCount(),
        ]);

    case 'PUT':
    case 'PATCH':
        if (queryString('action') === 'read_all') {
            $pdo->prepare("UPDATE notifications n SET n.is_read = 1 WHERE {$scope}")->execute([':me' => adminId()]);
            jsonResponse(['unread' => 0], 'All notifications marked as read.');
        }

        $id = requireId();
        loadNotification($id);
        $isRead = filter_var(requestBody()['is_read'] ?? true, FILTER_VALIDATE_BOOLEAN);
        $pdo->prepare('UPDATE notifications SET is_read = :read WHERE id = :id')->execute([':read' => $isRead ? 1 : 0, ':id' => $id]);
        jsonResponse(loadNotification($id), '', 200, ['unread' => unreadCount()]);

    case 'DELETE':
        if (queryString('action') === 'clear_read') {
            $pdo->prepare("DELETE n FROM notifications n WHERE {$scope} AND n.is_read = 1")->execute([':me' => adminId()]);
            jsonResponse(null, 'Read notifications cleared.', 200, ['unread' => unreadCount()]);
        }

        $id = requireId();
        loadNotification($id);
        $pdo->prepare('DELETE FROM notifications WHERE id = :id')->execute([':id' => $id]);
        jsonResponse(null, 'Notification deleted.', 200, ['unread' => unreadCount()]);

    default:
        requireMethod('GET', 'PUT', 'PATCH', 'DELETE');
}
