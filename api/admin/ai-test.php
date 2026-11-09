<?php

declare(strict_types=1);

/**
 * AI connection test (admins only). Open in the browser while logged in as admin:
 *
 *   GET api/admin/ai-test.php
 *     -> { provider, model, reach: {...}, models: [...], answer: {...} }
 *
 * For Gemini it checks, with short time limits, (1) that this server can reach
 * Google at all and which models the key may use, and (2) how long one tiny
 * answer from the configured model takes. The key itself is never shown.
 */

require_once __DIR__ . '/_admin.php';
require_once __DIR__ . '/../../security/ai.php';

requireMethod('GET');

$config = aiConfig();
if (!aiReady()) {
    jsonResponse(['ready' => false, 'problem' => aiProblem()]);
}
if (!aiUsesGemini()) {
    jsonResponse(['ready' => true, 'provider' => 'claude', 'note' => 'This test is for Gemini. Set provider to gemini in security/ai.local.php to use it.']);
}
if (!aiLoadSdk('GuzzleHttp\\Client')) {
    jsonResponse(['ready' => false, 'problem' => 'Guzzle could not be loaded: re-upload security/vendor.']);
}

$base = rtrim((string) ($config['base_url'] ?: BH_AI_GEMINI_URL), '/');
$model = trim((string) $config['gemini_model']) ?: BH_AI_DEFAULT_GEMINI_MODEL;
$headers = ['x-goog-api-key' => trim((string) $config['gemini_api_key'])];

/** Runs one request with its own time limit and reports status, time and the decoded body. */
$timed = static function (string $method, string $url, array $options, float $timeout) use ($headers): array {
    $http = new \GuzzleHttp\Client(['timeout' => $timeout, 'connect_timeout' => 8, 'http_errors' => false] + array_diff_key(aiHttpOptions(), ['timeout' => 1, 'connect_timeout' => 1]));
    $start = microtime(true);
    try {
        $response = $http->request($method, $url, $options + ['headers' => $headers]);
        $body = json_decode((string) $response->getBody(), true);
        return ['ok' => $response->getStatusCode() === 200, 'http' => $response->getStatusCode(), 'seconds' => round(microtime(true) - $start, 2), 'body' => is_array($body) ? $body : []];
    } catch (\Throwable $e) {
        return ['ok' => false, 'http' => null, 'seconds' => round(microtime(true) - $start, 2), 'error' => substr(str_replace(["\r", "\n"], ' ', $e->getMessage()), 0, 300), 'body' => []];
    }
};

// 1. Can this server reach Google, and which models may the key use?
$list = $timed('GET', $base . '/v1beta/models?pageSize=200', [], 15);
$models = [];
foreach ($list['body']['models'] ?? [] as $m) {
    if (in_array('generateContent', $m['supportedGenerationMethods'] ?? [], true) && str_contains((string) ($m['name'] ?? ''), 'flash')) {
        $models[] = substr((string) $m['name'], strlen('models/'));
    }
}
$reach = ['ok' => $list['ok'], 'http' => $list['http'], 'seconds' => $list['seconds']]
    + (isset($list['error']) ? ['error' => $list['error']] : [])
    + (!$list['ok'] && isset($list['body']['error']['message']) ? ['error' => $list['body']['error']['message']] : []);

// 2. One tiny answer from the configured model.
$answer = ['skipped' => 'Google could not be reached.'];
if ($list['http'] !== null) {
    $gen = $timed('POST', $base . '/v1beta/models/' . rawurlencode($model) . ':generateContent', [
        'json' => ['contents' => [['role' => 'user', 'parts' => [['text' => 'Reply with the single word OK.']]]], 'generationConfig' => ['maxOutputTokens' => 1024]],
    ], 40);
    $text = '';
    foreach ($gen['body']['candidates'][0]['content']['parts'] ?? [] as $part) {
        if (empty($part['thought'])) {
            $text .= (string) ($part['text'] ?? '');
        }
    }
    $answer = ['ok' => $gen['ok'] && $text !== '', 'http' => $gen['http'], 'seconds' => $gen['seconds'], 'text' => trim(substr($text, 0, 80))]
        + (isset($gen['error']) ? ['error' => $gen['error']] : [])
        + (!$gen['ok'] && isset($gen['body']['error']['message']) ? ['error' => $gen['body']['error']['message']] : []);
}

$verdict = match (true) {
    !$reach['ok'] && $reach['http'] === null => 'This server cannot reach Google (blocked or very slow network). Gemini cannot work on this host.',
    !$reach['ok'] => 'Google answered with an error: check the Gemini API key.',
    !$answer['ok'] && ($answer['http'] ?? null) === 404 => "The model '{$model}' is not available: put one of the listed models in 'gemini_model'.",
    !$answer['ok'] && ($answer['http'] ?? null) === null => "Google is reachable, but '{$model}' did not answer in time: try a lite model from the list.",
    !$answer['ok'] => 'The model answered with an error (see answer.error).',
    default => 'Everything works.',
};

jsonResponse(['ready' => true, 'provider' => 'gemini', 'model' => $model, 'verdict' => $verdict, 'reach' => $reach, 'models' => $models, 'answer' => $answer]);
