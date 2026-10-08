<?php

declare(strict_types=1);

/**
 * Rooms inside the logged-in landlord's boarding houses.
 *
 *   GET    rooms.php?house_id=3                   rooms of one house (or all my rooms without house_id)
 *   GET    rooms.php?id=7                         one room with photos and amenities
 *   POST   rooms.php                              add room(s)
 *   PUT    rooms.php?id=7                         edit a room
 *   PUT    rooms.php?id=7&action=availability     { availability: available | unavailable }
 *   DELETE rooms.php?id=7                         delete a room
 *   POST   rooms.php?id=7&action=images           multipart images[]
 *   DELETE rooms.php?image_id=4                   remove a room photo
 *
 * Room form:
 *   room_number   name/number, e.g. "Room 1"
 *   room_type     single | shared
 *   price         single: monthly rent; shared: price per person (per month)
 *   capacity      shared: number of occupants (single rooms are always 1)
 *   available_slots  how many of those places are free right now
 *   availability  available | unavailable (temporarily not for rent)
 *   quantity      create this many identical rooms at once (Room 1, Room 2, ...)
 *   amenity_ids[] + custom_amenities[] (new names)
 *   description, deposit, size_sqm
 */

require_once __DIR__ . '/_landlord.php';

const MAX_ROOM_PHOTOS = 10;

function formatLandlordRoom(array $row, array $amenities = [], array $images = []): array {
    $row = castRow($row, ['id', 'boarding_house_id', 'capacity', 'occupants', 'photo_count', 'pending_reservations'], ['price', 'deposit', 'size_sqm']);
    $row['room_type_label'] = ROOM_TYPE_LABELS[$row['room_type']] ?? $row['room_type'];
    $row['available_slots'] = max(0, $row['capacity'] - $row['occupants']);
    $row['availability'] = $row['status'] === 'maintenance' ? 'unavailable' : ($row['available_slots'] > 0 ? 'available' : 'full');
    if ($amenities) {
        $row['amenities'] = $amenities;
    }
    $row['images'] = $images;
    return $row;
}

function loadLandlordRoom(int $id): array {
    $pdo = getDb();
    $room = ownRoom($id);
    $amen = $pdo->prepare('SELECT a.id, a.name FROM room_amenities ra JOIN amenities a ON a.id = ra.amenity_id WHERE ra.room_id = :id ORDER BY a.name');
    $amen->execute([':id' => $id]);
    $amenities = $amen->fetchAll();
    $imgs = $pdo->prepare('SELECT id, file_path FROM room_images WHERE room_id = :id ORDER BY sort_order, id');
    $imgs->execute([':id' => $id]);

    $out = formatLandlordRoom($room, [], array_map(fn ($i) => castRow($i, ['id']), $imgs->fetchAll()));
    $out['amenities'] = array_column($amenities, 'name');
    $out['amenity_ids'] = array_map('intval', array_column($amenities, 'id'));
    unset($out['landlord_id']);
    return $out;
}

/**
 * Creates any custom amenity names that do not exist yet and returns all ids.
 */
function resolveAmenities(array $ids, array $customNames): array {
    $pdo = getDb();
    $ids = array_values(array_unique(array_filter(array_map('intval', $ids), fn ($i) => $i > 0)));
    if ($ids) {
        $in = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM amenities WHERE id IN ({$in})");
        $stmt->execute($ids);
        if ((int) $stmt->fetchColumn() !== count($ids)) {
            throw new ApiException('One of the amenities no longer exists. Refresh the page.', 422, ['amenity_ids' => 'Unknown amenity.']);
        }
    }

    foreach ($customNames as $name) {
        $name = trim(preg_replace('/\s+/', ' ', (string) $name));
        if ($name === '') {
            continue;
        }
        if (mb_strlen($name) > 60) {
            throw new ApiException('Amenity names must be 60 characters or less.', 422, ['custom_amenities' => 'Too long.']);
        }
        $find = $pdo->prepare('SELECT id FROM amenities WHERE LOWER(name) = LOWER(:name)');
        $find->execute([':name' => $name]);
        $id = $find->fetchColumn();
        if (!$id) {
            $pdo->prepare('INSERT INTO amenities (name, created_by) VALUES (:name, :user)')->execute([':name' => $name, ':user' => landlordUserId()]);
            $id = $pdo->lastInsertId();
        }
        $ids[] = (int) $id;
    }

    return array_values(array_unique($ids));
}

function saveAmenities(int $roomId, array $ids): void {
    $pdo = getDb();
    $pdo->prepare('DELETE FROM room_amenities WHERE room_id = :id')->execute([':id' => $roomId]);
    $insert = $pdo->prepare('INSERT INTO room_amenities (room_id, amenity_id) VALUES (:room, :amenity)');
    foreach ($ids as $amenityId) {
        $insert->execute([':room' => $roomId, ':amenity' => $amenityId]);
    }
}

