<?php

declare(strict_types=1);

/**
 * Checks the Super Admin email-code setup and sends a test email.
 *
 *   php setup/check-mail.php                    check only
 *   php setup/check-mail.php you@gmail.com      check and send a test email
 *
 * Command line only (it is not a web page).
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../security/two_factor.php';

$ok = static fn (string $text) => print("  [OK]   {$text}\n");
$bad = static fn (string $text) => print("  [FIX]  {$text}\n");
$problems = 0;

echo "\nSuper Admin email code - setup check\n------------------------------------\n";

// 1. PHPMailer (Composer)
if (is_file(BH_COMPOSER_AUTOLOAD)) {
    $ok('PHPMailer is installed (security/vendor).');
} else {
    $bad('PHPMailer is NOT installed. In the project folder run:  composer install');
    $problems++;
}

// 2. Gmail settings file
$config = mailConfig();
if ($config === null) {
    $bad('security/mail.local.php is missing. Copy security/mail.local.example.php to security/mail.local.php and fill it in.');
    $problems++;
} else {
    $ok('security/mail.local.php found.');
    if (!filter_var($config['username'] ?? '', FILTER_VALIDATE_EMAIL)) {
        $bad("'username' must be your Gmail address (now: '" . ($config['username'] ?? '') . "').");
        $problems++;
    }
    if (strlen((string) $config['password']) !== 16 || in_array($config['password'], ['yourapppasswordhere', 'yournewapppasswordhere'], true)) {
        $bad("'password' must be the 16-letter Gmail App Password (https://myaccount.google.com/apppasswords), not your normal Gmail password.");
        $problems++;
    }
    if (($config['from_email'] ?? $config['username']) !== ($config['username'] ?? '')) {
        echo "  [NOTE] 'from_email' differs from 'username'. Gmail sends from the username address unless the other one is an alias in Gmail.\n";
    }
}

// 3. Super Admin accounts and their email addresses
try {
    $admins = getDb()->query("SELECT username, email, status FROM users WHERE role = 'super_admin'")->fetchAll();
    if (!$admins) {
        $bad('There is no Super Admin account. The code is only asked for Super Admin logins.');
        $problems++;
    }
    foreach ($admins as $a) {
        if (str_ends_with((string) $a['email'], '@bhrental.local')) {
            $bad("Super Admin '{$a['username']}' uses {$a['email']} - that is a demo address with no inbox. Change it to your real Gmail in Admin > Settings > My profile.");
            $problems++;
        } else {
            $ok("Super Admin '{$a['username']}' will get the code at {$a['email']}.");
        }
    }
} catch (Throwable $e) {
    $bad('Cannot read the database: ' . $e->getMessage());
    $problems++;
}

echo "\n" . (mailReady()
    ? "Email codes are ON: a Super Admin login now asks for the code.\n"
    : "Email codes are OFF (" . mailProblem() . ")\nSuper Admin logs in without a code until this is fixed.\n");

// 4. Optional test email
$to = $argv[1] ?? '';
if ($to !== '') {
    if (!mailReady()) {
        echo "\nTest email not sent: fix the items above first.\n";
        exit(1);
    }
    echo "\nSending a test email to {$to} ...\n";
    $result = sendMail($to, 'Test', 'Boarding House Rental System - test email',
        '<p>Gmail sending works. Super Admin login codes will arrive like this.</p>',
        'Gmail sending works. Super Admin login codes will arrive like this.');
    echo $result['success']
        ? "  [OK]   Sent! Check the inbox (and Spam).\n"
        : "  [FIX]  Not sent. The reason is in storage/app.log (last line). Usually a wrong App Password.\n";
    exit($result['success'] ? 0 : 1);
}

echo $problems ? "\n{$problems} thing(s) to fix.\n" : "\nAll good. Test sending with:  php setup/check-mail.php you@gmail.com\n";
exit($problems ? 1 : 0);
