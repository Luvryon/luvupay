<?php

return [
    // PayPal charges the sender of a payout; LuvuPay takes that fee from the MERCHANT wallet (never the platform balance).
    'payout_fee' => [
        'bps' => (int) env('LUVUPAY_PAYOUT_FEE_BPS', 200),
        'fixed_minor' => (int) env('LUVUPAY_PAYOUT_FEE_FIXED_MINOR', 0),
        'cap_minor' => (int) env('LUVUPAY_PAYOUT_FEE_CAP_MINOR', 0),
    ],
    // In PayPal sandbox, a rejected payout is recorded as a labelled simulation instead of an HTTP 502.
    'payout_sandbox_fallback' => env('LUVUPAY_PAYOUT_SANDBOX_FALLBACK', true),

    'payment' => ['driver' => env('LUVUPAY_PAYMENT_DRIVER', 'mock')],

    // Dashboard "New subscription checkout" with the paypal driver:
    //   subscription = PayPal Billing Subscriptions (approve once, PayPal bills every period) [default]
    //   order        = one-off Orders API payment (fallback when the Subscriptions API is unavailable)
    'checkout' => ['mode' => env('LUVUPAY_CHECKOUT_MODE', 'subscription')],   // mock | paypal (sandbox or live per PAYPAL_MODE)

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
        // How the BUYER'S payment is split: seller gets seller_bps, the rest is the LuvuPay platform fee.
        // The merchant keeps the original cycle charge and is NOT paid from the buyer's payment.
        'seller_bps' => 9000, 'merchant_bps' => 0,
        'min_price_minor' => 100,
        'reservation_minutes' => 15,
        // Asking price = original charge x (remaining days / period days) x this share (so unused time is never sold above value).
        'price_bps_of_remaining' => (int) env('LUVUPAY_RESALE_PRICE_BPS', 8000),
        'period_days' => 30,
    ],

    // Missed-cancellation timeline. The stage is derived from the hours left before the grace window closes;
    // it decides which options exist (enforced server-side) and what the subscriber is told.
    'grace_stages' => [
        'd7' => ['label' => '7 days left', 'hours' => 168, 'tone' => 'calm',
            'choices' => ['renew', 'split_pay', 'expire'],
            'message' => 'Friendly reminder, {name}: your {amount} charge is still unpaid and you have 7 days. Pay now, or spread it over two payments. No pressure.'],
        'd3' => ['label' => '3 days left', 'hours' => 72, 'tone' => 'nudge',
            'choices' => ['renew', 'split_pay', 'partial_refund', 'donate', 'resale', 'expire'],
            'message' => '{name}, 3 days left on your {amount} charge. You can still pay, split it, take a partial refund, donate, or resell the unused time.'],
        'h48' => ['label' => '48 hours left', 'hours' => 48, 'tone' => 'urgent',
            'choices' => ['renew', 'split_pay', 'partial_refund', 'donate', 'resale', 'expire'],
            'message' => '{name}, only {time} left to decide about your {amount} charge. After that the subscription ends.'],
        'h6' => ['label' => '6 hours left', 'hours' => 6, 'tone' => 'critical',
            'choices' => ['renew', 'split_pay', 'expire'],
            'message' => 'Last chance, {name}: {time} left. Resale and refund are closed. Pay {amount} now or split it to keep your access.'],
        'expired' => ['label' => 'Window closed', 'hours' => 0, 'tone' => 'closed',
            'choices' => ['expire'],
            'message' => 'The grace window closed. The subscription ended and the {amount} charge was voided.'],
    ],

    // Split pay: a subscriber who cannot pay the whole charge pays a down payment now and the rest in instalments.
    'split_pay' => [
        'parts' => (int) env('LUVUPAY_SPLIT_PARTS', 2),                       // 2..4
        'down_payment_bps' => (int) env('LUVUPAY_SPLIT_DOWN_BPS', 5000),      // share collected immediately
        'interval_days' => (int) env('LUVUPAY_SPLIT_INTERVAL_DAYS', 7),       // spacing between instalments
        'default_after_days' => (int) env('LUVUPAY_SPLIT_DEFAULT_AFTER_DAYS', 3), // overdue this long => plan defaults, access ends
        'min_total_minor' => 200,
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
        'admin' => ['settings.read', 'analytics.read', 'exports.read', 'keys.rotate', 'webhooks.write', 'payouts.write', 'billing.write', 'team.manage'],
        'finance' => ['settings.read', 'analytics.read', 'exports.read', 'payouts.write', 'billing.write'],
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
