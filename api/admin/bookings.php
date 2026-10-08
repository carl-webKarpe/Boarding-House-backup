<?php

declare(strict_types=1);

/**
 * Booking / rental records.
 *
 *   GET    bookings.php           list ?q=&status=&date_from=&date_to=&page=&sort=&order=
 *   GET    bookings.php?id=7      details
 *   POST   bookings.php           create { tenant_id, room_id, move_in_date, notes, status }
 *   PUT    bookings.php?id=7      update { status, move_in_date, notes }
 *   DELETE bookings.php?id=7      delete
 *
 * Occupancy rule: an "approved" booking holds occupants_count slots in the
 * room. Approving adds them; cancelling/completing an approved booking frees them.
 */

require_once __DIR__ . '/_admin.php';

const BOOKING_STATUSES = ['pending', 'approved', 'rejected', 'cancelled', 'completed'];

const BOOKING_SELECT = "SELECT b.id, b.tenant_id, b.room_id, b.booking_date, b.move_in_date, b.status, b.notes, b.created_at, b.updated_at,
    b.occupants_count, b.contact_name, b.contact_number, b.contact_email, b.message,
    t.first_name AS tenant_first_name, t.last_name AS tenant_last_name, t.username AS tenant_username, t.email AS tenant_email,
    t.contact_number AS tenant_contact,
    r.room_number, r.room_type, r.price, r.capacity, r.occupants, r.status AS room_status,
    bh.id AS boarding_house_id, bh.name AS boarding_house_name, bh.city";

const BOOKING_FROM = 'FROM bookings b JOIN users t ON t.id = b.tenant_id JOIN rooms r ON r.id = b.room_id JOIN boarding_houses bh ON bh.id = r.boarding_house_id';

function formatBooking(array $row): array {
    $row = castRow($row, ['id', 'tenant_id', 'room_id', 'boarding_house_id', 'capacity', 'occupants', 'occupants_count'], ['price']);
    $row['code'] = sprintf('BK-%05d', $row['id']);
    $row['tenant_name'] = fullName($row, 'tenant_');
    return $row;
}

function loadBooking(int $id): array {
    $stmt = getDb()->prepare(BOOKING_SELECT . ' ' . BOOKING_FROM . ' WHERE b.id = :id');
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch();
    if (!$row) {
        notFound('Booking');
    }

    return formatBooking($row);
}

function validateBookingPayload(array $body, bool $creating): array {
    $rules = [];
    if ($creating) {
        $rules['tenant_id'] = validateNumberValue($body['tenant_id'] ?? '', 'Tenant', 1, PHP_INT_MAX);
        $rules['room_id'] = validateNumberValue($body['room_id'] ?? '', 'Room', 1, PHP_INT_MAX);
    }
    if ($creating || array_key_exists('status', $body)) {
        $rules['status'] = validateEnumValue($body['status'] ?? 'pending', BOOKING_STATUSES, 'Status');
    }
    if (array_key_exists('move_in_date', $body)) {
        $rules['move_in_date'] = validateDateValue($body['move_in_date'] ?? '', 'Move-in date', false);
    }
    if (array_key_exists('notes', $body)) {
        $rules['notes'] = validateTextValue($body['notes'] ?? '', 'Notes', 500, false);
    }

    $values = collectValidated($rules);

    if ($creating) {
        $values['tenant_id'] = (int) $values['tenant_id'];
        $values['room_id'] = (int) $values['room_id'];
        $stmt = getDb()->prepare('SELECT role, status FROM users WHERE id = :id');
        $stmt->execute([':id' => $values['tenant_id']]);
        $tenant = $stmt->fetch();
        if (!$tenant || $tenant['role'] !== ROLE_TENANT) {
            throw new ApiException('Please choose a tenant account.', 422, ['tenant_id' => 'Not a tenant.']);
        }
        if ($tenant['status'] === 'disabled') {
            throw new ApiException('This tenant account is disabled.', 422, ['tenant_id' => 'Account disabled.']);
        }
        $stmt = getDb()->prepare('SELECT r.id, bh.status FROM rooms r JOIN boarding_houses bh ON bh.id = r.boarding_house_id WHERE r.id = :id');
        $stmt->execute([':id' => $values['room_id']]);
        $room = $stmt->fetch();
        if (!$room) {
            throw new ApiException('The selected room does not exist.', 422, ['room_id' => 'Unknown room.']);
        }
        if ($room['status'] !== 'approved') {
            throw new ApiException('Rooms can only be booked in approved boarding houses.', 422, ['room_id' => 'Listing not approved.']);
        }
    }

    return $values;
}

$pdo = getDb();

