<?php

namespace App\Jobs;

use App\Models\WebhookDelivery;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

/**
 * Re-delivers a signed outbound webhook to the merchant endpoint.
 * Simulates enterprise retry semantics for the Reliability Center UI.
 */
class RetryWebhookDeliveryJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 5;

    public function __construct(public string $deliveryId)
    {
        $this->onQueue('webhooks');
    }

    public function backoff(): array
    {
        return [5, 15, 60, 180, 600];
    }

    public function handle(): void
    {
        $d = WebhookDelivery::query()->find($this->deliveryId);
        if (! $d || $d->status === 'success') {
            return;
        }

        $d->increment('attempts');
        $d->refresh();

        $url = $d->target_url ?: 'https://httpbin.org/status/200';
        $payload = is_array($d->payload) ? $d->payload : [];
        $body = json_encode($payload, JSON_UNESCAPED_SLASHES);
        $timestamp = (string) time();
        $nonce = (string) Str::uuid();
        $secret = config('app.key', 'sandbox-secret');
        $signature = hash_hmac('sha256', $timestamp.'.'.$nonce.'.'.$body, $secret);

        try {
            // Sandbox: treat explicit fail targets as failures for demo
            if (str_contains((string) $url, 'force-fail') || ($payload['_force_fail'] ?? false)) {
                throw new \RuntimeException('Simulated merchant endpoint failure (500)');
            }

            $res = Http::timeout(8)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'X-LuvuPay-Timestamp' => $timestamp,
                    'X-LuvuPay-Nonce' => $nonce,
                    'X-LuvuPay-Signature' => $signature,
                    'X-LuvuPay-Event' => $d->event_type,
                ])
                ->withBody($body, 'application/json')
                ->post($url);

            $code = $res->status();
            if ($code >= 200 && $code < 300) {
                $d->update([
                    'status' => 'success',
                    'response_code' => $code,
                    'delivered_at' => now(),
                    'last_error' => null,
                    'next_retry_at' => null,
                ]);

                return;
            }

            $d->update([
                'status' => 'failed',
                'response_code' => $code,
                'last_error' => 'HTTP '.$code.': '.Str::limit($res->body(), 200),
                'next_retry_at' => now()->addMinutes(min(60, 5 * $d->attempts)),
            ]);
        } catch (Throwable $e) {
            $d->update([
                'status' => 'failed',
                'response_code' => 500,
                'last_error' => Str::limit($e->getMessage(), 300),
                'next_retry_at' => now()->addMinutes(min(60, 5 * $d->attempts)),
            ]);
            throw $e;
        }
    }
}
