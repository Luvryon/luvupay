<?php

namespace App\Services\PayPal;

use App\Exceptions\PaymentProviderException;
use App\Support\Money;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Throwable;

/**
 * Thin, defensive PayPal REST client.
 *
 * SECURITY — Least privilege OAuth scopes:
 * When requesting a PayPal access token, request ONLY the scopes required for LuvuPay operations, e.g.:
 *   - https://uri.paypal.com/services/subscriptions
 *   - https://uri.paypal.com/services/payments/refund
 *   - https://uri.paypal.com/services/payments/payouts
 *   - https://uri.paypal.com/services/payments/payment/authcapture (orders/captures for resale)
 * NEVER request wallet withdraw, account profile admin, or any scope that allows draining the merchant balance.
 * Sandbox credentials are fine for end-to-end testing; production must use a locked-down REST app.
 *  - OAuth2 client-credentials with cached token + stampede lock + one automatic re-auth on 401
 *  - Retries (Laravel retry()) on network errors, 429 and 5xx. Safe for POSTs because every
 *    money-moving call carries a PayPal-Request-Id, so PayPal de-duplicates replays.
 *  - Logs method/path/status/latency/debug_id. NEVER logs bodies, tokens or secrets (PII + credentials).
 */
class PayPalClient
{
    public const SANDBOX_URL = 'https://api-m.sandbox.paypal.com';
    public const LIVE_URL = 'https://api-m.paypal.com';

    /** @param  int[]  $retryDelaysMs  one entry per retry, e.g. [250, 750, 2000] */
    public function __construct(
        private readonly ClientInterface $http,
        private readonly string $clientId,
        private readonly string $secret,
        private readonly ?string $webhookId = null,
        private readonly array $retryDelaysMs = [250, 750, 2000],
        private readonly ?string $logChannel = null,
    ) {}

    public static function fromConfig(?ClientInterface $http = null): self
    {
        $c = config('services.paypal');
        if (empty($c['client_id']) || empty($c['secret'])) {
            throw new RuntimeException('PayPal is not configured: set PAYPAL_CLIENT_ID and PAYPAL_SECRET (services.paypal).');
        }

        $http ??= new Client([
            'base_uri' => ($c['mode'] ?? 'sandbox') === 'live' ? self::LIVE_URL : self::SANDBOX_URL,
            'timeout' => $c['timeout'] ?? 15,
            'connect_timeout' => $c['connect_timeout'] ?? 5,
            'http_errors' => false,
        ]);

        return new self($http, $c['client_id'], $c['secret'], $c['webhook_id'] ?? null, $c['retry_delays_ms'] ?? [250, 750, 2000], $c['log_channel'] ?? null);
    }

    // ------------------------------------------------------------------ OAuth 2.0

    public function accessToken(bool $forceRefresh = false): string
    {
        $key = 'paypal:oauth:'.sha1($this->clientId);

        if (! $forceRefresh && ($token = Cache::get($key))) {
            return $token;
        }

        // Lock prevents N concurrent workers from all hitting /oauth2/token when the token expires.
        return Cache::lock($key.':lock', 10)->block(5, function () use ($key, $forceRefresh) {
            if (! $forceRefresh && ($token = Cache::get($key))) {
                return $token;
            }

            $data = retry($this->retryDelaysMs, function (int $attempt) {
                $started = hrtime(true);
                try {
                    $res = $this->http->request('POST', '/v1/oauth2/token', [
                        'auth' => [$this->clientId, $this->secret],
                        'form_params' => ['grant_type' => 'client_credentials'],
                        'headers' => ['Accept' => 'application/json'],
                        'http_errors' => false,
                    ]);
                } catch (ConnectException $e) {
                    throw new PaymentProviderException('PayPal auth connection failed', true, null, $e);
                } catch (GuzzleException $e) {
                    throw new PaymentProviderException('PayPal auth transport error', false, null, $e);
                }

                $status = $res->getStatusCode();
                $this->log()->info('paypal.oauth', ['status' => $status, 'ms' => $this->ms($started), 'attempt' => $attempt]);

                if ($status !== 200) {
                    throw new PaymentProviderException('PayPal authentication failed (HTTP '.$status.')', $status >= 500 || $status === 429, ['status' => $status]);
                }

                return json_decode((string) $res->getBody(), true, 512, JSON_THROW_ON_ERROR);
            }, 0, $this->shouldRetry(...));

            $token = $data['access_token'] ?? throw new PaymentProviderException('PayPal returned no access_token');
            Cache::put($key, $token, max(60, ((int) ($data['expires_in'] ?? 3600)) - 120)); // refresh 2 min early

            return $token;
        });
    }

