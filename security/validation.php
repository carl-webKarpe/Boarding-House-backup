<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/sanitize.php';

function validateRequired(mixed $value, string $fieldName): array {
    if (trim((string) $value) === '') {
        return ['valid' => false, 'message' => sprintf('%s is required.', $fieldName)];
    }

    return ['valid' => true, 'message' => ''];
}

function validateEmailValue(mixed $value): array {
    $email = sanitizeEmail($value);
    if ($email === '') {
        return ['valid' => false, 'message' => 'Email is required.'];
    }

    if (strlen($email) > 255 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['valid' => false, 'message' => 'Please enter a valid email address.'];
    }

    return ['valid' => true, 'message' => '', 'value' => $email];
}

function validateUsernameValue(mixed $value): array {
    $username = trim((string) $value);
    if ($username === '') {
        return ['valid' => false, 'message' => 'Username is required.'];
    }

    if (!preg_match('/^[A-Za-z0-9_]+$/', $username)) {
        return ['valid' => false, 'message' => 'Username may only contain letters, numbers, and underscores.'];
    }

    if (strlen($username) < 5 || strlen($username) > 20) {
        return ['valid' => false, 'message' => 'Username must be 5-20 characters long.'];
    }

    return ['valid' => true, 'message' => '', 'value' => $username];
}

function validatePasswordValue(mixed $value): array {
    $password = (string) $value;
    if ($password === '') {
        return ['valid' => false, 'message' => 'Password is required.'];
    }

    if (strlen($password) < BH_PASSWORD_MIN_LENGTH) {
        return ['valid' => false, 'message' => 'Password must be at least 8 characters long.'];
    }

    if (strlen($password) > 72) {
        return ['valid' => false, 'message' => 'Password must be at most 72 characters long.'];
    }

    if (!preg_match('/[A-Z]/', $password) || !preg_match('/[a-z]/', $password)) {
        return ['valid' => false, 'message' => 'Password must include uppercase and lowercase letters.'];
    }

    if (!preg_match('/[0-9]/', $password)) {
        return ['valid' => false, 'message' => 'Password must include at least one number.'];
    }

    if (!preg_match('/[^A-Za-z0-9]/', $password)) {
        return ['valid' => false, 'message' => 'Password must include at least one special character.'];
    }

    return ['valid' => true, 'message' => '', 'value' => $password];
}

function validatePhoneValue(mixed $value): array {
    $phone = preg_replace('/[\s-]/', '', sanitizeText($value));
    if ($phone === '') {
        return ['valid' => false, 'message' => 'Phone number is required.'];
    }

    if (!preg_match('/^(09\d{9}|\+639\d{9})$/', $phone)) {
        return ['valid' => false, 'message' => 'Enter a valid Philippine mobile number (e.g. 09123456789).'];
    }

    return ['valid' => true, 'message' => '', 'value' => $phone];
}

/**
 * Text field with a maximum length. Returns the trimmed value.
 */
function validateTextValue(mixed $value, string $fieldName, int $maxLength, bool $required = true): array {
    $text = sanitizeText($value);
    if ($text === '') {
        return $required
            ? ['valid' => false, 'message' => sprintf('%s is required.', $fieldName)]
            : ['valid' => true, 'message' => '', 'value' => null];
    }

    if (mb_strlen($text) > $maxLength) {
        return ['valid' => false, 'message' => sprintf('%s must be at most %d characters.', $fieldName, $maxLength)];
    }

    return ['valid' => true, 'message' => '', 'value' => $text];
}

function validateEnumValue(mixed $value, array $allowed, string $fieldName): array {
    $text = (string) $value;
    if (!in_array($text, $allowed, true)) {
        return ['valid' => false, 'message' => sprintf('%s is not valid.', $fieldName)];
    }

    return ['valid' => true, 'message' => '', 'value' => $text];
}

function validateNumberValue(mixed $value, string $fieldName, float $min, float $max): array {
    if ($value === null || $value === '' || !is_numeric($value)) {
        return ['valid' => false, 'message' => sprintf('%s must be a number.', $fieldName)];
    }

    $number = (float) $value;
    if ($number < $min || $number > $max) {
        return ['valid' => false, 'message' => sprintf('%s must be between %s and %s.', $fieldName, rtrim(rtrim(number_format($min, 2, '.', ''), '0'), '.'), rtrim(rtrim(number_format($max, 2, '.', ''), '0'), '.'))];
    }

    return ['valid' => true, 'message' => '', 'value' => $number];
}

function validateDateValue(mixed $value, string $fieldName, bool $required = true): array {
    $text = trim((string) $value);
    if ($text === '') {
        return $required
            ? ['valid' => false, 'message' => sprintf('%s is required.', $fieldName)]
            : ['valid' => true, 'message' => '', 'value' => null];
    }

    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $text);
    if (!$date || $date->format('Y-m-d') !== $text) {
        return ['valid' => false, 'message' => sprintf('%s must be a valid date.', $fieldName)];
    }

    return ['valid' => true, 'message' => '', 'value' => $text];
}