/**
 * Validates the room form. Returns column values ready for the rooms table.
 */
function validateRoomForm(array $body, bool $creating, ?array $existing = null): array {
    $rules = [];
    if ($creating) {
        $rules['house_id'] = validateNumberValue($body['house_id'] ?? '', 'Boarding house', 1, PHP_INT_MAX);
        $rules['quantity'] = validateNumberValue(($body['quantity'] ?? '') === '' ? 1 : $body['quantity'], 'Number of rooms', 1, 20);
    }
    if ($creating || array_key_exists('room_number', $body)) {
        $rules['room_number'] = validateTextValue($body['room_number'] ?? '', 'Room name / number', 50);
    }
    if ($creating || array_key_exists('room_type', $body)) {
        $type = roomTypeFromInput((string) ($body['room_type'] ?? ''));
        $rules['room_type'] = $type ? ['valid' => true, 'value' => $type] : ['valid' => false, 'message' => 'Choose Single Room or Shared Room.'];
    }
    if ($creating || array_key_exists('price', $body)) {
        $rules['price'] = validateNumberValue($body['price'] ?? '', 'Monthly rent', 100, 100000);
    }
    if (array_key_exists('deposit', $body)) {
        $rules['deposit'] = ($body['deposit'] === '' || $body['deposit'] === null) ? ['valid' => true, 'value' => 0] : validateNumberValue($body['deposit'], 'Deposit', 0, 200000);
    }
    if ($creating || array_key_exists('capacity', $body)) {
        $rules['capacity'] = validateNumberValue(($body['capacity'] ?? '') === '' ? 1 : $body['capacity'], 'Number of occupants', 1, 20);
    }
    if ($creating || array_key_exists('available_slots', $body)) {
        $rules['available_slots'] = validateNumberValue(($body['available_slots'] ?? '') === '' ? 0 : $body['available_slots'], 'Available slots', 0, 20);
    }
    if (array_key_exists('size_sqm', $body)) {
        $rules['size_sqm'] = ($body['size_sqm'] === '' || $body['size_sqm'] === null) ? ['valid' => true, 'value' => null] : validateNumberValue($body['size_sqm'], 'Room size', 1, 1000);
    }
    if ($creating || array_key_exists('description', $body)) {
        $rules['description'] = validateTextValue($body['description'] ?? '', 'Room description', 5000, false);
    }
    if ($creating || array_key_exists('availability', $body)) {
        $rules['availability'] = validateEnumValue($body['availability'] ?? 'available', ['available', 'unavailable'], 'Availability');
    }

    $values = collectValidated($rules);

    $type = $values['room_type'] ?? $existing['room_type'] ?? 'solo';
    $capacity = (int) ($values['capacity'] ?? $existing['capacity'] ?? 1);
    if ($type === 'solo' || $type === 'studio') {
        $capacity = 1;
    } elseif ($type === 'shared' && $capacity < 2) {
        throw new ApiException('A shared room is for at least 2 occupants.', 422, ['capacity' => 'At least 2.']);
    }
    $values['capacity'] = $capacity;

    $existingOpen = $existing ? max(0, (int) $existing['capacity'] - (int) $existing['occupants']) : $capacity;
    $open = (int) ($values['available_slots'] ?? $existingOpen);
    if ($open > $capacity) {
        throw new ApiException('Available slots cannot be more than the number of occupants.', 422, ['available_slots' => "Maximum {$capacity}."]);
    }
    $values['occupants'] = $capacity - $open;

    $availability = $values['availability'] ?? ($existing && $existing['status'] === 'maintenance' ? 'unavailable' : 'available');
    $values['status'] = roomStatusFor($capacity, $values['occupants'], $availability === 'unavailable' ? 'maintenance' : 'available');
    unset($values['available_slots'], $values['availability']);

    $values['amenity_ids'] = resolveAmenities(
        is_array($body['amenity_ids'] ?? null) ? $body['amenity_ids'] : [],
        is_array($body['custom_amenities'] ?? null) ? $body['custom_amenities'] : []
    );

    return $values;
}

/**
 * "Room" + quantity 3 -> Room 1, Room 2, Room 3; "Room 4" + 2 -> Room 4, Room 5.
 */
function roomNames(string $base, int $quantity): array {
    if ($quantity === 1) {
        return [$base];
    }
    if (preg_match('/^(.*?)(\d+)$/', $base, $m)) {
        $prefix = $m[1];
        $start = (int) $m[2];
    } else {
        $prefix = rtrim($base) . ' ';
        $start = 1;
    }

    return array_map(fn ($i) => $prefix . ($start + $i), range(0, $quantity - 1));
}

