<?php

declare(strict_types=1);

/**
 * Amenity catalogue (WiFi, Aircon, CCTV, ...).
 *
 *   GET    amenities.php            list with how many rooms use each amenity
 *   POST   amenities.php            create { name }
 *   PUT    amenities.php?id=2       rename { name }
 *   DELETE amenities.php?id=2       delete (removed from every room)
 */

require_once __DIR__ . '/_admin.php';

$pdo = getDb();

function loadAmenity(int $id): array {
    $stmt = getDb()->prepare('SELECT a.id, a.name, a.created_at, (SELECT COUNT(*) FROM room_amenities ra WHERE ra.amenity_id = a.id) AS room_count FROM amenities a WHERE a.id = :id');
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch();
    if (!$row) {
        notFound('Amenity');
    }

    return castRow($row, ['id', 'room_count']);
}

function assertAmenityNameFree(string $name, int $ignoreId = 0): void {
    $stmt = getDb()->prepare('SELECT id FROM amenities WHERE name = :name AND id <> :id');
    $stmt->execute([':name' => $name, ':id' => $ignoreId]);
    if ($stmt->fetch()) {
        throw new ApiException('This amenity already exists.', 409, ['name' => 'Already exists.']);
    }
}

switch (requestMethod()) {
    case 'GET':
        $rows = $pdo->query('SELECT a.id, a.name, a.created_at, (SELECT COUNT(*) FROM room_amenities ra WHERE ra.amenity_id = a.id) AS room_count FROM amenities a ORDER BY a.name')->fetchAll();
        jsonResponse(array_map(fn ($r) => castRow($r, ['id', 'room_count']), $rows));

    case 'POST':
        $values = collectValidated(['name' => validateTextValue(requestBody()['name'] ?? '', 'Amenity name', 60)]);
        assertAmenityNameFree($values['name']);
        $pdo->prepare('INSERT INTO amenities (name) VALUES (:name)')->execute([':name' => $values['name']]);
        $id = (int) $pdo->lastInsertId();
        adminLog('amenity_create', 'Administrator added amenity: ' . $values['name'], 'amenity', $id);
        jsonResponse(loadAmenity($id), 'Amenity added.', 201);

    case 'PUT':
    case 'PATCH':
        $id = requireId();
        $before = loadAmenity($id);
        $values = collectValidated(['name' => validateTextValue(requestBody()['name'] ?? '', 'Amenity name', 60)]);
        assertAmenityNameFree($values['name'], $id);
        $pdo->prepare('UPDATE amenities SET name = :name WHERE id = :id')->execute([':name' => $values['name'], ':id' => $id]);
        adminLog('amenity_update', "Administrator renamed amenity {$before['name']} → {$values['name']}", 'amenity', $id);
        jsonResponse(loadAmenity($id), 'Amenity updated.');

    case 'DELETE':
        $id = requireId();
        $amenity = loadAmenity($id);
        $pdo->prepare('DELETE FROM amenities WHERE id = :id')->execute([':id' => $id]);
        adminLog('amenity_delete', 'Administrator deleted amenity: ' . $amenity['name'], 'amenity', $id);
        jsonResponse(null, 'Amenity deleted.');

    default:
        requireMethod('GET', 'POST', 'PUT', 'PATCH', 'DELETE');
}
