<?php

declare(strict_types=1);

function sanitizeText(mixed $value): string {
    return trim((string) $value);
}

function sanitizeEmail(mixed $value): string {
    return strtolower(trim((string) $value));
}

function sanitizeUsername(mixed $value): string {
    return preg_replace('/[^A-Za-z0-9_]/', '', trim((string) $value));
}

function sanitizeForOutput(mixed $value): string {
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
