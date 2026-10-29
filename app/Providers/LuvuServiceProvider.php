<?php

namespace App\Providers;

use App\Events\PayPal\{PaymentCaptured, PayoutFailed, PayoutSucceeded, SubscriptionActivated, SubscriptionCancelled, SubscriptionSuspended};
use App\Listeners\{ActivatePendingCheckoutFromWebhook, OpenSecondChanceForCharge, ReconcilePayoutOutcome, SyncSubscriptionState};
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

// bootstrap/providers.php: add App\Providers\LuvuServiceProvider::class
// Listener methods are deliberately NOT named handle(): Laravel 11 event auto-discovery would register them twice.
class LuvuServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Event::listen(PaymentCaptured::class, [OpenSecondChanceForCharge::class, 'process']);
        Event::listen(PaymentCaptured::class, [ActivatePendingCheckoutFromWebhook::class, 'onPaymentCaptured']);
        Event::listen(PayoutSucceeded::class, [ReconcilePayoutOutcome::class, 'whenSucceeded']);
        Event::listen(PayoutFailed::class, [ReconcilePayoutOutcome::class, 'whenFailed']);
        Event::listen(SubscriptionActivated::class, [SyncSubscriptionState::class, 'whenActivated']);
        Event::listen(SubscriptionActivated::class, [ActivatePendingCheckoutFromWebhook::class, 'onSubscriptionActivated']);
        Event::listen(SubscriptionCancelled::class, [SyncSubscriptionState::class, 'whenCancelled']);
        Event::listen(SubscriptionSuspended::class, [SyncSubscriptionState::class, 'whenSuspended']);
    }
}
