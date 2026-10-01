<?php

declare(strict_types=1);

/**
 * Shared helpers for every JSON API endpoint.
 *
 * Response format:
 *   success: { "success": true, "data": ..., "message": "..." }
 *   error:   { "success": false, "message": "...", "errors": { field: message } }
 * with a matching HTTP status code (200, 201, 400, 401, 403, 404, 409, 422, 429, 500, 503).
 */

require_once __DIR__ . '/../security/config.php';
require_once __DIR__ . '/../security/security_headers.php';
require_once __DIR__ . '/../security/session.php';
require_once __DIR__ . '/../security/database.php';
require_once __DIR__ . '/../security/sanitize.php';
require_once __DIR__ . '/../security/validation.php';
require_once __DIR__ . '/../security/roles.php';
require_once __DIR__ . '/../security/audit_log.php';

class ApiException extends RuntimeException {
    public function __construct(string $message, public readonly int $status = 400, public readonly array $errors = []) {
        parent::__construct($message);
    }
}

function apiBootstrap(): void {
    applySecurityHeaders();
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    startSecureSession();

    set_exception_handler(static function (Throwable $e): void {
        if ($e instanceof ApiException) {
            jsonError($e->getMessage(), $e->status, $e->errors);
        }

        if ($e instanceof DatabaseUnavailableException) {
            jsonError('The database is unavailable. Make sure MySQL is running and the database is imported.', 503);
        }

        if ($e instanceof PDOException && ($e->errorInfo[1] ?? null) === 1451) {
            jsonError('This record is still used by other records (for example bookings). Disable it instead of deleting it.', 409);
        }

        if ($e instanceof PDOException && ($e->errorInfo[1] ?? null) === 1062) {
            jsonError('A record with the same value already exists.', 409);
        }

        writeLog('API error: ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine(), 'ERROR');
        jsonError(BH_APP_DEBUG ? $e->getMessage() : 'Something went wrong. Please try again.', 500);
    });
}

function jsonResponse(mixed $data = null, string $message = '', int $status = 200, array $extra = []): never {
    http_response_code($status);
    echo json_encode(['success' => true, 'message' => $message, 'data' => $data] + $extra, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function jsonError(string $message, int $status = 400, array $errors = []): never {
    http_response_code($status);
    $body = ['success' => false, 'message' => $message];
    if ($errors) {
        $body['errors'] = $errors;
    }
    echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function requestMethod(): string {
    return strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
}

function requireMethod(string ...$methods): void {
    if (!in_array(requestMethod(), $methods, true)) {
        header('Allow: ' . implode(', ', $methods));
        jsonError('Method not allowed.', 405);
    }
}

/**
 * Reads the request body as JSON, falling back to form fields.
 */
function requestBody(): array {
    static $body = null;
    if ($body !== null) {
        return $body;
    }

    $contentType = (string) ($_SERVER['CONTENT_TYPE'] ?? '');
    if (str_contains($contentType, 'application/json')) {
        $decoded = json_decode((string) file_get_contents('php://input'), true);
        if (!is_array($decoded)) {
            throw new ApiException('Invalid JSON body.', 400);
        }
        return $body = $decoded;
    }

    return $body = $_POST;
}

/**
 * State-changing requests must send the session's CSRF token, either in the
 * X-CSRF-Token header or as a csrf_token field.
 */
function requireCsrf(): void {
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? (requestBody()['csrf_token'] ?? '');
    if (!verifyCsrfToken($token)) {
        jsonError('Your session security token is invalid or expired. Refresh the page and try again.', 403);
    }
}

function queryInt(string $key, int $default = 0, int $min = PHP_INT_MIN, int $max = PHP_INT_MAX): int {
    $value = $_GET[$key] ?? null;
    if ($value === null || $value === '' || !is_numeric($value)) {
        return $default;
    }

    return max($min, min($max, (int) $value));
}

function queryString(string $key, string $default = ''): string {
    $value = $_GET[$key] ?? $default;
    return is_string($value) ? trim($value) : $default;
}

/**
 * Standard page/per_page pagination.
 * @return array{page:int, per_page:int, offset:int}
 */
function paginationParams(int $defaultPerPage = 10): array {
    $page = queryInt('page', 1, 1);
    $perPage = queryInt('per_page', $defaultPerPage, 1, 100);
    return ['page' => $page, 'per_page' => $perPage, 'offset' => ($page - 1) * $perPage];
}

function paginationMeta(int $total, array $pagination): array {
    return [
        'total' => $total,
        'page' => $pagination['page'],
        'per_page' => $pagination['per_page'],
        'total_pages' => max(1, (int) ceil($total / $pagination['per_page'])),
    ];
}

/**
 * Throws a 422 with every failing field when any validation result failed.
 * @param array<string, array> $results field => validate*() result
 * @return array<string, mixed> field => cleaned value
 */
function collectValidated(array $results): array {
    $errors = [];
    $values = [];
    foreach ($results as $field => $result) {
        if (!$result['valid']) {
            $errors[$field] = $result['message'];
        } else {
            $values[$field] = $result['value'] ?? null;
        }
    }

    if ($errors) {
        throw new ApiException((string) reset($errors), 422, $errors);
    }

    return $values;
}

/**
 * Escapes LIKE wildcards in user search input.
 */
function likeValue(string $search): string {
    return '%' . addcslashes($search, '%_\\') . '%';
}
