<?php

declare(strict_types=1);

require_once __DIR__ . '/database.php';

/**
 * System settings stored in the `settings` table (Admin > Settings).
 */
const BH_DEFAULT_SETTINGS = [
    'site_name' => 'Boarding House Rental System',
    'support_email' => 'support@bhrental.local',
    'support_phone' => '09171234567',
    'require_listing_approval' => '1',
    'allow_tenant_registration' => '1',
    'allow_landlord_registration' => '1',
    'max_upload_mb' => '5',
];

function getAllSettings(): array {
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }

    $cache = BH_DEFAULT_SETTINGS;
    try {
        foreach (getDb()->query('SELECT setting_key, setting_value FROM settings') as $row) {
            $cache[$row['setting_key']] = (string) $row['setting_value'];
        }
    } catch (Throwable $e) {
        writeLog('Settings could not be loaded: ' . $e->getMessage(), 'WARN');
    }

    return $cache;
}

function getSetting(string $key): string {
    return getAllSettings()[$key] ?? (BH_DEFAULT_SETTINGS[$key] ?? '');
}

function settingEnabled(string $key): bool {
    return getSetting($key) === '1';
}
