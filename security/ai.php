<?php

declare(strict_types=1);

/**
 * AI assistant features. Two providers, chosen with 'provider' in security/ai.local.php:
 *   'claude' - Claude API through the official Anthropic PHP SDK (paid credit)
 *   'gemini' - Google Gemini API (has a free tier), plain HTTPS through Guzzle
 *
 *   1. Room finder        - students ask for a room in plain words (api/assistant.php)
 *   2. Listing writer     - landlords turn short notes into a description (api/landlord/ai-writer.php)
 *   3. Reply helper       - landlords get a suggested reply to a student (api/landlord/ai-reply.php)
 *   4. Listing check      - admins get a second opinion before approving (api/admin/ai-review.php)
 *   5. Dashboard summary  - admins get the week in plain words (api/admin/ai-summary.php)
 *
 * Settings: security/ai.local.php (copy security/ai.local.example.php). While that
 * file is missing the AI buttons are simply hidden; nothing else changes.
 *
 * Safety rules for every feature:
 *  - Only the data a feature needs is sent (public listing facts, the message
 *    being answered) - never passwords, emails, phone numbers or ID documents.
 *  - Text written by users is passed as data inside <untrusted> tags, and the
 *    answer is always shown as plain text (escaped), never as HTML.
 *  - Per-user and per-day limits keep the bill predictable.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/rate_limit.php';

const BH_AI_CONFIG_FILE = __DIR__ . '/ai.local.php';
const BH_AI_AUTOLOAD = __DIR__ . '/vendor/autoload.php';
const BH_AI_SDK_FILE = __DIR__ . '/vendor/anthropic-ai/sdk/src/Client.php';
const BH_AI_GUZZLE_FILE = __DIR__ . '/vendor/guzzlehttp/guzzle/src/Client.php';
const BH_AI_DEFAULT_MODEL = 'claude-opus-5-5';
const BH_AI_DEFAULT_GEMINI_MODEL = 'gemini-flash-lite-latest'; // Google's alias for its current Flash-Lite model (fast, no thinking)
const BH_AI_GEMINI_URL = 'https://generativelanguage.googleapis.com';

/** The AI settings, or null when security/ai.local.php does not exist yet. */
function aiConfig(): ?array {
    static $config = false;
    if ($config !== false) {
        return $config;
    }
    if (!is_file(BH_AI_CONFIG_FILE)) {
        return $config = null;
    }
    $loaded = require BH_AI_CONFIG_FILE;
    if (!is_array($loaded)) {
        return $config = null;
    }
    return $config = $loaded + [
        'provider' => 'claude',
        'api_key' => '',
        'model' => BH_AI_DEFAULT_MODEL,
        'gemini_api_key' => '',
        'gemini_model' => BH_AI_DEFAULT_GEMINI_MODEL,
        'enabled' => true,
        'per_user_per_hour' => 20,
        'per_day' => 300,
        'timeout_seconds' => 45,
        'base_url' => null, // only for local testing against a fake server
    ];
}

function aiUsesGemini(): bool {
    return strtolower(trim((string) (aiConfig()['provider'] ?? ''))) === 'gemini';
}

/** Why the AI is not available ('' when it is). */
function aiProblem(): string {
    $config = aiConfig();
    if ($config !== null && $config['enabled'] && aiUsesGemini()) {
        $key = trim((string) $config['gemini_api_key']);
        return match (true) {
            strlen($key) < 20 || stripos($key, 'your') !== false => 'Put your Gemini API key (from aistudio.google.com) in security/ai.local.php.',
            !is_file(BH_AI_AUTOLOAD) || !is_file(BH_AI_GUZZLE_FILE) => 'Guzzle is not installed. Run "composer install" in the project folder and upload security/vendor.',
            default => '',
        };
    }
    return match (true) {
        $config === null => 'security/ai.local.php is missing (copy security/ai.local.example.php).',
        !$config['enabled'] => "AI is turned off ('enabled' => false in security/ai.local.php).",
        !str_starts_with(trim((string) $config['api_key']), 'sk-ant-') => 'Put your Claude API key (starts with sk-ant-) in security/ai.local.php.',
        // File checks only: pages call aiReady() to show the AI buttons, so it must never
        // load the SDK (a half-uploaded vendor folder would otherwise break the whole page).
        !is_file(BH_AI_AUTOLOAD) || !is_file(BH_AI_SDK_FILE) => 'The Anthropic SDK is not installed. Run "composer install" in the project folder and upload security/vendor.',
        default => '',
    };
}

