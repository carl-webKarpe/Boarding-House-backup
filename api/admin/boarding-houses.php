<?php

declare(strict_types=1);

/**
 * Admin boarding house management.
 *
 *   GET    boarding-houses.php                       list ?q=&status=&landlord_id=&city=&page=&sort=&order=
 *   GET    boarding-houses.php?id=3                  details (+ rooms, images, amenities)
 *   POST   boarding-houses.php                       create
 *   POST   boarding-houses.php?id=3&action=images    upload photos (multipart, field "images[]")
 *   PUT    boarding-houses.php?id=3                  update fields, or status (approve / reject / deactivate)
 *   DELETE boarding-houses.php?id=3                  delete (rooms are deleted too)
 *   DELETE boarding-houses.php?image_id=9            delete one photo
 */

require_once __DIR__ . '/_admin.php';
require_once __DIR__ . '/../../security/upload_security.php';

const HOUSE_SELECT = "SELECT bh.id, bh.landlord_id, bh.name, bh.description, bh.address, bh.barangay, bh.city, bh.province,
    bh.latitude, bh.longitude, bh.nearby_school, bh.contact_number, bh.contact_email, bh.house_rules, bh.status,
    bh.rejection_reason, bh.approved_at, bh.created_at, bh.updated_at,
    u.id AS owner_user_id, u.first_name AS owner_first_name, u.last_name AS owner_last_name, u.username AS owner_username,
    u.email AS owner_email, u.contact_number AS owner_contact,
    (SELECT COUNT(*) FROM rooms r WHERE r.boarding_house_id = bh.id) AS room_count,
    (SELECT COUNT(*) FROM rooms r WHERE r.boarding_house_id = bh.id AND r.status = 'available') AS available_rooms,
    (SELECT MIN(r.price) FROM rooms r WHERE r.boarding_house_id = bh.id) AS min_price,
    (SELECT MAX(r.price) FROM rooms r WHERE r.boarding_house_id = bh.id) AS max_price,
    (SELECT COALESCE(SUM(r.capacity), 0) FROM rooms r WHERE r.boarding_house_id = bh.id) AS total_capacity,
    (SELECT COALESCE(SUM(r.occupants), 0) FROM rooms r WHERE r.boarding_house_id = bh.id) AS total_occupants,
    (SELECT i.file_path FROM boarding_house_images i WHERE i.boarding_house_id = bh.id ORDER BY i.is_cover DESC, i.id LIMIT 1) AS cover_image";

const HOUSE_FROM = 'FROM boarding_houses bh JOIN landlords l ON l.id = bh.landlord_id JOIN users u ON u.id = l.user_id';

function formatHouse(array $row): array {
    $row = castRow($row, ['id', 'landlord_id', 'owner_user_id', 'room_count', 'available_rooms', 'total_capacity', 'total_occupants'], ['latitude', 'longitude', 'min_price', 'max_price']);
    $row['owner_name'] = fullName($row, 'owner_');
    return $row;
}

function loadHouse(int $id): array {
    $stmt = getDb()->prepare(HOUSE_SELECT . ' ' . HOUSE_FROM . ' WHERE bh.id = :id');
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch();
    if (!$row) {
        notFound('Boarding house');
    }

    return formatHouse($row);
}

