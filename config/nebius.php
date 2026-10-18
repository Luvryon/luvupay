<?php

return [
    'api_key' => env('NEBIUS_API_KEY', ''),
    'base_url' => env('NEBIUS_BASE_URL', 'https://api.nebius.com/v1'),
    'model' => env('NEBIUS_MODEL', 'meta-llama/Llama-3.1-70b-instruct'),
    'timeout' => (int) env('NEBIUS_TIMEOUT', 30),
    'retries' => (int) env('NEBIUS_RETRIES', 3),
    'retry_ms' => [250, 750, 2000],
    'cache_ttl_seconds' => (int) env('NEBIUS_CACHE_TTL', 86400), // 24h
    'cache_store' => env('NEBIUS_CACHE_STORE'), // null = default cache store
    'enabled' => env('NEBIUS_ENABLED', true),
];
