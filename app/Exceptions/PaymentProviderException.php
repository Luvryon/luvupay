<?php

namespace App\Exceptions;

class PaymentProviderException extends LuvuApiException
{
    public function __construct(string $message = 'Payment provider error', int $status = 502, string $code = 'provider_error')
    {
        parent::__construct($code, $message, $status);
    }
}