function aiLoadSdk(string $class = 'Anthropic\\Client'): bool {
    try {
        require_once __DIR__ . '/ai_host_compat.php';
        if (is_file(BH_AI_AUTOLOAD)) {
            require_once BH_AI_AUTOLOAD;
        }
        return class_exists($class);
    } catch (\Throwable $e) {
        writeLog('AI SDK could not be loaded: ' . $e->getMessage(), 'ERROR');
        return false;
    }
}

function aiReady(): bool {
    return aiProblem() === '';
}

function aiClient(): \Anthropic\Client {
    static $client = null;
    if ($client) {
        return $client;
    }
    if (!aiLoadSdk()) {
        throw new \RuntimeException('The Anthropic SDK files are missing or incomplete (re-upload security/vendor).');
    }
    $config = aiConfig();
    return $client = new \Anthropic\Client(
        apiKey: trim((string) $config['api_key']),
        baseUrl: $config['base_url'] ?: null,
        requestOptions: [
            'transporter' => new \GuzzleHttp\Client(aiHttpOptions()),
            'maxRetries' => 1,
        ],
    );
}

/**
 * Checks, in order: AI set up, per-user (or visitor session) limit per hour for
 * this feature, and the daily total for the whole site. Returns null when the
 * request may go ahead, otherwise ['message' => ..., 'status' => HTTP code].
 */
function aiGuard(string $feature, string $who): ?array {
    if (!aiReady()) {
        writeLog('AI not available: ' . aiProblem(), 'WARN');
        return ['message' => 'The AI assistant is not set up on this server yet.', 'status' => 503];
    }
    $config = aiConfig();
    if (isRateLimited('ai_' . $feature, $who, max(1, (int) $config['per_user_per_hour']), 60)) {
        return ['message' => 'You have used the AI assistant a lot in the last hour. Please try again a bit later.', 'status' => 429];
    }
    if (isRateLimited('ai_day', 'site', max(1, (int) $config['per_day']), 1440)) {
        return ['message' => 'The AI assistant reached today\'s limit. Please try again tomorrow.', 'status' => 429];
    }
    return null;
}

function aiCountUse(string $feature, string $who): void {
    hitRateLimit('ai_' . $feature, $who, 60);
    hitRateLimit('ai_day', 'site', 1440);
}

/** "HTTP 400: <the API's own error message>" - the SDK's getMessage() starts with a generic title. */
function aiErrorDetail(\Anthropic\Core\Exceptions\APIStatusException $e): string {
    $body = is_array($e->body) ? $e->body : [];
    $text = $body['error']['message'] ?? (is_string($e->body) ? $e->body : $e->getMessage());
    return 'HTTP ' . ($e->status ?? '?') . ': ' . substr(str_replace(["\r", "\n"], ' ', (string) $text), 0, 300);
}

/** Wraps text written by users so the model treats it as data, not instructions. */
function aiUntrusted(string $label, string $text): string {
    $clean = str_replace(['<untrusted', '</untrusted'], ['‹untrusted', '‹/untrusted'], $text);
    return "<untrusted source=\"{$label}\">\n{$clean}\n</untrusted>";
}

/** Guzzle options shared by both providers. */
function aiHttpOptions(): array {
    $http = [
        'timeout' => (float) aiConfig()['timeout_seconds'],
        'connect_timeout' => 10,
    ];
    // XAMPP's PHP often has no certificate list: use the one installed by Composer.
    if (class_exists(\Composer\CaBundle\CaBundle::class)) {
        $http['verify'] = \Composer\CaBundle\CaBundle::getSystemCaRootBundlePath();
    }
    return $http;
}

/**
 * Gemini's responseSchema is an OpenAPI subset: upper-case types and no
 * additionalProperties. Converts the JSON Schemas the features already use.
 */
function aiGeminiSchema(array $schema): array {
    $out = [];
    foreach ($schema as $key => $value) {
        if ($key === 'additionalProperties') {
            continue;
        }
        if ($key === 'type' && is_string($value)) {
            $out['type'] = strtoupper($value);
        } elseif ($key === 'properties' && is_array($value)) {
            $out['properties'] = array_map(fn (array $prop): array => aiGeminiSchema($prop), $value);
        } elseif ($key === 'items' && is_array($value)) {
            $out['items'] = aiGeminiSchema($value);
        } else {
            $out[$key] = $value;
        }
    }
    return $out;
}

