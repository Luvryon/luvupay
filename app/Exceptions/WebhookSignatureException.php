<?php

namespace App\Exceptions;

use RuntimeException;

class WebhookSignatureException extends RuntimeException
{
    public function __construct(public readonly string $reason)
    {
        parent::__construct("Webhook signature rejected: {$reason}");
    }
}
