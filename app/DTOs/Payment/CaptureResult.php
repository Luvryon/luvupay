<?php

namespace App\DTOs\Payment;

use App\Support\Money;

final readonly class CaptureResult
{
    public function __construct(
        public string $providerCaptureId,
        public string $orderId,
        public string $status,                    // COMPLETED | PENDING | DECLINED ...
        public Money $amount,
        public ?string $payerEmail = null,
        public array $raw = [],
    ) {}

    public function completed(): bool
    {
        return $this->status === 'COMPLETED';
    }
}
