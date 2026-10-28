<?php

namespace App\Http\Controllers\Demo;

use App\Http\Controllers\Demo\Concerns\DemoSupport;
use App\Mail\PlainHtmlMail;
use App\Services\Billing\InvoiceIssuer;
use App\Services\PayPal\PayPalClient;
use App\Support\MerchantProject;
use App\Support\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

/**
 * Developer Sandbox: "New subscription checkout".
 *
 * Two modes, decided ONLY by LUVUPAY_PAYMENT_DRIVER (+ credentials):
 *  - mock   : everything is simulated in the DB, the subscription is ACTIVE immediately.
 *  - paypal : rows are created as PENDING, the buyer approves on PayPal (popup or redirect) and ONLY after
 *             PayPal confirms the payment do we activate the subscription, issue the invoice and send the emails.
 *
 * PayPal never moves money without the buyer's approval, not even in the sandbox, so approval is unavoidable.
 * With the Billing Subscriptions API the buyer approves ONCE and PayPal then bills every period by itself.
 *
 * Gated by the demo.simulator middleware (off by default in production).
 */
final class DemoCheckoutController
{
    use DemoSupport;

    /** Pending checkouts older than this are abandoned popups / closed tabs and get purged. */
    private const ABANDON_AFTER_MINUTES = 30;

    // ------------------------------------------------------------------ start

    /**
     * POST /api/v1/dashboard/demo/checkout
     *
     * Any unexpected failure becomes a readable JSON error instead of a bare HTTP 500,
     * so the checkout modal can tell the user what went wrong.
     */
    public function simulateCheckout(Request $request): JsonResponse
    {
        try {
            return $this->startCheckout($request);
        } catch (ValidationException|HttpExceptionInterface $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('demo.checkout.failed', ['err' => $e->getMessage(), 'at' => $e->getFile().':'.$e->getLine()]);

            return response()->json([
                'ok' => false,
                'error' => 'checkout_failed',
                'message' => 'Checkout could not be started: '.Str::limit($e->getMessage(), 160),
            ], 500);
        }
    }

