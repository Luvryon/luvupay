<?php

namespace App\Http\Middleware;

use App\Support\AuditContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/** First middleware on every API request: request id, IP, UA. Later middleware refine the actor. */
class CaptureAuditContext
{
    public function __construct(private readonly AuditContext $ctx) {}

    public function handle(Request $request, Closure $next): Response
    {
        $incoming = (string) $request->header('X-Request-Id');
        $this->ctx->requestId = preg_match('/^[A-Za-z0-9_\-.]{8,64}$/', $incoming) ? $incoming : (string) Str::uuid();
        $this->ctx->ip = $request->ip();
        $this->ctx->userAgent = $request->userAgent();

        if ($request->routeIs('webhooks.paypal')) {
            $this->ctx->actor('provider_webhook', 'paypal');
        }

        $response = $next($request);
        $response->headers->set('X-Request-Id', $this->ctx->requestId);

        return $response;
    }
}
