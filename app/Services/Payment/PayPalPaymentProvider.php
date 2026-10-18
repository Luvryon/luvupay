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
use App\Exceptions\PaymentProviderException;
use App\Services\PayPal\PayPalAmount;
use App\Services\PayPal\PayPalClient;
use App\Services\PayPal\WebhookHandler;
use App\Support\Money;
use DateTimeImmutable;

/** Adapter: LuvuPay domain contracts -> PayPal REST. The domain layer never sees PayPal JSON. */
class PayPalPaymentProvider implements PaymentProviderInterface
{
    public function __construct(private readonly PayPalClient $client, private readonly WebhookHandler $webhooks) {}

    public function name(): string { return 'paypal'; }

    // ---- subscriptions
    public function createSubscription(string $planId, string $subscriberEmail, string $customId, string $returnUrl, string $cancelUrl, string $idempotencyKey): ProviderSubscription
    {
        $r = $this->client->createSubscription($planId, $subscriberEmail, $customId, $returnUrl, $cancelUrl, $idempotencyKey);

        return $this->mapSubscription($r, PayPalClient::link($r, 'approve'));
    }

    public function getSubscription(string $id): ProviderSubscription
    {
        return $this->mapSubscription($this->client->getSubscription($id));
    }

    public function suspendSubscription(string $id, string $reason): ProviderSubscription
    {
        $this->client->suspendSubscription($id, $reason);
        return $this->getSubscription($id);
    }

    public function activateSubscription(string $id, string $reason): ProviderSubscription
    {
        $this->client->activateSubscription($id, $reason);
        return $this->getSubscription($id);
    }

    public function cancelSubscription(string $id, string $reason): ProviderSubscription
    {
        $this->client->cancelSubscription($id, $reason);
        return $this->getSubscription($id);
    }

    // ---- orders
    public function createOrder(Money $amount, string $referenceId, string $returnUrl, string $cancelUrl, string $idempotencyKey, ?string $customId = null): OrderResult
    {
        $r = $this->client->createOrder($amount, $referenceId, $returnUrl, $cancelUrl, $idempotencyKey, $customId);

        return new OrderResult($r['id'], $r['status'] ?? 'CREATED', PayPalClient::link($r, 'payer-action', 'approve'), $r);
    }

    public function capturePayment(string $orderId, string $idempotencyKey): CaptureResult
    {
        $r = $this->client->capturePayment($orderId, $idempotencyKey);
        $cap = $r['purchase_units'][0]['payments']['captures'][0]
            ?? throw new PaymentProviderException('PayPal capture response had no capture object', false, ['order' => $orderId]);

        return new CaptureResult(
            $cap['id'],
            $r['id'] ?? $orderId,
            $cap['status'] ?? 'UNKNOWN',
            PayPalAmount::toMoney($cap['amount'] ?? null) ?? throw new PaymentProviderException('Capture missing amount'),
            $r['payer']['email_address'] ?? null,
            $r,
        );
    }

    // ---- refunds / payouts
    public function refundCapture(string $captureId, Money $amount, string $idempotencyKey, ?string $note = null): RefundResult
    {
        $r = $this->client->refundPayment($captureId, $amount, $idempotencyKey, $note);

        return new RefundResult($r['id'], $r['status'] ?? 'UNKNOWN', PayPalAmount::toMoney($r['amount'] ?? null) ?? $amount, $r);
    }

    public function sendPayout(PayoutRequest $request, string $idempotencyKey): PayoutResult
    {
        $r = $this->client->createPayout([[
            'receiver' => $request->recipientEmail,
            'amount' => $request->amount,
            'note' => $request->note,
            'sender_item_id' => $request->senderItemId,
        ]], senderBatchId: $idempotencyKey, requestId: $idempotencyKey);

        return $this->mapPayout($r, $request->amount);
    }

    public function getPayoutStatus(string $providerBatchId): PayoutResult
    {
        $r = $this->client->getPayoutBatch($providerBatchId);
        $a = $r['batch_header']['amount'] ?? null;

        return $this->mapPayout($r, $a ? PayPalAmount::toMoney(['value' => $a['value'], 'currency_code' => $a['currency']]) : Money::of(0, 'USD'));
    }

    // ---- inbound webhooks
    public function verifyWebhook(array $headers, string $rawBody, string $providerWebhookId): bool
    {
        return $this->client->verifyWebhookSignature($headers, $rawBody, $providerWebhookId ?: null);
    }

    public function parseWebhook(string $rawBody): ProviderWebhookEvent
    {
        return $this->webhooks->parse($rawBody);
    }

    // ---- mapping
    private function mapSubscription(array $r, ?string $approvalUrl = null): ProviderSubscription
    {
        $next = $r['billing_info']['next_billing_time'] ?? null;
        $amount = PayPalAmount::toMoney($r['billing_info']['last_payment']['amount'] ?? null)
            ?? PayPalAmount::toMoney($r['plan']['billing_cycles'][0]['pricing_scheme']['fixed_price'] ?? null)
            ?? Money::of(0, 'USD');

        return new ProviderSubscription($r['id'], $r['status'] ?? 'UNKNOWN', $amount, $next ? new DateTimeImmutable($next) : null, $r['subscriber']['email_address'] ?? null, $r, $approvalUrl);
    }

    private function mapPayout(array $r, Money $amount): PayoutResult
    {
        $raw = strtoupper($r['batch_header']['batch_status'] ?? 'UNKNOWN');
        // PayPal batch states: PENDING/PROCESSING/SUCCESS/DENIED/CANCELED. Unknown stays unknown (= not succeeded).
        $status = in_array($raw, ['NEW', 'PENDING', 'PROCESSING'], true) ? 'PENDING' : $raw;

        return new PayoutResult($r['batch_header']['payout_batch_id'] ?? throw new PaymentProviderException('Payout response missing batch id'), $status, $amount, $r);
    }
}