    private function startCheckout(Request $request): JsonResponse
    {
        $merchantId = $this->merchantId($request);

        $data = $request->validate([
            'email' => ['nullable', 'email', 'max:190'],
            'display_name' => ['nullable', 'string', 'max:120'],
            'popup' => ['nullable', 'boolean'],
        ]);

        // The plan and price come from the merchant's project, never from the request.
        $project = MerchantProject::current();
        $currency = $project->isSet() ? strtoupper($project->currency) : $this->merchantCurrency($merchantId);
        $amountMinor = $project->priceMinor;
        $product = $project->product();
        $interval = $project->interval ?: 'month';

        $name = trim(strip_tags((string) ($data['display_name'] ?? ''))) ?: 'John Doe';
        $email = strtolower(trim((string) ($data['email'] ?? ''))) ?: ('john.doe.'.Str::lower(Str::random(4)).'@example.com');
        $popup = (bool) ($data['popup'] ?? false);

        $driver = $this->driver();
        $live = $this->liveMode();
        if ($driver === 'paypal' && ! $live) {
            return response()->json([
                'ok' => false,
                'error' => 'paypal_not_configured',
                'message' => 'LUVUPAY_PAYMENT_DRIVER=paypal but PAYPAL_CLIENT_ID / PAYPAL_SECRET are empty. Fill them from developer.paypal.com (Sandbox app) or set LUVUPAY_PAYMENT_DRIVER=mock',
            ], 422);
        }

        $this->purgeAbandoned($merchantId);

        $now = now();
        $userId = 'demo_user_'.Str::lower(Str::random(6));
        $subId = (string) Str::uuid();
        $txId = (string) Str::uuid();
        $ref = ($live ? 'PENDING-' : 'MOCK-').strtoupper(Str::random(10));
        $trust = random_int(72, 96);
        $churn = $trust >= 80 ? 'low' : ($trust >= 50 ? 'medium' : 'high');

        $meta = [
            'demo' => true,
            'source' => 'developer_sandbox',
            'display_name' => $name,
            'product' => $product,
            'plan_id' => 'premium_monthly',
        ];

        DB::transaction(function () use ($merchantId, $currency, $now, $userId, $email, $subId, $txId, $ref, $trust, $churn, $amountMinor, $live, $meta) {
            DB::table('subscriptions')->insert([
                'id' => $subId,
                'merchant_id' => $merchantId,
                'merchant_user_id' => $userId,
                'email' => $email,
                'provider' => $live ? 'paypal' : 'mock',
                'provider_subscription_id' => 'I-DEMO-'.strtoupper(Str::random(8)),   // replaced by PayPal's real id in live mode
                'provider_plan_id' => 'premium_monthly',
                'status' => $live ? 'pending_approval' : 'active',
                'amount_minor' => $amountMinor,
                'currency' => $currency,
                'current_period_start' => $now,
                'current_period_end' => $now->copy()->addDays(30),
                'next_billing_at' => $now->copy()->addDays(30),
                'forgotten_cancel_count' => 0,
                'metadata' => json_encode($meta + ['awaiting_paypal' => $live]),
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('transactions')->insert([
                'id' => $txId,
                'merchant_id' => $merchantId,
                'subscription_id' => $subId,
                'type' => 'charge',
                'status' => $live ? 'pending' : 'completed',
                'idempotency_key' => 'sandbox-checkout-'.$txId,
                'merchant_user_id' => $userId,
                'email' => $email,
                'amount_minor' => $amountMinor,
                'currency' => $currency,
                'provider' => $live ? 'paypal' : 'mock',
                'provider_reference' => $ref,
                'processed_at' => $live ? null : $now,
                'trust_score' => $trust,
                'churn_risk' => $churn,
                'metadata' => json_encode($meta + [
                    'lifecycle' => $live ? 'PENDING_PAYMENT' : 'ACTIVE',
                    'awaiting_paypal' => $live,
                    'popup' => $live ? $popup : false,
                ]),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        });

        // ---------------------------------------------------------------- mock: done right away
        if (! $live) {
            $this->logWebhookEvent('paypal', 'PAYMENT.CAPTURE.COMPLETED', [
                'id' => $ref, 'status' => 'COMPLETED',
                'amount' => ['currency_code' => $currency, 'value' => number_format($amountMinor / 100, 2, '.', '')],
                'custom_id' => $txId, 'mock' => true,
            ]);
            $this->logWebhookEvent('paypal', 'BILLING.SUBSCRIPTION.ACTIVATED', ['id' => 'I-MOCK', 'status' => 'ACTIVE', 'custom_id' => $txId, 'mock' => true]);
            $this->logWebhookEvent('luvupay', 'subscription.activated', ['transaction_id' => $txId, 'email' => $email]);

            $invoice = $this->ensureInvoice($merchantId, $txId, $name, $product);
            $this->recordOutboundDelivery($merchantId, 'subscription.activated', [
                'transaction_id' => $txId, 'subscription_id' => $subId, 'email' => $email, 'invoice_number' => $invoice['invoice_number'] ?? null,
            ], success: true);
            $mail = $this->safeEmails($merchantId, $email, $name, $product, $amountMinor, $currency, $ref, $txId);

            return $this->doneResponse($txId, 'mock', $name, $email, $product, $trust, $churn, $mail, 201);
        }

        // ---------------------------------------------------------------- paypal: create approval
        $this->logWebhookEvent('luvupay', 'checkout.pending', ['transaction_id' => $txId, 'email' => $email, 'status' => 'PENDING_BUYER_APPROVAL']);

        try {
            $approval = $this->createPayPalApproval($txId, $subId, $product, $interval, Money::of($amountMinor, $currency), $popup);
        } catch (\Throwable $e) {
            $this->logWebhookEvent('paypal', 'SANDBOX.CHECKOUT_FAILED', ['transaction_id' => $txId, 'error' => $e->getMessage()], 'failed');
            $this->discard($merchantId, $txId);   // nothing was paid, do not leave a ghost row behind

            return response()->json([
                'ok' => false,
                'error' => 'paypal_unavailable',
                'message' => 'PayPal Sandbox refused the checkout: '.Str::limit($e->getMessage(), 180),
            ], 502);
        }

        $row = $this->formatRow($txId);

        return response()->json([
            'ok' => true,
            'message' => 'Waiting for the buyer to approve the payment on PayPal',
            'mode' => 'paypal',
            'requires_approval' => true,
            'transaction_id' => $txId,
            'kind' => $approval['kind'],
            'paypal_sandbox' => [
                'live' => true,
                'mode' => config('services.paypal.mode', 'sandbox'),
                'kind' => $approval['kind'],
                'approve_url' => $approval['approve_url'],
                'order_id' => $approval['order_id'] ?? null,
                'subscription_id' => $approval['subscription_id'] ?? null,
            ],
            'row' => $row,
            'recent_events' => $this->recentWebhookEvents(10),
        ], 201);
    }

    // ------------------------------------------------------------------ return / cancel / status

    /** GET /dashboard/paypal/return: the buyer came back from PayPal. Settle the checkout, then close the popup or redirect. */
    public function paypalReturn(Request $request)
    {
        $merchantId = $this->merchantId($request);
        $txId = (string) $request->query('tx', '');
        $result = $this->settle($merchantId, $txId, $request->query('token'), $request->query('subscription_id'));

        return $this->finish($request, $result, $txId, 'return');
    }

    /** GET /dashboard/paypal/cancel */
    public function paypalCancel(Request $request)
    {
        $merchantId = $this->merchantId($request);
        $txId = (string) $request->query('tx', '');

        $this->logWebhookEvent('paypal', 'CHECKOUT.ORDER.CANCELLED', ['transaction_id' => $txId], 'failed');
        $discarded = $this->discard($merchantId, $txId);

        return $this->finish($request, ['state' => 'cancelled', 'message' => 'Checkout cancelled, no money moved', 'discarded' => $discarded], $txId, 'cancel');
    }

    /** GET /api/v1/dashboard/demo/checkout/{tx}/status: used by the popup watcher; re-checks PayPal while still pending. */
    public function checkoutStatus(Request $request, string $tx): JsonResponse
    {
        $merchantId = $this->merchantId($request);
        $row = $this->ownedTx($merchantId, $tx);
        if (! $row) {
            return response()->json(['ok' => true, 'state' => 'gone', 'message' => 'Checkout was cancelled or expired']);
        }
        $result = $row->status === 'completed'
            ? ['state' => 'captured', 'message' => 'Payment confirmed']
            : $this->settle($merchantId, $tx, null, null);

        // The popup was closed and the buyer never approved: nothing was paid, drop the pending rows.
        if ($request->boolean('abandon') && ($result['state'] ?? '') === 'processing' && ! empty($result['awaiting_buyer'])) {
            $this->logWebhookEvent('paypal', 'CHECKOUT.ORDER.CANCELLED', ['transaction_id' => $tx, 'reason' => 'popup_closed'], 'failed');
            $this->discard($merchantId, $tx);

            return response()->json(['ok' => true, 'state' => 'cancelled', 'message' => 'Checkout cancelled, no money moved', 'row' => null]);
        }

        return response()->json(['ok' => true] + $result + ['row' => $this->ownedTx($merchantId, $tx) ? $this->formatRow($tx) : null]);
    }

    // ------------------------------------------------------------------ settle (idempotent)

    /**
     * Verify with PayPal that the buyer really paid, then activate. Safe to call many times.
     *
     * @return array{state:string,message:string,error?:string,awaiting_buyer?:bool}  state: captured | processing | failed | not_found
     */
    private function settle(string $merchantId, string $txId, ?string $queryToken, ?string $querySubscriptionId): array
    {
        $row = $this->ownedTx($merchantId, $txId);
        if (! $row) {
            return ['state' => 'not_found', 'message' => 'Checkout not found for this merchant'];
        }
        if ($row->status === 'completed') {
            return ['state' => 'captured', 'message' => 'Payment already confirmed'];
        }
        if ($row->status !== 'pending') {
            return ['state' => 'failed', 'message' => 'Checkout is '.$row->status.'.'];
        }

        $meta = json_decode($row->metadata ?? '{}', true) ?: [];
        $kind = (string) ($meta['paypal_kind'] ?? '');

        try {
            $client = PayPalClient::fromConfig();

            if ($kind === 'subscription') {
                $paypalSubId = (string) ($meta['paypal_subscription_id'] ?? '');
                if ($paypalSubId === '' || ($querySubscriptionId && $querySubscriptionId !== $paypalSubId)) {
                    return $this->fail($merchantId, $txId, 'Subscription id mismatch.');
                }
                $sub = $client->getSubscription($paypalSubId);
                $status = strtoupper((string) ($sub['status'] ?? ''));
                // PayPal flips APPROVED -> ACTIVE a moment after the first payment; wait a little for it.
                for ($i = 0; $i < 4 && $status === 'APPROVED'; $i++) {
                    usleep(900_000);
                    $sub = $client->getSubscription($paypalSubId);
                    $status = strtoupper((string) ($sub['status'] ?? ''));
                }
                if (in_array($status, ['SUSPENDED', 'CANCELLED', 'EXPIRED'], true)) {
                    return $this->fail($merchantId, $txId, 'PayPal subscription is '.$status.'.');
                }
                if ($status !== 'ACTIVE') {
                    if ($status === 'APPROVAL_PENDING') {
                        return ['state' => 'processing', 'awaiting_buyer' => true, 'message' => 'The buyer has not approved the payment on PayPal yet'];
                    }
                    $this->logWebhookEvent('paypal', 'BILLING.SUBSCRIPTION.APPROVED', ['transaction_id' => $txId, 'status' => $status ?: 'UNKNOWN']);

                    return ['state' => 'processing', 'message' => 'Approved. Waiting for PayPal to confirm the first payment'];
                }
                $capture = $sub['billing_info']['last_payment'] ?? [];
                $this->activate($merchantId, $row, $meta, [
                    'provider_subscription_id' => $paypalSubId,
                    'reference' => (string) ($meta['paypal_subscription_id'] ?? $paypalSubId),
                    'next_billing' => $sub['billing_info']['next_billing_time'] ?? null,
                    'paid_amount' => $capture['amount']['value'] ?? null,
                ]);

                return ['state' => 'captured', 'message' => 'Subscription active. Funds are in your PayPal sandbox business balance'];
            }

            // ---- one-off order
            $orderId = (string) ($meta['paypal_order_id'] ?? '');
            if ($orderId === '' || ($queryToken && $queryToken !== $orderId)) {
                return $this->fail($merchantId, $txId, 'Order id mismatch.');
            }
            $order = $client->getOrder($orderId);
            $orderStatus = strtoupper((string) ($order['status'] ?? ''));
            $unit = $order['purchase_units'][0] ?? [];
            $amt = $unit['payments']['captures'][0]['amount'] ?? $unit['amount'] ?? [];
            if (isset($amt['value']) && (
                (int) round(((float) $amt['value']) * 100) !== (int) $row->amount_minor
                || strtoupper((string) ($amt['currency_code'] ?? $row->currency)) !== strtoupper(trim((string) $row->currency))
            )) {
                return $this->fail($merchantId, $txId, 'Paid amount does not match the checkout.');
            }
            if ($orderStatus === 'CREATED' || $orderStatus === 'PAYER_ACTION_REQUIRED') {
                return ['state' => 'processing', 'awaiting_buyer' => true, 'message' => 'The buyer has not approved the payment on PayPal yet'];
            }

            $captureId = $unit['payments']['captures'][0]['id'] ?? null;
            $capStatus = strtoupper((string) ($unit['payments']['captures'][0]['status'] ?? ''));
            if ($orderStatus === 'APPROVED') {
                $cap = $client->capturePayment($orderId, 'demo-capture-'.$orderId);
                $captureId = $cap['purchase_units'][0]['payments']['captures'][0]['id'] ?? null;
                $capStatus = strtoupper((string) ($cap['purchase_units'][0]['payments']['captures'][0]['status'] ?? ($cap['status'] ?? '')));
                $this->logWebhookEvent('paypal', 'PAYMENT.CAPTURE.COMPLETED', [
                    'order_id' => $orderId, 'status' => $capStatus, 'transaction_id' => $txId, 'capture' => $captureId,
                ]);
            }
            if ($capStatus === 'PENDING') {
                return ['state' => 'processing', 'message' => 'Payment is pending at PayPal'];
            }
            if ($capStatus !== 'COMPLETED' && $orderStatus !== 'COMPLETED') {
                return $this->fail($merchantId, $txId, 'PayPal did not complete the payment ('.($capStatus ?: $orderStatus).').');
            }
            $this->activate($merchantId, $row, $meta, ['reference' => $captureId ?: $orderId]);

            return ['state' => 'captured', 'message' => 'Payment captured. Funds are in your PayPal sandbox business balance'];
        } catch (\Throwable $e) {
            $this->logWebhookEvent('paypal', 'PAYMENT.CAPTURE.FAILED', ['transaction_id' => $txId, 'error' => $e->getMessage()], 'failed');
            Log::warning('demo.checkout.settle_failed', ['tx' => $txId, 'err' => $e->getMessage()]);

            return ['state' => 'failed', 'message' => 'Could not confirm the payment with PayPal', 'error' => Str::limit($e->getMessage(), 160)];
        }
    }

    /** Pending -> active, once. The conditional UPDATE is the lock: a refresh or the popup watcher cannot activate twice. */
    private function activate(string $merchantId, object $row, array $meta, array $info): void
    {
        $now = now();
        $meta['lifecycle'] = 'ACTIVE';
        $meta['awaiting_paypal'] = false;
        $meta['paypal_confirmed_at'] = $now->toIso8601String();
        unset($meta['popup']);

        $won = DB::table('transactions')->where('id', $row->id)->where('status', 'pending')->update([
            'status' => 'completed',
            'provider' => 'paypal',
            'provider_reference' => $info['reference'] ?? $row->provider_reference,
            'processed_at' => $now,
            'metadata' => json_encode($meta),
            'updated_at' => $now,
        ]);
        if ($won === 0) {
            return;   // another request already activated it
        }

        if (! empty($row->subscription_id)) {
            $subUpdate = ['status' => 'active', 'updated_at' => $now];
            if (! empty($info['provider_subscription_id'])) {
                $subUpdate['provider_subscription_id'] = $info['provider_subscription_id'];
            }
            if (! empty($info['next_billing'])) {
                try {
                    $subUpdate['next_billing_at'] = \Illuminate\Support\Carbon::parse($info['next_billing']);
                    $subUpdate['current_period_end'] = $subUpdate['next_billing_at'];
                } catch (\Throwable $e) {
                }
            }
            $subMeta = json_decode((string) DB::table('subscriptions')->where('id', $row->subscription_id)->value('metadata'), true) ?: [];
            $subMeta['awaiting_paypal'] = false;
            $subUpdate['metadata'] = json_encode($subMeta);
            DB::table('subscriptions')->where('id', $row->subscription_id)->update($subUpdate);
        }

        $name = (string) ($meta['display_name'] ?? 'Customer');
        $product = (string) ($meta['product'] ?? MerchantProject::current()->product());
        $this->logWebhookEvent('paypal', 'BILLING.SUBSCRIPTION.ACTIVATED', [
            'transaction_id' => $row->id, 'subscription_id' => $row->subscription_id, 'status' => 'ACTIVE', 'reference' => $info['reference'] ?? null,
        ]);
        $this->logWebhookEvent('luvupay', 'subscription.activated', [
            'transaction_id' => $row->id, 'email' => $row->email, 'funds' => 'deposited_to_paypal_sandbox_business',
        ]);

        // Invoice + emails only now that the money is really in.
        $invoice = $this->ensureInvoice($merchantId, (string) $row->id, $name, $product);
        $this->recordOutboundDelivery($merchantId, 'subscription.activated', [
            'transaction_id' => $row->id, 'subscription_id' => $row->subscription_id, 'email' => $row->email, 'invoice_number' => $invoice['invoice_number'] ?? null,
        ], success: true);
        $this->safeEmails($merchantId, (string) $row->email, $name, $product, (int) $row->amount_minor, trim((string) $row->currency), (string) ($info['reference'] ?? $row->provider_reference), (string) $row->id);
    }

    private function fail(string $merchantId, string $txId, string $why): array
    {
        $this->logWebhookEvent('paypal', 'PAYMENT.CAPTURE.FAILED', ['transaction_id' => $txId, 'error' => $why], 'failed');
        $this->discard($merchantId, $txId);

        return ['state' => 'failed', 'message' => $why];
    }

    // ------------------------------------------------------------------ PayPal calls

    /**
     * Creates the PayPal approval (subscription first, order as fallback) and stores its ids on our pending rows.
     *
     * @return array{kind:string,approve_url:string,order_id?:string,subscription_id?:string}
     */
    private function createPayPalApproval(string $txId, string $subId, string $product, string $interval, Money $price, bool $popup): array
    {
        $client = PayPalClient::fromConfig();
        $token = $client->accessToken(true);
        $this->logWebhookEvent('paypal', 'OAUTH2.CLIENT_CREDENTIALS', [
            'transaction_id' => $txId,
            'mode' => config('services.paypal.mode', 'sandbox'),
            'token_preview' => substr($token, 0, 8).'…',
        ]);

        $q = '?tx='.$txId.($popup ? '&popup=1' : '');
        $returnUrl = url('/dashboard/paypal/return'.$q);
        $cancelUrl = url('/dashboard/paypal/cancel'.$q);

        $mode = (string) config('luvupay.checkout.mode', 'subscription');
        $subscriptionError = null;

        if ($mode !== 'order') {
            try {
                $planId = $this->ensurePlan($client, $product, $interval, $price);
                $sub = $client->createSubscription($planId, '', $txId, $returnUrl, $cancelUrl, 'demo-sub-'.$txId);
                $approveUrl = PayPalClient::link($sub, 'approve');
                $paypalSubId = (string) ($sub['id'] ?? '');
                if (! $approveUrl || $paypalSubId === '') {
                    throw new \RuntimeException('PayPal returned no approval link');
                }
                $this->logWebhookEvent('paypal', 'BILLING.SUBSCRIPTION.CREATED', [
                    'transaction_id' => $txId, 'subscription_id' => $paypalSubId, 'plan_id' => $planId, 'status' => $sub['status'] ?? null,
                ]);
                $this->storePaypalIds($txId, $subId, ['paypal_kind' => 'subscription', 'paypal_subscription_id' => $paypalSubId, 'paypal_plan_id' => $planId], $paypalSubId, $paypalSubId);

                return ['kind' => 'subscription', 'approve_url' => $approveUrl, 'subscription_id' => $paypalSubId];
            } catch (\Throwable $e) {
                $subscriptionError = $e->getMessage();
                $this->logWebhookEvent('paypal', 'SANDBOX.SUBSCRIPTION_FAILED', [
                    'transaction_id' => $txId, 'error' => $subscriptionError, 'fallback' => 'order',
                ], 'failed');
            }
        }

        $order = $client->createOrder($price, $txId, $returnUrl, $cancelUrl, 'demo-order-'.$txId, $txId);
        $orderId = (string) ($order['id'] ?? '');
        $approveUrl = PayPalClient::link($order, 'payer-action', 'approve');
        if ($orderId === '' || ! $approveUrl) {
            throw new \RuntimeException('PayPal returned no approval link'.($subscriptionError ? ' (subscription: '.$subscriptionError.')' : ''));
        }
        $this->logWebhookEvent('paypal', 'CHECKOUT.ORDER.CREATED', [
            'transaction_id' => $txId, 'order_id' => $orderId, 'status' => $order['status'] ?? null,
            'amount' => $price->toDecimalString().' '.$price->currency,
        ]);
        $this->storePaypalIds($txId, $subId, ['paypal_kind' => 'order', 'paypal_order_id' => $orderId], null, $orderId);

        return ['kind' => 'order', 'approve_url' => $approveUrl, 'order_id' => $orderId];
    }

    /** One PayPal product + plan per (project, price, interval): created on first use, then cached. */
    private function ensurePlan(PayPalClient $client, string $product, string $interval, Money $price): string
    {
        $forced = trim((string) config('services.paypal.plan_id', ''));
        if ($forced !== '') {
            return $forced;
        }

        $unit = match (strtolower($interval)) {
            'day' => 'DAY', 'week' => 'WEEK', 'year', 'annual', 'yearly' => 'YEAR', default => 'MONTH',
        };
        $name = $product !== '' ? $product : 'LuvuPay Subscription';
        $scope = sha1((string) config('services.paypal.client_id').'|'.config('services.paypal.mode', 'sandbox'));
        $planKey = 'paypal:plan:'.$scope.':'.sha1($name.'|'.$price->currency.'|'.$price->amountMinor.'|'.$unit);

        if ($cached = Cache::get($planKey)) {
            return (string) $cached;
        }

        $productKey = 'paypal:product:'.$scope.':'.sha1($name);
        $productId = Cache::get($productKey);
        if (! $productId) {
            $p = $client->createProduct($name, 'lp-prod-'.sha1($scope.$name));
            $productId = (string) ($p['id'] ?? throw new \RuntimeException('PayPal returned no product id'));
            Cache::forever($productKey, $productId);
        }

        $plan = $client->createPlan((string) $productId, $name.' ('.$price->toDecimalString().' '.$price->currency.' / '.strtolower($unit).')', $price, $unit, 'lp-plan-'.sha1($planKey));
        $planId = (string) ($plan['id'] ?? throw new \RuntimeException('PayPal returned no plan id'));
        Cache::forever($planKey, $planId);
        $this->logWebhookEvent('paypal', 'BILLING.PLAN.CREATED', ['plan_id' => $planId, 'product_id' => $productId, 'price' => $price->toDecimalString().' '.$price->currency]);

        return $planId;
    }

    private function storePaypalIds(string $txId, string $subId, array $meta, ?string $paypalSubId, string $reference): void
    {
        $tx = DB::table('transactions')->where('id', $txId)->first();
        $m = json_decode($tx->metadata ?? '{}', true) ?: [];
        DB::table('transactions')->where('id', $txId)->update([
            'provider_reference' => $reference,
            'metadata' => json_encode($m + $meta),
            'updated_at' => now(),
        ]);
        $sub = ['updated_at' => now()];
        if ($paypalSubId) {
            $sub['provider_subscription_id'] = $paypalSubId;
        }
        DB::table('subscriptions')->where('id', $subId)->update($sub);
    }

    // ------------------------------------------------------------------ helpers

    private function driver(): string
    {
        return (string) (config('luvupay.payment.driver') ?: env('LUVUPAY_PAYMENT_DRIVER', 'mock'));
    }

    /** Real PayPal only when the driver says so AND credentials exist. A leftover client id never forces PayPal in mock mode. */
    private function liveMode(): bool
    {
        return $this->driver() === 'paypal'
            && trim((string) config('services.paypal.client_id', '')) !== ''
            && trim((string) config('services.paypal.secret', '')) !== '';
    }

    private function ownedTx(string $merchantId, string $txId): ?object
    {
        if (! preg_match('/^[0-9a-f-]{36}$/i', $txId)) {
            return null;
        }

        return DB::table('transactions')->where('id', $txId)->where('merchant_id', $merchantId)->first() ?: null;
    }

    /** Drop a never-paid checkout (pending only). Returns true when something was removed. */
    private function discard(string $merchantId, string $txId): bool
    {
        $row = $this->ownedTx($merchantId, $txId);
        if (! $row || $row->status !== 'pending') {
            return false;
        }
        try {
            DB::transaction(function () use ($row) {
                DB::table('invoices')->where('transaction_id', $row->id)->delete();
                DB::table('transactions')->where('id', $row->id)->where('status', 'pending')->delete();
                if (! empty($row->subscription_id)) {
                    DB::table('subscriptions')->where('id', $row->subscription_id)->where('status', 'pending_approval')->delete();
                }
            });

            return true;
        } catch (\Throwable $e) {
            Log::warning('demo.checkout.discard_failed', ['tx' => $txId ?? $row->id, 'err' => $e->getMessage()]);

            return false;
        }
    }

    private function purgeAbandoned(string $merchantId): void
    {
        try {
            $stale = DB::table('transactions')
                ->where('merchant_id', $merchantId)->where('status', 'pending')->where('type', 'charge')
                ->where('created_at', '<', now()->subMinutes(self::ABANDON_AFTER_MINUTES))
                ->get(['id', 'metadata']);
            foreach ($stale as $r) {
                $meta = json_decode($r->metadata ?? '{}', true) ?: [];
                if (($meta['source'] ?? '') === 'developer_sandbox' && ! empty($meta['awaiting_paypal'])) {
                    $this->discard($merchantId, (string) $r->id);
                }
            }
        } catch (\Throwable $e) {
        }
    }

    /** @return array{id:string,invoice_number:string}|array{} */
    private function ensureInvoice(string $merchantId, string $txId, string $name, string $product): array
    {
        try {
            $existing = DB::table('invoices')->where('transaction_id', $txId)->first();
            if ($existing) {
                return ['id' => $existing->id, 'invoice_number' => $existing->invoice_number];
            }
            $tx = DB::table('transactions')->where('id', $txId)->first();

            return app(InvoiceIssuer::class)->issueForTransaction($merchantId, $tx, ['customer_name' => $name, 'product' => $product]);
        } catch (\Throwable $e) {
            Log::warning('demo.checkout.invoice_failed', ['tx' => $txId, 'err' => $e->getMessage()]);

            return [];
        }
    }

    /** @return array{user_sent:bool,merchant_sent:bool,error:?string} */
    private function safeEmails(string $merchantId, string $email, string $name, string $product, int $amountMinor, string $currency, string $ref, string $txId): array
    {
        try {
            return $this->sendCheckoutEmails($merchantId, $email, $name, $product, $amountMinor, $currency, $ref, $txId);
        } catch (\Throwable $e) {
            return ['user_sent' => false, 'merchant_sent' => false, 'error' => $e->getMessage()];
        }
    }

    private function doneResponse(string $txId, string $mode, string $name, string $email, string $product, int $trust, string $churn, array $mail, int $status): JsonResponse
    {
        $row = $this->formatRow($txId);

        return response()->json([
            'ok' => true,
            'message' => 'Checkout complete: subscription ACTIVE · Invoice '.($row['invoice_number'] ?? 'n/a'),
            'mode' => $mode,
            'requires_approval' => false,
            'transaction_id' => $txId,
            'email_sent' => $mail['user_sent'] ?? false,
            'merchant_email_sent' => $mail['merchant_sent'] ?? false,
            'email_error' => $mail['error'] ?? null,
            'email_to' => $email,
            'paypal_sandbox' => ['live' => false, 'mock' => true],
            'row' => $row,
            'recent_events' => $this->recentWebhookEvents(10),
            'invoice' => [
                'id' => $row['invoice_id'] ?? null,
                'invoice_number' => $row['invoice_number'] ?? null,
                'url' => isset($row['invoice_id']) ? url('/dashboard/invoices/'.$row['invoice_id']) : null,
            ],
            'preview' => [
                'phase' => 'checkout', 'name' => $name, 'email' => $email, 'product' => $product,
                'status' => 'ACTIVE', 'trust_score' => $trust, 'churn_risk' => $churn,
            ],
        ], $status);
    }

    /** Popup: tiny page that tells the dashboard tab and closes itself. Otherwise: back to the dashboard. */
    private function finish(Request $request, array $result, string $txId, string $leg)
    {
        $state = $result['state'] ?? 'failed';
        if ($request->boolean('popup')) {
            return response()
                ->view('dashboard.paypal-popup', [
                    'state' => $state,
                    'tx' => $txId,
                    'message' => $result['message'] ?? '',
                    'error' => $result['error'] ?? null,
                ])
                ->header('Cache-Control', 'no-store');
        }

        $qs = match ($state) {
            'captured' => 'paypal=captured',
            'processing' => 'paypal=processing',
            'cancelled' => 'paypal=cancelled',
            default => 'paypal=capture_failed&err='.urlencode(mb_substr((string) ($result['error'] ?? $result['message'] ?? 'failed'), 0, 120)),
        };

        return redirect('/dashboard?'.$qs.($txId !== '' ? '&tx='.urlencode($txId) : ''));
    }

    /**
     * @return array{user_sent:bool,merchant_sent:bool,error:?string}
     */
    private function sendCheckoutEmails(
        string $merchantId,
        string $userEmail,
        string $userName,
        string $product,
        int $amountMinor,
        string $currency,
        string $paypalRef,
        string $txId,
    ): array {
        $userSent = false;
        $merchantSent = false;
        $error = null;
        $amountLabel = number_format($amountMinor / 100, 2).' '.$currency;
        // Merchant branding for invoice email
        $merchantRow = DB::table('merchants')->where('id', $merchantId)->first(['id', 'name', 'logo_path', 'email']);
        $merchantBrand = $merchantRow->name ?? 'Merchant';
        $logoUrl = null;
        if (! empty($merchantRow->logo_path)) {
            $logoUrl = \App\Support\MerchantBranding::logoUrl($merchantRow);
        }
        $logoHtml = $logoUrl
            ? '<img src="'.e($logoUrl).'" alt="'.e($merchantBrand).'" width="120" style="display:block;max-width:120px;height:auto;margin:0 auto 12px;border:0" />'
            : '';
        $invNum = null;
        try {
            $invNum = DB::table('invoices')->where('transaction_id', $txId)->value('invoice_number');
        } catch (\Throwable $e) {
        }

        // Subscriber receipt: invoice inline (Bookipi-style) + PDF attached, merchant logo from Settings.
        $res = \App\Services\Billing\InvoiceMailer::sendForTransaction($txId, 'subscription', $userEmail);
        $userSent = $res['sent'];
        $error = $res['error'];
        $this->logWebhookEvent('luvupay', $userSent ? 'email.user_confirmation_sent' : 'email.user_confirmation_failed', [
            'to' => $userEmail,
            'transaction_id' => $txId,
            'invoice_attached' => $userSent,
            'error' => $error,
        ], $userSent ? 'processed' : 'failed');

        // Escape values for the merchant notification HTML body
        $safeName = e($userName);
        $safeEmail = e($userEmail);
        $safeProduct = e($product);
        $safeAmount = e($amountLabel);
        $safePaypal = e($paypalRef);
        $safeInv = $invNum ? e((string) $invNum) : null;

        try {
            $merchant = DB::table('merchants')->where('id', $merchantId)->first();
            $merchantEmail = is_object($merchant) ? trim((string) ($merchant->email ?? '')) : '';
            if ($merchantEmail !== '') {
                $mHtml = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>
<body style="margin:0;padding:0;background:#0f172a;font-family:Inter,Segoe UI,system-ui,sans-serif">
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="padding:32px 16px">
    <tr><td align="center">
      <table role="presentation" width="100%" style="max-width:560px;background:#111827;border:1px solid #1e293b;border-radius:16px;overflow:hidden">
        <tr><td style="padding:24px 28px;background:#0f172a;border-bottom:1px solid #1e293b">
          <p style="margin:0;font-size:12px;font-weight:600;letter-spacing:0.06em;text-transform:uppercase;color:#a78bfa">Merchant notification</p>
          <h1 style="margin:6px 0 0;font-size:20px;color:#fff">New subscription</h1>
        </td></tr>
        <tr><td style="padding:28px;color:#e2e8f0">
          <p style="margin:0 0 16px;font-size:15px;line-height:1.6">
            Customer <strong style="color:#fff">{$safeName}</strong>
            (<a href="mailto:{$safeEmail}" style="color:#a78bfa;text-decoration:none">{$safeEmail}</a>)
            activated <strong style="color:#fff">{$safeProduct}</strong> for <strong style="color:#34d399">{$safeAmount}</strong>.
          </p>
          <p style="margin:0;font-size:13px;color:#94a3b8;font-family:ui-monospace,monospace">
            PayPal ref: {$safePaypal}
          </p>
        </td></tr>
      </table>
    </td></tr>
  </table>
</body>
</html>
HTML;
                if ($safeInv) {
                    $mHtml = str_replace('PayPal ref: '.$safePaypal, 'PayPal ref: '.$safePaypal.' · Invoice '.$safeInv, $mHtml);
                }
                Mail::to($merchantEmail)->sendNow(new PlainHtmlMail(
                    'New subscription: '.$product,
                    $mHtml
                ));
                $merchantSent = true;
                $this->logWebhookEvent('luvupay', 'email.merchant_notification_sent', [
                    'to' => $merchantEmail,
                    'transaction_id' => $txId,
                ]);
            }
        } catch (\Throwable $e) {
            $error = $error ?: $e->getMessage();
            Log::warning('demo.checkout.merchant_email_failed', ['err' => $e->getMessage()]);
        }

        return [
            'user_sent' => $userSent,
            'merchant_sent' => $merchantSent,
            'error' => $error,
        ];
    }
}
