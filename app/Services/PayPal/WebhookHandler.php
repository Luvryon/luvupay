<?php

namespace App\Services\PayPal;

use App\DTOs\Payment\ProviderWebhookEvent;
use App\Events\PayPal;
use App\Exceptions\InvalidWebhookSignatureException;
use App\Jobs\ProcessWebhookEventJob;
use App\Models\WebhookEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use JsonException;
use UnexpectedValueException;

/**
 * Inbound PayPal webhooks, "inbox" pattern:
 *
 *   verify signature  ->  persist (unique provider+event_id)  ->  ack 200  ->  process asynchronously
 *
 * Nothing is parsed or acted on before the signature is verified. Once the row is committed the event can
 * never be lost: processing retries with backoff, ends in `dead` (dead-letter) and can be replayed with
 * `php artisan webhooks:replay`. Listeners must still be idempotent (the DB unique index on
 * transactions(provider, provider_event_id) is the final guard).
 */
class WebhookHandler
{
    /** PayPal event_type => Laravel event */
    public const EVENT_MAP = [
        'PAYMENT.CAPTURE.COMPLETED' => PayPal\PaymentCaptured::class,
        'PAYMENT.SALE.COMPLETED' => PayPal\PaymentCaptured::class,           // recurring subscription charges
        'PAYMENT.CAPTURE.REFUNDED' => PayPal\PaymentRefunded::class,
        'PAYMENT.SALE.REFUNDED' => PayPal\PaymentRefunded::class,
        'BILLING.SUBSCRIPTION.ACTIVATED' => PayPal\SubscriptionActivated::class,
        'BILLING.SUBSCRIPTION.CANCELLED' => PayPal\SubscriptionCancelled::class,
        'BILLING.SUBSCRIPTION.SUSPENDED' => PayPal\SubscriptionSuspended::class,
        'BILLING.SUBSCRIPTION.PAYMENT.FAILED' => PayPal\SubscriptionPaymentFailed::class,
        'PAYMENT.PAYOUTS-ITEM.SUCCEEDED' => PayPal\PayoutSucceeded::class,
        'PAYMENT.PAYOUTS-ITEM.FAILED' => PayPal\PayoutFailed::class,
        'PAYMENT.PAYOUTS-ITEM.DENIED' => PayPal\PayoutFailed::class,
        'PAYMENT.PAYOUTS-ITEM.RETURNED' => PayPal\PayoutFailed::class,
        'PAYMENT.PAYOUTS-ITEM.BLOCKED' => PayPal\PayoutFailed::class,
    ];

    /** A `received` row older than this was probably orphaned by a crash between commit and dispatch. */
    private const STUCK_AFTER_SECONDS = 120;

    public function __construct(private readonly PayPalClient $client) {}

    /**
     * @param  array<string,string|string[]>  $headers
     * @throws InvalidWebhookSignatureException  signature invalid -> respond 400
     */
    public function handle(array $headers, string $rawBody): WebhookOutcome
    {
        if (! $this->client->verifyWebhookSignature($headers, $rawBody)) {
            Log::channel(config('services.paypal.log_channel') ?: config('logging.default'))->warning('paypal.webhook.invalid_signature', [
                'transmission_id' => is_array($t = $headers['paypal-transmission-id'] ?? null) ? ($t[0] ?? null) : $t,
            ]);
            throw new InvalidWebhookSignatureException();
        }

        try {
            $event = $this->parse($rawBody);
        } catch (JsonException|UnexpectedValueException $e) {
            // Signed by PayPal but unusable: redelivery cannot fix it, so ack and keep a trace.
            Log::warning('paypal.webhook.unparseable', ['error' => $e->getMessage()]);

            return WebhookOutcome::Ignored;
        }

        $mapped = isset(self::EVENT_MAP[$event->type]);
        $id = (string) Str::uuid();
        $now = now();

        $inserted = DB::table('webhook_events')->insertOrIgnore([
            'id' => $id, 'provider' => 'paypal', 'event_id' => $event->eventId, 'event_type' => $event->type,
            'status' => $mapped ? WebhookEvent::RECEIVED : WebhookEvent::IGNORED,
            'payload' => $rawBody, 'attempts' => 0, 'received_at' => $now,
            'processed_at' => $mapped ? null : $now, 'created_at' => $now, 'updated_at' => $now,
        ]);

        if ($inserted === 0) {
            $this->redeliverIfStuck($event->eventId);

            return WebhookOutcome::Duplicate;
        }

        if (! $mapped) {
            return WebhookOutcome::Ignored;          // verified, stored for audit, nothing to do
        }

        ProcessWebhookEventJob::dispatch($id);
        Log::info('paypal.webhook.accepted', [
            'webhook_event_id' => $id,
            'event_type' => $event->type,
            'event_id' => $event->eventId,
        ]);

        return WebhookOutcome::Processed;            // "accepted"; the job does the work
    }

    public function parse(string $rawBody): ProviderWebhookEvent
    {
        $p = json_decode($rawBody, true, 512, JSON_THROW_ON_ERROR);
        if (! is_array($p)) {
            throw new UnexpectedValueException('Webhook body is not an object');
        }
        $type = (string) ($p['event_type'] ?? 'UNKNOWN');
        $r = is_array($p['resource'] ?? null) ? $p['resource'] : [];

        $isPayment = str_starts_with($type, 'PAYMENT.CAPTURE.') || str_starts_with($type, 'PAYMENT.SALE.');
        $subscriptionId = $r['billing_agreement_id']                                  // PAYMENT.SALE.* on subscriptions
            ?? (str_starts_with($type, 'BILLING.SUBSCRIPTION.') ? ($r['id'] ?? null) : null);

        return new ProviderWebhookEvent(
            eventId: (string) ($p['id'] ?? throw new UnexpectedValueException('Webhook has no event id')),
            type: $type,
            providerSubscriptionId: $subscriptionId,
            providerCaptureId: $isPayment ? ($r['id'] ?? null) : null,
            amount: PayPalAmount::toMoney(is_array($r['amount'] ?? null) ? $r['amount'] : null),
            payload: $p,
            customId: $r['custom_id'] ?? $r['custom'] ?? null,
            providerOrderId: $r['supplementary_data']['related_ids']['order_id'] ?? null,
        );
    }

    private function redeliverIfStuck(string $eventId): void
    {
        $row = WebhookEvent::query()->where('provider', 'paypal')->where('event_id', $eventId)->first();
        if (! $row) {
            return;
        }
        $stuck = $row->status === WebhookEvent::FAILED
            || ($row->status === WebhookEvent::RECEIVED && $row->received_at->lt(now()->subSeconds(self::STUCK_AFTER_SECONDS)));
        if ($stuck) {
            ProcessWebhookEventJob::dispatch($row->id);
        }
    }
}
