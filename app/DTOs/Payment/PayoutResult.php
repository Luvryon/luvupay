<?php

namespace App\DTOs\Payment;

final class PayoutResult
{
    public readonly string $providerBatchId;

    public function __construct(
        public readonly string $payoutBatchId,
        public readonly string $status,
        public readonly ?string $transactionId = null,
        public readonly ?string $error = null,
    ) {
        $this->providerBatchId = $payoutBatchId;
    }

    public function succeeded(): bool
    {
        return $this->isSuccessful();
    }

    public function isSuccessful(): bool
    {
        return in_array($this->status, ['SUCCESS', 'PENDING', 'UNCLAIMED', 'COMPLETED'], true);
    }
}
