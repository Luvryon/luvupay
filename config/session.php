<?php
use Illuminate\Support\Str;
return [
    'driver' => env('SESSION_DRIVER', 'file'),
    'lifetime' => (int) env('SESSION_LIFETIME', 60), // shorter default; override via env if needed
    'expire_on_close' => env('SESSION_EXPIRE_ON_CLOSE', false),
    'encrypt' => env('SESSION_ENCRYPT', true),
    'files' => storage_path('framework/sessions'),
    'connection' => env('SESSION_CONNECTION'),
    'table' => env('SESSION_TABLE', 'sessions'),
    'store' => env('SESSION_STORE'),
    'lottery' => [2, 100],
    'cookie' => env('SESSION_COOKIE', Str::slug((string) env('APP_NAME', 'laravel')).'-session'),
    'path' => env('SESSION_PATH', '/'),
    'domain' => env('SESSION_DOMAIN'),
    'secure' => env('SESSION_SECURE_COOKIE', null), // null = auto (true when HTTPS)
    'http_only' => true, // always HttpOnly — not overridable via env
    'same_site' => env('SESSION_SAME_SITE', 'lax'), // use 'strict' in pure first-party setups
    'partitioned' => env('SESSION_PARTITIONED_COOKIE', false),
];
