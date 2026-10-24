<?php

namespace App\Services\Payment;

use App\Contracts\Payment\PaymentProviderInterface;
use App\DTOs\Payment\CaptureResult;
use App\DTOs\Payment\OrderResult;
use App\DTOs\Payment\PayoutRequest;
use App\DTOs\Payment\PayoutResult;
use App\DTOs\Payment\ProviderSubscription;
use App\DTOs\Payment\ProviderWebhookEvent;
use App\DTOs\Payment\RefundResult;
use App\Support\Money;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/** Deterministic, idempotent fake. Replays with the same key return the first result, like PayPal. */
class MockPaymentProvider implements PaymentProviderInterface
{
    public function name(): string { return 'mock'; }

    public function createSubscription(string $planId, string $email, string $customId, string $returnUrl, string $cancelUrl, string $idempotencyKey): ProviderSubscription
    {
        return new ProviderSubscription('I-MOCK'.Str::upper(Str::random(10)), 'APPROVAL_PENDING', Money::of(2000, 'USD'), null, $email, [], 'https://mock.paypal.test/approve');
    }

    public function createOrder(Money $amount, string $referenceId, string $returnUrl, string $cancelUrl, string $idempotencyKey, ?string $customId = null): OrderResult
    {
        return Cache::rememberForever("mock:order:$idempotencyKey", fn () =>
            new OrderResult('MOCK-ORD-'.Str::upper(Str::random(10)), 'CREATED', 'https://mock.paypal.test/checkout'));
    }

    public function capturePayment(string $orderId, string $idempotencyKey): CaptureResult
    {
        return Cache::rememberForever("mock:capture:$idempotencyKey", fn () =>
            new CaptureResult('MOCK-CAP-'.Str::upper(Str::random(10)), $orderId, 'COMPLETED', Money::of(1000, 'USD'), 'buyer@example.com'));
    }

    public function getSubscription(string $id): ProviderSubscription
    {
        return new ProviderSubscription($id, 'ACTIVE', Money::of(2000, 'USD'), now()->addDays(29)->toDateTimeImmutable(), 'user@example.com');
    }

    public function suspendSubscription(string $id, string $reason): ProviderSubscription
    {
        Log::info('[mock] suspend', compact('id', 'reason'));
        return new ProviderSubscription($id, 'SUSPENDED', Money::of(2000, 'USD'), null);
    }

    public function activateSubscription(string $id, string $reason): ProviderSubscription
    {
        Log::info('[mock] activate', compact('id', 'reason'));
        return new ProviderSubscription($id, 'ACTIVE', Money::of(2000, 'USD'), now()->addDays(30)->toDateTimeImmutable());
    }

    public function cancelSubscription(string $id, string $reason): ProviderSubscription
    {
        Log::info('[mock] cancel', compact('id', 'reason'));
        return new ProviderSubscription($id, 'CANCELLED', Money::of(2000, 'USD'), null);
    }

    public function refundCapture(string $captureId, Money $amount, string $idempotencyKey, ?string $note = null): RefundResult
    {
        return Cache::rememberForever("mock:refund:$idempotencyKey", fn () =>
            new RefundResult('MOCK-RF-'.Str::upper(Str::random(12)), 'COMPLETED', $amount));
    }

    public function sendPayout(PayoutRequest $request, string $idempotencyKey): PayoutResult
    {
        return Cache::rememberForever("mock:payout:$idempotencyKey", fn () =>
            new PayoutResult('MOCK-PB-'.Str::upper(Str::random(12)), 'SUCCESS', $request->amount));
    }

    public function getPayoutStatus(string $batchId): PayoutResult
    {
        return new PayoutResult($batchId, 'SUCCESS', Money::of(0, 'USD'));
    }

    public function verifyWebhook(array $headers, string $rawBody, string $providerWebhookId): bool
    {
        return true; // real driver calls POST /v1/notifications/verify-webhook-signature
    }

    public function parseWebhook(string $rawBody): ProviderWebhookEvent
    {
        $p = json_decode($rawBody, true, 512, JSON_THROW_ON_ERROR);
        $r = $p['resource'] ?? [];
        $cents = isset($r['amount']['total']) ? (int) round(((float) $r['amount']['total']) * 100) : null;

        return new ProviderWebhookEvent(
            $p['id'] ?? (string) Str::uuid(),
            $p['event_type'] ?? 'UNKNOWN',
            $r['billing_agreement_id'] ?? $r['id'] ?? null,
            $r['id'] ?? null,
            $cents !== null ? Money::of($cents, $r['amount']['currency'] ?? 'USD') : null,
            $p,
        );
    }
}
