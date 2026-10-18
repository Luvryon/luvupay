<?php
/**
 * Merge into config/luvupay.php:
 *   'demo_simulator' => (bool) env('LUVUPAY_DEMO_SIMULATOR', false),
 *   'public_sandbox' => (bool) env('LUVUPAY_PUBLIC_SANDBOX', false),
 *
 * Public hackathon deploy:
 *   LUVUPAY_DEMO_SIMULATOR=true
 *   LUVUPAY_PUBLIC_SANDBOX=true
 *   APP_DEBUG=false
 */
return [
    'demo_simulator' => (bool) env('LUVUPAY_DEMO_SIMULATOR', false),
    'public_sandbox' => (bool) env('LUVUPAY_PUBLIC_SANDBOX', false),
];
