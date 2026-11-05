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

    // Renewal timeline of the NEXT subscription charge. Reference point is the expiry T of the current paid period
    // (subscriptions.current_period_end). The current period stays paid and in the wallet; only the next cycle's
    // charge (a separate ledger row) moves through these stages.
    //   before T : reminders (email + phone notification, written by Nebius AI, template fallback). The subscriber can already
    //              pay now, split the next payment or cancel the renewal; refund / resale / donation stay locked.
    //   T .. +1d : renewal payment failed, retries run, options still locked
    //   +1d .. +2d : ALL options open (retry, split, partial refund, resale, donate, expire)
    //   after +2d : window closed, the subscription ends
    'grace_stages' => [
        'd7' => ['label' => '7 days before expiry', 'hours' => 168, 'tone' => 'calm', 'reminder' => true, 'choices' => ['renew', 'split_pay', 'expire'],
            'message' => 'Hi {name}, your {product} renews in {time}. Your next payment of {amount} will be charged on {date}. Want to change or cancel? Do it before then.'],
        'd3' => ['label' => '3 days before expiry', 'hours' => 72, 'tone' => 'nudge', 'reminder' => true, 'choices' => ['renew', 'split_pay', 'expire'],
            'message' => '{name}, 3 days left. {amount} will be charged for your next {product} period on {date}. If you no longer need it, cancel now.'],
        'h48' => ['label' => '48 hours before expiry', 'hours' => 48, 'tone' => 'urgent', 'reminder' => true, 'choices' => ['renew', 'split_pay', 'expire'],
            'message' => '{name}, only {time} until your {product} renews and {amount} is charged. This is your 48 hour reminder.'],
        'h6' => ['label' => '6 hours before expiry', 'hours' => 6, 'tone' => 'critical', 'reminder' => true, 'choices' => ['renew', 'split_pay', 'expire'],
            'message' => 'Last reminder, {name}: {time} until {amount} is charged for your next {product} period.'],
        'lapsed' => ['label' => 'Expired, payment failed', 'hours' => -1, 'tone' => 'critical', 'reminder' => true, 'choices' => [],
            'message' => '{name}, your {product} renewal payment of {amount} did not go through. We keep retrying; your recovery options unlock {time} from now.'],
        'grace_open' => ['label' => '+1 day after expiry: all options open', 'hours' => -25, 'tone' => 'urgent', 'reminder' => true,
            'choices' => ['renew', 'split_pay', 'partial_refund', 'donate', 'resale', 'expire'],
            'message' => '{name}, you can now recover your {product} renewal of {amount}: retry the payment, split it, take a partial refund, resell the access, donate, or let it expire. {time} left to choose.'],
        'expired' => ['label' => 'Window closed', 'hours' => -49, 'tone' => 'closed', 'reminder' => false, 'choices' => ['expire'],
            'message' => 'The window closed. The subscription ended and the {amount} renewal was voided.'],
    ],
    // How long after expiry the options stay open (hours after T). Options unlock at +unlock_after_hours.
    'renewal' => [
        'window_hours' => 48,
        'unlock_after_hours' => 24,
        'reminder_email' => (bool) env('LUVUPAY_REMINDER_EMAIL', true),
        'reminder_max_per_hour' => (int) env('LUVUPAY_REMINDER_MAX_PER_HOUR', 30),
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

    // AI Smart-Convert (hero flow). See docs/AI_NEGOTIATION_EVIDENCE.md
    'smart_convert' => [
        // strict = if the live model is unavailable the run FAILS LOUDLY (HTTP 502) instead of silently using a canned split.
        // Set SMART_CONVERT_STRICT=false only for offline development; fallback runs are always labelled on every surface.
        'strict' => filter_var(env('SMART_CONVERT_STRICT', true), FILTER_VALIDATE_BOOLEAN),
        // auto = AI decides and PayPal executes at once; propose = merchant approves (and may lower charity) before PayPal moves money
        'default_mode' => env('SMART_CONVERT_MODE', 'auto'),
        // Guardrails applied to whatever the model proposes (basis points of the unused value)
        'max_fee_bps' => (int) env('SMART_CONVERT_MAX_FEE_BPS', 1500),
        'max_charity_bps' => (int) env('SMART_CONVERT_MAX_CHARITY_BPS', 5000),
        // Ask the endpoint to emit its native reasoning trace (slower, needs a larger NEBIUS_MAX_TOKENS). Rationale steps are always requested.
        'request_reasoning' => filter_var(env('SMART_CONVERT_REQUEST_REASONING', false), FILTER_VALIDATE_BOOLEAN),
        // Confirm the batch with GET /v1/payments/payouts/{id} after creating it
        'verify_batch' => filter_var(env('SMART_CONVERT_VERIFY_BATCH', true), FILTER_VALIDATE_BOOLEAN),
        // The donation step is an EXAMPLE: the recipient is illustrative and PayPal Sandbox only moves test money.
        // Every surface (dashboard, evidence page, JSON) labels it that way while SMART_CONVERT_CHARITY_EXAMPLE=true.
        'charity_example' => filter_var(env('SMART_CONVERT_CHARITY_EXAMPLE', true), FILTER_VALIDATE_BOOLEAN),
        'charity_label' => env('SMART_CONVERT_CHARITY_LABEL', 'Example Charity (sandbox)'),
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
        // After a sale the seller is paid automatically via PayPal Payouts (SettleMarketplaceSaleJob).
        'auto_payout' => filter_var(env('MARKETPLACE_AUTO_PAYOUT', true), FILTER_VALIDATE_BOOLEAN),
        // Money only moves to an address whose owner clicked the signed link. Unverified sellers get the link by email and the payout is held until they do.
        'require_verified_for_payout' => filter_var(env('MARKETPLACE_REQUIRE_VERIFIED_FOR_PAYOUT', true), FILTER_VALIDATE_BOOLEAN),
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