/** The same contract as aiJson(), answered by Google Gemini (generateContent REST API). */
function aiGeminiJson(string $feature, string $system, array $messages, array $schema, int $maxTokens, ?string $context): array {
    $config = aiConfig();
    if (!aiLoadSdk('GuzzleHttp\\Client')) {
        writeLog("AI {$feature}: Guzzle could not be loaded (re-upload security/vendor).", 'ERROR');
        return ['success' => false, 'status' => 504, 'message' => 'The AI assistant took too long or could not be reached. Please try again.'];
    }

    $systemParts = [['text' => $system]];
    if ($context !== null) {
        $systemParts[] = ['text' => $context];
    }
    $contents = array_map(fn (array $m): array => [
        'role' => $m['role'] === 'assistant' ? 'model' : 'user',
        'parts' => [
            ...array_map(fn (array $img): array => ['inlineData' => ['mimeType' => $img['mime'], 'data' => $img['data']]], $m['images'] ?? []),
            ['text' => (string) $m['content']],
        ],
    ], $messages);

    $model = trim((string) $config['gemini_model']) ?: BH_AI_DEFAULT_GEMINI_MODEL;
    $base = rtrim((string) ($config['base_url'] ?: BH_AI_GEMINI_URL), '/');
    $url = $base . '/v1beta/models/' . rawurlencode($model) . ':generateContent';
    $body = [
        'systemInstruction' => ['parts' => $systemParts],
        'contents' => $contents,
        'generationConfig' => [
            'responseMimeType' => 'application/json',
            'responseSchema' => aiGeminiSchema($schema),
            // Flash models may think first, and thinking counts toward this limit.
            'maxOutputTokens' => max($maxTokens, 8192),
        ],
    ];

    $http = new \GuzzleHttp\Client(aiHttpOptions() + ['http_errors' => false]);
    try {
        for ($attempt = 1; ; $attempt++) {
            $response = $http->post($url, [
                'headers' => ['x-goog-api-key' => trim((string) $config['gemini_api_key'])],
                'json' => $body,
            ]);
            $status = $response->getStatusCode();
            // One retry when Google is briefly overloaded.
            if ($attempt === 1 && in_array($status, [500, 503], true)) {
                usleep(800000);
                continue;
            }
            break;
        }
    } catch (\Throwable $e) {
        writeLog("AI {$feature}: Gemini " . get_class($e) . ' - ' . substr(str_replace(["\r", "\n"], ' ', $e->getMessage()), 0, 300), 'ERROR');
        return ['success' => false, 'status' => 504, 'message' => 'The AI assistant took too long or could not be reached. Please try again.'];
    }

    $json = json_decode((string) $response->getBody(), true);
    $json = is_array($json) ? $json : [];
    if ($status !== 200) {
        $error = $json['error'] ?? [];
        $detail = 'HTTP ' . $status . ': ' . substr(str_replace(["\r", "\n"], ' ', (string) ($error['message'] ?? 'no details')), 0, 300);
        writeLog("AI {$feature}: Gemini error " . ($error['status'] ?? 'unknown') . ' - ' . $detail, 'ERROR');
        $message = match (true) {
            $status === 429 => 'The free AI limit was reached. Please try again in a few minutes.',
            $status === 401 || $status === 403 || stripos($detail, 'API key') !== false
                => 'The AI assistant is not available right now (check the Gemini API key in security/ai.local.php).',
            $status === 404 => 'The Gemini model in security/ai.local.php is not available. Try gemini-2.5-flash.',
            $status >= 500 => 'The AI assistant is busy right now. Please try again in a minute.',
            default => 'The AI assistant could not answer (' . $detail . ').',
        };
        return ['success' => false, 'status' => $status === 429 ? 429 : 502, 'message' => $message];
    }

    $candidate = $json['candidates'][0] ?? null;
    $finish = (string) ($candidate['finishReason'] ?? '');
    if (!empty($json['promptFeedback']['blockReason']) || in_array($finish, ['SAFETY', 'PROHIBITED_CONTENT', 'BLOCKLIST', 'SPII', 'RECITATION'], true)) {
        writeLog("AI {$feature}: Gemini declined the request (" . ($json['promptFeedback']['blockReason'] ?? $finish) . ').', 'WARN');
        return ['success' => false, 'status' => 422, 'message' => 'The AI assistant cannot help with that request. Please rephrase it.'];
    }
    if ($finish === 'MAX_TOKENS') {
        writeLog("AI {$feature}: Gemini answer was cut off.", 'WARN');
        return ['success' => false, 'status' => 502, 'message' => 'The AI answer was too long. Please ask a shorter question.'];
    }

    $text = '';
    foreach ($candidate['content']['parts'] ?? [] as $part) {
        if (empty($part['thought']) && isset($part['text'])) {
            $text .= $part['text'];
        }
    }
    $data = json_decode($text, true);
    if (!is_array($data)) {
        writeLog("AI {$feature}: the Gemini answer was not valid JSON.", 'ERROR');
        return ['success' => false, 'status' => 502, 'message' => 'The AI assistant gave an unexpected answer. Please try again.'];
    }
    return ['success' => true, 'data' => $data];
}

