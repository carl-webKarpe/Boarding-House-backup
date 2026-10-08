<?php

declare(strict_types=1);

/**
 * Landlord chat with tenants.
 *
 *   GET  chat.php                                 my conversations (+ unread total)
 *   GET  chat.php?type=contacts                   tenants I can start a chat with
 *   GET  chat.php?conversation_id=3&after=120     messages (only newer than "after" when given)
 *   POST chat.php   { conversation_id, body }     send in a conversation
 *   POST chat.php   { tenant_id, body }           start (or continue) a chat with a tenant
 */

require_once __DIR__ . '/_landlord.php';
require_once __DIR__ . '/../_chat.php';

$pdo = getDb();
$me = landlordUserId();

function landlordConversationInfo(array $c): array {
    return [
        'id' => (int) $c['id'],
        'tenant_id' => (int) $c['tenant_id'],
        'name' => trim($c['t_first'] . ' ' . $c['t_last']) ?: $c['t_username'],
        'photo' => $c['t_avatar'] ? publicAssetUrl((string) $c['t_avatar']) : null,
        'phone' => $c['t_phone'],
        'email' => $c['t_email'],
    ];
}

switch (requestMethod()) {
    case 'GET':
        if (queryString('type') === 'contacts') {
            // Tenants with a reservation in my boarding houses or who messaged me.
            $stmt = $pdo->prepare("SELECT u.id, u.first_name, u.last_name, u.username,
                    MAX(CASE WHEN b.status = 'approved' THEN 2 WHEN b.status = 'pending' THEN 1 ELSE 0 END) AS rank_status,
                    GROUP_CONCAT(DISTINCT bh.name ORDER BY bh.name SEPARATOR ', ') AS houses
                FROM users u
                LEFT JOIN bookings b ON b.tenant_id = u.id
                LEFT JOIN rooms r ON r.id = b.room_id
                LEFT JOIN boarding_houses bh ON bh.id = r.boarding_house_id AND bh.landlord_id = :landlord
                WHERE u.role = 'tenant' AND u.status <> 'disabled' AND (bh.id IS NOT NULL
                    OR EXISTS (SELECT 1 FROM inquiries i WHERE i.tenant_id = u.id AND i.landlord_user_id = :me))
                GROUP BY u.id, u.first_name, u.last_name, u.username
                ORDER BY rank_status DESC, u.first_name, u.last_name");
            $stmt->execute([':landlord' => landlordId(), ':me' => $me]);
            $labels = [2 => 'Boarder', 1 => 'Pending reservation', 0 => 'Asked a question'];
            jsonResponse(array_map(static fn ($r) => [
                'id' => (int) $r['id'],
                'name' => fullName($r),
                'houses' => $r['houses'],
                'relation' => $labels[(int) $r['rank_status']],
            ], $stmt->fetchAll()));
        }

        if (isset($_GET['conversation_id'])) {
            $conversation = chatConversation(queryInt('conversation_id', 0, 1), $me);
            if ((int) $conversation['landlord_user_id'] !== $me) {
                notFound('Conversation');
            }
            jsonResponse(chatMessages((int) $conversation['id'], $me, queryInt('after', 0, 0)), '', 200, [
                'conversation' => landlordConversationInfo($conversation),
                'unread_total' => chatUnread($me),
            ]);
        }

        jsonResponse(chatList($me, 'landlord'), '', 200, ['unread_total' => chatUnread($me)]);

    case 'POST':
        $body = requestBody();
        if (!empty($body['conversation_id'])) {
            $conversation = chatConversation((int) $body['conversation_id'], $me);
            if ((int) $conversation['landlord_user_id'] !== $me) {
                notFound('Conversation');
            }
        } else {
            $tenantId = (int) ($body['tenant_id'] ?? 0);
            if ($tenantId <= 0) {
                throw new ApiException('Choose a tenant to chat with.', 422, ['tenant_id' => 'Required.']);
            }
            $conversation = chatConversation(chatOpen($me, $tenantId), $me);
        }
        $message = chatSend($conversation, $me, landlordName(), $body['body'] ?? '', 'chat');
        jsonResponse($message, 'Message sent.', 201, ['conversation' => landlordConversationInfo($conversation)]);

    default:
        requireMethod('GET', 'POST');
}
