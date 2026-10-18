<?php

namespace App\Services\PayPal;

enum WebhookOutcome: string
{
    case Processed = 'processed';
    case Duplicate = 'duplicate';
    case Ignored = 'ignored';
}
