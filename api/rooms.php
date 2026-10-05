<?php

declare(strict_types=1);

/**
 * Public room listings for students (php/browse-rooms.php).
 *
 *   GET api/rooms.php                 newest rooms first
 *       ?page=1&per_page=6
 *       &q=        search boarding house, barangay, city, address
 *       &type=     solo | shared | dormitory | studio
 *       &max_price=
 *       &available=1   only rooms with an open slot
 *       &sort=     newest (default) | price_asc | price_desc | nearest
 *   GET api/rooms.php?id=12           one room with full details
 *   GET api/rooms.php?version=1       fingerprint of the listings, so the page
 *                                     can tell when a landlord changed something
 *
 * Only rooms in APPROVED boarding houses of active landlords are listed, and
 * rooms under maintenance are hidden. Nothing here is hard-coded: every
 * value comes from the database.
 */

require_once __DIR__ . '/_common.php';

apiBootstrap();
requireMethod('GET');

const ROOM_TYPE_LABELS = [
    'solo' => 'Single Room',
    'shared' => 'Shared Room',
    'dormitory' => 'Bedspace',
    'studio' => 'Studio',
];

// Siargao Island Institute of Technology, National Highway, Dapa.
const SCHOOL_NAME = 'SIIT';
const SCHOOL_LAT = 9.760829;
const SCHOOL_LNG = 126.047129;

const ROOM_BASE_FROM = "FROM rooms r
    JOIN boarding_houses bh ON bh.id = r.boarding_house_id
    JOIN landlords l ON l.id = bh.landlord_id
    JOIN users u ON u.id = l.user_id";

const ROOM_BASE_WHERE = "bh.status = 'approved' AND u.status <> 'disabled' AND r.status <> 'maintenance'";

const ROOM_SELECT = "SELECT r.id, r.room_number, r.room_type, r.price, r.deposit, r.capacity, r.occupants, r.size_sqm,
    r.description AS room_description, r.status, r.created_at, r.updated_at,
    GREATEST(r.created_at, COALESCE(r.updated_at, r.created_at), COALESCE(bh.updated_at, bh.created_at)) AS last_change,
    bh.id AS house_id, bh.name AS house_name, bh.description AS house_description, bh.address, bh.barangay, bh.city, bh.province,
    bh.latitude, bh.longitude, bh.nearby_school, bh.contact_number AS house_contact, bh.contact_email AS house_email, bh.house_rules,
    u.id AS landlord_user_id, u.first_name, u.last_name, u.contact_number AS landlord_contact, u.avatar_path,
    l.verification_status,
    (SELECT COALESCE(SUM(b.occupants_count), 0) FROM bookings b WHERE b.room_id = r.id AND b.status = 'pending') AS pending_slots";

function distanceToSchool(?float $lat, ?float $lng): ?int {
    if ($lat === null || $lng === null) {
        return null;
    }
    $toRad = static fn (float $deg): float => $deg * M_PI / 180;
    $dLat = $toRad($lat - SCHOOL_LAT);
    $dLng = $toRad($lng - SCHOOL_LNG);
    $h = sin($dLat / 2) ** 2 + cos($toRad(SCHOOL_LAT)) * cos($toRad($lat)) * sin($dLng / 2) ** 2;
    return (int) round(2 * 6371000 * asin(sqrt($h)));
}

/**
 * AVAILABLE: has an open slot nobody has asked for yet.
 * RESERVED:  every open slot is waiting on a pending reservation.
 * FULL:      no open slot.
 */
function availabilityBadge(int $openSlots, int $pendingSlots): string {
    if ($openSlots <= 0) {
        return 'full';
    }

    return $pendingSlots >= $openSlots ? 'reserved' : 'available';
}

/**
 * Loads amenities and photos for many rooms at once (2 queries instead of 2 per room).
 * @param int[] $roomIds
 * @param int[] $houseIds
 */
