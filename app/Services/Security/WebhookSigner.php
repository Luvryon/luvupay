<?php

namespace App\Services\Security;

use App\Exceptions\WebhookSignatureException;
use App\Models\WebhookEndpoint;
use Illuminate\Cache\CacheManager;

/**
 * HMAC-SHA256 request signing with replay protection. Used in BOTH directions:
 *   - outbound: LuvuPay -> merchant (Entitlement Bridge)       => sign()
 *   - inbound : merchant -> LuvuPay (acks / callbacks)         => verify()
 *
 * Wire format (headers):
 *   X-LuvuPay-Timestamp : unix seconds
 *   X-LuvuPay-Nonce     : 128-bit random hex, single use
 *   X-LuvuPay-Signature : v1=<hex hmac>[,v1=<hex hmac>]   (two values while a secret is being rotated)
 *
 * Signed string (newline-delimited; body last so it can never be confused with the metadata):
 *   "v1\n{timestamp}\n{nonce}\n{raw body}"
 */
class WebhookSigner
{
    public const HEADER_TIMESTAMP = 'X-LuvuPay-Timestamp';
    public const HEADER_NONCE = 'X-LuvuPay-Nonce';
    public const HEADER_SIGNATURE = 'X-LuvuPay-Signature';

    public function __construct(private readonly CacheManager $cache) {}

    public static function generateSecret(): string
    {
        return 'whsec_'.rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    }

    /** @return array<string,string> headers to attach to the outgoing request (sign the EXACT bytes you send) */
    public function sign(string $rawBody, string|array $secrets, ?int $timestamp = null, ?string $nonce = null): array
    {
        $ts = $timestamp ?? time();
        $nonce ??= bin2hex(random_bytes(16));

        $sigs = array_map(fn (string $s) => 'v1='.$this->mac($ts, $nonce, $rawBody, $s), (array) $secrets);

        return [
            self::HEADER_TIMESTAMP => (string) $ts,
            self::HEADER_NONCE => $nonce,
            self::HEADER_SIGNATURE => implode(',', $sigs),
        ];
    }

    public function headersFor(WebhookEndpoint $endpoint, string $rawBody): array
    {
        // During rotation sign with every active secret so merchants can switch whenever they like.
        return $this->sign($rawBody, $endpoint->activeSecrets());
    }

    /**
     * @param  array<string,string|string[]>  $headers  request headers (any case)
     * @param  string|string[]  $secrets                current (+ previous, during rotation)
     * @param  string  $scope                           tenant key for nonce storage, e.g. the merchant id
     * @throws WebhookSignatureException
     */
    public function verify(string $rawBody, array $headers, string|array $secrets, string $scope, ?int $now = null): void
    {
        $now ??= time();
        $h = [];
        foreach ($headers as $k => $v) {
            $h[strtolower($k)] = is_array($v) ? (string) ($v[0] ?? '') : (string) $v;
        }

        $ts = $h['x-luvupay-timestamp'] ?? '';
        $nonce = $h['x-luvupay-nonce'] ?? '';
        $sigHeader = $h['x-luvupay-signature'] ?? '';
        if ($ts === '' || $nonce === '' || $sigHeader === '') {
            throw new WebhookSignatureException('missing_headers');
        }
        if (! preg_match('/^\d{9,11}$/', $ts)) {
            throw new WebhookSignatureException('malformed_timestamp');
        }
        if (! preg_match('/^[A-Za-z0-9_-]{16,64}$/', $nonce)) {
            throw new WebhookSignatureException('malformed_nonce');
        }

        // 1) freshness: rejects stale AND far-future timestamps (clock-skew abuse)
        if (abs($now - (int) $ts) > (int) config('luvupay.webhooks.timestamp_tolerance_seconds', 300)) {
            throw new WebhookSignatureException('timestamp_out_of_window');
        }

        // 2) signature: constant-time, checks every (secret x signature) pair without early exit
        $given = [];
        foreach (explode(',', $sigHeader) as $part) {
            $part = trim($part);
            if (str_starts_with($part, 'v1=')) {
                $given[] = substr($part, 3);
            }
        }
        $valid = 0;
        foreach ((array) $secrets as $secret) {
            $expected = $this->mac((int) $ts, $nonce, $rawBody, $secret);
            foreach ($given as $g) {
                $valid |= (int) hash_equals($expected, $g);
            }
        }
        if (! $valid) {
            throw new WebhookSignatureException('bad_signature');
        }

        // 3) replay: consume the nonce ONLY after the signature is proven valid, so unauthenticated
        //    traffic can neither burn legitimate nonces nor bloat the cache. Cache::add is atomic (Redis SET NX).
        $store = $this->cache->store(config('luvupay.webhooks.nonce_store'));
        if (! $store->add("luvupay:nonce:{$scope}:{$nonce}", 1, (int) config('luvupay.webhooks.nonce_ttl_seconds', 600))) {
            throw new WebhookSignatureException('replay_detected');
        }
    }

    private function mac(int $ts, string $nonce, string $body, string $secret): string
    {
        return hash_hmac('sha256', "v1\n{$ts}\n{$nonce}\n{$body}", $secret);
    }
}
