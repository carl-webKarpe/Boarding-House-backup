<?php

declare(strict_types=1);

/**
 * AI photo check for the landlord's own listing (My Properties > details).
 *
 *   POST ai-photos.php  { house_id }  -> { summary, photos: [{ url, label, verdict, shows, note }], checked, skipped }
 */

require_once __DIR__ . '/_landlord.php';
require_once __DIR__ . '/../_ai_photos.php';

requireMethod('POST');

$who = 'user:' . landlordUserId();
if ($stop = aiGuard('photos', $who)) {
    jsonError($stop['message'], $stop['status']);
}

$house = ownHouse((int) (requestBody()['house_id'] ?? 0));
aiCountUse('photos', $who);
jsonResponse(runPhotoCheck($house), 'Photo check ready. It is advice only - you decide which photos to keep.');
