<?php

namespace App\DTOs\Payment;

final readonly class OrderResult
{
    public function __construct(
        public string $orderId,
        public string $status,                    // CREATED | PAYER_ACTION_REQUIRED | APPROVED ...
        public ?string $approvalUrl,
        public array $raw = [],
    ) {}
}
