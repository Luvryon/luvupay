<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

/**
 * Demo simulator on public hosts: explicit env + auth + PayPal DB link + rate limit.
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

        if ($user && ! EnsurePayPalConnected::isConnected($request)) {
            abort(403, 'Connect PayPal before running demo simulations.');
        }

        $key = 'demo-sim:'.($merchantId ?: $request->ip());
        if (RateLimiter::tooManyAttempts($key, 60)) {
            return response()->json(['error' => 'rate_limited', 'message' => 'Demo simulator rate limit exceeded.'], 429);
        }
        RateLimiter::hit($key, 60);

        Log::info('demo.simulator.hit', [
            'path' => $request->path(),
            'ip' => $request->ip(),
            'merchant_id' => $merchantId,
            'user_id' => $user?->getAuthIdentifier(),
        ]);

        return $next($request);
    }
}
