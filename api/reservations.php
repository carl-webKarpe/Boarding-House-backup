<?php

declare(strict_types=1);

/**
 * Student reservations ("Reserve / Book a Room").
 *
 *   GET  api/reservations.php                    my reservations (newest first)
 *   POST api/reservations.php                    request a room
 *        { room_id, contact_name, contact_number, contact_email,
 *          move_in_date, occupants_count, message }
 *   PUT  api/reservations.php?id=5&action=cancel cancel my pending reservation
 *
 * A new reservation is always "pending": the landlord (or an administrator,
 * in Admin > Bookings) reviews it and approves or rejects it. Only student
 * (tenant) accounts can reserve.
 */

require_once __DIR__ . '/_common.php';

apiBootstrap();

if (!isLoggedIn()) {
    jsonError('Please log in with your student account to reserve a room.', 401);
}

$pdo = getDb();
$me = $pdo->prepare('SELECT id, role, status, first_name, last_name, email, contact_number FROM users WHERE id = :id');
$me->execute([':id' => currentUserId()]);
$user = $me->fetch();

if (!$user || $user['status'] === 'disabled') {
    logoutUser();
    jsonError('Your session has ended. Please log in again.', 401);
}
if ($user['role'] !== ROLE_TENANT) {
    jsonError('Only student (tenant) accounts can reserve rooms.', 403);
}
$tenantId = (int) $user['id'];

if (requestMethod() !== 'GET') {
    requireCsrf();
}

const MAX_OPEN_RESERVATIONS = 5;

