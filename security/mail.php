<?php

declare(strict_types=1);

/**
 * Sending email with PHPMailer (installed with Composer) through Gmail SMTP.
 *
 * Settings: security/mail.local.php (copy security/mail.local.example.php).
 * Library:  composer install   ->  security/vendor/
 */

require_once __DIR__ . '/config.php';

const BH_MAIL_CONFIG_FILE = __DIR__ . '/mail.local.php';
const BH_COMPOSER_AUTOLOAD = __DIR__ . '/vendor/autoload.php';

/** The mail settings, or null when security/mail.local.php does not exist yet. */
function mailConfig(): ?array {
    static $config = false;
    if ($config !== false) {
        return $config;
    }
    if (!is_file(BH_MAIL_CONFIG_FILE)) {
        return $config = null;
    }
    $loaded = require BH_MAIL_CONFIG_FILE;
    if (!is_array($loaded)) {
        return $config = null;
    }
    $loaded['password'] = str_replace(' ', '', (string) ($loaded['password'] ?? ''));
    return $config = $loaded + ['host' => 'smtp.gmail.com', 'port' => 587, 'encryption' => 'tls', 'from_name' => 'Boarding House Rental System'];
}

/** True when email can be sent (settings filled in and PHPMailer installed). */
function mailReady(): bool {
    $config = mailConfig();
    return $config !== null
        && filter_var($config['username'] ?? '', FILTER_VALIDATE_EMAIL)
        && !in_array($config['password'], ['', 'yourapppasswordhere', 'yournewapppasswordhere'], true)
        && is_file(BH_COMPOSER_AUTOLOAD);
}

/** Why mail is not ready, for the logs and the setup check. */
function mailProblem(): string {
    $config = mailConfig();
    return match (true) {
        $config === null => 'security/mail.local.php is missing (copy security/mail.local.example.php).',
        !filter_var($config['username'] ?? '', FILTER_VALIDATE_EMAIL) => 'The Gmail username in security/mail.local.php is not a valid email address.',
        in_array($config['password'], ['', 'yourapppasswordhere', 'yournewapppasswordhere'], true) => 'Put your Gmail App Password in security/mail.local.php.',
        !is_file(BH_COMPOSER_AUTOLOAD) => 'PHPMailer is not installed. Run "composer install" in the project folder.',
        default => '',
    };
}

/**
 * Sends one email. Returns ['success' => bool, 'message' => string].
 * Errors are written to storage/app.log; the visitor only gets a short message.
 */
function sendMail(string $toEmail, string $toName, string $subject, string $htmlBody, string $textBody): array {
    if (!mailReady()) {
        writeLog('Email not sent: ' . mailProblem(), 'ERROR');
        return ['success' => false, 'message' => 'Email is not set up on this server yet.'];
    }
    require_once BH_COMPOSER_AUTOLOAD;
    $config = mailConfig();

    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = (string) $config['host'];
        $mail->Port = (int) $config['port'];
        $mail->SMTPAuth = true;
        $mail->Username = (string) $config['username'];
        $mail->Password = (string) $config['password'];
        $encryption = strtolower((string) $config['encryption']);
        if ($encryption === 'none' || $encryption === '') {
            // Only for a local test mail server (e.g. Mailpit); Gmail needs tls or ssl.
            $mail->SMTPSecure = '';
            $mail->SMTPAutoTLS = false;
        } else {
            $mail->SMTPSecure = $encryption === 'ssl'
                ? PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS
                : PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        }
        $mail->Timeout = 20;                         // connecting
        $mail->getSMTPInstance()->Timelimit = 30;     // waiting for each server reply (default 300 s)
        $mail->CharSet = 'UTF-8';

        $from = filter_var($config['from_email'] ?? '', FILTER_VALIDATE_EMAIL) ? $config['from_email'] : $config['username'];
        $mail->setFrom((string) $from, (string) $config['from_name']);
        $mail->addAddress($toEmail, $toName);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $htmlBody;
        $mail->AltBody = $textBody;
        $mail->send();

        return ['success' => true, 'message' => 'Email sent.'];
    } catch (Throwable $e) {
        writeLog('Email to ' . $toEmail . ' failed: ' . ($mail->ErrorInfo ?: $e->getMessage()), 'ERROR');
        return ['success' => false, 'message' => 'The verification email could not be sent. Please try again in a moment.'];
    }
}
