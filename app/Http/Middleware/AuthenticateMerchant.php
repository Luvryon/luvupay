<?php

namespace App\Http\Middleware;

use App\Models\Merchant;
use App\Support\AuditContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Bearer API-key auth. Looks the key up by SHA-256 (indexed, constant effort); one generic error for every failure. */
class AuthenticateMerchant
{
    public function __construct(private readonly AuditContext $ctx) {}

    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken() ?: $request->header('X-LuvuPay-Key');
        $merchant = (is_string($token) && str_starts_with($token, 'lp_')) ? Merchant::findByApiKey($token) : null;

        if (! $merchant || !$merchant->is_active) {
            return response()->json(['error' => 'unauthorized', 'message' => 'Invalid or missing API key'], 401, ['WWW-Authenticate' => 'Bearer']);
        }

        $request->attributes->set('merchant', $merchant);
        $request->attributes->set('merchant_id', $merchant->getKey());
        // Ignore any client-supplied merchant_id body/query - tenant is the API key only.
        $this->ctx->actor('merchant_api', $merchant->api_key_prefix);

        return $next($request);
    }
}
