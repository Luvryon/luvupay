<?php

namespace App\Listeners;

use App\Events\PayPal\PaymentCaptured;
use App\Events\PayPal\SubscriptionActivated;
use App\Services\Checkout\PendingCheckoutActivator;
use App\Support\AuditContext;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

/**
 * When PayPal confirms a subscription/payment, settle any pending dashboard checkout.
 * Complements the return-URL path so sandbox still works if the buyer closes the popup early.
 */
final class ActivatePendingCheckoutFromWebhook implements ShouldQueue
{
    public string $queue = 'webhooks';

    public int $tries = 5;

    public function backoff(): array
    {
        return [5, 20, 60, 180, 600];
    }

    public function onSubscriptionActivated(SubscriptionActivated $event): void
    {
        $this->run($event->webhook->providerSubscriptionId, $event->webhook->customId, $event->webhook->providerOrderId, $event->webhook->providerCaptureId, $event->webhook->type);
    }

    public function onPaymentCaptured(PaymentCaptured $event): void
    {
        $this->run($event->webhook->providerSubscriptionId, $event->webhook->customId, $event->webhook->providerOrderId, $event->webhook->providerCaptureId, $event->webhook->type);
    }

    private function run(?string $providerSubId, ?string $customId, ?string $orderId, ?string $captureId, string $type): void
    {
        app(AuditContext::class)->actor('provider_webhook', 'paypal');
        try {
            $ok = app(PendingCheckoutActivator::class)->activateFromWebhook([
                'provider_subscription_id' => $providerSubId,
                'custom_id' => $customId,
                'order_id' => $orderId,
                'provider_reference' => $captureId ?: $orderId,
            ]);
            if (! $ok) {
                Log::info('luvupay.webhook.no_pending_checkout', ['event_type' => $type, 'provider_subscription_id' => $providerSubId]);
            }
        } catch (\Throwable $e) {
            Log::error('luvupay.webhook.activate_checkout_failed', [
                'event_type' => $type,
                'error' => $e->getMessage(),
            ]);
            throw $e; // let queue retry - do not swallow
        }
    }
}
