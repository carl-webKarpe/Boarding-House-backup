<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';

class DatabaseUnavailableException extends RuntimeException {}

/**
 * Small schema upgrades applied automatically, so a database created before a
 * feature keeps working without running its database/migrate-*.sql by hand.
 * Bump BH_SCHEMA_VERSION when adding a step to upgradeSchema().
 */
const BH_SCHEMA_VERSION = '2026-11-09-apartments';
const BH_SCHEMA_FLAG_FILE = BH_STORAGE_DIR . '/schema_version.txt';

function getDb(): PDO {
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=%s', BH_DB_HOST, BH_DB_PORT, BH_DB_NAME, BH_DB_CHARSET);
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];

    try {
        $pdo = new PDO($dsn, BH_DB_USER, BH_DB_PASS, $options);
        // Keep MySQL's NOW() in the same timezone as PHP's date().
        $pdo->exec("SET time_zone = '" . (new DateTimeImmutable())->format('P') . "'");
    } catch (PDOException $e) {
        writeLog('Database connection failed: ' . $e->getMessage(), 'ERROR');
        throw new DatabaseUnavailableException('Database unavailable.', 0, $e);
    }

    upgradeSchema($pdo);

    return $pdo;
}

/**
 * Brings an older database up to date (same changes as database/migrate-apartments.sql).
 * Runs once: afterwards a flag file in storage/ skips the check. Never deletes data.
 */
function upgradeSchema(PDO $pdo): void {
    // The flag names the database too, so switching databases checks again.
    $flag = BH_SCHEMA_VERSION . '|' . md5(BH_DB_HOST . '|' . BH_DB_PORT . '|' . BH_DB_NAME);
    if (@file_get_contents(BH_SCHEMA_FLAG_FILE) === $flag) {
        return;
    }

    try {
        $column = static function (string $table, string $name) use ($pdo): ?array {
            $stmt = $pdo->prepare('SELECT COLUMN_TYPE FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t AND COLUMN_NAME = :c');
            $stmt->execute([':t' => $table, ':c' => $name]);
            $type = $stmt->fetchColumn();
            return $type === false ? null : ['type' => (string) $type];
        };

        // Apartments: boarding_houses.property_type and the 'apartment' room type.
        if ($column('boarding_houses', 'property_type') === null) {
            $pdo->exec("ALTER TABLE boarding_houses ADD COLUMN property_type ENUM('boarding_house', 'apartment') NOT NULL DEFAULT 'boarding_house' AFTER landlord_id");
            writeLog('Database upgraded: added boarding_houses.property_type (apartments).', 'INFO');
        }
        $roomType = $column('rooms', 'room_type');
        if ($roomType !== null && !str_contains($roomType['type'], "'apartment'")) {
            $pdo->exec("ALTER TABLE rooms MODIFY room_type ENUM('solo', 'shared', 'dormitory', 'studio', 'apartment') NOT NULL DEFAULT 'shared'");
            writeLog("Database upgraded: added the 'apartment' room type.", 'INFO');
        }

        @file_put_contents(BH_SCHEMA_FLAG_FILE, $flag, LOCK_EX);
    } catch (Throwable $e) {
        // E.g. the database user may not ALTER tables: the site keeps running and
        // the log says what to do. The check is tried again on the next request.
        writeLog('Automatic database upgrade failed (' . $e->getMessage() . '). Run database/migrate-apartments.sql in phpMyAdmin or MySQL Workbench.', 'ERROR');
    }
}
