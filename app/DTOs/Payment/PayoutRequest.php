<?php

namespace App\DTOs\Payment;

use App\Support\Money;

final class PayoutRequest
{
    public function __construct(
        public readonly string $recipientEmail,
        public readonly Money $amount,
        public readonly string $note = '',
        public readonly ?string $senderItemId = null,
    ) {}
}
