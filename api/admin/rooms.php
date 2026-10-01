<?php

declare(strict_types=1);

/**
 * Admin room management.
 *
 *   GET    rooms.php            list ?q=&boarding_house_id=&status=&room_type=&amenity_id=&min_price=&max_price=&page=&sort=&order=
 *   GET    rooms.php?id=4       details
 *   POST   rooms.php            create   (amenity_ids: [1, 2, 3])
 *   PUT    rooms.php?id=4       update
 *   DELETE rooms.php?id=4       delete
 *
 * Status rule: a room is "occupied" when occupants >= capacity, otherwise
 * "available" - unless an admin marks it "maintenance".
 */

require_once __DIR__ . '/_admin.php';

const ROOM_SELECT = "SELECT r.id, r.boarding_house_id, r.room_number, r.room_type, r.price, r.deposit, r.capacity, r.occupants,
    r.size_sqm, r.description, r.status, r.created_at, r.updated_at,
    bh.name AS boarding_house_name, bh.status AS boarding_house_status, bh.city,
    (SELECT GROUP_CONCAT(a.name ORDER BY a.name SEPARATOR '||') FROM room_amenities ra JOIN amenities a ON a.id = ra.amenity_id WHERE ra.room_id = r.id) AS amenity_names,
    (SELECT GROUP_CONCAT(ra.amenity_id) FROM room_amenities ra WHERE ra.room_id = r.id) AS amenity_id_list";

const ROOM_FROM = 'FROM rooms r JOIN boarding_houses bh ON bh.id = r.boarding_house_id';

const ROOM_TYPES = ['solo', 'shared', 'dormitory', 'studio'];

function formatRoom(array $row): array {
    $row = castRow($row, ['id', 'boarding_house_id', 'capacity', 'occupants'], ['price', 'deposit', 'size_sqm']);
    $row['available_slots'] = max(0, $row['capacity'] - $row['occupants']);
    $row['amenities'] = $row['amenity_names'] ? explode('||', $row['amenity_names']) : [];
    $row['amenity_ids'] = $row['amenity_id_list'] ? array_map('intval', explode(',', $row['amenity_id_list'])) : [];
    unset($row['amenity_names'], $row['amenity_id_list']);
    return $row;
}

function loadRoom(int $id): array {
    $stmt = getDb()->prepare(ROOM_SELECT . ' ' . ROOM_FROM . ' WHERE r.id = :id');
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch();
    if (!$row) {
        notFound('Room');
    }

    return formatRoom($row);
}

function validateRoomPayload(array $body, bool $creating, ?array $existing = null): array {
    $rules = [];
    if ($creating || array_key_exists('boarding_house_id', $body)) {
        $rules['boarding_house_id'] = validateNumberValue($body['boarding_house_id'] ?? '', 'Boarding house', 1, PHP_INT_MAX);
    }
    if ($creating || array_key_exists('room_number', $body)) {
        $rules['room_number'] = validateTextValue($body['room_number'] ?? '', 'Room number', 20);
    }
    if ($creating || array_key_exists('room_type', $body)) {
        $rules['room_type'] = validateEnumValue($body['room_type'] ?? '', ROOM_TYPES, 'Room type');
    }
    if ($creating || array_key_exists('price', $body)) {
        $rules['price'] = validateNumberValue($body['price'] ?? '', 'Monthly price', 1, 1000000);
    }
    if (array_key_exists('deposit', $body)) {
        $rules['deposit'] = ($body['deposit'] === '' || $body['deposit'] === null) ? ['valid' => true, 'value' => 0] : validateNumberValue($body['deposit'], 'Deposit', 0, 1000000);
    }
    if ($creating || array_key_exists('capacity', $body)) {
        $rules['capacity'] = validateNumberValue($body['capacity'] ?? '', 'Capacity', 1, 50);
    }
    if (array_key_exists('occupants', $body)) {
        $rules['occupants'] = validateNumberValue($body['occupants'] === '' ? 0 : $body['occupants'], 'Current occupants', 0, 50);
    }
    if (array_key_exists('size_sqm', $body)) {
        $rules['size_sqm'] = ($body['size_sqm'] === '' || $body['size_sqm'] === null) ? ['valid' => true, 'value' => null] : validateNumberValue($body['size_sqm'], 'Room size', 1, 1000);
    }
    if (array_key_exists('description', $body)) {
        $rules['description'] = validateTextValue($body['description'] ?? '', 'Description', 500, false);
    }
    if (array_key_exists('status', $body)) {
        $rules['status'] = validateEnumValue($body['status'] ?? '', ['available', 'occupied', 'maintenance'], 'Status');
    }

    $values = collectValidated($rules);

    foreach (['boarding_house_id', 'capacity', 'occupants'] as $intField) {
        if (isset($values[$intField])) {
            $values[$intField] = (int) $values[$intField];
        }
    }

    if (isset($values['boarding_house_id'])) {
        $stmt = getDb()->prepare('SELECT id FROM boarding_houses WHERE id = :id');
        $stmt->execute([':id' => $values['boarding_house_id']]);
        if (!$stmt->fetch()) {
            throw new ApiException('The selected boarding house does not exist.', 422, ['boarding_house_id' => 'Unknown boarding house.']);
        }
    }

    $capacity = $values['capacity'] ?? ($existing['capacity'] ?? 1);
    $occupants = $values['occupants'] ?? ($existing['occupants'] ?? 0);
    if ($occupants > $capacity) {
        throw new ApiException('Current occupants cannot be more than the room capacity.', 422, ['occupants' => 'Exceeds capacity.']);
    }

    if (array_key_exists('amenity_ids', $body)) {
        $ids = is_array($body['amenity_ids']) ? array_values(array_unique(array_map('intval', $body['amenity_ids']))) : [];
        $ids = array_values(array_filter($ids, fn ($id) => $id > 0));
        if ($ids) {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $stmt = getDb()->prepare("SELECT COUNT(*) FROM amenities WHERE id IN ({$placeholders})");
            $stmt->execute($ids);
            if ((int) $stmt->fetchColumn() !== count($ids)) {
                throw new ApiException('One or more amenities do not exist.', 422, ['amenity_ids' => 'Unknown amenity.']);
            }
        }
        $values['amenity_ids'] = $ids;
    }

    return $values;
}

