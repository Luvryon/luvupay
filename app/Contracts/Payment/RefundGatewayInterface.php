<?php

namespace App\Contracts\Payment;

use App\DTOs\Payment\RefundResult;
use App\Support\Money;

interface RefundGatewayInterface
{
    /** @throws \App\Exceptions\PaymentProviderException */
    public function refundCapture(string $captureId, Money $amount, string $idempotencyKey, string $note = ''): RefundResult;
}
