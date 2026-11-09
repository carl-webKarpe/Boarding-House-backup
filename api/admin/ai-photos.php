<?php

declare(strict_types=1);

/**
 * AI photo check for any listing (Admin > Properties > details), e.g. before approving.
 *
 *   POST ai-photos.php  { house_id }  -> { summary, photos: [{ url, label, verdict, shows, note }], checked, skipped }
 */

require_once __DIR__ . '/_admin.php';
require_once __DIR__ . '/../_ai_photos.php';

requireMethod('POST');

$who = 'user:' . adminId();
if ($stop = aiGuard('photos', $who)) {
    jsonError($stop['message'], $stop['status']);
}

$stmt = getDb()->prepare('SELECT id, name, property_type FROM boarding_houses WHERE id = :id');
$stmt->execute([':id' => (int) (requestBody()['house_id'] ?? 0)]);
$house = $stmt->fetch() ?: notFound('Property');

aiCountUse('photos', $who);
$result = runPhotoCheck($house);
$flagged = count(array_filter($result['photos'], fn ($p) => $p['verdict'] !== 'good'));
adminLog('ai_photo_check', "AI photo check for {$house['name']}: {$flagged} of {$result['checked']} photo(s) flagged", 'boarding_house', (int) $house['id']);
jsonResponse($result);