function validateHousePayload(array $body, bool $creating): array {
    $rules = [];
    $text = [
        'name' => ['Boarding house name', 150, true],
        'description' => ['Description', 5000, false],
        'address' => ['Street address', 255, true],
        'barangay' => ['Barangay', 100, false],
        'city' => ['City / municipality', 100, true],
        'province' => ['Province', 100, false],
        'nearby_school' => ['Nearby school', 150, false],
        'house_rules' => ['House rules', 5000, false],
        'rejection_reason' => ['Rejection reason', 255, false],
    ];
    foreach ($text as $field => [$label, $max, $required]) {
        if ($creating || array_key_exists($field, $body)) {
            $rules[$field] = validateTextValue($body[$field] ?? '', $label, $max, $required);
        }
    }

    if ($creating || array_key_exists('landlord_id', $body)) {
        $rules['landlord_id'] = validateNumberValue($body['landlord_id'] ?? '', 'Landlord', 1, PHP_INT_MAX);
    }
    if (array_key_exists('contact_number', $body)) {
        $rules['contact_number'] = trim((string) $body['contact_number']) === '' ? ['valid' => true, 'value' => null] : validatePhoneValue($body['contact_number']);
    }
    if (array_key_exists('contact_email', $body)) {
        $rules['contact_email'] = trim((string) $body['contact_email']) === '' ? ['valid' => true, 'value' => null] : validateEmailValue($body['contact_email']);
    }
    foreach (['latitude' => [-90, 90], 'longitude' => [-180, 180]] as $field => [$min, $max]) {
        if (array_key_exists($field, $body)) {
            $rules[$field] = ($body[$field] === '' || $body[$field] === null) ? ['valid' => true, 'value' => null] : validateNumberValue($body[$field], ucfirst($field), $min, $max);
        }
    }
    if (array_key_exists('status', $body)) {
        $rules['status'] = validateEnumValue($body['status'] ?? '', ['pending', 'approved', 'rejected', 'inactive'], 'Status');
    }

    $values = collectValidated($rules);

    if (isset($values['landlord_id'])) {
        $values['landlord_id'] = (int) $values['landlord_id'];
        $stmt = getDb()->prepare('SELECT id FROM landlords WHERE id = :id');
        $stmt->execute([':id' => $values['landlord_id']]);
        if (!$stmt->fetch()) {
            throw new ApiException('The selected landlord does not exist.', 422, ['landlord_id' => 'Unknown landlord.']);
        }
    }

    if (($values['status'] ?? '') === 'rejected' && empty($values['rejection_reason'])) {
        throw new ApiException('Please give a reason so the landlord knows what to fix.', 422, ['rejection_reason' => 'Required when rejecting.']);
    }

    return $values;
}

$pdo = getDb();
$action = queryString('action');