function loadRoomExtras(array $roomIds, array $houseIds): array {
    $pdo = getDb();
    $amenities = [];
    $roomPhotos = [];
    $housePhotos = [];

    if ($roomIds) {
        $in = implode(',', array_map('intval', $roomIds));
        foreach ($pdo->query("SELECT ra.room_id, a.name FROM room_amenities ra JOIN amenities a ON a.id = ra.amenity_id WHERE ra.room_id IN ({$in}) ORDER BY a.name") as $row) {
            $amenities[(int) $row['room_id']][] = $row['name'];
        }
        foreach ($pdo->query("SELECT room_id, file_path FROM room_images WHERE room_id IN ({$in}) ORDER BY room_id, sort_order, id") as $row) {
            $roomPhotos[(int) $row['room_id']][] = publicAssetUrl((string) $row['file_path']);
        }
    }
    if ($houseIds) {
        $in = implode(',', array_map('intval', array_unique($houseIds)));
        foreach ($pdo->query("SELECT boarding_house_id, file_path FROM boarding_house_images WHERE boarding_house_id IN ({$in}) ORDER BY boarding_house_id, is_cover DESC, id") as $row) {
            $housePhotos[(int) $row['boarding_house_id']][] = publicAssetUrl((string) $row['file_path']);
        }
    }

    return [$amenities, $roomPhotos, $housePhotos];
}

function formatRoom(array $row, array $amenities, array $roomPhotos, array $housePhotos, bool $full = false): array {
    $id = (int) $row['id'];
    $houseId = (int) $row['house_id'];
    $capacity = (int) $row['capacity'];
    $occupants = (int) $row['occupants'];
    $open = max(0, $capacity - $occupants);
    $pending = (int) $row['pending_slots'];
    $lat = $row['latitude'] !== null ? (float) $row['latitude'] : null;
    $lng = $row['longitude'] !== null ? (float) $row['longitude'] : null;

    // Room photos first, then the boarding house photos; no duplicates.
    $photos = array_values(array_unique(array_merge($roomPhotos[$id] ?? [], $housePhotos[$houseId] ?? [])));

    $room = [
        'id' => $id,
        'room_number' => $row['room_number'],
        'room_type' => $row['room_type'],
        'room_type_label' => ROOM_TYPE_LABELS[$row['room_type']] ?? ucfirst((string) $row['room_type']),
        'price' => (float) $row['price'],
        'deposit' => (float) $row['deposit'],
        'capacity' => $capacity,
        'occupants' => $occupants,
        'open_slots' => $open,
        'pending_slots' => $pending,
        'availability' => availabilityBadge($open, $pending),
        'size_sqm' => $row['size_sqm'] !== null ? (float) $row['size_sqm'] : null,
        'description' => $row['room_description'] ?: $row['house_description'],
        'amenities' => $amenities[$id] ?? [],
        'images' => $photos,
        'added_at' => $row['created_at'],
        'updated_at' => $row['last_change'],
        'distance_m' => distanceToSchool($lat, $lng),
        'school' => SCHOOL_NAME,
        'house' => [
            'id' => $houseId,
            'name' => $row['house_name'],
            'location' => implode(', ', array_filter([$row['barangay'] ?: $row['address'], $row['city']])),
            'address' => implode(', ', array_unique(array_filter([$row['address'], $row['barangay'], $row['city'], $row['province']]))),
        ],
        'landlord' => [
            'name' => trim($row['first_name'] . ' ' . $row['last_name']),
            'verified' => $row['verification_status'] === 'verified',
        ],
    ];

    if ($full) {
        $room['house'] += [
            'description' => $row['house_description'],
            'rules' => $row['house_rules'],
            'nearby_school' => $row['nearby_school'],
            'latitude' => $lat,
            'longitude' => $lng,
        ];
        $room['landlord'] += [
            'contact_number' => $row['house_contact'] ?: $row['landlord_contact'],
            'email' => $row['house_email'],
            'photo' => $row['avatar_path'] ? publicAssetUrl((string) $row['avatar_path']) : null,
        ];
    }

    return $room;
}

$pdo = getDb();

// Fingerprint used by the page to notice new, edited or removed rooms.
if (isset($_GET['version'])) {
    $row = $pdo->query('SELECT COUNT(*) AS total, MAX(GREATEST(r.created_at, COALESCE(r.updated_at, r.created_at), COALESCE(bh.updated_at, bh.created_at))) AS last_change '
        . ROOM_BASE_FROM . ' WHERE ' . ROOM_BASE_WHERE)->fetch();
    jsonResponse(['version' => md5($row['total'] . '|' . $row['last_change'])]);
}

