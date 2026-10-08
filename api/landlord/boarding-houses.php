<?php

declare(strict_types=1);

/**
 * The logged-in landlord's own boarding houses.
 *
 *   GET    boarding-houses.php                        my listings (?q=&status=)
 *   GET    boarding-houses.php?id=3                   one listing with photos and rooms
 *   POST   boarding-houses.php                        create a listing
 *   PUT    boarding-houses.php?id=3                   edit a listing
 *   PUT    boarding-houses.php?id=3&action=status     { availability_status }
 *   DELETE boarding-houses.php?id=3                   delete a listing
 *   POST   boarding-houses.php?id=3&action=images     multipart: cover (main photo), images[] (more photos)
 *   PUT    boarding-houses.php?image_id=9&action=cover make a photo the main photo
 *   DELETE boarding-houses.php?image_id=9             remove a photo
 */

require_once __DIR__ . '/_landlord.php';

const MAX_HOUSE_PHOTOS = 20;

const HOUSE_LIST_SELECT = "SELECT bh.id, bh.name, bh.barangay, bh.city, bh.province, bh.address, bh.status, bh.availability_status,
    bh.rejection_reason, bh.created_at, bh.updated_at,
    (SELECT COUNT(*) FROM rooms r WHERE r.boarding_house_id = bh.id) AS room_count,
    (SELECT COUNT(*) FROM rooms r WHERE r.boarding_house_id = bh.id AND r.status = 'available') AS available_rooms,
    (SELECT COALESCE(SUM(GREATEST(r.capacity - r.occupants, 0)), 0) FROM rooms r WHERE r.boarding_house_id = bh.id AND r.status <> 'maintenance') AS open_slots,
    (SELECT MIN(r.price) FROM rooms r WHERE r.boarding_house_id = bh.id) AS min_price,
    (SELECT MAX(r.price) FROM rooms r WHERE r.boarding_house_id = bh.id) AS max_price,
    (SELECT GROUP_CONCAT(DISTINCT r.room_type) FROM rooms r WHERE r.boarding_house_id = bh.id) AS room_types,
    (SELECT COUNT(*) FROM bookings b JOIN rooms r ON r.id = b.room_id WHERE r.boarding_house_id = bh.id AND b.status = 'pending') AS pending_reservations,
    (SELECT i.file_path FROM boarding_house_images i WHERE i.boarding_house_id = bh.id ORDER BY i.is_cover DESC, i.id LIMIT 1) AS cover_image
    FROM boarding_houses bh";

function formatHouseListItem(array $row): array {
    $row = castRow($row, ['id', 'room_count', 'available_rooms', 'open_slots', 'pending_reservations'], ['min_price', 'max_price']);
    $types = array_filter(explode(',', (string) $row['room_types']));
    $row['room_types'] = array_values(array_map(fn ($t) => ROOM_TYPE_LABELS[$t] ?? $t, $types));
    $row['display_status'] = listingStatus($row['status'], $row['availability_status']);
    return $row;
}

