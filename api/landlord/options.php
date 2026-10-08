<?php

declare(strict_types=1);

/**
 * Lists for the landlord forms.
 *
 *   GET  options.php?type=barangays    grouped by municipality (from the barangays table)
 *   GET  options.php?type=amenities    standard amenities + my custom ones
 *   GET  options.php?type=houses       my boarding houses (for dropdowns)
 *   POST options.php?type=amenities    { name }  add a custom amenity (or reuse an existing one)
 */

require_once __DIR__ . '/_landlord.php';

$pdo = getDb();
$type = queryString('type');

if (requestMethod() === 'POST' && $type === 'amenities') {
    $values = collectValidated(['name' => validateTextValue(requestBody()['name'] ?? '', 'Amenity name', 60)]);
    $name = preg_replace('/\s+/', ' ', $values['name']);
    $find = $pdo->prepare('SELECT id, name FROM amenities WHERE LOWER(name) = LOWER(:name)');
    $find->execute([':name' => $name]);
    if ($row = $find->fetch()) {
        jsonResponse(['id' => (int) $row['id'], 'name' => $row['name'], 'custom' => false], 'This amenity is already in the list.');
    }
    $pdo->prepare('INSERT INTO amenities (name, created_by) VALUES (:name, :user)')->execute([':name' => $name, ':user' => landlordUserId()]);
    jsonResponse(['id' => (int) $pdo->lastInsertId(), 'name' => $name, 'custom' => true], 'Custom amenity added.', 201);
}

requireMethod('GET');

switch ($type) {
    case 'barangays':
        $groups = [];
        foreach ($pdo->query('SELECT id, name, municipality, province FROM barangays WHERE is_active = 1 ORDER BY municipality, name') as $row) {
            $key = $row['municipality'] . ', ' . $row['province'];
            $groups[$key] ??= ['municipality' => $row['municipality'], 'province' => $row['province'], 'barangays' => []];
            $groups[$key]['barangays'][] = ['id' => (int) $row['id'], 'name' => $row['name']];
        }
        foreach ($groups as &$group) {
            usort($group['barangays'], fn ($a, $b) => strnatcasecmp($a['name'], $b['name']));
        }
        unset($group);
        jsonResponse(array_values($groups));

    case 'amenities':
        $stmt = $pdo->prepare('SELECT id, name, created_by FROM amenities WHERE created_by IS NULL OR created_by = :me ORDER BY created_by IS NOT NULL, id');
        $stmt->execute([':me' => landlordUserId()]);
        jsonResponse(array_map(fn ($r) => ['id' => (int) $r['id'], 'name' => $r['name'], 'custom' => $r['created_by'] !== null], $stmt->fetchAll()));

    case 'houses':
        $stmt = $pdo->prepare('SELECT id, name, barangay, city FROM boarding_houses WHERE landlord_id = :landlord ORDER BY name');
        $stmt->execute([':landlord' => landlordId()]);
        jsonResponse(array_map(fn ($r) => ['id' => (int) $r['id'], 'label' => $r['name'] . ' (' . ($r['barangay'] ?: $r['city']) . ')'], $stmt->fetchAll()));

    default:
        throw new ApiException('Unknown option type.', 400);
}
