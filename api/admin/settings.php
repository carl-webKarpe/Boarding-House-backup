<?php

declare(strict_types=1);

/**
 * System settings.
 *
 *   GET settings.php
 *   PUT settings.php   { site_name, support_email, support_phone, require_listing_approval, ... }
 */

require_once __DIR__ . '/_admin.php';
require_once __DIR__ . '/../../security/settings.php';

$pdo = getDb();

switch (requestMethod()) {
    case 'GET':
        jsonResponse(getAllSettings());

    case 'PUT':
    case 'PATCH':
        $body = requestBody();
        $rules = [];
        if (array_key_exists('site_name', $body)) {
            $rules['site_name'] = validateTextValue($body['site_name'], 'System name', 100);
        }
        if (array_key_exists('support_email', $body)) {
            $rules['support_email'] = validateEmailValue($body['support_email']);
        }
        if (array_key_exists('support_phone', $body)) {
            $rules['support_phone'] = validatePhoneValue($body['support_phone']);
        }
        if (array_key_exists('max_upload_mb', $body)) {
            $rules['max_upload_mb'] = validateNumberValue($body['max_upload_mb'], 'Maximum upload size', 1, 5);
        }
        foreach (['require_listing_approval', 'allow_tenant_registration', 'allow_landlord_registration'] as $flag) {
            if (array_key_exists($flag, $body)) {
                $rules[$flag] = ['valid' => true, 'value' => filter_var($body[$flag], FILTER_VALIDATE_BOOLEAN) ? '1' : '0'];
            }
        }

        $values = collectValidated($rules);
        if (!$values) {
            throw new ApiException('Nothing to update.', 400);
        }

        $stmt = $pdo->prepare('INSERT INTO settings (setting_key, setting_value) VALUES (:key, :value) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)');
        foreach ($values as $key => $value) {
            $stmt->execute([':key' => $key, ':value' => (string) ($key === 'max_upload_mb' ? (int) $value : $value)]);
        }

        adminLog('settings_update', 'Administrator updated system settings (' . implode(', ', array_keys($values)) . ')', 'settings', null);
        $current = $pdo->query('SELECT setting_key, setting_value FROM settings')->fetchAll(PDO::FETCH_KEY_PAIR);
        jsonResponse($current + BH_DEFAULT_SETTINGS, 'Settings saved.');

    default:
        requireMethod('GET', 'PUT', 'PATCH');
}
