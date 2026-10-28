<?php

namespace App\Http\Middleware;

use App\Services\Usage\ApiUsageMeter;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Increments per-merchant monthly API usage and returns 429 when limit exceeded.
 * Attach after merchant.auth so merchant_id is available.
 */
final class TrackMerchantApiUsage
{
    public function __construct(private ApiUsageMeter $meter) {}

    public function handle(Request $request, Closure $next): Response
    {
        $merchantId = $request->attributes->get('merchant_id');
        if (! $merchantId && $request->user('merchant_web')) {
            $merchantId = $request->user('merchant_web')->merchant_id;
        }

        if (! $merchantId) {
            return $next($request);
        }

        $merchantId = (string) $merchantId;

        if ($this->meter->exceeded($merchantId)) {
            $retryAfter = now()->endOfMonth()->diffInSeconds(now());

            return response()->json([
                'message' => 'API rate limit exceeded for this billing period',
                'code' => 'rate_limit_exceeded',
                'usage' => $this->meter->snapshot($merchantId),
            ], 429, [
                'Retry-After' => (string) max(60, (int) $retryAfter),
                'X-RateLimit-Limit' => (string) $this->meter->limit($merchantId),
                'X-RateLimit-Remaining' => '0',
            ]);
        }

        $this->meter->increment($merchantId);

        $response = $next($request);

        $snap = $this->meter->snapshot($merchantId);
        $response->headers->set('X-RateLimit-Limit', (string) $snap['limit']);
        $response->headers->set('X-RateLimit-Remaining', (string) $snap['remaining']);

        return $response;
    }
}