if (isset($_GET['id'])) {
    $id = queryInt('id', 0, 0);
    $stmt = $pdo->prepare(ROOM_SELECT . ' ' . ROOM_BASE_FROM . ' WHERE ' . ROOM_BASE_WHERE . ' AND r.id = :id');
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch();
    if (!$row) {
        jsonError('This room is no longer listed.', 404);
    }

    [$amenities, $roomPhotos, $housePhotos] = loadRoomExtras([$id], [(int) $row['house_id']]);
    $room = formatRoom($row, $amenities, $roomPhotos, $housePhotos, true);

    $others = $pdo->prepare("SELECT COUNT(*) FROM rooms WHERE boarding_house_id = :house AND id <> :id AND status = 'available'");
    $others->execute([':house' => $room['house']['id'], ':id' => $id]);
    $room['house']['other_available_rooms'] = (int) $others->fetchColumn();

    jsonResponse($room);
}

$where = [ROOM_BASE_WHERE];
$params = [];

$search = queryString('q');
if ($search !== '') {
    $where[] = '(bh.name LIKE :q1 OR bh.barangay LIKE :q2 OR bh.city LIKE :q3 OR bh.address LIKE :q4)';
    foreach (['q1', 'q2', 'q3', 'q4'] as $key) {
        $params[':' . $key] = likeValue($search);
    }
}

$type = queryString('type');
if (isset(ROOM_TYPE_LABELS[$type])) {
    $where[] = 'r.room_type = :type';
    $params[':type'] = $type;
}

if (is_numeric($_GET['max_price'] ?? null) && (float) $_GET['max_price'] > 0) {
    $where[] = 'r.price <= :max_price';
    $params[':max_price'] = (float) $_GET['max_price'];
}

if (queryString('available') === '1') {
    $where[] = 'r.occupants < r.capacity';
}

$orderBy = match (queryString('sort', 'newest')) {
    'price_asc' => ' ORDER BY r.price ASC, last_change DESC, r.id DESC',
    'price_desc' => ' ORDER BY r.price DESC, last_change DESC, r.id DESC',
    // Approximate (flat-earth) distance is fine for sorting within a town.
    'nearest' => ' ORDER BY bh.latitude IS NULL, POW(bh.latitude - ' . SCHOOL_LAT . ', 2) + POW((bh.longitude - ' . SCHOOL_LNG . ') * COS(RADIANS(' . SCHOOL_LAT . ')), 2), r.price',
    default => ' ORDER BY last_change DESC, r.id DESC',
};

$pagination = paginationParams(6);
$fromWhere = ROOM_BASE_FROM . ' WHERE ' . implode(' AND ', $where);

$count = $pdo->prepare('SELECT COUNT(*) ' . $fromWhere);
$count->execute($params);
$total = (int) $count->fetchColumn();

$stmt = $pdo->prepare(ROOM_SELECT . ' ' . $fromWhere . $orderBy . ' LIMIT ' . (int) $pagination['per_page'] . ' OFFSET ' . (int) $pagination['offset']);
$stmt->execute($params);
$rows = $stmt->fetchAll();

[$amenities, $roomPhotos, $housePhotos] = loadRoomExtras(array_column($rows, 'id'), array_column($rows, 'house_id'));

$summary = $pdo->query("SELECT COUNT(*) AS rooms, COALESCE(SUM(r.occupants < r.capacity), 0) AS available, COUNT(DISTINCT bh.id) AS houses, MIN(r.price) AS min_price "
    . ROOM_BASE_FROM . ' WHERE ' . ROOM_BASE_WHERE)->fetch();

jsonResponse(
    array_map(fn ($row) => formatRoom($row, $amenities, $roomPhotos, $housePhotos), $rows),
    '',
    200,
    [
        'meta' => paginationMeta($total, $pagination),
        'summary' => [
            'rooms' => (int) $summary['rooms'],
            'available' => (int) $summary['available'],
            'houses' => (int) $summary['houses'],
            'min_price' => $summary['min_price'] !== null ? (float) $summary['min_price'] : null,
        ],
    ]
);
