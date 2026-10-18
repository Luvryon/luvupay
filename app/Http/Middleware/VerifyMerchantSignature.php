<?php

namespace App\Http\Middleware;

use App\Exceptions\WebhookSignatureException;
use App\Services\Security\WebhookSigner;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * For INBOUND calls from a merchant's server (e.g. the entitlement acknowledgement): the merchant must
 * sign the body with its webhook secret. Defence in depth on top of the API key. Use after merchant.auth.
 */
class VerifyMerchantSignature
{
    public function __construct(private readonly WebhookSigner $signer) {}

    public function handle(Request $request, Closure $next): Response
    {
        $merchant = $request->attributes->get('merchant');

        $secrets = $merchant
            ? $merchant->webhookEndpoints()->where('is_active', true)->get()->flatMap->activeSecrets()->all()
            : [];

        try {
            if ($secrets === []) {
                throw new WebhookSignatureException('no_active_endpoint');
            }
            // getContent() = exact raw bytes; never re-encode before verifying.
            $this->signer->verify($request->getContent(), $request->headers->all(), $secrets, (string) $merchant->getKey());
        } catch (WebhookSignatureException $e) {
            Log::warning('luvupay.signature_rejected', ['reason' => $e->reason, 'merchant_id' => $merchant?->getKey(), 'ip' => $request->ip()]);

            return response()->json(['error' => 'invalid_signature', 'reason' => $e->reason], 401);
        }

        return $next($request);
    }
}
