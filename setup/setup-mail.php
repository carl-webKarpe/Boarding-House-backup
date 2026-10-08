<?php

declare(strict_types=1);

/**
 * Creates security/mail.local.php (Gmail settings for the Super Admin login
 * code) by asking two questions, then sends a test email.
 *
 *   php setup/setup-mail.php
 *
 * Command line only. The file it writes is ignored by Git.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$target = __DIR__ . '/../security/mail.local.php';

$ask = static function (string $question): string {
    echo $question;
    return trim((string) fgets(STDIN));
};

echo "\nGmail setup for the Super Admin login code\n------------------------------------------\n";
if (is_file($target) && strtolower($ask("security/mail.local.php already exists. Replace it? (y/n): ")) !== 'y') {
    echo "Nothing changed.\n";
    exit(0);
}

do {
    $gmail = $ask('Your Gmail address (e.g. you@gmail.com): ');
} while (!filter_var($gmail, FILTER_VALIDATE_EMAIL) && print("  That is not a valid email address.\n"));

do {
    $password = str_replace(' ', '', $ask('Your 16-letter Gmail App Password (spaces are OK): '));
} while (strlen($password) !== 16 && print("  An App Password has 16 letters. Create one at https://myaccount.google.com/apppasswords\n"));

$file = "<?php\n\n// Gmail settings for the Super Admin login code (created by setup/setup-mail.php).\n// This file is ignored by Git, so the App Password is never uploaded.\n\nreturn [\n"
    . "    'host' => 'smtp.gmail.com',\n"
    . "    'port' => 587,\n\n"
    . '    \'username\' => ' . var_export($gmail, true) . ",\n\n"
    . "    // Gmail App Password (16 characters)\n"
    . '    \'password\' => ' . var_export($password, true) . ",\n\n"
    . "    'encryption' => 'tls',\n\n"
    . '    \'from_email\' => ' . var_export($gmail, true) . ",\n"
    . "    'from_name' => 'Boarding House Rental System',\n];\n";

if (file_put_contents($target, $file) === false) {
    echo "Could not write {$target}. Check the folder permissions.\n";
    exit(1);
}
echo "\n  [OK]   Created security/mail.local.php\n";

// Send a test email right away.
require_once __DIR__ . '/../security/two_factor.php';
if (!mailReady()) {
    echo '  [FIX]  ' . mailProblem() . "\n";
    exit(1);
}
echo "  Sending a test email to {$gmail} ...\n";
$result = sendMail($gmail, 'Test', 'Boarding House Rental System - Gmail works',
    '<p>Gmail sending works. Super Admin login codes will arrive like this.</p>',
    'Gmail sending works. Super Admin login codes will arrive like this.');
if ($result['success']) {
    echo "  [OK]   Sent! Check your inbox (and Spam).\n\n"
        . "Done. Restart start-server.bat, then log in as the Super Admin: you will be asked for the code.\n"
        . "Check everything any time with:  php setup/check-mail.php\n";
    exit(0);
}
echo "  [FIX]  Not sent. Usually a wrong App Password. Details: last line of storage/app.log\n"
    . "         Run this script again to type the App Password again.\n";
exit(1);
