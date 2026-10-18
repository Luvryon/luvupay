<?php

namespace App\Http\Middleware;

use App\Support\AuditContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use LogicException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Idempotency-Key protection for every state-changing (financial) request.
 *
 *   first request  -> atomically claims the key ("processing"), runs, stores the response
 *   same key+body  -> replays the stored response (header `Idempotent-Replayed: true`), handler NOT executed
 *   same key+other body -> 422 (client bug: never silently run a different payment under an old key)
 *   same key while the first is still running -> 409 + Retry-After
 *
 * Usage: ->middleware(['merchant.auth', 'idempotent'])   // 'idempotent:optional' to not require the header
 * Keys are scoped per merchant. Must run AFTER merchant.auth.
 */
class EnsureIdempotency
{
    private const METHODS = ['POST', 'PUT', 'PATCH', 'DELETE'];
    /** Client errors that are NOT remembered, so the client can fix the request and retry with the same key. */
    private const UNCACHED_4XX = [400, 401, 408, 422, 429];

    public function __construct(private readonly AuditContext $ctx) {}

    public function handle(Request $request, Closure $next, string $mode = 'required'): Response
    {
        if (! in_array($request->method(), self::METHODS, true)) {
            return $next($request);
        }

        $key = $request->header('Idempotency-Key');
        if ($key === null || $key === '') {
            return $mode === 'optional'
                ? $next($request)
                : $this->error(400, 'idempotency_key_required', 'This endpoint requires an Idempotency-Key header (8-255 chars).');
        }
        if (! preg_match('/^[A-Za-z0-9_\-:.]{8,255}$/', $key)) {
            return $this->error(400, 'idempotency_key_invalid', 'Idempotency-Key must be 8-255 chars of [A-Za-z0-9_-:.].');
        }

        $merchantId = $request->attributes->get('merchant_id')
            ?? throw new LogicException('EnsureIdempotency must run after merchant.auth.');

        $cfg = config('luvupay.idempotency');
        $store = Cache::store($cfg['store'] ?? null);
        $cacheKey = "luvupay:idem:{$merchantId}:".hash('sha256', $key);
        $fingerprint = hash('sha256', $request->method()."\n".$request->getRequestUri()."\n".$request->getContent());

        // Atomic claim (Redis SET NX EX). Exactly one concurrent request wins.
        if (! $store->add($cacheKey, ['state' => 'processing', 'fp' => $fingerprint], (int) $cfg['lock_seconds'])) {
            $rec = $store->get($cacheKey);

            if (! is_array($rec)) {      // record expired between add() and get(): ask the client to retry
                return $this->error(409, 'request_in_progress', 'Please retry.', ['Retry-After' => '1']);
            }
            if (! hash_equals($rec['fp'], $fingerprint)) {
                return $this->error(422, 'idempotency_key_reused', 'This Idempotency-Key was already used with a different request.');
            }
            if ($rec['state'] === 'processing') {
                return $this->error(409, 'request_in_progress', 'A request with this Idempotency-Key is still being processed.', ['Retry-After' => '1']);
            }

            return response($rec['body'], $rec['status'], ['Content-Type' => $rec['ct'], 'Idempotent-Replayed' => 'true', 'Idempotency-Key' => $key]);
        }

        $request->attributes->set('idempotency_key', $key);   // downstream: transactions.idempotency_key
        $this->ctx->idempotencyKey = $key;

        try {
            $response = $next($request);
        } catch (Throwable $e) {
            $store->forget($cacheKey);   // nothing completed: let the client retry
            throw $e;
        }

        $status = $response->getStatusCode();
        $body = (string) $response->getContent();
        $cacheable = ($status >= 200 && $status < 300) || ($status >= 400 && $status < 500 && ! in_array($status, self::UNCACHED_4XX, true));

        if ($cacheable && strlen($body) <= (int) $cfg['max_cached_bytes']) {
            $store->put($cacheKey, [
                'state' => 'done', 'fp' => $fingerprint, 'status' => $status, 'body' => $body,
                'ct' => $response->headers->get('Content-Type', 'application/json'),
            ], (int) $cfg['ttl_seconds']);
        } else {
            $store->forget($cacheKey);   // 5xx / validation errors / oversized: the key stays reusable
        }

        $response->headers->set('Idempotency-Key', $key);

        return $response;
    }

    private function error(int $status, string $code, string $message, array $headers = []): Response
    {
        return response()->json(['error' => $code, 'message' => $message], $status, $headers);
    }
}
