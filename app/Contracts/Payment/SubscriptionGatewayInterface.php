<?php

namespace App\Contracts\Payment;

use App\DTOs\Payment\ProviderSubscription;

interface SubscriptionGatewayInterface
{
    public function createSubscription(string $planId, string $subscriberEmail, string $customId, string $returnUrl, string $cancelUrl, string $idempotencyKey): ProviderSubscription;

    public function getSubscription(string $providerSubscriptionId): ProviderSubscription;

    /** Used by the Adaptive Nudge to pause auto-charge before the billing date. */
    public function suspendSubscription(string $providerSubscriptionId, string $reason): ProviderSubscription;

    /** Resume after the user confirms they still want the subscription. */
    public function activateSubscription(string $providerSubscriptionId, string $reason): ProviderSubscription;

    /** Used by the Luvi Engine when "Second Chance" is triggered. */
    public function cancelSubscription(string $providerSubscriptionId, string $reason): ProviderSubscription;
}
