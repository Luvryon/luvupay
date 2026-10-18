<?php

namespace App\DTOs\Payment;

use App\Support\Money;

final class RefundResult
{
    public readonly string $providerRefundId;

    public function __construct(
        public readonly string $refundId,
        public readonly string $status,
        public readonly Money $amount,
        public readonly ?string $error = null,
    ) {
        $this->providerRefundId = $refundId;
    }

    public function succeeded(): bool
    {
        return $this->isSuccessful();
    }

    public function isSuccessful(): bool
    {
        return in_array($this->status, ['COMPLETED', 'PENDING', 'SUCCESS'], true);
    }
}