/**
 * A stored photo ("uploads/listings/x.jpg") ready to send to the AI as
 * ['mime' => ..., 'data' => base64], shrunk to at most 768 px so requests stay
 * small and fast. Null when the file is missing or cannot be read.
 */
function aiImage(string $storedPath): ?array {
    $path = BH_SYSTEM_ROOT . '/' . ltrim(str_replace('\\', '/', $storedPath), '/');
    $real = realpath($path);
    if ($real === false || !is_file($real) || !str_starts_with($real, realpath(BH_SYSTEM_ROOT))) {
        return null;
    }
    $bytes = @file_get_contents($real);
    if ($bytes === false || $bytes === '') {
        return null;
    }
    if (function_exists('imagecreatefromstring')) {
        $img = @imagecreatefromstring($bytes);
        if ($img !== false) {
            [$w, $h] = [imagesx($img), imagesy($img)];
            $scale = min(1, 768 / max($w, $h));
            if ($scale < 1) {
                $small = imagecreatetruecolor(max(1, (int) round($w * $scale)), max(1, (int) round($h * $scale)));
                imagecopyresampled($small, $img, 0, 0, 0, 0, imagesx($small), imagesy($small), $w, $h);
                $img = $small;
            }
            ob_start();
            imagejpeg($img, null, 75);
            $jpeg = (string) ob_get_clean();
            if ($jpeg !== '') {
                return ['mime' => 'image/jpeg', 'data' => base64_encode($jpeg)];
            }
        }
    }
    // No GD: send small JPEG/PNG/WebP files as they are.
    $mime = (new finfo(FILEINFO_MIME_TYPE))->buffer($bytes) ?: '';
    if (in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true) && strlen($bytes) <= 1500000) {
        return ['mime' => $mime, 'data' => base64_encode($bytes)];
    }
    return null;
}

/**
 * One request to the AI that must answer with JSON matching $schema.
 * A message may carry photos: ['role' => 'user', 'content' => '...', 'images' => [aiImage(...), ...]].
 *
 * @param array<int, array{role: string, content: string}> $messages
 * @return array{success: bool, data?: array, message?: string, status?: int}
 */
