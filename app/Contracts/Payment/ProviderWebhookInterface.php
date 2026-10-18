<?php

namespace App\Contracts\Payment;

use App\DTOs\Payment\ProviderWebhookEvent;
use Illuminate\Http\Request;

interface ProviderWebhookInterface
{
    /** Verify signature and parse the incoming provider webhook. */
    public function parseWebhook(Request $request): ProviderWebhookEvent;
}
