<?php

namespace App\Contracts\Payment;

interface PaymentProviderInterface extends
    SubscriptionGatewayInterface,
    OrderGatewayInterface,
    RefundGatewayInterface,
    PayoutGatewayInterface,
    ProviderWebhookInterface
{
    public function name(): string;
}
