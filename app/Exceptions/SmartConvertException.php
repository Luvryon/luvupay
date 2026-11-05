<?php

namespace App\Exceptions;

use RuntimeException;

/** Smart-Convert could not complete honestly. $stage tells the UI which hop failed (ai | paypal). */
final class SmartConvertException extends RuntimeException
{
    public function __construct(public readonly string $stage, string $message, public readonly array $context = [])
    {
        parent::__construct($message);
    }
}
