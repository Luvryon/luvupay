<?php

namespace App\Events\PayPal;

use App\DTOs\Payment\ProviderWebhookEvent;
use Illuminate\Foundation\Events\Dispatchable;

/** Base for all verified PayPal webhooks. Listeners should be queued and idempotent (PayPal may redeliver). */
abstract class PayPalWebhookEvent
{
    use Dispatchable;

    public function __construct(public readonly ProviderWebhookEvent $webhook) {}
}
