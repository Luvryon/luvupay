<?php

return [
    // In PayPal sandbox, a rejected payout is recorded as a labelled simulation instead of an HTTP 502.
    'payout_sandbox_fallback' => env('LUVUPAY_PAYOUT_SANDBOX_FALLBACK', true),

    'payment' => ['driver' => env('LUVUPAY_PAYMENT_DRIVER', 'mock')],   // mock | paypal (sandbox or live per PAYPAL_MODE)

    // Platform defaults; each merchant can override in the merchants table. Basis points, sum = 10000.
    'defaults' => [
        'split_user_bps' => 3000, 'split_charity_bps' => 2000, 'split_merchant_bps' => 5000,
        'second_chance_window_hours' => 48, 'nudge_lead_hours' => 72, 'serial_forgetter_threshold' => 2,
    ],

    'second_chance' => [
        // The very first charge of a subscription is a deliberate purchase, not a "forgot to cancel" charge.
        'on_first_charge' => false,
    ],

    'resale' => [
        // How the BUYER'S payment is split. ASSUMPTION (confirm!): seller receives 100%, merchant keeps the original charge.
        'seller_bps' => 9000, 'merchant_bps' => 0,
        'min_price_minor' => 100,
        'reservation_minutes' => 15,
    ],

    // The merchant's "project" the sandbox dashboard operates on. Single source of truth for plan name/price in demos;
    // clients may never choose the product or the amount (see DemoCheckoutController).
    'demo_project' => [
        'name' => env('DEMO_PROJECT_NAME', 'StreamNetFly'),
        'plan' => env('DEMO_PROJECT_PLAN', 'Premium'),
        'price_minor' => (int) env('DEMO_PROJECT_PRICE_MINOR', 2000),
        'currency' => env('DEMO_PROJECT_CURRENCY', 'USD'),
        'interval' => 'month',
        'active_subscribers' => (int) env('DEMO_PROJECT_SUBSCRIBERS', 0),
    ],

    'marketplace' => [
        'reservation_minutes' => (int) env('MARKETPLACE_RESERVATION_MINUTES', 15),
        // Seller must click a signed link emailed to them before the listing is public.
        // Defaults to ON in production, OFF elsewhere so the local demo shows the card immediately.
        'require_seller_verification' => (bool) env('MARKETPLACE_REQUIRE_SELLER_VERIFICATION', env('APP_ENV') === 'production'),
        'verification_ttl_hours' => 24,
        'currencies' => ['USD'],
        // Share of the buyer's payment owed to the seller; the rest is the platform fee. Reuses the resale split.
        'seller_bps' => (int) env('MARKETPLACE_SELLER_BPS', 9000),
    ],

    'webhooks' => [
        'timestamp_tolerance_seconds' => 300,
        'nonce_ttl_seconds' => 600,
        'nonce_store' => env('LUVUPAY_NONCE_CACHE_STORE'),
        // LOCAL/TESTING ONLY: lets entitlement webhooks target http:// and private IPs. Ignored in production.
        'allow_insecure_targets' => env('LUVUPAY_ALLOW_INSECURE_WEBHOOK_TARGETS', false),
    ],

    'idempotency' => ['ttl_seconds' => 86400, 'lock_seconds' => 30, 'max_cached_bytes' => 524288, 'store' => env('LUVUPAY_IDEMPOTENCY_CACHE_STORE')],
    'audit' => ['key' => env('AUDIT_HMAC_KEY')],
    // Console roles. "*" = everything. Keep least-privilege: add permissions here, never widen a role ad hoc.
    'rbac' => [
        'owner' => ['*'],
        'admin' => ['settings.read', 'analytics.read', 'exports.read', 'keys.rotate', 'webhooks.write', 'payouts.write', 'team.manage'],
        'finance' => ['settings.read', 'analytics.read', 'exports.read', 'payouts.write'],
        'developer' => ['settings.read', 'analytics.read', 'webhooks.write'],
        'viewer' => ['settings.read', 'analytics.read'],
    ],

    'security' => [
        // Owners/admins/finance must enrol in TOTP before using the console. Turn on for staging/production.
        'require_mfa' => (bool) env('LUVUPAY_REQUIRE_MFA', false),
        'default_rate_limit_per_minute' => (int) env('LUVUPAY_API_RATE_LIMIT', 300),
        'default_api_monthly_limit' => (int) env('LUVUPAY_API_MONTHLY_LIMIT', 1000),
        'invite_ttl_hours' => 72,
    ],

    'observability' => ['metrics_token' => env('METRICS_TOKEN')],

    // Hackathon / local demo only. Never enable in production.
    'demo_simulator' => filter_var(env('LUVUPAY_DEMO_SIMULATOR', true), FILTER_VALIDATE_BOOLEAN),

    'queues' => ['default' => 'default', 'financial' => 'financial', 'webhooks' => 'webhooks'],
];
