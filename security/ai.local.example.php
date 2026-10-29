<?php

/**
 * AI assistant settings (Claude API). Copy this file to  security/ai.local.php
 * (that file is ignored by Git, so your key is never uploaded to GitHub).
 *
 * 1. Create an account at https://platform.claude.com and add credit (Billing).
 * 2. API keys -> Create key -> copy it (starts with sk-ant-).
 * 3. Paste it below. The AI buttons appear on the site as soon as this file exists.
 *
 * To turn the AI off without deleting the key, set 'enabled' => false.
 */

return [
    // Your Claude API key (starts with sk-ant-)
    'api_key' => 'sk-ant-your-key-here',

    // Claude model. claude-opus-5-5 gives the best answers; claude-haiku-5-5 is much
    // cheaper and faster if you want to keep costs very low.
    'model' => 'claude-opus-5-5',

    'enabled' => true,

    // Limits that keep the bill predictable
    'per_user_per_hour' => 20,   // AI requests per person per feature per hour
    'per_day' => 300,            // AI requests for the whole site per day

    // Seconds to wait for an answer (free hosts may stop slower requests)
    'timeout_seconds' => 45,
];
