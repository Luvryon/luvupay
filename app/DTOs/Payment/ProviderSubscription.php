<?php

namespace App\DTOs\Payment;

use App\Support\Money;
use DateTimeImmutable;

final readonly class ProviderSubscription
{
    public function __construct(
        public string $providerSubscriptionId,
        public string $status,                    // provider-native status (APPROVAL_PENDING, ACTIVE, SUSPENDED, CANCELLED...)
        public Money $amount,
        public ?DateTimeImmutable $nextBillingAt,
        public ?string $payerEmail = null,
        public array $raw = [],
        public ?string $approvalUrl = null,       // where the end-user approves the subscription (createSubscription only)
    ) {}
}