function loadHouseDetails(int $id): array {
    $pdo = getDb();
    $house = ownHouse($id);
    $house = castRow($house, ['id', 'landlord_id', 'barangay_id'], ['latitude', 'longitude']);
    unset($house['approved_by']);
    $house['display_status'] = listingStatus($house['status'], $house['availability_status']);

    $images = $pdo->prepare('SELECT id, file_path, is_cover FROM boarding_house_images WHERE boarding_house_id = :id ORDER BY is_cover DESC, id');
    $images->execute([':id' => $id]);
    $house['images'] = array_map(fn ($i) => castRow($i, ['id', 'is_cover']), $images->fetchAll());

    $rooms = $pdo->prepare("SELECT r.id, r.room_number, r.room_type, r.price, r.capacity, r.occupants, r.status,
            (SELECT COUNT(*) FROM room_images i WHERE i.room_id = r.id) AS photo_count
        FROM rooms r WHERE r.boarding_house_id = :id ORDER BY r.room_number");
    $rooms->execute([':id' => $id]);
    $house['rooms'] = array_map(static function ($r) {
        $r = castRow($r, ['id', 'capacity', 'occupants', 'photo_count'], ['price']);
        $r['room_type_label'] = ROOM_TYPE_LABELS[$r['room_type']] ?? $r['room_type'];
        $r['available_slots'] = max(0, $r['capacity'] - $r['occupants']);
        return $r;
    }, $rooms->fetchAll());

    return $house;
}

/**
 * Validates the listing form. Barangay, municipality and province come from
 * the barangays table, so tenants always see consistent location names.
 */
function validateHouseForm(array $body, bool $creating): array {
    $rules = [];
    $text = [
        'name' => ['Boarding house name', 150, true],
        'contact_name' => ['Landlord / contact name', 160, true],
        'address' => ['Complete address', 255, true],
        'location_note' => ['Location / landmark', 255, false],
        'nearby_school' => ['Nearby school', 150, false],
        'distance_note' => ['Distance from school', 150, false],
        'description' => ['Description', 5000, false],
        'house_rules' => ['House rules', 5000, false],
    ];
    foreach ($text as $field => [$label, $max, $required]) {
        if ($creating || array_key_exists($field, $body)) {
            $rules[$field] = validateTextValue($body[$field] ?? '', $label, $max, $required);
        }
    }
    if ($creating || array_key_exists('contact_number', $body)) {
        $rules['contact_number'] = validatePhoneValue($body['contact_number'] ?? '');
    }
    if ($creating || array_key_exists('contact_email', $body)) {
        $rules['contact_email'] = validateEmailValue($body['contact_email'] ?? '');
    }
    if ($creating || array_key_exists('barangay_id', $body)) {
        $rules['barangay_id'] = validateNumberValue($body['barangay_id'] ?? '', 'Barangay', 1, PHP_INT_MAX);
    }
    foreach (['latitude' => [-90, 90], 'longitude' => [-180, 180]] as $field => [$min, $max]) {
        if (array_key_exists($field, $body)) {
            $rules[$field] = ($body[$field] === '' || $body[$field] === null)
                ? ['valid' => true, 'value' => null]
                : validateNumberValue($body[$field], ucfirst($field), $min, $max);
        }
    }
    if (array_key_exists('map_url', $body)) {
        $url = trim((string) $body['map_url']);
        $rules['map_url'] = $url === ''
            ? ['valid' => true, 'value' => null]
            : (filter_var($url, FILTER_VALIDATE_URL) && preg_match('#^https?://#i', $url) && strlen($url) <= 500
                ? ['valid' => true, 'value' => $url]
                : ['valid' => false, 'message' => 'Paste a full Google Maps link that starts with https://']);
    }
    if (array_key_exists('availability_status', $body)) {
        $rules['availability_status'] = validateEnumValue($body['availability_status'], array_keys(AVAILABILITY_LABELS), 'Listing status');
    }

    $values = collectValidated($rules);

    if (isset($values['barangay_id'])) {
        $stmt = getDb()->prepare('SELECT id, name, municipality, province FROM barangays WHERE id = :id AND is_active = 1');
        $stmt->execute([':id' => (int) $values['barangay_id']]);
        $barangay = $stmt->fetch();
        if (!$barangay) {
            throw new ApiException('Please choose a barangay from the list.', 422, ['barangay_id' => 'Unknown barangay.']);
        }
        $values['barangay_id'] = (int) $barangay['id'];
        $values['barangay'] = $barangay['name'];
        $values['city'] = $barangay['municipality'];
        $values['province'] = $barangay['province'];
    }

    $hasLat = ($values['latitude'] ?? null) !== null;
    $hasLng = ($values['longitude'] ?? null) !== null;
    if ($hasLat !== $hasLng) {
        throw new ApiException('Set both latitude and longitude, or leave both empty.', 422, ['latitude' => 'Incomplete map location.']);
    }

    return $values;
}

$pdo = getDb();
$action = queryString('action');

switch (requestMethod()) {
    case 'GET':
        if (isset($_GET['id'])) {
            jsonResponse(loadHouseDetails(requireId()));
        }

        $where = ['bh.landlord_id = :landlord'];
        $params = [':landlord' => landlordId()];
        $search = queryString('q');
        if ($search !== '') {
            $where[] = '(bh.name LIKE :q1 OR bh.barangay LIKE :q2 OR bh.address LIKE :q3)';
            $params += [':q1' => likeValue($search), ':q2' => likeValue($search), ':q3' => likeValue($search)];
        }
        $stmt = $pdo->prepare(HOUSE_LIST_SELECT . ' WHERE ' . implode(' AND ', $where) . ' ORDER BY bh.created_at DESC, bh.id DESC LIMIT 200');
        $stmt->execute($params);
        $rows = array_map('formatHouseListItem', $stmt->fetchAll());

        $filter = queryString('status');
        if ($filter !== '') {
            $rows = array_values(array_filter($rows, fn ($r) => $r['display_status']['key'] === $filter));
        }
        jsonResponse($rows);

    case 'POST':
        if ($action === 'images') {
            $house = ownHouse(requireId());
            $houseId = (int) $house['id'];
            $cover = uploadedFileList($_FILES['cover'] ?? null);
            $more = uploadedFileList($_FILES['images'] ?? null);
            if (!$cover && !$more) {
                throw new ApiException('Choose at least one photo.', 422);
            }

            $count = $pdo->prepare('SELECT COUNT(*) FROM boarding_house_images WHERE boarding_house_id = :id');
            $count->execute([':id' => $houseId]);
            if ((int) $count->fetchColumn() + count($cover) + count($more) > MAX_HOUSE_PHOTOS) {
                throw new ApiException('A boarding house can have at most ' . MAX_HOUSE_PHOTOS . ' photos. Remove some first.', 422);
            }

            $saved = 0;
            $errors = [];
            $insert = $pdo->prepare('INSERT INTO boarding_house_images (boarding_house_id, file_path, is_cover) VALUES (:id, :path, :cover)');
            foreach ([[$cover, true], [$more, false]] as [$files, $isCover]) {
                foreach ($files as $file) {
                    $result = storeListingImage($file);
                    if (!$result['success']) {
                        $errors[] = $file['name'] . ': ' . $result['message'];
                        continue;
                    }
                    if ($isCover) {
                        $pdo->prepare('UPDATE boarding_house_images SET is_cover = 0 WHERE boarding_house_id = :id')->execute([':id' => $houseId]);
                    }
                    $insert->execute([':id' => $houseId, ':path' => $result['path'], ':cover' => $isCover ? 1 : 0]);
                    $saved++;
                }
            }
            // Make sure there is always one main photo.
            $hasCover = $pdo->prepare('SELECT COUNT(*) FROM boarding_house_images WHERE boarding_house_id = :id AND is_cover = 1');
            $hasCover->execute([':id' => $houseId]);
            if ((int) $hasCover->fetchColumn() === 0) {
                $pdo->prepare('UPDATE boarding_house_images SET is_cover = 1 WHERE boarding_house_id = :id ORDER BY id LIMIT 1')->execute([':id' => $houseId]);
            }

            if ($saved === 0) {
                throw new ApiException(implode(' ', $errors) ?: 'No photos were uploaded.', 422);
            }
            landlordLog('listing_update', "Landlord added {$saved} photo(s) to {$house['name']}", 'boarding_house', $houseId);
            jsonResponse(loadHouseDetails($houseId), $saved . ' photo(s) uploaded.' . ($errors ? ' Skipped: ' . implode(' ', $errors) : ''), 201);
        }

        $values = validateHouseForm(requestBody(), true);
        $needsApproval = settingEnabled('require_listing_approval');
        $status = $needsApproval ? 'pending' : 'approved';
        $pdo->prepare('INSERT INTO boarding_houses (landlord_id, name, description, address, barangay_id, barangay, city, province, location_note,
                latitude, longitude, map_url, nearby_school, distance_note, contact_name, contact_number, contact_email, house_rules,
                status, availability_status, approved_at)
            VALUES (:landlord, :name, :description, :address, :barangay_id, :barangay, :city, :province, :location_note,
                :latitude, :longitude, :map_url, :nearby_school, :distance_note, :contact_name, :contact_number, :contact_email, :house_rules,
                :status, :availability, :approved_at)')
            ->execute([
                ':landlord' => landlordId(),
                ':name' => $values['name'],
                ':description' => $values['description'] ?? null,
                ':address' => $values['address'],
                ':barangay_id' => $values['barangay_id'],
                ':barangay' => $values['barangay'],
                ':city' => $values['city'],
                ':province' => $values['province'],
                ':location_note' => $values['location_note'] ?? null,
                ':latitude' => $values['latitude'] ?? null,
                ':longitude' => $values['longitude'] ?? null,
                ':map_url' => $values['map_url'] ?? null,
                ':nearby_school' => $values['nearby_school'] ?? null,
                ':distance_note' => $values['distance_note'] ?? null,
                ':contact_name' => $values['contact_name'],
                ':contact_number' => $values['contact_number'],
                ':contact_email' => $values['contact_email'],
                ':house_rules' => $values['house_rules'] ?? null,
                ':status' => $status,
                ':availability' => $values['availability_status'] ?? 'available',
                ':approved_at' => $needsApproval ? null : date('Y-m-d H:i:s'),
            ]);
        $id = (int) $pdo->lastInsertId();

        landlordLog('listing_create', 'New boarding house submitted: ' . $values['name'], 'boarding_house', $id);
        if ($needsApproval) {
            notifyAdmins('listing_submitted', 'Listing requires approval', landlordName() . ' submitted "' . $values['name'] . '" (' . $values['barangay'] . ', ' . $values['city'] . ').', '#/boarding-houses?status=pending');
        }

        jsonResponse(loadHouseDetails($id), $needsApproval
            ? 'Boarding house saved. It will appear to students after an administrator approves it.'
            : 'Boarding house saved and published.', 201);

    case 'PUT':
    case 'PATCH':
        if (isset($_GET['image_id']) && $action === 'cover') {
            $imageId = requireId('image_id');
            $stmt = $pdo->prepare('SELECT i.boarding_house_id FROM boarding_house_images i JOIN boarding_houses bh ON bh.id = i.boarding_house_id
                WHERE i.id = :id AND bh.landlord_id = :landlord');
            $stmt->execute([':id' => $imageId, ':landlord' => landlordId()]);
            $houseId = $stmt->fetchColumn();
            if (!$houseId) {
                notFound('Photo');
            }
            $pdo->prepare('UPDATE boarding_house_images SET is_cover = (id = :image) WHERE boarding_house_id = :house')
                ->execute([':image' => $imageId, ':house' => $houseId]);
            jsonResponse(loadHouseDetails((int) $houseId), 'Main photo updated.');
        }

        $house = ownHouse(requireId());
        $id = (int) $house['id'];

        if ($action === 'status') {
            $values = collectValidated(['availability_status' => validateEnumValue(requestBody()['availability_status'] ?? '', array_keys(AVAILABILITY_LABELS), 'Listing status')]);
            $pdo->prepare('UPDATE boarding_houses SET availability_status = :status WHERE id = :id')->execute([':status' => $values['availability_status'], ':id' => $id]);
            landlordLog('listing_update', "Listing status of {$house['name']} set to " . AVAILABILITY_LABELS[$values['availability_status']], 'boarding_house', $id);
            jsonResponse(loadHouseDetails($id), 'Listing status updated to "' . AVAILABILITY_LABELS[$values['availability_status']] . '".');
        }

        $values = validateHouseForm(requestBody(), false);
        $columns = ['name', 'description', 'address', 'barangay_id', 'barangay', 'city', 'province', 'location_note', 'latitude', 'longitude',
            'map_url', 'nearby_school', 'distance_note', 'contact_name', 'contact_number', 'contact_email', 'house_rules', 'availability_status'];
        $sets = [];
        $params = [':id' => $id];
        foreach ($columns as $column) {
            if (array_key_exists($column, $values)) {
                $sets[] = "{$column} = :{$column}";
                $params[':' . $column] = $values[$column];
            }
        }
        // A rejected listing is sent back for review after the landlord fixes it.
        $resubmitted = $house['status'] === 'rejected';
        if ($resubmitted) {
            $sets[] = "status = 'pending', rejection_reason = NULL";
        }
        if (!$sets) {
            jsonResponse(loadHouseDetails($id), 'Nothing to update.');
        }
        $pdo->prepare('UPDATE boarding_houses SET ' . implode(', ', $sets) . ' WHERE id = :id AND landlord_id = ' . landlordId())->execute($params);
        landlordLog('listing_update', 'Landlord updated boarding house: ' . ($values['name'] ?? $house['name']), 'boarding_house', $id);
        if ($resubmitted) {
            notifyAdmins('listing_submitted', 'Listing resubmitted', landlordName() . ' updated "' . ($values['name'] ?? $house['name']) . '" and sent it for approval again.', '#/boarding-houses?status=pending');
        }
        jsonResponse(loadHouseDetails($id), $resubmitted ? 'Changes saved and sent for approval again.' : 'Boarding house updated.');

    case 'DELETE':
        if (isset($_GET['image_id'])) {
            $imageId = requireId('image_id');
            $stmt = $pdo->prepare('SELECT i.id, i.file_path, i.is_cover, i.boarding_house_id FROM boarding_house_images i
                JOIN boarding_houses bh ON bh.id = i.boarding_house_id WHERE i.id = :id AND bh.landlord_id = :landlord');
            $stmt->execute([':id' => $imageId, ':landlord' => landlordId()]);
            $image = $stmt->fetch();
            if (!$image) {
                notFound('Photo');
            }
            $pdo->prepare('DELETE FROM boarding_house_images WHERE id = :id')->execute([':id' => $imageId]);
            if ((int) $image['is_cover'] === 1) {
                $pdo->prepare('UPDATE boarding_house_images SET is_cover = 1 WHERE boarding_house_id = :id ORDER BY id LIMIT 1')->execute([':id' => $image['boarding_house_id']]);
            }
            deleteUploadedImage((string) $image['file_path']);
            jsonResponse(loadHouseDetails((int) $image['boarding_house_id']), 'Photo removed.');
        }

        $house = ownHouse(requireId());
        $id = (int) $house['id'];
        $photos = $pdo->prepare('SELECT file_path FROM boarding_house_images WHERE boarding_house_id = :id
            UNION ALL SELECT ri.file_path FROM room_images ri JOIN rooms r ON r.id = ri.room_id WHERE r.boarding_house_id = :id2');
        $photos->execute([':id' => $id, ':id2' => $id]);
        $paths = $photos->fetchAll(PDO::FETCH_COLUMN);

        try {
            $pdo->prepare('DELETE FROM boarding_houses WHERE id = :id AND landlord_id = :landlord')->execute([':id' => $id, ':landlord' => landlordId()]);
        } catch (PDOException $e) {
            if (($e->errorInfo[1] ?? null) === 1451) {
                throw new ApiException('This boarding house has reservation history, so it cannot be deleted. Set its status to "Temporarily Unavailable" instead.', 409);
            }
            throw $e;
        }
        foreach ($paths as $path) {
            deleteUploadedImage((string) $path);
        }
        landlordLog('listing_delete', 'Landlord deleted boarding house: ' . $house['name'], 'boarding_house', $id);
        jsonResponse(null, 'Boarding house deleted.');

    default:
        requireMethod('GET', 'POST', 'PUT', 'PATCH', 'DELETE');
}
