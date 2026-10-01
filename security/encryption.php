<?php

declare(strict_types=1);

function encryptSensitiveData(string $data): string {
    $key = hash('sha256', 'bhrs-secure-key', true);
    $iv = openssl_random_pseudo_bytes(16);
    $ciphertext = openssl_encrypt($data, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
    return base64_encode($iv . $ciphertext);
}

function decryptSensitiveData(string $ciphertext): string {
    $key = hash('sha256', 'bhrs-secure-key', true);
    $raw = base64_decode($ciphertext, true);
    if ($raw === false || strlen($raw) < 16) {
        return '';
    }

    $iv = substr($raw, 0, 16);
    $data = substr($raw, 16);
    $decrypted = openssl_decrypt($data, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
    return $decrypted === false ? '' : $decrypted;
}
