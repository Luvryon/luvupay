<?php

namespace App\Exceptions;

use RuntimeException;

class EntitlementDeliveryException extends RuntimeException
{
    public function __construct(string $message, public readonly bool $permanent = false) { parent::__construct($message); }
}