    // ------------------------------------------------------------------ Subscriptions API

    /** POST /v1/billing/subscriptions. Response contains an `approve` link for the end-user. */
    public function createSubscription(string $planId, string $subscriberEmail, string $customId, string $returnUrl, string $cancelUrl, string $requestId, array $extra = []): array
    {
        $payload = array_replace_recursive([
            'plan_id' => $planId,
            'custom_id' => $customId,                                  // our merchant_user_id / subscription uuid (max 127 chars)
            'application_context' => [
                'brand_name' => 'LuvuPay',
                'shipping_preference' => 'NO_SHIPPING',
                'user_action' => 'SUBSCRIBE_NOW',
                'payment_method' => ['payer_selected' => 'PAYPAL', 'payee_preferred' => 'IMMEDIATE_PAYMENT_REQUIRED'],
                'return_url' => $returnUrl,
                'cancel_url' => $cancelUrl,
            ],
        ], $extra);

        // Only prefill the PayPal login when we really know the payer's PayPal email.
        if ($subscriberEmail !== '') {
            $payload['subscriber'] = ['email_address' => $subscriberEmail];
        }

        return $this->post('/v1/billing/subscriptions', $payload, $requestId);
    }

    /** POST /v1/catalogs/products: a PayPal catalog product, required before a billing plan can exist. */
    public function createProduct(string $name, string $requestId): array
    {
        return $this->post('/v1/catalogs/products', [
            'name' => mb_substr($name, 0, 127),
            'type' => 'SERVICE',
            'category' => 'SOFTWARE',
        ], $requestId);
    }

    /**
     * POST /v1/billing/plans: an open-ended recurring plan charged immediately on approval.
     *
     * @param  string  $intervalUnit  DAY|WEEK|MONTH|YEAR
     */
    public function createPlan(string $productId, string $name, Money $price, string $intervalUnit, string $requestId): array
    {
        return $this->post('/v1/billing/plans', [
            'product_id' => $productId,
            'name' => mb_substr($name, 0, 127),
            'status' => 'ACTIVE',
            'billing_cycles' => [[
                'frequency' => ['interval_unit' => $intervalUnit, 'interval_count' => 1],
                'tenure_type' => 'REGULAR',
                'sequence' => 1,
                'total_cycles' => 0,                                   // 0 = until cancelled
                'pricing_scheme' => ['fixed_price' => PayPalAmount::fromMoney($price)],
            ]],
            'payment_preferences' => [
                'auto_bill_outstanding' => true,
                'setup_fee_failure_action' => 'CANCEL',
                'payment_failure_threshold' => 3,
            ],
        ], $requestId);
    }

    public function getSubscription(string $subscriptionId): array
    {
        return $this->get('/v1/billing/subscriptions/'.rawurlencode($subscriptionId));
    }

    public function suspendSubscription(string $subscriptionId, string $reason): void
    {
        $this->post('/v1/billing/subscriptions/'.rawurlencode($subscriptionId).'/suspend', ['reason' => mb_substr($reason, 0, 128)]);
    }

    public function activateSubscription(string $subscriptionId, string $reason): void
    {
        $this->post('/v1/billing/subscriptions/'.rawurlencode($subscriptionId).'/activate', ['reason' => mb_substr($reason, 0, 128)]);
    }

    public function cancelSubscription(string $subscriptionId, string $reason): void
    {
        $this->post('/v1/billing/subscriptions/'.rawurlencode($subscriptionId).'/cancel', ['reason' => mb_substr($reason, 0, 128)]);
    }

    // ------------------------------------------------------------------ Orders API (resale checkout)

    /** POST /v2/checkout/orders: the buyer approves this, then we capture it. */
    public function createOrder(Money $amount, string $referenceId, string $returnUrl, string $cancelUrl, string $requestId, ?string $customId = null): array
    {
        $unit = ['reference_id' => $referenceId, 'amount' => PayPalAmount::fromMoney($amount)];
        if ($customId !== null) {
            $unit['custom_id'] = $customId;
        }

        return $this->post('/v2/checkout/orders', [
            'intent' => 'CAPTURE',
            'purchase_units' => [$unit],
            'payment_source' => ['paypal' => ['experience_context' => [
                'brand_name' => 'LuvuPay',
                'shipping_preference' => 'NO_SHIPPING',
                'user_action' => 'PAY_NOW',
                'return_url' => $returnUrl,
                'cancel_url' => $cancelUrl,
            ]]],
        ], $requestId);
    }

