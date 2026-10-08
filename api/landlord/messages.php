<?php

declare(strict_types=1);

/**
 * Messages ("Contact Landlord" inquiries) sent to the logged-in landlord.
 *
 *   GET messages.php                 ?status=new|replied|closed|all
 *   GET messages.php?id=4            opens a message (marks it read)
 *   PUT messages.php?id=4            { reply }   answer the student
 *   PUT messages.php?id=4&action=close
 */

require_once __DIR__ . '/_landlord.php';

const MESSAGE_SELECT = "SELECT i.id, i.sender_name, i.sender_email, i.sender_phone, i.message, i.status, i.reply, i.replied_at, i.created_at,
    i.tenant_id, i.room_id, bh.id AS house_id, bh.name AS house_name, r.room_number
    FROM inquiries i JOIN boarding_houses bh ON bh.id = i.boarding_house_id LEFT JOIN rooms r ON r.id = i.room_id";

function loadOwnMessage(int $id): array {
    $stmt = getDb()->prepare(MESSAGE_SELECT . ' WHERE i.id = :id AND i.landlord_user_id = :me');
    $stmt->execute([':id' => $id, ':me' => landlordUserId()]);
    $row = $stmt->fetch();
    if (!$row) {
        notFound('Message');
    }

    $row = castRow($row, ['id', 'tenant_id', 'room_id', 'house_id']);
    $row['is_registered'] = $row['tenant_id'] !== null;
    return $row;
}

$pdo = getDb();

switch (requestMethod()) {
    case 'GET':
        if (isset($_GET['id'])) {
            $message = loadOwnMessage(requireId());
            if ($message['status'] === 'new') {
                $pdo->prepare("UPDATE inquiries SET status = 'read' WHERE id = :id")->execute([':id' => $message['id']]);
                $message['status'] = 'read';
            }
            jsonResponse($message);
        }

        $where = ['i.landlord_user_id = :me'];
        $params = [':me' => landlordUserId()];
        $status = queryString('status');
        if ($status === 'new') {
            $where[] = "i.status IN ('new', 'read')";
        } elseif (in_array($status, ['replied', 'closed'], true)) {
            $where[] = 'i.status = :status';
            $params[':status'] = $status;
        }
        $stmt = $pdo->prepare(MESSAGE_SELECT . ' WHERE ' . implode(' AND ', $where) . ' ORDER BY i.created_at DESC, i.id DESC LIMIT 200');
        $stmt->execute($params);

        $counts = $pdo->prepare("SELECT COUNT(*) AS all_messages, COALESCE(SUM(status = 'new'), 0) AS unread,
                COALESCE(SUM(status IN ('new', 'read')), 0) AS open_messages, COALESCE(SUM(status = 'replied'), 0) AS replied, COALESCE(SUM(status = 'closed'), 0) AS closed
            FROM inquiries WHERE landlord_user_id = :me");
        $counts->execute([':me' => landlordUserId()]);

        jsonResponse(array_map(fn ($r) => castRow($r, ['id', 'tenant_id', 'room_id', 'house_id']), $stmt->fetchAll()), '', 200, [
            'counts' => array_map('intval', $counts->fetch()),
        ]);

    case 'PUT':
    case 'PATCH':
        $message = loadOwnMessage(requireId());
        if (queryString('action') === 'close') {
            $pdo->prepare("UPDATE inquiries SET status = 'closed' WHERE id = :id")->execute([':id' => $message['id']]);
            jsonResponse(loadOwnMessage($message['id']), 'Conversation closed.');
        }

        $values = collectValidated(['reply' => validateTextValue(requestBody()['reply'] ?? '', 'Reply', 2000)]);
        $pdo->prepare("UPDATE inquiries SET reply = :reply, status = 'replied', replied_at = NOW() WHERE id = :id")
            ->execute([':reply' => $values['reply'], ':id' => $message['id']]);
        if ($message['tenant_id']) {
            notifyUser($message['tenant_id'], 'inquiry_reply', 'The landlord replied',
                landlordName() . ' replied about ' . $message['house_name'] . ': "' . mb_strimwidth((string) $values['reply'], 0, 120, '…') . '"');
        }
        landlordLog('inquiry_reply', 'Landlord replied to ' . $message['sender_name'] . ' about ' . $message['house_name'], 'inquiry', $message['id']);
        jsonResponse(loadOwnMessage($message['id']), $message['tenant_id']
            ? 'Reply sent. The student will see it in My Reservations.'
            : 'Reply saved. This visitor has no account, so also contact them by email or phone.');

    default:
        requireMethod('GET', 'PUT', 'PATCH');
}
