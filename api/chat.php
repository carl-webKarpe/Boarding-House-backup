<?php

declare(strict_types=1);

/**
 * Student (tenant) chat with landlords.
 *
 *   GET  api/chat.php                                my conversations (+ unread total)
 *   GET  api/chat.php?conversation_id=3&after=120    messages
 *   POST api/chat.php  { conversation_id, body }     send in a conversation
 *   POST api/chat.php  { room_id, body }             start a chat with the landlord of a room
 *                                                    I reserved or asked about
 */

require_once __DIR__ . '/_common.php';
require_once __DIR__ . '/_queries.php';
require_once __DIR__ . '/_chat.php';

apiBootstrap();

if (!isLoggedIn()) {
    jsonError('Please log in to chat with landlords.', 401);
}
$pdo = getDb();
$stmt = $pdo->prepare('SELECT id, role, status, first_name, last_name, username FROM users WHERE id = :id');
$stmt->execute([':id' => currentUserId()]);
$user = $stmt->fetch();
if (!$user || $user['status'] === 'disabled') {
    logoutUser();
    jsonError('Your session has ended. Please log in again.', 401);
}
if ($user['role'] !== ROLE_TENANT) {
    jsonError('Chat on this page is for student (tenant) accounts.', 403);
}
$me = (int) $user['id'];
if (requestMethod() !== 'GET') {
    requireCsrf();
}

function tenantConversationInfo(array $c): array {
    return [
        'id' => (int) $c['id'],
        'name' => trim($c['l_first'] . ' ' . $c['l_last']) ?: $c['l_username'],
        'photo' => $c['l_avatar'] ? publicAssetUrl((string) $c['l_avatar']) : null,
    ];
}

function ownConversation(int $id, int $me): array {
    $conversation = chatConversation($id, $me);
    if ((int) $conversation['tenant_id'] !== $me) {
        throw new ApiException('Conversation not found.', 404);
    }
    return $conversation;
}

switch (requestMethod()) {
    case 'GET':
        if (isset($_GET['conversation_id'])) {
            $conversation = ownConversation(queryInt('conversation_id', 0, 1), $me);
            jsonResponse(chatMessages((int) $conversation['id'], $me, queryInt('after', 0, 0)), '', 200, [
                'conversation' => tenantConversationInfo($conversation),
                'unread_total' => chatUnread($me),
            ]);
        }
        jsonResponse(chatList($me, 'tenant'), '', 200, ['unread_total' => chatUnread($me)]);

    case 'POST':
        $body = requestBody();
        if (!empty($body['conversation_id'])) {
            $conversation = ownConversation((int) $body['conversation_id'], $me);
        } else {
            $roomId = (int) ($body['room_id'] ?? 0);
            $owner = $pdo->prepare('SELECT l.user_id FROM rooms r JOIN boarding_houses bh ON bh.id = r.boarding_house_id
                JOIN landlords l ON l.id = bh.landlord_id WHERE r.id = :id');
            $owner->execute([':id' => $roomId]);
            $landlordUserId = (int) $owner->fetchColumn();
            if (!$landlordUserId) {
                throw new ApiException('This room is no longer listed.', 404);
            }
            $conversation = chatConversation(chatOpen($landlordUserId, $me), $me);
        }
        $message = chatSend($conversation, $me, fullName($user), $body['body'] ?? '', 'chat');
        jsonResponse($message, 'Message sent.', 201, ['conversation' => tenantConversationInfo($conversation)]);

    default:
        requireMethod('GET', 'POST');
}