function aiJson(string $feature, string $system, array $messages, array $schema, string $effort = 'low', int $maxTokens = 4000, ?string $cacheableContext = null): array {
    if (!aiReady()) {
        writeLog('AI not available: ' . aiProblem(), 'WARN');
        return ['success' => false, 'status' => 503, 'message' => 'The AI assistant is not set up on this server yet.'];
    }
    if (aiUsesGemini()) {
        return aiGeminiJson($feature, $system, $messages, $schema, $maxTokens, $cacheableContext);
    }

    $config = aiConfig();
    $messages = array_map(static function (array $m): array {
        if (empty($m['images'])) {
            unset($m['images']);
            return $m;
        }
        $blocks = array_map(fn (array $img): array => ['type' => 'image', 'source' => ['type' => 'base64', 'mediaType' => $img['mime'], 'data' => $img['data']]], $m['images']);
        $blocks[] = ['type' => 'text', 'text' => (string) $m['content']];
        return ['role' => $m['role'], 'content' => $blocks];
    }, $messages);
    $systemBlocks = [['type' => 'text', 'text' => $system]];
    if ($cacheableContext !== null) {
        // Large, rarely changing context (e.g. the room list) is cached between requests.
        $systemBlocks[] = ['type' => 'text', 'text' => $cacheableContext, 'cacheControl' => ['type' => 'ephemeral']];
    }

    $request = [
        'model' => (string) $config['model'],
        'maxTokens' => $maxTokens,
        'system' => $systemBlocks,
        'messages' => $messages,
        'outputConfig' => [
            'effort' => $effort,
            'format' => ['type' => 'json_schema', 'schema' => $schema],
        ],
        // If the model declines for policy reasons, the API retries on its default fallback model.
        'fallbacks' => 'default',
        'betas' => ['server-side-fallback-2026-07-01'],
    ];

    try {
        try {
            $message = aiClient()->beta->messages->create(...$request);
        } catch (\Anthropic\Core\Exceptions\BadRequestException $e) {
            // The fallback option is a beta; if this account or model rejects it, ask again without it.
            if (!preg_match('/fallback|beta/i', aiErrorDetail($e))) {
                throw $e;
            }
            writeLog("AI {$feature}: the API rejected the fallback option, retrying without it.", 'WARN');
            unset($request['fallbacks'], $request['betas']);
            $message = aiClient()->beta->messages->create(...$request);
        }
    } catch (\Anthropic\Core\Exceptions\AuthenticationException $e) {
        writeLog("AI {$feature}: the API key was rejected (check security/ai.local.php).", 'ERROR');
        return ['success' => false, 'status' => 503, 'message' => 'The AI assistant is not available right now.'];
    } catch (\Anthropic\Core\Exceptions\PermissionDeniedException $e) {
        writeLog("AI {$feature}: permission denied - " . aiErrorDetail($e), 'ERROR');
        return ['success' => false, 'status' => 503, 'message' => 'The AI assistant is not available right now.'];
    } catch (\Anthropic\Core\Exceptions\RateLimitException $e) {
        writeLog("AI {$feature}: rate limited by the API.", 'WARN');
        return ['success' => false, 'status' => 429, 'message' => 'The AI assistant is busy. Please try again in a minute.'];
    } catch (\Anthropic\Core\Exceptions\APIStatusException $e) {
        $type = $e->type?->value ?? 'unknown';
        $detail = aiErrorDetail($e);
        writeLog("AI {$feature}: API error {$type} - " . $detail, 'ERROR');
        // Name the common setup problems so the site owner can fix them without reading the log.
        $message = match (true) {
            $type === 'billing_error' || (bool) preg_match('/credit balance|billing|purchase credits/i', $detail)
                => 'The AI assistant is paused: the Claude account has no credit (platform.claude.com > Billing).',
            $e->status === 404 || $type === 'not_found_error'
                => 'The AI model in security/ai.local.php is not available to this Claude account. Try claude-haiku-5-5.',
            $type === 'overloaded_error' || $e->status === 529
                => 'The AI assistant is busy right now. Please try again in a minute.',
            default => 'The AI assistant could not answer (' . $detail . ').',
        };
        return ['success' => false, 'status' => 502, 'message' => $message];
    } catch (\Throwable $e) {
        // Connection problems, timeouts, missing SDK classes ...
        writeLog("AI {$feature}: " . get_class($e) . ' - ' . substr(str_replace(["\r", "\n"], ' ', $e->getMessage()), 0, 300), 'ERROR');
        return ['success' => false, 'status' => 504, 'message' => 'The AI assistant took too long or could not be reached. Please try again.'];
    }

    if ($message->stopReason === 'refusal') {
        writeLog("AI {$feature}: request declined (" . ($message->stopDetails->category ?? 'no category') . ').', 'WARN');
        return ['success' => false, 'status' => 422, 'message' => 'The AI assistant cannot help with that request. Please rephrase it.'];
    }
    if ($message->stopReason === 'max_tokens') {
        writeLog("AI {$feature}: answer was cut off at maxTokens={$maxTokens}.", 'WARN');
        return ['success' => false, 'status' => 502, 'message' => 'The AI answer was too long. Please ask a shorter question.'];
    }

    $text = '';
    foreach ($message->content as $block) {
        if ($block->type === 'text') {
            $text .= $block->text;
        }
    }
    $data = json_decode($text, true);
    if (!is_array($data)) {
        writeLog("AI {$feature}: the answer was not valid JSON.", 'ERROR');
        return ['success' => false, 'status' => 502, 'message' => 'The AI assistant gave an unexpected answer. Please try again.'];
    }
    return ['success' => true, 'data' => $data];
}
