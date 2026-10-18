<?php

namespace App\DTOs\Payment;

use App\Support\Money;

/**
 * Provider-neutral view of a verified webhook. Positional order is relied on by WebhookHandler::parse()
 * and MockPaymentProvider::parseWebhook(); listeners read ->type / ->payload.
 */
final class ProviderWebhookEvent
{
    public function __construct(
        public readonly string $eventId,
        public readonly string $type,
        public readonly ?string $providerSubscriptionId = null,
        public readonly ?string $providerCaptureId = null,
        public readonly ?Money $amount = null,
        public readonly array $payload = [],
        public readonly ?string $customId = null,
        public readonly ?string $providerOrderId = null,
    ) {}
}
