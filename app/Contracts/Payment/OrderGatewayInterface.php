<?php

namespace App\Contracts\Payment;

use App\DTOs\Payment\CaptureResult;
use App\DTOs\Payment\OrderResult;
use App\Support\Money;

/** One-off payments (the Resale Marketplace buyer checkout). */
interface OrderGatewayInterface
{
    public function createOrder(Money $amount, string $referenceId, string $returnUrl, string $cancelUrl, string $idempotencyKey, ?string $customId = null): OrderResult;

    /** @throws \App\Exceptions\PaymentProviderException */
    public function capturePayment(string $orderId, string $idempotencyKey): CaptureResult;
}
