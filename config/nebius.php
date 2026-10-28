<?php

return [
    // Nebius Token Factory (OpenAI-compatible). Region endpoint comes from the Token Factory console
    'api_key' => env('NEBIUS_API_KEY', ''),
    'base_url' => env('NEBIUS_BASE_URL', 'https://api.tokenfactory.eu-north1.nebius.com/v1'),
    'model' => env('NEBIUS_MODEL', 'nvidia/NVIDIA-Nemotron-3-Nano-30B-A3B'),

    'timeout' => (int) env('NEBIUS_TIMEOUT', 45),
    'retries' => (int) env('NEBIUS_RETRIES', 3),
    'retry_ms' => [250, 750, 2000],
    'temperature' => (float) env('NEBIUS_TEMPERATURE', 0.2),

    // Room for the model's reasoning plus the answer. Too small a value truncates the JSON
    'max_tokens' => (int) env('NEBIUS_MAX_TOKENS', 2048),
    // Ask JSON mode and skip the long reasoning trace. If the endpoint rejects either, the client retries with a plain request
    'json_mode' => (bool) env('NEBIUS_JSON_MODE', true),
    'disable_thinking' => (bool) env('NEBIUS_DISABLE_THINKING', true),

    'cache_ttl_seconds' => (int) env('NEBIUS_CACHE_TTL', 86400), // 24h
    'cache_store' => env('NEBIUS_CACHE_STORE'), // null = default cache store
    'enabled' => (bool) env('NEBIUS_ENABLED', true),
];
