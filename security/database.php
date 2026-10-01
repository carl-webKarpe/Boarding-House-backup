<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';

function getDb(): PDO {
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', BH_DB_HOST, BH_DB_NAME, BH_DB_CHARSET);
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];

    try {
        $pdo = new PDO($dsn, BH_DB_USER, BH_DB_PASS, $options);
    } catch (PDOException $e) {
        writeLog('Database connection failed: ' . $e->getMessage(), 'ERROR');
        http_response_code(503);
        echo json_encode(['success' => false, 'message' => 'Service temporarily unavailable.']);
        exit;
    }

    return $pdo;
}
