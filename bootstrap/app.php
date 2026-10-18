<?php

use App\Http\Middleware\AssignRequestId;
use App\Http\Middleware\AuthenticateMerchant;
use App\Http\Middleware\AuthenticateMerchantWeb;
use App\Http\Middleware\CaptureAuditContext;
use App\Http\Middleware\EnsureIdempotency;
use App\Http\Middleware\RequireMfa;
use App\Http\Middleware\RequireRole;
use App\Http\Middleware\RequireScope;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\VerifyMerchantSignature;
use App\Http\Middleware\EnsureDemoSimulator;
use App\Http\Middleware\TrackMerchantApiUsage;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            // API routes (merchant-authenticated + public badge + PayPal webhooks)
            Route::middleware('api')
                ->prefix('api')
                ->group(base_path('routes/luvupay-api.php'));

            Route::middleware('api')
                ->prefix('api')
                ->group(base_path('routes/paypal.php'));

            // Public signed-URL pages (Second Chance + Adaptive Nudge)
            Route::middleware('web')
                ->group(base_path('routes/luvupay-public.php'));

            // Merchant dashboard (web + JSON under same origin)
            Route::middleware('web')
                ->group(base_path('routes/dashboard.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Behind a load balancer: TRUSTED_PROXIES="10.0.0.0/8,172.16.0.0/12" (or "*" only if the LB strips spoofed headers).
        $proxies = trim((string) env('TRUSTED_PROXIES', ''));
        if ($proxies !== '') {
            $middleware->trustProxies(at: $proxies === '*' ? '*' : array_map('trim', explode(',', $proxies)));
        }

        // Every response: correlation id + security headers.
        $middleware->append([AssignRequestId::class, SecurityHeaders::class]);

        // Global for every request that hits the API stack
        $middleware->api(prepend: [
            CaptureAuditContext::class,
        ]);

        $middleware->alias([
            'merchant.auth' => AuthenticateMerchant::class,
            'merchant.web'  => AuthenticateMerchantWeb::class,
            'idempotent'    => EnsureIdempotency::class,
            'signed.merchant' => VerifyMerchantSignature::class,
            'audit'         => CaptureAuditContext::class,
            'scope'         => RequireScope::class,
            'merchant.can'  => RequireRole::class,
            'merchant.mfa'  => RequireMfa::class,
            'demo.simulator' => EnsureDemoSimulator::class,
            'paypal.connected' => \App\Http\Middleware\EnsurePayPalConnected::class,
            'api.usage' => TrackMerchantApiUsage::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