    /** GET /v2/checkout/orders/{id} */
    public function getOrder(string $orderId): array
    {
        return $this->get('/v2/checkout/orders/'.rawurlencode($orderId));
    }

    /** POST /v2/checkout/orders/{id}/capture */
    public function capturePayment(string $orderId, string $requestId): array
    {
        return $this->post('/v2/checkout/orders/'.rawurlencode($orderId).'/capture', null, $requestId);
    }

    // ------------------------------------------------------------------ Payments API

    /** POST /v2/payments/captures/{id}/refund: pass null amount for a full refund. */
    public function refundPayment(string $captureId, ?Money $amount, string $requestId, ?string $note = null, ?string $invoiceId = null): array
    {
        $payload = [];
        if ($amount) {
            $payload['amount'] = PayPalAmount::fromMoney($amount);
        }
        if ($note) {
            $payload['note_to_payer'] = mb_substr($note, 0, 255);
        }
        if ($invoiceId) {
            $payload['invoice_id'] = $invoiceId;
        }

        return $this->post('/v2/payments/captures/'.rawurlencode($captureId).'/refund', $payload ?: null, $requestId);
    }

    // ------------------------------------------------------------------ Payouts API

    /**
     * POST /v1/payments/payouts
     *
     * @param  array<int,array{receiver:string,amount:Money,note:string,sender_item_id:string}>  $items
     */
    public function createPayout(array $items, string $senderBatchId, string $requestId, string $emailSubject = 'You have a payout from LuvuPay'): array
    {
        return $this->post('/v1/payments/payouts', [
            'sender_batch_header' => [
                'sender_batch_id' => $senderBatchId,
                'email_subject' => mb_substr($emailSubject, 0, 255),
            ],
            'items' => array_map(fn (array $i) => [
                'recipient_type' => 'EMAIL',
                'receiver' => $i['receiver'],
                'amount' => ['currency' => $i['amount']->currency, 'value' => $i['amount']->toDecimalString()],
                'note' => mb_substr($i['note'], 0, 4000),
                'sender_item_id' => $i['sender_item_id'],
            ], $items),
        ], $requestId);
    }

    public function getPayoutBatch(string $payoutBatchId): array
    {
        return $this->get('/v1/payments/payouts/'.rawurlencode($payoutBatchId));
    }

    // ------------------------------------------------------------------ Notifications API

    /**
     * POST /v1/notifications/verify-webhook-signature. FAIL CLOSED: only an explicit SUCCESS returns true.
     *
     * @param  array<string,string|string[]>  $headers  incoming request headers
     * @param  string  $rawBody  the EXACT bytes PayPal sent (never re-encoded: signature covers them)
     */
    public function verifyWebhookSignature(array $headers, string $rawBody, ?string $webhookId = null): bool
    {
        $webhookId ??= $this->webhookId;
        if (! $webhookId) {
            // Misconfiguration must be loud, never a silent "skip verification".
            throw new RuntimeException('PAYPAL_WEBHOOK_ID is not configured; refusing to process webhooks unverified.');
        }

        $h = [];
        foreach ($headers as $k => $v) {
            $h[strtolower($k)] = is_array($v) ? (string) ($v[0] ?? '') : (string) $v;
        }
        $required = ['paypal-auth-algo', 'paypal-cert-url', 'paypal-transmission-id', 'paypal-transmission-sig', 'paypal-transmission-time'];
        foreach ($required as $name) {
            if (($h[$name] ?? '') === '') {
                $this->log()->warning('paypal.webhook.missing_header', ['header' => $name]);
                return false;
            }
        }

        // The raw body is spliced into the request (re-encoding could change bytes and break verification).
        // SECURITY: it must decode to exactly ONE JSON object, otherwise a crafted body like
        //   {"a":1}, "webhook_id":"attacker-id"
        // could inject extra keys into our verification request.
        $decoded = json_decode($rawBody, true);
        if (! is_array($decoded) || $decoded === [] || array_is_list($decoded)) {
            $this->log()->warning('paypal.webhook.malformed_body');
            return false;
        }

        $body = '{"auth_algo":'.json_encode($h['paypal-auth-algo'])
            .',"cert_url":'.json_encode($h['paypal-cert-url'], JSON_UNESCAPED_SLASHES)
            .',"transmission_id":'.json_encode($h['paypal-transmission-id'])
            .',"transmission_sig":'.json_encode($h['paypal-transmission-sig'])
            .',"transmission_time":'.json_encode($h['paypal-transmission-time'])
            .',"webhook_id":'.json_encode($webhookId)       // OUR configured id, never taken from the request
            .',"webhook_event":'.$rawBody.'}';

        try {
            $res = $this->request('POST', '/v1/notifications/verify-webhook-signature', rawBody: $body);
        } catch (PaymentProviderException $e) {
            if ($e->retryable) {
                throw $e;   // PayPal outage: let the controller 500 so PayPal redelivers later
            }
            $this->log()->warning('paypal.webhook.verify_rejected', ['message' => $e->getMessage()]);
            return false;
        }

        return ($res['verification_status'] ?? null) === 'SUCCESS';
    }

