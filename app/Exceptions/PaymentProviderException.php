<?php

namespace App\Exceptions;

use Throwable;

/**
 * A payment provider (PayPal) call failed.
 *
 * `$retryable` tells the retry helper and the queue jobs whether trying again can help (network, 429, 5xx).
 * `$context` carries safe diagnostics (HTTP status, issue, debug id), never secrets.
 */
class PaymentProviderException extends LuvuApiException
{
    /** @param array<string,mixed>|null $context */
    public function __construct(
        string $message = 'Payment provider error',
        public readonly bool $retryable = false,
        public readonly ?array $context = null,
        ?Throwable $previous = null,
        int $status = 502,
        string $code = 'provider_error',
    ) {
        parent::__construct($code, $message, $status, $previous);
    }
}
