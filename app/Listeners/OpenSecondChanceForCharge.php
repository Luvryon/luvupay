<?php

namespace App\Listeners;

use App\Events\PayPal\PaymentCaptured;
use App\Models\Subscription;
use App\Services\Luvu\SecondChanceService;
use App\Support\AuditContext;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

/** PayPal says a subscription charge was captured: start the Second Chance clock. */
class OpenSecondChanceForCharge implements ShouldQueue
{
    public string $queue = 'financial';
    public int $tries = 5;

    public function backoff(): array { return [5, 30, 120, 600]; }

    public function process(PaymentCaptured $event): void
    {
        app(AuditContext::class)->actor('provider_webhook', 'paypal');
        $w = $event->webhook;

        // Resale checkout captures arrive here too (no subscription id): they are handled by ResaleService.
        if (! $w->providerSubscriptionId || ! $w->providerCaptureId || ! $w->amount || $w->amount->isZero()) {
            return;
        }
        $sub = Subscription::query()->where('provider_subscription_id', $w->providerSubscriptionId)->first();
        if (! $sub) {
            Log::info('luvi.charge_for_unknown_subscription', ['provider_subscription_id' => $w->providerSubscriptionId]);
            return;
        }

        app(SecondChanceService::class)->registerCharge($sub, $w);
    }
}
