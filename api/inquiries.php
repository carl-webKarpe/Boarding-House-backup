<?php

declare(strict_types=1);

/**
 * "Contact Landlord" messages.
 *
 *   POST api/inquiries.php   send a message about a room
 *        { room_id, sender_name, sender_email, sender_phone, message }
 *        Logged-in students are linked to their account; guests may also
 *        ask a question by leaving a name and email.
 *   GET  api/inquiries.php   my sent messages and the landlords' replies
 *                            (logged-in students only)
 *
 * Messages are saved in the `inquiries` table for the landlord to read and
 * answer, and the landlord gets a notification.
 */

require_once __DIR__ . '/_common.php';
require_once __DIR__ . '/../security/rate_limit.php';

apiBootstrap();

$pdo = getDb();
$userId = currentUserId();

switch (requestMethod()) {
    case 'GET':
        if (!$userId) {
            jsonError('Please log in to see your messages.', 401);
        }
        $stmt = $pdo->prepare("SELECT i.id, i.message, i.status, i.reply, i.replied_at, i.created_at,
                bh.name AS house_name, r.room_number,
                u.first_name, u.last_name
            FROM inquiries i JOIN boarding_houses bh ON bh.id = i.boarding_house_id
            LEFT JOIN rooms r ON r.id = i.room_id
            JOIN users u ON u.id = i.landlord_user_id
            WHERE i.tenant_id = :me ORDER BY i.created_at DESC, i.id DESC LIMIT 50");
        $stmt->execute([':me' => $userId]);
        jsonResponse(array_map(static fn ($row) => [
            'id' => (int) $row['id'],
            'house_name' => $row['house_name'],
            'room_number' => $row['room_number'],
            'landlord_name' => trim($row['first_name'] . ' ' . $row['last_name']),
            'message' => $row['message'],
            'status' => $row['status'],
            'reply' => $row['reply'],
            'replied_at' => $row['replied_at'],
            'created_at' => $row['created_at'],
        ], $stmt->fetchAll()));

    case 'POST':
        requireCsrf();

        // Slow down spam: 8 messages per hour per visitor.
        $ip = getClientIp();
        if (isRateLimited('inquiry', $ip, 8, 60)) {
            jsonError('You have sent several messages already. Please wait a while before sending another.', 429);
        }

        $body = requestBody();
        $values = collectValidated([
            'room_id' => validateNumberValue($body['room_id'] ?? '', 'Room', 1, PHP_INT_MAX),
            'sender_name' => validateTextValue($body['sender_name'] ?? '', 'Your name', 160),
            'sender_email' => validateEmailValue($body['sender_email'] ?? ''),
            'sender_phone' => trim((string) ($body['sender_phone'] ?? '')) === ''
                ? ['valid' => true, 'value' => null]
                : validatePhoneValue($body['sender_phone']),
            'message' => validateTextValue($body['message'] ?? '', 'Message', 2000),
        ]);
        if (mb_strlen((string) $values['message']) < 5) {
            throw new ApiException('Please write a slightly longer message.', 422, ['message' => 'Too short.']);
        }

        $stmt = $pdo->prepare("SELECT r.id, r.room_number, bh.id AS house_id, bh.name AS house_name, u.id AS landlord_user_id
            FROM rooms r JOIN boarding_houses bh ON bh.id = r.boarding_house_id
            JOIN landlords l ON l.id = bh.landlord_id JOIN users u ON u.id = l.user_id
            WHERE r.id = :id AND bh.status = 'approved' AND u.status <> 'disabled'");
        $stmt->execute([':id' => (int) $values['room_id']]);
        $room = $stmt->fetch();
        if (!$room) {
            throw new ApiException('This listing is no longer available.', 404);
        }

        // Only link the message to an account when a student is logged in.
        $tenantId = null;
        if ($userId && currentUserRole() === ROLE_TENANT) {
            $tenantId = $userId;
        }

        $pdo->prepare('INSERT INTO inquiries (boarding_house_id, room_id, landlord_user_id, tenant_id, sender_name, sender_email, sender_phone, message)
            VALUES (:house, :room, :landlord, :tenant, :name, :email, :phone, :message)')
            ->execute([
                ':house' => $room['house_id'],
                ':room' => $room['id'],
                ':landlord' => $room['landlord_user_id'],
                ':tenant' => $tenantId,
                ':name' => $values['sender_name'],
                ':email' => $values['sender_email'],
                ':phone' => $values['sender_phone'],
                ':message' => $values['message'],
            ]);
        $id = (int) $pdo->lastInsertId();
        hitRateLimit('inquiry', $ip, 60);

        notifyUser((int) $room['landlord_user_id'], 'inquiry', 'New message about room ' . $room['room_number'],
            $values['sender_name'] . ' asked about ' . $room['house_name'] . ': "' . mb_strimwidth((string) $values['message'], 0, 120, '…') . '"');
        auditLog('inquiry', $values['sender_name'] . ' sent a message about ' . $room['house_name'] . ' room ' . $room['room_number'], $tenantId, 'inquiry', $id);

        jsonResponse(['id' => $id], 'Message sent. The landlord will reply to you by email, phone or in My Reservations.', 201);

    default:
        requireMethod('GET', 'POST');
}
