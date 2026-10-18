<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/** Drop into your product app (AI Tools). Point env at local LuvuPay. */
class LuvuPayClient
{
    public function createSubscription(array $payload): array
    {
        $response = Http::baseUrl(rtrim(config('services.luvupay.base'), '/'))
            ->withToken(config('services.luvupay.secret'))
            ->acceptJson()
            ->withHeaders(['Idempotency-Key' => (string) Str::uuid()])
            ->post('/api/v1/subscriptions', $payload);

        $response->throw();

        return $response->json();
    }

    public function triggerSecondChance(string $transactionId): array
    {
        $response = Http::baseUrl(rtrim(config('services.luvupay.base'), '/'))
            ->withToken(config('services.luvupay.secret'))
            ->acceptJson()
            ->withHeaders(['Idempotency-Key' => (string) Str::uuid()])
            ->post("/api/v1/transactions/{$transactionId}/second-chance");

        $response->throw();

        return $response->json();
    }
}
