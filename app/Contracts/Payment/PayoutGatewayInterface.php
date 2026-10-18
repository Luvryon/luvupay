<?php

namespace App\Contracts\Payment;

use App\DTOs\Payment\PayoutRequest;
use App\DTOs\Payment\PayoutResult;

interface PayoutGatewayInterface
{
    /** @throws \App\Exceptions\PaymentProviderException */
    public function sendPayout(PayoutRequest $request, string $idempotencyKey): PayoutResult;
}
