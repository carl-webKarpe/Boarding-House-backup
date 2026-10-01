<?php

declare(strict_types=1);

/**
 * System activity log.
 *
 *   GET activity.php ?q=&action=&date_from=&date_to=&page=&per_page=
 */

require_once __DIR__ . '/_admin.php';

requireMethod('GET');

$where = [];
$params = [];
$search = queryString('q');
if ($search !== '') {
    $where[] = '(a.description LIKE :q1 OR CONCAT(u.first_name, \' \', u.last_name) LIKE :q2 OR a.ip_address LIKE :q3)';
    foreach (['q1', 'q2', 'q3'] as $k) {
        $params[':' . $k] = likeValue($search);
    }
}
$action = queryString('action');
if ($action !== '' && preg_match('/^[a-z_]+$/', $action)) {
    $where[] = 'a.action LIKE :action';
    $params[':action'] = $action . '%';
}
$from = validateDateValue(queryString('date_from'), 'From', false);
if ($from['valid'] && $from['value']) {
    $where[] = 'a.created_at >= :date_from';
    $params[':date_from'] = $from['value'] . ' 00:00:00';
}
$to = validateDateValue(queryString('date_to'), 'To', false);
if ($to['valid'] && $to['value']) {
    $where[] = 'a.created_at <= :date_to';
    $params[':date_to'] = $to['value'] . ' 23:59:59';
}

[$rows, $meta] = pagedQuery(
    'SELECT a.id, a.action, a.description, a.entity_type, a.entity_id, a.ip_address, a.created_at, u.first_name, u.last_name, u.username, u.role',
    'FROM activity_logs a LEFT JOIN users u ON u.id = a.user_id' . ($where ? ' WHERE ' . implode(' AND ', $where) : ''),
    $params,
    ' ORDER BY a.created_at DESC, a.id DESC',
    paginationParams(20)
);

jsonResponse(array_map(static function (array $row): array {
    $row = castRow($row, ['id', 'entity_id']);
    $row['actor'] = $row['username'] ? fullName($row) : 'System';
    unset($row['first_name'], $row['last_name'], $row['username']);
    return $row;
}, $rows), '', 200, ['meta' => $meta]);
