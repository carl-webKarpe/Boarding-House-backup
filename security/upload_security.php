<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';

function ensureUploadDirectory(): void {
    if (!is_dir(BH_UPLOAD_DIR)) {
        @mkdir(BH_UPLOAD_DIR, 0755, true);
    }
}

function secureUpload(array $file): array {
    ensureUploadDirectory();

    if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
        return ['success' => false, 'message' => 'Invalid upload.'];
    }

    $allowedMime = ['image/jpeg', 'image/png', 'application/pdf'];
    $allowedExt = ['jpg', 'jpeg', 'png', 'pdf'];

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    if (!in_array($mime, $allowedMime, true)) {
        return ['success' => false, 'message' => 'Only JPG, PNG, and PDF files are allowed.'];
    }

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedExt, true)) {
        return ['success' => false, 'message' => 'Unsupported file extension.'];
    }

    if ($file['size'] > 5 * 1024 * 1024) {
        return ['success' => false, 'message' => 'File exceeds the 5MB limit.'];
    }

    $safeName = 'doc_' . bin2hex(random_bytes(8)) . '.' . $ext;
    $target = BH_UPLOAD_DIR . '/' . $safeName;

    if (!move_uploaded_file($file['tmp_name'], $target)) {
        return ['success' => false, 'message' => 'Could not store the uploaded file.'];
    }

    return ['success' => true, 'path' => $target, 'url' => BH_UPLOAD_URL . '/' . $safeName];
}
