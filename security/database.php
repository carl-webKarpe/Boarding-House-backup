<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';

class DatabaseUnavailableException extends RuntimeException {}

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

    return $pdo;
}
