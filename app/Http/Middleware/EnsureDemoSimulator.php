<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

/**
 * Demo simulator (hackathon / local).
 *
 * Requires: LUVUPAY_DEMO_SIMULATOR=true (or non-production default).
 * PayPal link is required only when the payment driver is "paypal".
 * With LUVUPAY_PAYMENT_DRIVER=mock, simulations run against the DB without PayPal.
 */
final class EnsureDemoSimulator
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('luvupay.demo_simulator')) {
            abort(404);
        }

        $user = $request->user('merchant_web');
        $merchantId = $request->attributes->get('merchant_id')
            ?: ($user->merchant_id ?? null);

        if (! $merchantId && ! $user) {
            abort(401, 'Authentication required for demo simulator.');
        }

        $driver = (string) (config('luvupay.payment.driver') ?: env('LUVUPAY_PAYMENT_DRIVER', 'mock'));
        $paypalRequired = $driver === 'paypal';

        if ($paypalRequired && $user && ! EnsurePayPalConnected::isConnected($request)) {
            return response()->json([
                'ok' => false,
                'error' => 'paypal_not_connected',
                'message' => 'Connect PayPal in Settings (Merchant ID is enough) before running Sandbox simulations. Or set LUVUPAY_PAYMENT_DRIVER=mock in .env for offline demo.',
            ], 403);
        }

        $key = 'demo-sim:'.($merchantId ?: $request->ip());
        if (RateLimiter::tooManyAttempts($key, 120)) {
            return response()->json(['error' => 'rate_limited', 'message' => 'Demo simulator rate limit exceeded.'], 429);
        }
        RateLimiter::hit($key, 60);

        Log::info('demo.simulator.hit', [
            'path' => $request->path(),
            'ip' => $request->ip(),
            'merchant_id' => $merchantId,
            'user_id' => $user?->getAuthIdentifier(),
            'payment_driver' => $driver,
        ]);

        return $next($request);
    }
}
