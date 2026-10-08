<?php

declare(strict_types=1);

/**
 * Reservations for rooms in the logged-in landlord's boarding houses.
 *
 *   GET reservations.php            ?status=&q=&page=
 *   GET reservations.php?id=5
 *   PUT reservations.php?id=5       { status: approved|rejected|completed|cancelled, note }
 *
 * Allowed changes: pending -> approved / rejected; approved -> completed / cancelled.
 * Approving takes the reserved number of places in the room; completing or
 * cancelling an approved reservation frees them again.
 */

require_once __DIR__ . '/_landlord.php';

const RESERVATION_SELECT = "SELECT b.id, b.status, b.booking_date, b.move_in_date, b.occupants_count, b.contact_name, b.contact_number,
    b.contact_email, b.message, b.notes, b.updated_at, b.tenant_id, b.room_id,
    t.first_name AS tenant_first_name, t.last_name AS tenant_last_name, t.username AS tenant_username, t.email AS tenant_email,
    t.contact_number AS tenant_phone,
    r.room_number, r.room_type, r.price, r.capacity, r.occupants, bh.id AS house_id, bh.name AS house_name";

const RESERVATION_FROM = 'FROM bookings b JOIN users t ON t.id = b.tenant_id JOIN rooms r ON r.id = b.room_id
    JOIN boarding_houses bh ON bh.id = r.boarding_house_id';

const TRANSITIONS = [
    'pending' => ['approved', 'rejected'],
    'approved' => ['completed', 'cancelled'],
];

function formatReservation(array $row): array {
    $row = castRow($row, ['id', 'occupants_count', 'tenant_id', 'room_id', 'capacity', 'occupants', 'house_id'], ['price']);
    $row['code'] = sprintf('BK-%05d', $row['id']);
    $row['tenant_name'] = $row['contact_name'] ?: fullName($row, 'tenant_');
    $row['email'] = $row['contact_email'] ?: $row['tenant_email'];
    $row['phone'] = $row['contact_number'] ?: $row['tenant_phone'];
    $row['room_type_label'] = ROOM_TYPE_LABELS[$row['room_type']] ?? $row['room_type'];
    $row['available_slots'] = max(0, $row['capacity'] - $row['occupants']);
    $row['next_statuses'] = TRANSITIONS[$row['status']] ?? [];
    return $row;
}

function loadOwnReservation(int $id): array {
    $stmt = getDb()->prepare(RESERVATION_SELECT . ' ' . RESERVATION_FROM . ' WHERE b.id = :id AND bh.landlord_id = :landlord');
    $stmt->execute([':id' => $id, ':landlord' => landlordId()]);
    $row = $stmt->fetch();
    if (!$row) {
        notFound('Reservation');
    }

    return formatReservation($row);
}

$pdo = getDb();

switch (requestMethod()) {
    case 'GET':
        if (isset($_GET['id'])) {
            jsonResponse(loadOwnReservation(requireId()));
        }

        $where = ['bh.landlord_id = :landlord'];
        $params = [':landlord' => landlordId()];
        $status = queryString('status');
        if (in_array($status, ['pending', 'approved', 'rejected', 'cancelled', 'completed'], true)) {
            $where[] = 'b.status = :status';
            $params[':status'] = $status;
        }
        $search = queryString('q');
        if ($search !== '') {
            $where[] = '(b.contact_name LIKE :q1 OR CONCAT(t.first_name, \' \', t.last_name) LIKE :q2 OR bh.name LIKE :q3 OR r.room_number LIKE :q4)';
            $params += [':q1' => likeValue($search), ':q2' => likeValue($search), ':q3' => likeValue($search), ':q4' => likeValue($search)];
        }
        [$rows, $meta] = pagedQuery(RESERVATION_SELECT, RESERVATION_FROM . ' WHERE ' . implode(' AND ', $where), $params,
            " ORDER BY FIELD(b.status, 'pending') DESC, b.booking_date DESC, b.id DESC", paginationParams(10));

        $counts = $pdo->prepare("SELECT COUNT(*) AS all_reservations, COALESCE(SUM(b.status = 'pending'), 0) AS pending, COALESCE(SUM(b.status = 'approved'), 0) AS approved,
                COALESCE(SUM(b.status = 'rejected'), 0) AS rejected, COALESCE(SUM(b.status = 'cancelled'), 0) AS cancelled, COALESCE(SUM(b.status = 'completed'), 0) AS completed
            FROM bookings b JOIN rooms r ON r.id = b.room_id JOIN boarding_houses bh ON bh.id = r.boarding_house_id WHERE bh.landlord_id = :landlord");
        $counts->execute([':landlord' => landlordId()]);

        jsonResponse(array_map('formatReservation', $rows), '', 200, ['meta' => $meta, 'counts' => array_map('intval', $counts->fetch())]);

    case 'PUT':
    case 'PATCH':
        $before = loadOwnReservation(requireId());
        $body = requestBody();
        $values = collectValidated([
            'status' => validateEnumValue($body['status'] ?? '', ['approved', 'rejected', 'completed', 'cancelled'], 'Status'),
            'note' => validateTextValue($body['note'] ?? '', 'Note to the student', 500, false),
        ]);
        $new = $values['status'];
        if (!in_array($new, TRANSITIONS[$before['status']] ?? [], true)) {
            throw new ApiException("A {$before['status']} reservation cannot be changed to {$new}.", 409);
        }

        $pdo->beginTransaction();
        if ($new === 'approved') {
            adjustOccupancy($before['room_id'], +$before['occupants_count']);
        } elseif ($before['status'] === 'approved') {
            adjustOccupancy($before['room_id'], -$before['occupants_count']);
        }
        $pdo->prepare('UPDATE bookings SET status = :status, notes = COALESCE(:note, notes) WHERE id = :id')
            ->execute([':status' => $new, ':note' => $values['note'], ':id' => $before['id']]);
        $pdo->commit();

        $after = loadOwnReservation($before['id']);
        $labels = ['approved' => 'approved', 'rejected' => 'not accepted', 'completed' => 'marked as completed', 'cancelled' => 'cancelled'];
        notifyUser($after['tenant_id'], 'booking_status', 'Reservation ' . $new,
            "Your reservation {$after['code']} for room {$after['room_number']} at {$after['house_name']} was {$labels[$new]}." . ($values['note'] ? ' Note: ' . $values['note'] : ''));
        landlordLog('booking_' . $new, "Landlord {$labels[$new]} reservation {$after['code']} ({$after['tenant_name']}, {$after['house_name']} room {$after['room_number']})", 'booking', $after['id']);
        jsonResponse($after, "Reservation {$after['code']} {$labels[$new]}.");

    default:
        requireMethod('GET', 'PUT', 'PATCH');
}
