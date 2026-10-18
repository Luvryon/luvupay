<?php

return [
    'defaults' => [
        'guard' => 'merchant_web',
        'passwords' => 'merchant_users',
    ],
    'guards' => [
        'web' => [
            'driver' => 'session',
            'provider' => 'users',
        ],
        'merchant_web' => [
            'driver' => 'session',
            'provider' => 'merchant_users',
        ],
    ],
    'providers' => [
        'users' => [
            'driver' => 'eloquent',
            'model' => env('AUTH_MODEL', App\Models\User::class),
        ],
        'merchant_users' => [
            'driver' => 'eloquent',
            'model' => App\Models\MerchantUser::class,
        ],
    ],
    'passwords' => [
        'merchant_users' => [
            'provider' => 'merchant_users',
            'table' => env('AUTH_PASSWORD_RESET_TOKEN_TABLE', 'password_reset_tokens'),
            'expire' => 60,
            'throttle' => 60,
        ],
    ],
    'password_timeout' => env('AUTH_PASSWORD_TIMEOUT', 10800),
];
