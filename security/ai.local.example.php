<?php

/**
 * AI assistant settings. Copy this file to  security/ai.local.php
 * (that file is ignored by Git, so your keys are never uploaded to GitHub).
 *
 * Choose ONE provider below with 'provider'. You can keep both keys in this
 * file and switch any time by changing that one line.
 *
 * FREE: Google Gemini
 *   1. Go to https://aistudio.google.com and sign in with a Google account.
 *   2. Click "Get API key" -> "Create API key" -> copy it.
 *   3. Paste it in 'gemini_api_key' and set 'provider' => 'gemini'.
 *   Note: on the free tier Google may use what is sent to improve its products,
 *   and there is a daily limit.
 *
 * PAID: Claude (needs credit on the account)
 *   1. https://platform.claude.com -> Billing (add credit) -> API keys -> Create key.
 *   2. Paste it in 'api_key' and set 'provider' => 'claude'.
 *
 * The AI buttons appear on the site as soon as this file has a key for the chosen
 * provider. To turn the AI off without deleting the keys, set 'enabled' => false.
 */

return [
    // 'gemini' (free tier) or 'claude' (paid)
    'provider' => 'gemini',

    // Google Gemini API key (from aistudio.google.com)
    'gemini_api_key' => 'your-gemini-key-here',
    // gemini-flash-latest always points to Google's current Flash model.
    'gemini_model' => 'gemini-flash-latest',

    // Claude API key (starts with sk-ant-)
    'api_key' => 'sk-ant-your-key-here',
    // claude-opus-5-5 gives the best answers; claude-haiku-5-5 is much cheaper and faster.
    'model' => 'claude-opus-5-5',

    'enabled' => true,

    // Limits that keep the bill (or the free quota) predictable
    'per_user_per_hour' => 20,   // AI requests per person per feature per hour
    'per_day' => 300,            // AI requests for the whole site per day

    // Seconds to wait for an answer (free hosts may stop slower requests)
    'timeout_seconds' => 45,
];
