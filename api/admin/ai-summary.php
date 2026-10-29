<?php

declare(strict_types=1);

/**
 * AI feature 5 - Dashboard summary (Admin > Dashboard).
 *
 *   GET ai-summary.php              the summary (kept for 30 minutes)
 *   GET ai-summary.php?refresh=1    make a new one
 *     -> { headline, highlights: [], actions: [], generated_at }
 *
 * Only counts and averages are sent - no names, emails or messages.
 */

require_once __DIR__ . '/_admin.php';
require_once __DIR__ . '/../../security/ai.php';

requireMethod('GET');

const AI_SUMMARY_CACHE = BH_STORAGE_DIR . '/ai_summary.json';
const AI_SUMMARY_MINUTES = 30;

if (queryString('refresh') !== '1' && is_file(AI_SUMMARY_CACHE) && filemtime(AI_SUMMARY_CACHE) > time() - AI_SUMMARY_MINUTES * 60) {
    $cached = json_decode((string) file_get_contents(AI_SUMMARY_CACHE), true);
    if (is_array($cached)) {
        jsonResponse($cached);
    }
}

$who = 'user:' . adminId();
if ($stop = aiGuard('summary', $who)) {
    jsonError($stop['message'], $stop['status']);
}

$pdo = getDb();
$one = static fn (string $sql) => (int) $pdo->query($sql)->fetchColumn();
$pairs = static function (string $sql) use ($pdo): string {
    $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_NUM);
    return $rows ? implode(', ', array_map(static fn ($r) => "{$r[0]}: {$r[1]}", $rows)) : 'none';
};

$stats = [
    'Users by role' => $pairs("SELECT role, COUNT(*) FROM users GROUP BY role"),
    'New accounts in the last 7 days' => $pairs("SELECT role, COUNT(*) FROM users WHERE created_at >= NOW() - INTERVAL 7 DAY GROUP BY role"),
    'Disabled accounts' => $one("SELECT COUNT(*) FROM users WHERE status = 'disabled'"),
    'Landlords by verification' => $pairs("SELECT verification_status, COUNT(*) FROM landlords GROUP BY verification_status"),
    'Boarding houses by status' => $pairs("SELECT status, COUNT(*) FROM boarding_houses GROUP BY status"),
    'Boarding houses waiting more than 3 days' => $one("SELECT COUNT(*) FROM boarding_houses WHERE status = 'pending' AND created_at < NOW() - INTERVAL 3 DAY"),
    'Rooms' => $one('SELECT COUNT(*) FROM rooms'),
    'Beds: occupied / total' => (static function () use ($pdo): string {
        $r = $pdo->query('SELECT COALESCE(SUM(occupants), 0), COALESCE(SUM(capacity), 0) FROM rooms')->fetch(PDO::FETCH_NUM);
        return "{$r[0]} / {$r[1]}";
    })(),
    'Average rent by room type (approved listings)' => $pairs("SELECT r.room_type, CONCAT('PHP ', FORMAT(AVG(r.price), 0)) FROM rooms r JOIN boarding_houses bh ON bh.id = r.boarding_house_id WHERE bh.status = 'approved' GROUP BY r.room_type"),
    'Listings by barangay (top 5)' => $pairs("SELECT COALESCE(barangay, 'unknown'), COUNT(*) AS n FROM boarding_houses WHERE status = 'approved' GROUP BY barangay ORDER BY n DESC LIMIT 5"),
    'Reservations by status (all time)' => $pairs("SELECT status, COUNT(*) FROM bookings GROUP BY status"),
    'Reservations created in the last 7 days' => $pairs("SELECT status, COUNT(*) FROM bookings WHERE created_at >= NOW() - INTERVAL 7 DAY GROUP BY status"),
    'Pending reservations older than 3 days' => $one("SELECT COUNT(*) FROM bookings WHERE status = 'pending' AND created_at < NOW() - INTERVAL 3 DAY"),
    'Unanswered student questions' => $one("SELECT COUNT(*) FROM inquiries WHERE status IN ('new', 'read')"),
    'Unread admin notifications' => $one("SELECT COUNT(*) FROM notifications WHERE audience = 'admin' AND is_read = 0"),
];
$lines = [];
foreach ($stats as $label => $value) {
    $lines[] = "{$label}: {$value}";
}

$system = <<<TXT
You write the dashboard summary for the administrator of the Boarding House Rental System (boarding houses for SIIT students in Dapa, Surigao del Norte).

From the numbers given, write:
- headline: one plain sentence about the most important thing right now.
- highlights: 2 to 5 short facts worth knowing (what grew, what is waiting, occupancy).
- actions: 0 to 4 short, concrete things the admin should do next (e.g. "Review 2 listings waiting more than 3 days"). Empty if nothing needs doing.
Use only the numbers given; never invent trends or names. Plain English, no markdown.
TXT;

$schema = [
    'type' => 'object',
    'properties' => [
        'headline' => ['type' => 'string'],
        'highlights' => ['type' => 'array', 'items' => ['type' => 'string']],
        'actions' => ['type' => 'array', 'items' => ['type' => 'string']],
    ],
    'required' => ['headline', 'highlights', 'actions'],
    'additionalProperties' => false,
];

aiCountUse('summary', $who);
$result = aiJson('summary', $system, [['role' => 'user', 'content' => "Today is " . date('l, F j, Y') . ".\n\n" . implode("\n", $lines)]], $schema, 'low', 2000);
if (!$result['success']) {
    jsonError($result['message'], $result['status']);
}

$clean = static fn ($list, int $max) => array_values(array_filter(array_map(static fn ($t) => trim(mb_substr((string) $t, 0, 300)), array_slice((array) $list, 0, $max))));
$summary = [
    'headline' => trim(mb_substr((string) ($result['data']['headline'] ?? ''), 0, 300)),
    'highlights' => $clean($result['data']['highlights'] ?? [], 5),
    'actions' => $clean($result['data']['actions'] ?? [], 4),
    'generated_at' => date('Y-m-d H:i:s'),
];
@file_put_contents(AI_SUMMARY_CACHE, json_encode($summary, JSON_UNESCAPED_UNICODE), LOCK_EX);

jsonResponse($summary);