    /** Find a link by rel (e.g. 'approve') in a PayPal HATEOAS response. */
    public static function link(array $response, string ...$rels): ?string
    {
        foreach ($response['links'] ?? [] as $l) {
            if (in_array($l['rel'] ?? '', $rels, true)) {
                return $l['href'] ?? null;
            }
        }

        return null;
    }

    // ------------------------------------------------------------------ transport

    private function get(string $path): array
    {
        return $this->request('GET', $path);
    }

    private function post(string $path, ?array $json, ?string $requestId = null): array
    {
        $headers = $requestId ? ['PayPal-Request-Id' => $requestId] : [];

        // PayPal expects a JSON object; "{}" when there is no payload (json_encode([]) would give "[]").
        return $this->request('POST', $path, $headers, json_encode($json ?? (object) [], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
    }

    private function request(string $method, string $path, array $headers = [], ?string $rawBody = null): array
    {
        return retry(
            $this->retryDelaysMs,
            fn (int $attempt) => $this->sendOnce($method, $path, $headers, $rawBody, $attempt),
            0,
            $this->shouldRetry(...),
        );
    }

    private function sendOnce(string $method, string $path, array $headers, ?string $rawBody, int $attempt): array
    {
        $reauthed = false;

        while (true) {
            $started = hrtime(true);
            $options = [
                'headers' => $headers + [
                    'Authorization' => 'Bearer '.$this->accessToken(forceRefresh: $reauthed),
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                    'Prefer' => 'return=representation',
                ],
                'http_errors' => false,
            ];
            if ($rawBody !== null) {
                $options['body'] = $rawBody;
            }

            try {
                $res = $this->http->request($method, $path, $options);
            } catch (ConnectException $e) {            // DNS, connect and read timeouts
                $this->log()->warning('paypal.request.network_error', ['method' => $method, 'path' => $path, 'attempt' => $attempt, 'ms' => $this->ms($started)]);
                throw new PaymentProviderException('PayPal network error', true, ['path' => $path], $e);
            } catch (GuzzleException $e) {
                throw new PaymentProviderException('PayPal transport error', false, ['path' => $path], $e);
            }

            $status = $res->getStatusCode();
            $debugId = $res->getHeaderLine('Paypal-Debug-Id') ?: null;

            if ($status === 401 && ! $reauthed) {        // token revoked/expired early: refresh once and replay
                $reauthed = true;
                $this->log()->notice('paypal.request.reauth', ['path' => $path]);
                continue;
            }

            $data = (string) $res->getBody() === '' ? [] : (json_decode((string) $res->getBody(), true) ?? []);
            $ctx = ['method' => $method, 'path' => $path, 'status' => $status, 'ms' => $this->ms($started), 'attempt' => $attempt, 'debug_id' => $debugId, 'request_id' => $headers['PayPal-Request-Id'] ?? null];

            if ($status >= 200 && $status < 300) {
                $this->log()->info('paypal.request', $ctx);
                return $data;
            }

            $retryable = $status === 429 || $status >= 500;
            $issue = $data['details'][0]['issue'] ?? ($data['name'] ?? 'UNKNOWN_ERROR');
            $this->log()->{$retryable ? 'warning' : 'error'}('paypal.request.failed', $ctx + ['issue' => $issue, 'message' => $data['message'] ?? null]);

            throw new PaymentProviderException(
                sprintf('PayPal %s %s failed: HTTP %d %s', $method, $path, $status, $issue),
                $retryable,
                ['status' => $status, 'issue' => $issue, 'debug_id' => $debugId, 'details' => $data['details'] ?? null],
            );
        }
    }

    private function shouldRetry(Throwable $e): bool
    {
        return $e instanceof PaymentProviderException && $e->retryable;
    }

    private function log(): LoggerInterface
    {
        return $this->logChannel ? Log::channel($this->logChannel) : Log::channel();
    }

    private function ms(int $startedNs): int
    {
        return (int) ((hrtime(true) - $startedNs) / 1_000_000);
    }
}
