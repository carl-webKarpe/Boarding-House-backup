<?php

declare(strict_types=1);

/**
 * AI assistant features (Claude API, official Anthropic PHP SDK).
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
const BH_AI_DEFAULT_MODEL = 'claude-opus-5-5';

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
        'api_key' => '',
        'model' => BH_AI_DEFAULT_MODEL,
        'enabled' => true,
        'per_user_per_hour' => 20,
        'per_day' => 300,
        'timeout_seconds' => 45,
        'base_url' => null, // only for local testing against a fake server
    ];
}

/** Why the AI is not available ('' when it is). */
function aiProblem(): string {
    $config = aiConfig();
    return match (true) {
        $config === null => 'security/ai.local.php is missing (copy security/ai.local.example.php).',
        !$config['enabled'] => "AI is turned off ('enabled' => false in security/ai.local.php).",
        !str_starts_with(trim((string) $config['api_key']), 'sk-ant-') => 'Put your Claude API key (starts with sk-ant-) in security/ai.local.php.',
        !is_file(BH_AI_AUTOLOAD) || !class_exists('Anthropic\\Client', true) && !aiLoadSdk() => 'The Anthropic SDK is not installed. Run "composer install" in the project folder.',
        default => '',
    };
}

function aiLoadSdk(): bool {
    if (is_file(BH_AI_AUTOLOAD)) {
        require_once BH_AI_AUTOLOAD;
    }
    return class_exists('Anthropic\\Client');
}

function aiReady(): bool {
    return aiProblem() === '';
}

function aiClient(): \Anthropic\Client {
    static $client = null;
    if ($client) {
        return $client;
    }
    aiLoadSdk();
    $config = aiConfig();
    $http = [
        'timeout' => (float) $config['timeout_seconds'],
        'connect_timeout' => 10,
    ];
    // XAMPP's PHP often has no certificate list: use the one installed by Composer.
    if (class_exists(\Composer\CaBundle\CaBundle::class)) {
        $http['verify'] = \Composer\CaBundle\CaBundle::getSystemCaRootBundlePath();
    }
    return $client = new \Anthropic\Client(
        apiKey: trim((string) $config['api_key']),
        baseUrl: $config['base_url'] ?: null,
        requestOptions: [
            'transporter' => new \GuzzleHttp\Client($http),
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

/** Wraps text written by users so the model treats it as data, not instructions. */
function aiUntrusted(string $label, string $text): string {
    $clean = str_replace(['<untrusted', '</untrusted'], ['‹untrusted', '‹/untrusted'], $text);
    return "<untrusted source=\"{$label}\">\n{$clean}\n</untrusted>";
}

/**
 * One request to Claude that must answer with JSON matching $schema.
 *
 * @param array<int, array{role: string, content: string}> $messages
 * @return array{success: bool, data?: array, message?: string, status?: int}
 */
function aiJson(string $feature, string $system, array $messages, array $schema, string $effort = 'low', int $maxTokens = 4000, ?string $cacheableContext = null): array {
    if (!aiReady()) {
        writeLog('AI not available: ' . aiProblem(), 'WARN');
        return ['success' => false, 'status' => 503, 'message' => 'The AI assistant is not set up on this server yet.'];
    }

    $config = aiConfig();
    $systemBlocks = [['type' => 'text', 'text' => $system]];
    if ($cacheableContext !== null) {
        // Large, rarely changing context (e.g. the room list) is cached between requests.
        $systemBlocks[] = ['type' => 'text', 'text' => $cacheableContext, 'cacheControl' => ['type' => 'ephemeral']];
    }

    try {
        $message = aiClient()->beta->messages->create(
            model: (string) $config['model'],
            maxTokens: $maxTokens,
            system: $systemBlocks,
            messages: $messages,
            outputConfig: [
                'effort' => $effort,
                'format' => ['type' => 'json_schema', 'schema' => $schema],
            ],
            // If the model declines for policy reasons, the API retries on its default fallback model.
            fallbacks: 'default',
            betas: ['server-side-fallback-2026-07-01'],
        );
    } catch (\Anthropic\Core\Exceptions\AuthenticationException $e) {
        writeLog("AI {$feature}: the API key was rejected (check security/ai.local.php).", 'ERROR');
        return ['success' => false, 'status' => 503, 'message' => 'The AI assistant is not available right now.'];
    } catch (\Anthropic\Core\Exceptions\PermissionDeniedException $e) {
        writeLog("AI {$feature}: permission denied - " . $e->getMessage(), 'ERROR');
        return ['success' => false, 'status' => 503, 'message' => 'The AI assistant is not available right now.'];
    } catch (\Anthropic\Core\Exceptions\RateLimitException $e) {
        writeLog("AI {$feature}: rate limited by the API.", 'WARN');
        return ['success' => false, 'status' => 429, 'message' => 'The AI assistant is busy. Please try again in a minute.'];
    } catch (\Anthropic\Core\Exceptions\APIStatusException $e) {
        $type = $e->type?->value ?? 'unknown';
        writeLog("AI {$feature}: API error {$type} - " . substr($e->getMessage(), 0, 300), 'ERROR');
        return ['success' => false, 'status' => 502, 'message' => $type === 'billing_error'
            ? 'The AI assistant is paused (the AI account has no credit).'
            : 'The AI assistant could not answer. Please try again.'];
    } catch (\Throwable $e) {
        // Connection problems, timeouts, missing SDK classes ...
        writeLog("AI {$feature}: " . get_class($e) . ' - ' . substr($e->getMessage(), 0, 300), 'ERROR');
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