switch (requestMethod()) {
    case 'GET':
        if (isset($_GET['id'])) {
            $house = loadHouse(requireId());

            $rooms = $pdo->prepare('SELECT id, room_number, room_type, price, capacity, occupants, status FROM rooms WHERE boarding_house_id = :id ORDER BY room_number');
            $rooms->execute([':id' => $house['id']]);
            $house['rooms'] = array_map(fn ($r) => castRow($r, ['id', 'capacity', 'occupants'], ['price']), $rooms->fetchAll());

            $images = $pdo->prepare('SELECT id, file_path, is_cover FROM boarding_house_images WHERE boarding_house_id = :id ORDER BY is_cover DESC, id');
            $images->execute([':id' => $house['id']]);
            $house['images'] = array_map(fn ($i) => castRow($i, ['id', 'is_cover']), $images->fetchAll());

            $amenities = $pdo->prepare('SELECT DISTINCT a.name FROM amenities a JOIN room_amenities ra ON ra.amenity_id = a.id JOIN rooms r ON r.id = ra.room_id WHERE r.boarding_house_id = :id ORDER BY a.name');
            $amenities->execute([':id' => $house['id']]);
            $house['amenities'] = $amenities->fetchAll(PDO::FETCH_COLUMN);

            jsonResponse($house);
        }

        $where = [];
        $params = [];
        $search = queryString('q');
        if ($search !== '') {
            $where[] = '(bh.name LIKE :q1 OR bh.address LIKE :q2 OR bh.city LIKE :q3 OR bh.barangay LIKE :q4 OR CONCAT(u.first_name, \' \', u.last_name) LIKE :q5)';
            foreach (['q1', 'q2', 'q3', 'q4', 'q5'] as $k) {
                $params[':' . $k] = likeValue($search);
            }
        }
        $status = queryString('status');
        if (in_array($status, ['pending', 'approved', 'rejected', 'inactive'], true)) {
            $where[] = 'bh.status = :status';
            $params[':status'] = $status;
        }
        $landlordId = queryInt('landlord_id', 0, 0);
        if ($landlordId > 0) {
            $where[] = 'bh.landlord_id = :landlord_id';
            $params[':landlord_id'] = $landlordId;
        }
        $city = queryString('city');
        if ($city !== '') {
            $where[] = 'bh.city = :city';
            $params[':city'] = $city;
        }

        $fromWhere = HOUSE_FROM . ($where ? ' WHERE ' . implode(' AND ', $where) : '');
        $order = orderBy([
            'created_at' => 'bh.created_at',
            'name' => 'bh.name',
            'city' => 'bh.city',
            'status' => 'bh.status',
            'rooms' => 'room_count',
            'price' => 'min_price',
        ], 'created_at');

        [$rows, $meta] = pagedQuery(HOUSE_SELECT, $fromWhere, $params, $order, paginationParams());
        $counts = $pdo->query("SELECT COUNT(*) AS all_houses, SUM(status = 'pending') AS pending, SUM(status = 'approved') AS approved,
            SUM(status = 'rejected') AS rejected, SUM(status = 'inactive') AS inactive FROM boarding_houses")->fetch();
        $cities = $pdo->query('SELECT DISTINCT city FROM boarding_houses ORDER BY city')->fetchAll(PDO::FETCH_COLUMN);

        jsonResponse(array_map('formatHouse', $rows), '', 200, [
            'meta' => $meta,
            'counts' => array_map('intval', $counts),
            'cities' => $cities,
        ]);

    case 'POST':
        if ($action === 'images') {
            $house = loadHouse(requireId());
            $files = $_FILES['images'] ?? null;
            if (!$files || !is_array($files['name'] ?? null)) {
                throw new ApiException('Choose at least one photo to upload.', 422);
            }

            $hasCover = (int) $pdo->query('SELECT COUNT(*) FROM boarding_house_images WHERE is_cover = 1 AND boarding_house_id = ' . (int) $house['id'])->fetchColumn() > 0;
            $saved = 0;
            $errors = [];
            foreach (array_keys($files['name']) as $i) {
                $result = storeListingImage([
                    'name' => $files['name'][$i],
                    'type' => $files['type'][$i],
                    'tmp_name' => $files['tmp_name'][$i],
                    'error' => $files['error'][$i],
                    'size' => $files['size'][$i],
                ]);
                if (!$result['success']) {
                    $errors[] = $files['name'][$i] . ': ' . $result['message'];
                    continue;
                }
                $pdo->prepare('INSERT INTO boarding_house_images (boarding_house_id, file_path, is_cover) VALUES (:id, :path, :cover)')
                    ->execute([':id' => $house['id'], ':path' => $result['path'], ':cover' => $hasCover ? 0 : 1]);
                $hasCover = true;
                $saved++;
            }

            if ($saved === 0) {
                throw new ApiException($errors ? implode(' ', $errors) : 'No photos were uploaded.', 422);
            }

            adminLog('listing_update', "Administrator added {$saved} photo(s) to {$house['name']}", 'boarding_house', $house['id']);
            jsonResponse(loadHouse($house['id']), $saved . ' photo(s) uploaded.' . ($errors ? ' Some files were skipped: ' . implode(' ', $errors) : ''), 201);
        }

        $values = validateHousePayload(requestBody(), true);
        $status = $values['status'] ?? 'approved';
        $stmt = $pdo->prepare('INSERT INTO boarding_houses (landlord_id, name, description, address, barangay, city, province, latitude, longitude, nearby_school, contact_number, contact_email, house_rules, status, approved_at, approved_by)
            VALUES (:landlord_id, :name, :description, :address, :barangay, :city, :province, :latitude, :longitude, :nearby_school, :contact_number, :contact_email, :house_rules, :status, :approved_at, :approved_by)');
        $stmt->execute([
            ':landlord_id' => $values['landlord_id'],
            ':name' => $values['name'],
            ':description' => $values['description'] ?? null,
            ':address' => $values['address'],
            ':barangay' => $values['barangay'] ?? null,
            ':city' => $values['city'],
            ':province' => $values['province'] ?? null,
            ':latitude' => $values['latitude'] ?? null,
            ':longitude' => $values['longitude'] ?? null,
            ':nearby_school' => $values['nearby_school'] ?? null,
            ':contact_number' => $values['contact_number'] ?? null,
            ':contact_email' => $values['contact_email'] ?? null,
            ':house_rules' => $values['house_rules'] ?? null,
            ':status' => $status,
            ':approved_at' => $status === 'approved' ? date('Y-m-d H:i:s') : null,
            ':approved_by' => $status === 'approved' ? adminId() : null,
        ]);
        $id = (int) $pdo->lastInsertId();
        $house = loadHouse($id);
        adminLog('listing_create', 'Administrator added boarding house: ' . $house['name'], 'boarding_house', $id);
        jsonResponse($house, 'Boarding house created.', 201);

    case 'PUT':
    case 'PATCH':
        $id = requireId();
        $before = loadHouse($id);
        $values = validateHousePayload(requestBody(), false);

        $columns = ['landlord_id', 'name', 'description', 'address', 'barangay', 'city', 'province', 'latitude', 'longitude', 'nearby_school', 'contact_number', 'contact_email', 'house_rules', 'status'];
        $sets = [];
        $params = [':id' => $id];
        foreach ($columns as $column) {
            if (array_key_exists($column, $values)) {
                $sets[] = "{$column} = :{$column}";
                $params[':' . $column] = $values[$column];
            }
        }

        $newStatus = $values['status'] ?? $before['status'];
        if ($newStatus !== $before['status']) {
            if ($newStatus === 'approved') {
                $sets[] = 'approved_at = NOW(), approved_by = :approved_by, rejection_reason = NULL';
                $params[':approved_by'] = adminId();
            } elseif ($newStatus === 'rejected') {
                $sets[] = 'rejection_reason = :rejection_reason';
                $params[':rejection_reason'] = $values['rejection_reason'];
            }
        }

        if (!$sets) {
            jsonResponse($before, 'Nothing to update.');
        }

        $pdo->prepare('UPDATE boarding_houses SET ' . implode(', ', $sets) . ' WHERE id = :id')->execute($params);
        $house = loadHouse($id);

        if ($newStatus !== $before['status']) {
            $labels = ['approved' => 'approved', 'rejected' => 'rejected', 'inactive' => 'deactivated', 'pending' => 'moved back to pending'];
            $action = ['approved' => 'listing_approve', 'rejected' => 'listing_reject'][$newStatus] ?? 'listing_update';
            adminLog($action, 'Boarding house ' . $labels[$newStatus] . ': ' . $house['name'], 'boarding_house', $id);
            $message = 'Your boarding house "' . $house['name'] . '" was ' . $labels[$newStatus] . '.';
            if ($newStatus === 'rejected') {
                $message .= ' Reason: ' . $values['rejection_reason'];
            }
            notifyUser($house['owner_user_id'], 'listing_status', 'Listing ' . $labels[$newStatus], $message);
            jsonResponse($house, 'Boarding house ' . $labels[$newStatus] . '.');
        }

        adminLog('listing_update', 'Administrator updated boarding house: ' . $house['name'], 'boarding_house', $id);
        jsonResponse($house, 'Boarding house updated.');

    case 'DELETE':
        if (isset($_GET['image_id'])) {
            $imageId = requireId('image_id');
            $stmt = $pdo->prepare('SELECT i.id, i.file_path, i.is_cover, i.boarding_house_id, bh.name FROM boarding_house_images i JOIN boarding_houses bh ON bh.id = i.boarding_house_id WHERE i.id = :id');
            $stmt->execute([':id' => $imageId]);
            $image = $stmt->fetch();
            if (!$image) {
                notFound('Photo');
            }
            $pdo->prepare('DELETE FROM boarding_house_images WHERE id = :id')->execute([':id' => $imageId]);
            if ((int) $image['is_cover'] === 1) {
                $pdo->prepare('UPDATE boarding_house_images SET is_cover = 1 WHERE boarding_house_id = :id ORDER BY id LIMIT 1')->execute([':id' => $image['boarding_house_id']]);
            }
            @unlink(BH_UPLOAD_DIR . '/listings/' . basename((string) $image['file_path']));
            adminLog('listing_update', 'Administrator removed a photo from ' . $image['name'], 'boarding_house', (int) $image['boarding_house_id']);
            jsonResponse(loadHouse((int) $image['boarding_house_id']), 'Photo removed.');
        }

        $id = requireId();
        $house = loadHouse($id);
        $images = $pdo->prepare('SELECT file_path FROM boarding_house_images WHERE boarding_house_id = :id');
        $images->execute([':id' => $id]);
        $paths = $images->fetchAll(PDO::FETCH_COLUMN);

        try {
            $pdo->prepare('DELETE FROM boarding_houses WHERE id = :id')->execute([':id' => $id]);
        } catch (PDOException $e) {
            if (($e->errorInfo[1] ?? null) === 1451) {
                throw new ApiException('Rooms in this boarding house have booking records. Deactivate the listing instead of deleting it.', 409);
            }
            throw $e;
        }
        foreach ($paths as $path) {
            @unlink(BH_UPLOAD_DIR . '/listings/' . basename((string) $path));
        }

        adminLog('listing_delete', 'Administrator deleted boarding house: ' . $house['name'], 'boarding_house', $id);
        jsonResponse(null, 'Boarding house deleted.');

    default:
        requireMethod('GET', 'POST', 'PUT', 'PATCH', 'DELETE');
}
