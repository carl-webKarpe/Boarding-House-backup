<?php

declare(strict_types=1);

/**
 * Converts a MySQL Workbench export (MySQL 8) into a file XAMPP (MariaDB)
 * can import, so the data you have in MySQL Workbench can be moved to XAMPP.
 *
 *   C:\xampp\php\php.exe database\xampp\convert-workbench-dump.php "C:\path\to\export.sql"
 *
 * Writes "<name>-xampp.sql" next to the original and changes only what
 * MariaDB does not understand:
 *  - MySQL 8 collations (utf8mb4_0900_ai_ci ...) -> utf8mb4_unicode_ci
 *  - DEFAULT ENCRYPTION='N' (MySQL 8 only)
 *  - GTID / binary-log lines (SET @@GLOBAL.GTID_PURGED ...), which need SUPER rights
 *  - DEFINER=`user`@`host` on views, triggers and routines
 * and makes sure the file creates and uses the `bhsystem` database.
 *
 * Command line only. The original file is never changed.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$input = $argv[1] ?? '';
if ($input === '' || !is_file($input)) {
    fwrite(STDERR, "Usage: php convert-workbench-dump.php \"C:\\path\\to\\export.sql\"\n");
    fwrite(STDERR, "Export it first in MySQL Workbench: Server > Data Export > Export to Self-Contained File.\n");
    exit(1);
}

$sql = (string) file_get_contents($input);
$sql = preg_replace('/^\xEF\xBB\xBF/', '', $sql); // byte-order mark
$changes = [];
$count = static function (string $label, int $n) use (&$changes): void {
    if ($n > 0) {
        $changes[] = "$label: $n";
    }
};

// MySQL 8 collations -> MariaDB ones.
$sql = preg_replace('/utf8mb4_0900_(?:as_cs|as_ci|ai_ci)\b/i', 'utf8mb4_unicode_ci', $sql, -1, $n);
$count('MySQL 8 collations replaced', $n);
$sql = preg_replace('/utf8mb4_0900_bin\b/i', 'utf8mb4_bin', $sql, -1, $n);
$count('utf8mb4_0900_bin replaced', $n);

// DEFAULT ENCRYPTION='N' (plain or inside a /*!80016 ... */ comment).
$sql = preg_replace('#/\*!80016\s+DEFAULT\s+ENCRYPTION\s*=\s*\'[NY]\'\s*\*/#i', '', $sql, -1, $n);
$sql = preg_replace('/\s+DEFAULT\s+ENCRYPTION\s*=\s*\'[NY]\'/i', '', $sql, -1, $m);
$count("DEFAULT ENCRYPTION removed", $n + $m);

// GTID and binary-log statements need SUPER rights and do not exist in MariaDB.
$sql = preg_replace('/^.*(GTID_PURGED|@MYSQLDUMP_TEMP_LOG_BIN|SQL_LOG_BIN).*$\R?/mi', '', $sql, -1, $n);
$count('GTID / binary-log lines removed', $n);

// DEFINER clauses (views, triggers, procedures) point to users that may not exist in XAMPP.
$sql = preg_replace('/\s*DEFINER\s*=\s*`[^`]*`@`[^`]*`/i', '', $sql, -1, $n);
$count('DEFINER clauses removed', $n);

// Make sure the file creates and selects the database.
if (!preg_match('/^\s*CREATE\s+(DATABASE|SCHEMA)\b/mi', $sql)) {
    $sql = "CREATE DATABASE IF NOT EXISTS `bhsystem` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;\nUSE `bhsystem`;\n\n" . $sql;
    $changes[] = 'Added CREATE DATABASE bhsystem + USE bhsystem';
} elseif (!preg_match('/^\s*USE\s+/mi', $sql)) {
    $sql = preg_replace('/^(\s*CREATE\s+(?:DATABASE|SCHEMA)\b[^;]*;)/mi', "$1\nUSE `bhsystem`;", $sql, 1);
    $changes[] = 'Added USE bhsystem';
}

$header = "-- Converted for XAMPP (MariaDB) by database/xampp/convert-workbench-dump.php\n"
    . '-- Source: ' . basename($input) . ' · ' . date('Y-m-d H:i') . "\n"
    . "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS = 0;\n\n";
$footer = "\n\nSET FOREIGN_KEY_CHECKS = 1;\n";

$output = preg_replace('/\.sql$/i', '', $input) . '-xampp.sql';
if (file_put_contents($output, $header . $sql . $footer) === false) {
    fwrite(STDERR, "Could not write $output\n");
    exit(1);
}

echo "\nCreated: $output\n";
echo $changes ? '  - ' . implode("\n  - ", $changes) . "\n" : "  (nothing needed changing; the file was already compatible)\n";
echo "\nNext: open http://localhost/phpmyadmin > Import > choose this file > Import.\n";
