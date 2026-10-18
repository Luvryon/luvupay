<?php

namespace App\Listeners;

use App\Enums\SubscriptionStatus as S;
use App\Events\PayPal\PayPalWebhookEvent;
use App\Models\Subscription;
use App\Support\AuditContext;
use Illuminate\Contracts\Queue\ShouldQueue;

/** Keeps subscriptions.status in sync with PayPal. Never overrides LuvuPay-owned states (Resold, PausedByNudge). */
class SyncSubscriptionState implements ShouldQueue
{
    public string $queue = 'default';

    public function whenActivated(PayPalWebhookEvent $e): void
    {
        $this->apply($e, [S::PendingApproval, S::Suspended, S::PausedByNudge], fn () => ['status' => S::Active]);
    }

    public function whenCancelled(PayPalWebhookEvent $e): void
    {
        $this->apply($e, [S::PendingApproval, S::Active, S::Suspended, S::PausedByNudge], fn () => ['status' => S::Cancelled, 'cancelled_at' => now()]);
    }

    public function whenSuspended(PayPalWebhookEvent $e): void
    {
        $this->apply($e, [S::Active], fn () => ['status' => S::Suspended]);   // PausedByNudge is already set by us: leave it
    }

    private function apply(PayPalWebhookEvent $e, array $from, \Closure $changes): void
    {
        app(AuditContext::class)->actor('provider_webhook', 'paypal');
        $sub = Subscription::query()->where('provider_subscription_id', $e->webhook->providerSubscriptionId)->first();
        if ($sub && in_array($sub->status, $from, true)) {
            $sub->update($changes());
        }
    }
}
