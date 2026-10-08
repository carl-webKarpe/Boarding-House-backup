<?php

declare(strict_types=1);

/**
 * Landlord <-> tenant chat, shared by api/landlord/chat.php and api/chat.php.
 *
 * A conversation belongs to exactly one landlord and one tenant. Both sides
 * can only open conversations they are part of; a new conversation can only
 * be started between a landlord and a tenant who already have a reservation
 * or a "Contact Landlord" message between them.
 */

const CHAT_MAX_LENGTH = 2000;

/** True when this landlord and tenant are connected by a reservation, a message or an earlier chat. */
function chatAllowed(int $landlordUserId, int $tenantId): bool {
    $stmt = getDb()->prepare("SELECT
        EXISTS (SELECT 1 FROM bookings b JOIN rooms r ON r.id = b.room_id JOIN boarding_houses bh ON bh.id = r.boarding_house_id
                JOIN landlords l ON l.id = bh.landlord_id WHERE l.user_id = :l1 AND b.tenant_id = :t1)
        OR EXISTS (SELECT 1 FROM inquiries i WHERE i.landlord_user_id = :l2 AND i.tenant_id = :t2)
        OR EXISTS (SELECT 1 FROM conversations c WHERE c.landlord_user_id = :l3 AND c.tenant_id = :t3)");
    $stmt->execute([':l1' => $landlordUserId, ':t1' => $tenantId, ':l2' => $landlordUserId, ':t2' => $tenantId, ':l3' => $landlordUserId, ':t3' => $tenantId]);
    return (bool) $stmt->fetchColumn();
}

/** Returns the conversation id for this pair, creating it when needed. */
function chatOpen(int $landlordUserId, int $tenantId): int {
    if (!chatAllowed($landlordUserId, $tenantId)) {
        throw new ApiException('You can only chat with landlords and tenants you have a reservation or message with.', 403);
    }
    $pdo = getDb();
    $pdo->prepare('INSERT IGNORE INTO conversations (landlord_user_id, tenant_id) VALUES (:l, :t)')
        ->execute([':l' => $landlordUserId, ':t' => $tenantId]);
    $stmt = $pdo->prepare('SELECT id FROM conversations WHERE landlord_user_id = :l AND tenant_id = :t');
    $stmt->execute([':l' => $landlordUserId, ':t' => $tenantId]);
    return (int) $stmt->fetchColumn();
}

/** Loads a conversation the user is part of, or answers 404. */
function chatConversation(int $conversationId, int $userId): array {
    $stmt = getDb()->prepare('SELECT c.*, lu.first_name AS l_first, lu.last_name AS l_last, lu.username AS l_username, lu.avatar_path AS l_avatar,
            tu.first_name AS t_first, tu.last_name AS t_last, tu.username AS t_username, tu.avatar_path AS t_avatar, tu.contact_number AS t_phone, tu.email AS t_email
        FROM conversations c JOIN users lu ON lu.id = c.landlord_user_id JOIN users tu ON tu.id = c.tenant_id
        WHERE c.id = :id AND (c.landlord_user_id = :u1 OR c.tenant_id = :u2)');
    $stmt->execute([':id' => $conversationId, ':u1' => $userId, ':u2' => $userId]);
    $row = $stmt->fetch();
    if (!$row) {
        throw new ApiException('Conversation not found.', 404);
    }

    return $row;
}

/**
 * Conversation list for one side ('landlord' or 'tenant') with the other
 * person's name, the last message and how many messages are unread.
 */
function chatList(int $userId, string $side): array {
    $mine = $side === 'landlord' ? 'c.landlord_user_id' : 'c.tenant_id';
    $other = $side === 'landlord' ? 'c.tenant_id' : 'c.landlord_user_id';
    $stmt = getDb()->prepare("SELECT c.id, c.last_message_at, c.created_at, o.id AS other_id, o.first_name, o.last_name, o.username, o.avatar_path,
            (SELECT m.body FROM chat_messages m WHERE m.conversation_id = c.id ORDER BY m.id DESC LIMIT 1) AS last_body,
            (SELECT m.sender_id FROM chat_messages m WHERE m.conversation_id = c.id ORDER BY m.id DESC LIMIT 1) AS last_sender,
            (SELECT COUNT(*) FROM chat_messages m WHERE m.conversation_id = c.id AND m.sender_id <> :me1 AND m.read_at IS NULL) AS unread,
            (SELECT GROUP_CONCAT(DISTINCT bh.name ORDER BY bh.name SEPARATOR ', ') FROM bookings b JOIN rooms r ON r.id = b.room_id
                JOIN boarding_houses bh ON bh.id = r.boarding_house_id JOIN landlords l ON l.id = bh.landlord_id
                WHERE b.tenant_id = c.tenant_id AND l.user_id = c.landlord_user_id AND b.status IN ('pending', 'approved')) AS houses
        FROM conversations c JOIN users o ON o.id = {$other}
        WHERE {$mine} = :me2
        ORDER BY COALESCE(c.last_message_at, c.created_at) DESC");
    $stmt->execute([':me1' => $userId, ':me2' => $userId]);

    return array_map(static fn ($r) => [
        'id' => (int) $r['id'],
        'other_id' => (int) $r['other_id'],
        'name' => fullName($r),
        'photo' => $r['avatar_path'] ? publicAssetUrl((string) $r['avatar_path']) : null,
        'houses' => $r['houses'],
        'last_message' => $r['last_body'],
        'last_from_me' => (int) $r['last_sender'] === $userId,
        'last_message_at' => $r['last_message_at'] ?? $r['created_at'],
        'unread' => (int) $r['unread'],
    ], $stmt->fetchAll());
}

/** Messages after $afterId (0 = the latest 100). Marks the other person's messages as read. */
function chatMessages(int $conversationId, int $userId, int $afterId = 0): array {
    $pdo = getDb();
    if ($afterId > 0) {
        $stmt = $pdo->prepare('SELECT id, sender_id, body, read_at, created_at FROM chat_messages WHERE conversation_id = :c AND id > :after ORDER BY id LIMIT 200');
        $stmt->execute([':c' => $conversationId, ':after' => $afterId]);
        $rows = $stmt->fetchAll();
    } else {
        $stmt = $pdo->prepare('SELECT id, sender_id, body, read_at, created_at FROM chat_messages WHERE conversation_id = :c ORDER BY id DESC LIMIT 100');
        $stmt->execute([':c' => $conversationId]);
        $rows = array_reverse($stmt->fetchAll());
    }
    $pdo->prepare('UPDATE chat_messages SET read_at = NOW() WHERE conversation_id = :c AND sender_id <> :me AND read_at IS NULL')
        ->execute([':c' => $conversationId, ':me' => $userId]);

    return array_map(static fn ($m) => [
        'id' => (int) $m['id'],
        'mine' => (int) $m['sender_id'] === $userId,
        'body' => $m['body'],
        'read' => $m['read_at'] !== null,
        'created_at' => $m['created_at'],
    ], $rows);
}

/** Sends a message and notifies the other person (once until they read their notifications). */
function chatSend(array $conversation, int $senderId, string $senderName, mixed $body, string $notifyLink): array {
    $values = collectValidated(['body' => validateTextValue($body ?? '', 'Message', CHAT_MAX_LENGTH)]);
    $pdo = getDb();
    $conversationId = (int) $conversation['id'];
    $pdo->prepare('INSERT INTO chat_messages (conversation_id, sender_id, body) VALUES (:c, :s, :b)')
        ->execute([':c' => $conversationId, ':s' => $senderId, ':b' => $values['body']]);
    $id = (int) $pdo->lastInsertId();
    $pdo->prepare('UPDATE conversations SET last_message_at = NOW() WHERE id = :c')->execute([':c' => $conversationId]);

    $recipient = (int) $conversation['landlord_user_id'] === $senderId ? (int) $conversation['tenant_id'] : (int) $conversation['landlord_user_id'];
    $pending = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = :u AND type = 'chat' AND is_read = 0 AND link = :link");
    $pending->execute([':u' => $recipient, ':link' => $notifyLink]);
    if (!(int) $pending->fetchColumn()) {
        notifyUser($recipient, 'chat', 'New chat message', $senderName . ': "' . mb_strimwidth((string) $values['body'], 0, 120, '…') . '"', $notifyLink);
    }

    return ['id' => $id, 'mine' => true, 'body' => $values['body'], 'read' => false, 'created_at' => date('Y-m-d H:i:s')];
}

/** Unread chat messages for a user (badge counts). */
function chatUnread(int $userId): int {
    $stmt = getDb()->prepare('SELECT COUNT(*) FROM chat_messages m JOIN conversations c ON c.id = m.conversation_id
        WHERE (c.landlord_user_id = :u1 OR c.tenant_id = :u2) AND m.sender_id <> :u3 AND m.read_at IS NULL');
    $stmt->execute([':u1' => $userId, ':u2' => $userId, ':u3' => $userId]);
    return (int) $stmt->fetchColumn();
}
