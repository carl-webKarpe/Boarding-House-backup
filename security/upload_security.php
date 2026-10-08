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

    // Photos must really be images, and not absurdly large (they are shown in browsers).
    if (str_starts_with($mime, 'image/')) {
        $info = @getimagesize($file['tmp_name']);
        if ($info === false) {
            return ['success' => false, 'message' => 'The file is not a valid image.'];
        }
        if ($info[0] > 8000 || $info[1] > 8000) {
            return ['success' => false, 'message' => 'The image is too large (maximum 8000 × 8000 pixels).'];
        }
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

/**
 * Landlord profile photo, stored under uploads/avatars.
 */
function storeAvatarImage(array $file): array {
    $result = storeUploadedFile($file, BH_UPLOAD_DIR . '/avatars', BH_IMAGE_MIME, 'avatar');
    if ($result['success']) {
        $result['path'] = BH_UPLOAD_URL . '/avatars/' . $result['stored_name'];
    }

    return $result;
}

/**
 * Turns $_FILES['x'] (single or multiple "x[]") into a list of single-file arrays.
 * @return array<int, array{name:string,type:string,tmp_name:string,error:int,size:int}>
 */
function uploadedFileList(?array $files): array {
    if (!$files || !isset($files['name'])) {
        return [];
    }
    if (!is_array($files['name'])) {
        return ($files['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE ? [] : [$files];
    }

    $list = [];
    foreach (array_keys($files['name']) as $i) {
        if (($files['error'][$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            continue;
        }
        $list[] = [
            'name' => $files['name'][$i],
            'type' => $files['type'][$i],
            'tmp_name' => $files['tmp_name'][$i],
            'error' => $files['error'][$i],
            'size' => $files['size'][$i],
        ];
    }

    return $list;
}

/**
 * Deletes a photo file only if it was uploaded through the dashboards
 * (uploads/...). Paths pointing to Image/ or to a web address are left alone.
 */
function deleteUploadedImage(string $path): void {
    if (preg_match('#^' . preg_quote(BH_UPLOAD_URL, '#') . '/(listings|avatars)/([A-Za-z0-9_.-]+)$#', $path, $m)) {
        @unlink(BH_UPLOAD_DIR . '/' . $m[1] . '/' . $m[2]);
    }
}
