<?php

declare(strict_types=1);

/**
 * Streams a private verification document (ID, selfie, permit) to an
 * administrator. These files live in storage/documents and are never
 * reachable by URL.
 *
 *   GET documents.php?id=4
 */

require_once __DIR__ . '/_admin.php';

requireMethod('GET');

$id = requireId();
$stmt = getDb()->prepare('SELECT d.id, d.user_id, d.doc_type, d.original_name, d.stored_name, d.mime_type, u.first_name, u.last_name, u.username
    FROM verification_documents d JOIN users u ON u.id = d.user_id WHERE d.id = :id');
$stmt->execute([':id' => $id]);
$doc = $stmt->fetch();
if (!$doc) {
    notFound('Document');
}

$path = BH_DOCUMENT_DIR . '/' . basename((string) $doc['stored_name']);
if (!is_file($path)) {
    throw new ApiException('The file is missing from storage.', 404);
}

adminLog('document_view', 'Administrator viewed ' . str_replace('_', ' ', $doc['doc_type']) . ' of ' . fullName($doc), 'user', (int) $doc['user_id']);

header_remove('Content-Type');
header('Content-Type: ' . $doc['mime_type']);
header('Content-Length: ' . filesize($path));
header('Content-Disposition: inline; filename="' . preg_replace('/[^A-Za-z0-9._-]/', '_', (string) $doc['original_name']) . '"');
header("Content-Security-Policy: default-src 'none'; img-src 'self'; style-src 'unsafe-inline'; sandbox");
readfile($path);
exit;
