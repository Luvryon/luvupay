<?php

/**
 * Merge these keys into config/luvupay.php.
 *
 * Public hackathon demo (still strict):
 *   APP_DEBUG=false
 *   APP_ENV=production
 *   LUVUPAY_DEMO_SIMULATOR=true
 *   LUVUPAY_PUBLIC_SANDBOX=true
 *   LUVUPAY_ALLOW_SESSION_PAYPAL_FALLBACK=false
 */
return [
    // Explicit opt-in (default OFF even outside production for safer deploys)
    'demo_simulator' => (bool) env('LUVUPAY_DEMO_SIMULATOR', false),
    'public_sandbox' => (bool) env('LUVUPAY_PUBLIC_SANDBOX', false),
    // Production must use DB columns only
    'allow_session_paypal_fallback' => (bool) env('LUVUPAY_ALLOW_SESSION_PAYPAL_FALLBACK', false),
];