$pdo = getDb();
$action = queryString('action');

switch (requestMethod()) {
    case 'GET':
        if (isset($_GET['id'])) {
            jsonResponse(loadLandlordRoom(requireId()));
        }

        $where = ['bh.landlord_id = :landlord'];
        $params = [':landlord' => landlordId()];
        $houseId = queryInt('house_id', 0, 0);
        if ($houseId > 0) {
            ownHouse($houseId);
            $where[] = 'r.boarding_house_id = :house';
            $params[':house'] = $houseId;
        }
        $stmt = $pdo->prepare("SELECT r.id, r.boarding_house_id, r.room_number, r.room_type, r.price, r.deposit, r.capacity, r.occupants,
                r.size_sqm, r.status, r.created_at, r.updated_at, bh.name AS house_name,
                (SELECT COUNT(*) FROM room_images i WHERE i.room_id = r.id) AS photo_count,
                (SELECT i.file_path FROM room_images i WHERE i.room_id = r.id ORDER BY i.sort_order, i.id LIMIT 1) AS photo,
                (SELECT COUNT(*) FROM bookings b WHERE b.room_id = r.id AND b.status = 'pending') AS pending_reservations,
                (SELECT GROUP_CONCAT(a.name ORDER BY a.name SEPARATOR '||') FROM room_amenities ra JOIN amenities a ON a.id = ra.amenity_id WHERE ra.room_id = r.id) AS amenity_names
            FROM rooms r JOIN boarding_houses bh ON bh.id = r.boarding_house_id
            WHERE " . implode(' AND ', $where) . ' ORDER BY bh.name, r.room_number LIMIT 500');
        $stmt->execute($params);
        $rows = array_map(static function ($row) {
            $amenities = $row['amenity_names'] ? explode('||', $row['amenity_names']) : [];
            unset($row['amenity_names']);
            $room = formatLandlordRoom($row);
            $room['amenities'] = $amenities;
            return $room;
        }, $stmt->fetchAll());
        jsonResponse($rows);

    case 'POST':
        if ($action === 'images') {
            $room = ownRoom(requireId());
            $roomId = (int) $room['id'];
            $files = uploadedFileList($_FILES['images'] ?? null);
            if (!$files) {
                throw new ApiException('Choose at least one photo.', 422);
            }
            $count = $pdo->prepare('SELECT COUNT(*), COALESCE(MAX(sort_order), 0) FROM room_images WHERE room_id = :id');
            $count->execute([':id' => $roomId]);
            [$existing, $maxOrder] = array_map('intval', $count->fetch(PDO::FETCH_NUM));
            if ($existing + count($files) > MAX_ROOM_PHOTOS) {
                throw new ApiException('A room can have at most ' . MAX_ROOM_PHOTOS . ' photos.', 422);
            }
            $insert = $pdo->prepare('INSERT INTO room_images (room_id, file_path, sort_order) VALUES (:room, :path, :sort)');
            $saved = 0;
            $errors = [];
            foreach ($files as $file) {
                $result = storeListingImage($file);
                if (!$result['success']) {
                    $errors[] = $file['name'] . ': ' . $result['message'];
                    continue;
                }
                $insert->execute([':room' => $roomId, ':path' => $result['path'], ':sort' => ++$maxOrder]);
                $saved++;
            }
            if ($saved === 0) {
                throw new ApiException(implode(' ', $errors) ?: 'No photos were uploaded.', 422);
            }
            jsonResponse(loadLandlordRoom($roomId), $saved . ' room photo(s) uploaded.' . ($errors ? ' Skipped: ' . implode(' ', $errors) : ''), 201);
        }

        $body = requestBody();
        $values = validateRoomForm($body, true);
        $house = ownHouse((int) $values['house_id']);
        $names = roomNames($values['room_number'], (int) $values['quantity']);

        $check = $pdo->prepare('SELECT room_number FROM rooms WHERE boarding_house_id = :house AND room_number = :name');
        foreach ($names as $name) {
            $check->execute([':house' => $house['id'], ':name' => $name]);
            if ($check->fetch()) {
                throw new ApiException("\"{$name}\" already exists in this boarding house. Use a different room name.", 409, ['room_number' => 'Already used.']);
            }
        }

        $pdo->beginTransaction();
        $insert = $pdo->prepare('INSERT INTO rooms (boarding_house_id, room_number, room_type, price, deposit, capacity, occupants, size_sqm, description, status)
            VALUES (:house, :name, :type, :price, :deposit, :capacity, :occupants, :size, :description, :status)');
        $ids = [];
        foreach ($names as $name) {
            $insert->execute([
                ':house' => $house['id'],
                ':name' => $name,
                ':type' => $values['room_type'],
                ':price' => $values['price'],
                ':deposit' => $values['deposit'] ?? 0,
                ':capacity' => $values['capacity'],
                ':occupants' => $values['occupants'],
                ':size' => $values['size_sqm'] ?? null,
                ':description' => $values['description'],
                ':status' => $values['status'],
            ]);
            $ids[] = (int) $pdo->lastInsertId();
            saveAmenities(end($ids), $values['amenity_ids']);
        }
        $pdo->commit();

        landlordLog('room_create', 'Landlord added ' . (count($names) === 1 ? 'room ' . $names[0] : count($names) . ' rooms') . ' to ' . $house['name'], 'room', $ids[0]);
        jsonResponse([
            'ids' => $ids,
            'rooms' => array_map('loadLandlordRoom', $ids),
        ], count($ids) === 1 ? 'Room added.' : count($ids) . ' rooms added.', 201);

    case 'PUT':
    case 'PATCH':
        $room = ownRoom(requireId());
        $id = (int) $room['id'];

        if ($action === 'availability') {
            $values = collectValidated(['availability' => validateEnumValue(requestBody()['availability'] ?? '', ['available', 'unavailable'], 'Availability')]);
            $status = roomStatusFor((int) $room['capacity'], (int) $room['occupants'], $values['availability'] === 'unavailable' ? 'maintenance' : 'available');
            $pdo->prepare('UPDATE rooms SET status = :status WHERE id = :id')->execute([':status' => $status, ':id' => $id]);
            landlordLog('room_update', "Landlord set room {$room['room_number']} at {$room['house_name']} to " . ($status === 'maintenance' ? 'unavailable' : $status), 'room', $id);
            jsonResponse(loadLandlordRoom($id), $status === 'maintenance' ? 'Room marked as temporarily unavailable.' : 'Room is open for reservations.');
        }

        $body = requestBody();
        $values = validateRoomForm($body, false, $room);
        if (isset($values['room_number']) && $values['room_number'] !== $room['room_number']) {
            $check = $pdo->prepare('SELECT id FROM rooms WHERE boarding_house_id = :house AND room_number = :name AND id <> :id');
            $check->execute([':house' => $room['boarding_house_id'], ':name' => $values['room_number'], ':id' => $id]);
            if ($check->fetch()) {
                throw new ApiException('Another room in this boarding house already has that name.', 409, ['room_number' => 'Already used.']);
            }
        }

        $columns = ['room_number', 'room_type', 'price', 'deposit', 'capacity', 'occupants', 'size_sqm', 'description', 'status'];
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
        if (array_key_exists('amenity_ids', $body) || array_key_exists('custom_amenities', $body)) {
            saveAmenities($id, $values['amenity_ids']);
        }
        $pdo->commit();
        landlordLog('room_update', "Landlord updated room {$room['room_number']} at {$room['house_name']}", 'room', $id);
        jsonResponse(loadLandlordRoom($id), 'Room updated.');

    case 'DELETE':
        if (isset($_GET['image_id'])) {
            $imageId = requireId('image_id');
            $stmt = $pdo->prepare('SELECT i.id, i.room_id, i.file_path FROM room_images i JOIN rooms r ON r.id = i.room_id
                JOIN boarding_houses bh ON bh.id = r.boarding_house_id WHERE i.id = :id AND bh.landlord_id = :landlord');
            $stmt->execute([':id' => $imageId, ':landlord' => landlordId()]);
            $image = $stmt->fetch();
            if (!$image) {
                notFound('Photo');
            }
            $pdo->prepare('DELETE FROM room_images WHERE id = :id')->execute([':id' => $imageId]);
            deleteUploadedImage((string) $image['file_path']);
            jsonResponse(loadLandlordRoom((int) $image['room_id']), 'Photo removed.');
        }

        $room = ownRoom(requireId());
        $id = (int) $room['id'];
        $photos = $pdo->prepare('SELECT file_path FROM room_images WHERE room_id = :id');
        $photos->execute([':id' => $id]);
        $paths = $photos->fetchAll(PDO::FETCH_COLUMN);
        try {
            $pdo->prepare('DELETE FROM rooms WHERE id = :id')->execute([':id' => $id]);
        } catch (PDOException $e) {
            if (($e->errorInfo[1] ?? null) === 1451) {
                throw new ApiException('This room has reservation history, so it cannot be deleted. Mark it as unavailable instead.', 409);
            }
            throw $e;
        }
        foreach ($paths as $path) {
            deleteUploadedImage((string) $path);
        }
        landlordLog('room_delete', "Landlord deleted room {$room['room_number']} from {$room['house_name']}", 'room', $id);
        jsonResponse(null, 'Room deleted.');

    default:
        requireMethod('GET', 'POST', 'PUT', 'PATCH', 'DELETE');
}