function loadReservation(int $id, int $tenantId): array {
    $stmt = getDb()->prepare("SELECT b.id, b.status, b.booking_date, b.move_in_date, b.occupants_count, b.contact_name, b.contact_number,
            b.contact_email, b.message, b.notes, b.updated_at,
            r.id AS room_id, r.room_number, r.room_type, r.price,
            bh.id AS house_id, bh.name AS house_name, bh.barangay, bh.city,
            (SELECT i.file_path FROM room_images i WHERE i.room_id = r.id ORDER BY i.sort_order, i.id LIMIT 1) AS room_photo,
            (SELECT i.file_path FROM boarding_house_images i WHERE i.boarding_house_id = bh.id ORDER BY i.is_cover DESC, i.id LIMIT 1) AS house_photo
        FROM bookings b JOIN rooms r ON r.id = b.room_id JOIN boarding_houses bh ON bh.id = r.boarding_house_id
        WHERE b.id = :id AND b.tenant_id = :tenant");
    $stmt->execute([':id' => $id, ':tenant' => $tenantId]);
    $row = $stmt->fetch();
    if (!$row) {
        throw new ApiException('Reservation not found.', 404);
    }

    return formatReservation($row);
}

function formatReservation(array $row): array {
    $photo = $row['room_photo'] ?: $row['house_photo'];
    return [
        'id' => (int) $row['id'],
        'code' => sprintf('BK-%05d', $row['id']),
        'status' => $row['status'],
        'requested_at' => $row['booking_date'],
        'updated_at' => $row['updated_at'],
        'move_in_date' => $row['move_in_date'],
        'occupants_count' => (int) $row['occupants_count'],
        'contact_name' => $row['contact_name'],
        'contact_number' => $row['contact_number'],
        'contact_email' => $row['contact_email'],
        'message' => $row['message'],
        'landlord_note' => $row['notes'],
        'room' => [
            'id' => (int) $row['room_id'],
            'room_number' => $row['room_number'],
            'room_type' => $row['room_type'],
            'price' => (float) $row['price'],
            'photo' => $photo ? publicAssetUrl((string) $photo) : null,
        ],
        'house' => [
            'id' => (int) $row['house_id'],
            'name' => $row['house_name'],
            'location' => implode(', ', array_filter([$row['barangay'], $row['city']])),
        ],
    ];
}

switch (requestMethod()) {
    case 'GET':
        $stmt = $pdo->prepare("SELECT b.id FROM bookings b WHERE b.tenant_id = :tenant ORDER BY b.booking_date DESC, b.id DESC LIMIT 50");
        $stmt->execute([':tenant' => $tenantId]);
        $list = array_map(fn ($id) => loadReservation((int) $id, $tenantId), $stmt->fetchAll(PDO::FETCH_COLUMN));
        jsonResponse($list, '', 200, ['profile' => [
            'name' => trim($user['first_name'] . ' ' . $user['last_name']),
            'email' => $user['email'],
            'contact_number' => $user['contact_number'],
        ]]);

    case 'POST':
        $body = requestBody();
        $values = collectValidated([
            'room_id' => validateNumberValue($body['room_id'] ?? '', 'Room', 1, PHP_INT_MAX),
            'contact_name' => validateTextValue($body['contact_name'] ?? '', 'Your name', 160),
            'contact_number' => validatePhoneValue($body['contact_number'] ?? ''),
            'contact_email' => validateEmailValue($body['contact_email'] ?? ''),
            'move_in_date' => validateDateValue($body['move_in_date'] ?? '', 'Preferred move-in date'),
            'occupants_count' => validateNumberValue($body['occupants_count'] ?? '', 'Number of occupants', 1, 20),
            'message' => validateTextValue($body['message'] ?? '', 'Message', 1000, false),
        ]);
        $roomId = (int) $values['room_id'];
        $occupants = (int) $values['occupants_count'];

        $moveIn = new DateTimeImmutable($values['move_in_date']);
        $today = new DateTimeImmutable('today');
        if ($moveIn < $today) {
            throw new ApiException('The move-in date cannot be in the past.', 422, ['move_in_date' => 'Choose today or a later date.']);
        }
        if ($moveIn > $today->modify('+1 year')) {
            throw new ApiException('Choose a move-in date within the next 12 months.', 422, ['move_in_date' => 'Too far ahead.']);
        }

        $open = $pdo->prepare("SELECT COUNT(*) FROM bookings WHERE tenant_id = :tenant AND status = 'pending'");
        $open->execute([':tenant' => $tenantId]);
        if ((int) $open->fetchColumn() >= MAX_OPEN_RESERVATIONS) {
            throw new ApiException('You already have ' . MAX_OPEN_RESERVATIONS . ' pending reservations. Please wait for a reply or cancel one first.', 429);
        }

        $pdo->beginTransaction();
        // Lock the room so two students cannot both take the last slot.
        $stmt = $pdo->prepare("SELECT r.id, r.room_number, r.capacity, r.occupants, r.status, bh.id AS house_id, bh.name AS house_name, bh.status AS house_status, bh.availability_status,
                u.id AS landlord_user_id, u.status AS landlord_status
            FROM rooms r JOIN boarding_houses bh ON bh.id = r.boarding_house_id
            JOIN landlords l ON l.id = bh.landlord_id JOIN users u ON u.id = l.user_id
            WHERE r.id = :id FOR UPDATE");
        $stmt->execute([':id' => $roomId]);
        $room = $stmt->fetch();
        if (!$room || $room['house_status'] !== 'approved' || $room['landlord_status'] === 'disabled' || $room['status'] === 'maintenance') {
            $pdo->rollBack();
            throw new ApiException('This room is no longer available for reservation.', 409);
        }

        if ($room['availability_status'] === 'temporarily_unavailable') {
            $pdo->rollBack();
            throw new ApiException('The landlord is not accepting reservations for this boarding house right now.', 409);
        }

        $openSlots = $room['availability_status'] === 'fully_occupied' ? 0 : (int) $room['capacity'] - (int) $room['occupants'];
        if ($openSlots <= 0) {
            $pdo->rollBack();
            throw new ApiException('Sorry, this room is already full.', 409);
        }
        if ($occupants > $openSlots) {
            $pdo->rollBack();
            throw new ApiException("This room only has {$openSlots} open slot" . ($openSlots === 1 ? '' : 's') . '.', 422, ['occupants_count' => "Maximum {$openSlots}."]);
        }

        $dup = $pdo->prepare("SELECT id FROM bookings WHERE tenant_id = :tenant AND room_id = :room AND status IN ('pending', 'approved') LIMIT 1");
        $dup->execute([':tenant' => $tenantId, ':room' => $roomId]);
        if ($dup->fetch()) {
            $pdo->rollBack();
            throw new ApiException('You already have an active reservation for this room. Check "My Reservations".', 409);
        }

        $pdo->prepare('INSERT INTO bookings (tenant_id, room_id, move_in_date, occupants_count, contact_name, contact_number, contact_email, message, status)
            VALUES (:tenant, :room, :move_in, :occupants, :name, :phone, :email, :message, \'pending\')')
            ->execute([
                ':tenant' => $tenantId,
                ':room' => $roomId,
                ':move_in' => $values['move_in_date'],
                ':occupants' => $occupants,
                ':name' => $values['contact_name'],
                ':phone' => $values['contact_number'],
                ':email' => $values['contact_email'],
                ':message' => $values['message'],
            ]);
        $id = (int) $pdo->lastInsertId();
        $pdo->commit();

        $code = sprintf('BK-%05d', $id);
        $who = $values['contact_name'];
        auditLog('booking_request', "{$who} requested room {$room['room_number']} at {$room['house_name']} ({$code})", $tenantId, 'booking', $id);
        notifyUser((int) $room['landlord_user_id'], 'booking_created', 'New reservation request',
            "{$who} wants to reserve room {$room['room_number']} at {$room['house_name']} from " . $moveIn->format('M j, Y') . " ({$occupants} occupant" . ($occupants === 1 ? '' : 's') . ').');
        notifyAdmins('booking_created', 'New reservation request', "{$who} requested room {$room['room_number']} at {$room['house_name']}.", '#/bookings?status=pending');

        jsonResponse(loadReservation($id, $tenantId), 'Reservation sent. The landlord will review it.', 201);

    case 'PUT':
    case 'PATCH':
        $id = queryInt('id', 0, 0);
        if (queryString('action') !== 'cancel' || $id <= 0) {
            throw new ApiException('Unknown action.', 400);
        }
        $reservation = loadReservation($id, $tenantId);
        if ($reservation['status'] !== 'pending') {
            throw new ApiException('Only pending reservations can be cancelled here. Contact the landlord about approved reservations.', 409);
        }
        $pdo->prepare("UPDATE bookings SET status = 'cancelled', notes = 'Cancelled by the student.' WHERE id = :id AND tenant_id = :tenant AND status = 'pending'")
            ->execute([':id' => $id, ':tenant' => $tenantId]);
        auditLog('booking_cancelled', "Student cancelled reservation {$reservation['code']} ({$reservation['house']['name']})", $tenantId, 'booking', $id);
        $owner = $pdo->prepare('SELECT l.user_id FROM boarding_houses bh JOIN landlords l ON l.id = bh.landlord_id WHERE bh.id = :house');
        $owner->execute([':house' => $reservation['house']['id']]);
        if ($ownerId = (int) $owner->fetchColumn()) {
            notifyUser($ownerId, 'booking_created', 'Reservation cancelled',
                "{$reservation['contact_name']} cancelled reservation {$reservation['code']} for room {$reservation['room']['room_number']} at {$reservation['house']['name']}.");
        }
        jsonResponse(loadReservation($id, $tenantId), 'Reservation cancelled.');

    default:
        requireMethod('GET', 'POST', 'PUT', 'PATCH');
}
