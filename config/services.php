<?php

return [
    'postmark' => ['token' => env('POSTMARK_TOKEN')],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'paypal' => [
        'mode' => env('PAYPAL_MODE', 'sandbox'),            // sandbox | live
        'client_id' => env('PAYPAL_CLIENT_ID'),
        'secret' => env('PAYPAL_SECRET'),
        'webhook_id' => env('PAYPAL_WEBHOOK_ID'),
        'plan_id' => env('PAYPAL_PLAN_ID'),                 // optional: force one pre-made sandbox plan (must match the project price)           // from the PayPal developer dashboard, per environment
        /*
         | Environment-swap receivers (NEVER hardcode sb-*** emails in PHP/Blade).
         | Sandbox: ugly auto-generated Business accounts from developer.paypal.com
         | Production: swap to real merchant@company.com / charity@redcross.org — zero code change.
         */
        'merchant_payout_email' => env('PAYPAL_MERCHANT_SANDBOX_EMAIL'),
        'charity_payout_email' => env('PAYPAL_CHARITY_SANDBOX_EMAIL'),
        'timeout' => (float) env('PAYPAL_TIMEOUT', 15),
        'connect_timeout' => (float) env('PAYPAL_CONNECT_TIMEOUT', 5),
        'retry_delays_ms' => [250, 750, 2000],              // 3 retries with backoff
        'log_channel' => env('PAYPAL_LOG_CHANNEL'),         // null = default channel
    ],
];
