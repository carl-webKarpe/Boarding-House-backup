<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';

const BH_DOCUMENT_MIME = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'application/pdf' => 'pdf'];
const BH_IMAGE_MIME = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
const BH_MAX_UPLOAD_BYTES = 5 * 1024 * 1024;

/**
 * Validates and stores one uploaded file ($_FILES entry).
 *
 * The extension is chosen from the detected MIME type (never from the
 * user's file name) and the file gets a random name, so an upload can
 * never be executed as a script.
 *
 * @param array<string,string> $allowedMime  mime => extension
 * @return array{success:bool, message?:string, stored_name?:string, mime?:string, size?:int, original_name?:string}
 */
function storeUploadedFile(array $file, string $targetDir, array $allowedMime, string $prefix): array {
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return ['success' => false, 'message' => 'The file could not be uploaded.'];
    }

    if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
        return ['success' => false, 'message' => 'Invalid upload.'];
    }

    $size = (int) ($file['size'] ?? 0);
    if ($size <= 0 || $size > BH_MAX_UPLOAD_BYTES) {
        return ['success' => false, 'message' => 'Each file must be smaller than 5MB.'];
    }

    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    if (!is_string($mime) || !isset($allowedMime[$mime])) {
        return ['success' => false, 'message' => 'Unsupported file type.'];
    }

    if (!is_dir($targetDir) && !@mkdir($targetDir, 0750, true)) {
        return ['success' => false, 'message' => 'Upload folder is not writable.'];
    }

    $storedName = $prefix . '_' . bin2hex(random_bytes(12)) . '.' . $allowedMime[$mime];
    if (!move_uploaded_file($file['tmp_name'], $targetDir . '/' . $storedName)) {
        return ['success' => false, 'message' => 'Could not store the uploaded file.'];
    }

    return [
        'success' => true,
        'stored_name' => $storedName,
        'mime' => $mime,
        'size' => $size,
        'original_name' => substr(basename((string) ($file['name'] ?? 'file')), 0, 255),
    ];
}

/**
 * Private verification documents (IDs, selfies, permits). Stored under
 * storage/documents, which the web server never serves directly.
 */
function storeVerificationDocument(array $file): array {
    return storeUploadedFile($file, BH_DOCUMENT_DIR, BH_DOCUMENT_MIME, 'doc');
}

/**
 * Public listing photos, stored under uploads/listings.
 */
function storeListingImage(array $file): array {
    $result = storeUploadedFile($file, BH_UPLOAD_DIR . '/listings', BH_IMAGE_MIME, 'img');
    if ($result['success']) {
        $result['path'] = BH_UPLOAD_URL . '/listings/' . $result['stored_name'];
    }

    return $result;
}
