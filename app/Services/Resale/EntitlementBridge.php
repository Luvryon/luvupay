<?php

namespace App\Services\Resale;

use App\Exceptions\EntitlementDeliveryException;
use App\Exceptions\UnsafeUrlException;
use App\Models\ResaleListing;
use App\Models\WebhookEndpoint;
use App\Services\Security\UrlGuard;
use App\Services\Security\WebhookSigner;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * ZERO-TRUST ENTITLEMENT BRIDGE. LuvuPay never touches the merchant's database or keys: it only SIGNALS
 * "revoke A / grant B" in ONE signed webhook (one atomic event, so B can never be granted without A being revoked).
 * Access granted to B is for the REMAINDER of the CURRENT billing cycle only (see access_expires_at).
 * Next cycle the merchant bills B at the standard subscription price — that is outside this webhook.
 * The merchant verifies the HMAC, enforces it, and acknowledges via POST /api/v1/entitlements/ack.
 *
 * The body is deterministic per listing (stable event id = correlation id), so retries are safe for the merchant to
 * de-duplicate; timestamp + nonce + signature are regenerated on EVERY attempt (replay protection).
 */
class EntitlementBridge
{
    public const EVENT = 'entitlement.transfer';

    public function __construct(private readonly WebhookSigner $signer, private readonly UrlGuard $guard) {}

    public function payload(ResaleListing $l): array
    {
        $l->loadMissing('subscription');

        return [
            'id' => $l->entitlement_correlation_id,
            'type' => self::EVENT,
            'created' => $l->sold_at->toIso8601String(),
            'data' => [                                   // data minimisation: ids only, no emails
                'listing_id' => $l->id,
                'subscription_id' => $l->subscription_id,
                'plan_code' => $l->subscription->plan_code,
                'access_expires_at' => $l->access_expires_at->toIso8601String(),
                'revoke' => ['merchant_user_id' => $l->seller_merchant_user_id],
                'grant' => ['merchant_user_id' => $l->buyer_merchant_user_id],
                'ack_url' => route('api.entitlements.ack'),
            ],
        ];
    }

    /** @throws EntitlementDeliveryException */
    public function deliver(ResaleListing $l): void
    {
        $endpoints = WebhookEndpoint::query()->where('merchant_id', $l->merchant_id)->where('is_active', true)->get()
            ->filter(fn (WebhookEndpoint $e) => in_array(self::EVENT, (array) $e->events, true));

        if ($endpoints->isEmpty()) {
            throw new EntitlementDeliveryException('No active webhook endpoint subscribed to '.self::EVENT.'.', permanent: true);
        }

        $body = json_encode($this->payload($l), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $errors = [];

        foreach ($endpoints as $e) {
            try {
                $target = $this->guard->resolve($e->url);
                $res = Http::withHeaders($this->signer->headersFor($e, $body) + [
                        'User-Agent' => 'LuvuPay-Webhooks/1.0', 'X-LuvuPay-Event' => self::EVENT, 'X-LuvuPay-Delivery' => $l->entitlement_correlation_id,
                    ])
                    ->withBody($body, 'application/json')        // the exact bytes that were signed
                    ->timeout(10)->connectTimeout(5)
                    ->withOptions(['allow_redirects' => false] + (defined('CURLOPT_RESOLVE') ? ['curl' => [CURLOPT_RESOLVE => ["{$target['host']}:{$target['port']}:{$target['ip']}"]]] : []))
                    ->post($e->url);

                if ($res->successful()) {
                    $e->update(['consecutive_failures' => 0, 'last_success_at' => now()]);
                    continue;
                }
                $errors[] = "{$e->id}: HTTP {$res->status()}";
            } catch (UnsafeUrlException|ConnectionException $x) {
                $errors[] = "{$e->id}: ".$x->getMessage();
            }
            $e->update(['consecutive_failures' => $e->consecutive_failures + 1, 'last_failure_at' => now()]);
        }

        if ($errors) {
            throw new EntitlementDeliveryException(implode('; ', $errors));
        }
    }
}
