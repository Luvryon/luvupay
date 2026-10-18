<?php

namespace App\Exceptions;

use RuntimeException;

class InvalidWebhookSignatureException extends RuntimeException
{
    public function __construct() { parent::__construct('PayPal webhook signature verification failed.'); }
}
