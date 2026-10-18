<?php

namespace App\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Redis;
use Throwable;

/**
 * Detailed health checks are not public.
 * GET /internal/health requires Bearer METRICS_TOKEN (same as /internal/metrics).
 * Returns 404 when METRICS_TOKEN is unset.
 */
final class HealthController
{
    public function __invoke(Request $request): JsonResponse
    {
        $token = (string) config('luvupay.observability.metrics_token');
        abort_if($token === '', 404);
        abort_unless(hash_equals($token, (string) $request->bearerToken()), 401);

        $checks = [
            'database' => $this->checkDatabase(),
            'cache' => $this->checkCache(),
            'redis' => $this->checkRedis(),
            'queue' => $this->checkQueue(),
            'payment_provider' => $this->checkPaymentProvider(),
        ];

        $ok = collect($checks)->every(fn ($c) => ($c['status'] ?? '') === 'ok' || ($c['status'] ?? '') === 'skipped');

        return response()->json([
            'service' => 'luvupay-api',
            'status' => $ok ? 'healthy' : 'degraded',
            'timestamp' => now()->toIso8601String(),
            'checks' => $checks,
        ], $ok ? 200 : 503);
    }

    private function checkDatabase(): array
    {
        try {
            $ms = $this->timed(fn () => DB::select('select 1'));
            return ['status' => 'ok', 'driver' => DB::getDriverName(), 'latency_ms' => $ms];
        } catch (Throwable $e) {
            return ['status' => 'fail', 'error' => $e->getMessage()];
        }
    }

    private function checkCache(): array
    {
        try {
            $key = 'health:'.uniqid('', true);
            $ms = $this->timed(function () use ($key) {
                Cache::put($key, 1, 10);
                Cache::forget($key);
            });
            return ['status' => 'ok', 'store' => config('cache.default'), 'latency_ms' => $ms];
        } catch (Throwable $e) {
            return ['status' => 'fail', 'error' => $e->getMessage()];
        }
    }

    private function checkRedis(): array
    {
        if (! in_array(config('cache.default'), ['redis'], true)
            && config('queue.default') !== 'redis'
            && config('session.driver') !== 'redis') {
            return ['status' => 'skipped', 'reason' => 'Redis not configured for cache/queue/session'];
        }

        try {
            $ms = $this->timed(fn () => Redis::connection()->ping());
            return ['status' => 'ok', 'latency_ms' => $ms];
        } catch (Throwable $e) {
            return ['status' => 'fail', 'error' => $e->getMessage()];
        }
    }

    private function checkQueue(): array
    {
        $driver = config('queue.default');
        if ($driver === 'sync') {
            return ['status' => 'ok', 'driver' => 'sync', 'note' => 'Jobs run inline (dev mode)'];
        }

        try {
            // Presence of jobs table / connection is enough for a shallow check
            if ($driver === 'database') {
                DB::table('jobs')->limit(1)->count();
            }
            return ['status' => 'ok', 'driver' => $driver];
        } catch (Throwable $e) {
            return ['status' => 'fail', 'driver' => $driver, 'error' => $e->getMessage()];
        }
    }

    private function checkPaymentProvider(): array
    {
        $driver = config('luvupay.payment.driver', 'mock');
        if ($driver === 'mock') {
            return ['status' => 'ok', 'driver' => 'mock'];
        }

        if ($driver === 'paypal') {
            $base = config('services.paypal.mode') === 'live'
                ? 'https://api-m.paypal.com'
                : 'https://api-m.sandbox.paypal.com';
            try {
                $ms = $this->timed(function () use ($base) {
                    Http::timeout(3)->get($base);
                });
                return ['status' => 'ok', 'driver' => 'paypal', 'latency_ms' => $ms];
            } catch (Throwable $e) {
                return ['status' => 'fail', 'driver' => 'paypal', 'error' => $e->getMessage()];
            }
        }

        return ['status' => 'skipped', 'driver' => $driver];
    }

    private function timed(callable $fn): float
    {
        $t0 = microtime(true);
        $fn();
        return round((microtime(true) - $t0) * 1000, 2);
    }
}