switch (requestMethod()) {
    case 'GET':
        if (isset($_GET['id'])) {
            jsonResponse(loadBooking(requireId()));
        }

        $where = [];
        $params = [];
        $search = queryString('q');
        if ($search !== '') {
            $where[] = '(CONCAT(t.first_name, \' \', t.last_name) LIKE :q1 OR t.email LIKE :q2 OR bh.name LIKE :q3 OR r.room_number LIKE :q4 OR b.id = :q5)';
            foreach (['q1', 'q2', 'q3', 'q4'] as $k) {
                $params[':' . $k] = likeValue($search);
            }
            $params[':q5'] = (int) preg_replace('/\D/', '', $search);
        }
        $status = queryString('status');
        if (in_array($status, BOOKING_STATUSES, true)) {
            $where[] = 'b.status = :status';
            $params[':status'] = $status;
        }
        $from = validateDateValue(queryString('date_from'), 'From', false);
        if ($from['valid'] && $from['value']) {
            $where[] = 'b.booking_date >= :date_from';
            $params[':date_from'] = $from['value'] . ' 00:00:00';
        }
        $to = validateDateValue(queryString('date_to'), 'To', false);
        if ($to['valid'] && $to['value']) {
            $where[] = 'b.booking_date <= :date_to';
            $params[':date_to'] = $to['value'] . ' 23:59:59';
        }
        $tenantId = queryInt('tenant_id', 0, 0);
        if ($tenantId > 0) {
            $where[] = 'b.tenant_id = :tenant_id';
            $params[':tenant_id'] = $tenantId;
        }

        $fromWhere = BOOKING_FROM . ($where ? ' WHERE ' . implode(' AND ', $where) : '');
        $order = orderBy([
            'booking_date' => 'b.booking_date',
            'id' => 'b.id',
            'tenant' => 't.first_name',
            'house' => 'bh.name',
            'status' => 'b.status',
        ], 'booking_date');

        [$rows, $meta] = pagedQuery(BOOKING_SELECT, $fromWhere, $params, $order, paginationParams());
        $counts = $pdo->query("SELECT COUNT(*) AS all_bookings, SUM(status = 'pending') AS pending, SUM(status = 'approved') AS approved,
            SUM(status = 'rejected') AS rejected, SUM(status = 'cancelled') AS cancelled, SUM(status = 'completed') AS completed FROM bookings")->fetch();

        jsonResponse(array_map('formatBooking', $rows), '', 200, [
            'meta' => $meta,
            'counts' => array_map('intval', $counts),
        ]);

    case 'POST':
        $values = validateBookingPayload(requestBody(), true);
        $pdo->beginTransaction();
        if ($values['status'] === 'approved') {
            adjustOccupancy($values['room_id'], +1);
        }
        $pdo->prepare('INSERT INTO bookings (tenant_id, room_id, move_in_date, status, notes) VALUES (:tenant, :room, :move_in, :status, :notes)')
            ->execute([
                ':tenant' => $values['tenant_id'],
                ':room' => $values['room_id'],
                ':move_in' => $values['move_in_date'] ?? null,
                ':status' => $values['status'],
                ':notes' => $values['notes'] ?? null,
            ]);
        $id = (int) $pdo->lastInsertId();
        $pdo->commit();

        $booking = loadBooking($id);
        adminLog('booking_create', "Administrator created booking {$booking['code']} for {$booking['tenant_name']} at {$booking['boarding_house_name']}", 'booking', $id);
        jsonResponse($booking, 'Booking created.', 201);

    case 'PUT':
    case 'PATCH':
        $id = requireId();
        $before = loadBooking($id);
        $values = validateBookingPayload(requestBody(), false);
        $newStatus = $values['status'] ?? $before['status'];

        $pdo->beginTransaction();
        if ($newStatus !== $before['status']) {
            if ($newStatus === 'approved') {
                adjustOccupancy($before['room_id'], +$before['occupants_count']);
            } elseif ($before['status'] === 'approved') {
                adjustOccupancy($before['room_id'], -$before['occupants_count']);
            }
        }

        $sets = [];
        $params = [':id' => $id];
        foreach (['status', 'move_in_date', 'notes'] as $column) {
            if (array_key_exists($column, $values)) {
                $sets[] = "{$column} = :{$column}";
                $params[':' . $column] = $values[$column];
            }
        }
        if ($sets) {
            $pdo->prepare('UPDATE bookings SET ' . implode(', ', $sets) . ' WHERE id = :id')->execute($params);
        }
        $pdo->commit();

        $booking = loadBooking($id);
        if ($newStatus !== $before['status']) {
            adminLog('booking_' . $newStatus, "Booking {$booking['code']} marked {$newStatus} ({$booking['tenant_name']}, {$booking['boarding_house_name']} room {$booking['room_number']})", 'booking', $id);
            notifyUser($booking['tenant_id'], 'booking_status', 'Booking ' . $newStatus, "Your booking {$booking['code']} at {$booking['boarding_house_name']} is now {$newStatus}.");
        } else {
            adminLog('booking_update', "Administrator updated booking {$booking['code']}", 'booking', $id);
        }
        jsonResponse($booking, 'Booking updated.');

    case 'DELETE':
        $id = requireId();
        $booking = loadBooking($id);
        $pdo->beginTransaction();
        if ($booking['status'] === 'approved') {
            adjustOccupancy($booking['room_id'], -$booking['occupants_count']);
        }
        $pdo->prepare('DELETE FROM bookings WHERE id = :id')->execute([':id' => $id]);
        $pdo->commit();
        adminLog('booking_delete', "Administrator deleted booking {$booking['code']}", 'booking', $id);
        jsonResponse(null, 'Booking deleted.');

    default:
        requireMethod('GET', 'POST', 'PUT', 'PATCH', 'DELETE');
}