function saveRoomAmenities(int $roomId, array $ids): void {
    $pdo = getDb();
    $pdo->prepare('DELETE FROM room_amenities WHERE room_id = :id')->execute([':id' => $roomId]);
    $stmt = $pdo->prepare('INSERT INTO room_amenities (room_id, amenity_id) VALUES (:room, :amenity)');
    foreach ($ids as $amenityId) {
        $stmt->execute([':room' => $roomId, ':amenity' => $amenityId]);
    }
}

$pdo = getDb();

switch (requestMethod()) {
    case 'GET':
        if (isset($_GET['id'])) {
            jsonResponse(loadRoom(requireId()));
        }

        $where = [];
        $params = [];
        $search = queryString('q');
        if ($search !== '') {
            $where[] = '(r.room_number LIKE :q1 OR bh.name LIKE :q2 OR bh.city LIKE :q3)';
            foreach (['q1', 'q2', 'q3'] as $k) {
                $params[':' . $k] = likeValue($search);
            }
        }
        $houseId = queryInt('boarding_house_id', 0, 0);
        if ($houseId > 0) {
            $where[] = 'r.boarding_house_id = :house_id';
            $params[':house_id'] = $houseId;
        }
        $status = queryString('status');
        if (in_array($status, ['available', 'occupied', 'maintenance'], true)) {
            $where[] = 'r.status = :status';
            $params[':status'] = $status;
        }
        $type = queryString('room_type');
        if (in_array($type, ROOM_TYPES, true)) {
            $where[] = 'r.room_type = :type';
            $params[':type'] = $type;
        }
        $amenityId = queryInt('amenity_id', 0, 0);
        if ($amenityId > 0) {
            $where[] = 'EXISTS (SELECT 1 FROM room_amenities ra WHERE ra.room_id = r.id AND ra.amenity_id = :amenity_id)';
            $params[':amenity_id'] = $amenityId;
        }
        if (is_numeric($_GET['min_price'] ?? null)) {
            $where[] = 'r.price >= :min_price';
            $params[':min_price'] = (float) $_GET['min_price'];
        }
        if (is_numeric($_GET['max_price'] ?? null)) {
            $where[] = 'r.price <= :max_price';
            $params[':max_price'] = (float) $_GET['max_price'];
        }

        $fromWhere = ROOM_FROM . ($where ? ' WHERE ' . implode(' AND ', $where) : '');
        $order = orderBy([
            'created_at' => 'r.created_at',
            'room_number' => 'r.room_number',
            'house' => 'bh.name',
            'price' => 'r.price',
            'capacity' => 'r.capacity',
            'status' => 'r.status',
        ], 'created_at');

        [$rows, $meta] = pagedQuery(ROOM_SELECT, $fromWhere, $params, $order, paginationParams());
        // Tab counts follow the boarding house filter so they match the list.
        $countStmt = $pdo->prepare("SELECT COUNT(*) AS all_rooms, COALESCE(SUM(status = 'available'), 0) AS available, COALESCE(SUM(status = 'occupied'), 0) AS occupied,
            COALESCE(SUM(status = 'maintenance'), 0) AS maintenance, COALESCE(SUM(capacity), 0) AS beds, COALESCE(SUM(occupants), 0) AS occupants
            FROM rooms" . ($houseId > 0 ? ' WHERE boarding_house_id = :house_id' : ''));
        $countStmt->execute($houseId > 0 ? [':house_id' => $houseId] : []);
        $counts = $countStmt->fetch();

        jsonResponse(array_map('formatRoom', $rows), '', 200, [
            'meta' => $meta,
            'counts' => array_map('intval', $counts),
        ]);

    case 'POST':
        $body = requestBody();
        $values = validateRoomPayload($body, true);
        $occupants = $values['occupants'] ?? 0;
        $status = roomStatusFor($values['capacity'], $occupants, $values['status'] ?? 'available');

        $pdo->beginTransaction();
        $pdo->prepare('INSERT INTO rooms (boarding_house_id, room_number, room_type, price, deposit, capacity, occupants, size_sqm, description, status)
            VALUES (:house, :number, :type, :price, :deposit, :capacity, :occupants, :size, :description, :status)')
            ->execute([
                ':house' => $values['boarding_house_id'],
                ':number' => $values['room_number'],
                ':type' => $values['room_type'],
                ':price' => $values['price'],
                ':deposit' => $values['deposit'] ?? 0,
                ':capacity' => $values['capacity'],
                ':occupants' => $occupants,
                ':size' => $values['size_sqm'] ?? null,
                ':description' => $values['description'] ?? null,
                ':status' => $status,
            ]);
        $id = (int) $pdo->lastInsertId();
        saveRoomAmenities($id, $values['amenity_ids'] ?? []);
        $pdo->commit();

        $room = loadRoom($id);
        adminLog('room_create', "Administrator added room {$room['room_number']} to {$room['boarding_house_name']}", 'room', $id);
        jsonResponse($room, 'Room created.', 201);

    case 'PUT':
    case 'PATCH':
        $id = requireId();
        $before = loadRoom($id);
        $body = requestBody();
        $values = validateRoomPayload($body, false, $before);

        $capacity = $values['capacity'] ?? $before['capacity'];
        $occupants = $values['occupants'] ?? $before['occupants'];
        $values['status'] = roomStatusFor($capacity, $occupants, $values['status'] ?? ($before['status'] === 'maintenance' ? 'maintenance' : 'available'));

        $columns = ['boarding_house_id', 'room_number', 'room_type', 'price', 'deposit', 'capacity', 'occupants', 'size_sqm', 'description', 'status'];
        $sets = [];
        $params = [':id' => $id];
        foreach ($columns as $column) {
            if (array_key_exists($column, $values)) {
                $sets[] = "{$column} = :{$column}";
                $params[':' . $column] = $values[$column];
            }
        }

        $pdo->beginTransaction();
        $pdo->prepare('UPDATE rooms SET ' . implode(', ', $sets) . ' WHERE id = :id')->execute($params);
        if (array_key_exists('amenity_ids', $values)) {
            saveRoomAmenities($id, $values['amenity_ids']);
        }
        $pdo->commit();

        $room = loadRoom($id);
        $note = $room['status'] !== $before['status'] ? " (availability: {$before['status']} → {$room['status']})" : '';
        adminLog('room_update', "Administrator updated room {$room['room_number']} at {$room['boarding_house_name']}{$note}", 'room', $id);
        jsonResponse($room, 'Room updated.');

    case 'DELETE':
        $id = requireId();
        $room = loadRoom($id);
        try {
            $pdo->prepare('DELETE FROM rooms WHERE id = :id')->execute([':id' => $id]);
        } catch (PDOException $e) {
            if (($e->errorInfo[1] ?? null) === 1451) {
                throw new ApiException('This room has booking records. Set it to maintenance instead of deleting it.', 409);
            }
            throw $e;
        }
        adminLog('room_delete', "Administrator deleted room {$room['room_number']} from {$room['boarding_house_name']}", 'room', $id);
        jsonResponse(null, 'Room deleted.');

    default:
        requireMethod('GET', 'POST', 'PUT', 'PATCH', 'DELETE');
}
