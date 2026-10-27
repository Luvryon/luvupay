<?php

namespace App\Http\Controllers\Demo;

use App\Support\MerchantProject;
use App\Http\Controllers\Demo\Concerns\DemoSupport;
use App\Mail\PlainHtmlMail;
use App\Services\Billing\InvoiceIssuer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * Developer Sandbox: Trigger Test: New Subscription Checkout and the PayPal sandbox return/cancel pages.
 * Gated by the demo.simulator middleware (off by default in production).
 */
final class DemoCheckoutController
{
    use DemoSupport;

    /** POST /api/v1/dashboard/demo/checkout — Trigger Test: New Subscription Checkout */
    public function simulateCheckout(Request $request): JsonResponse
    {
        $merchantId = $this->merchantId($request);
        $currency = $this->merchantCurrency($merchantId);
        $now = now();

        $data = $request->validate([
            'email' => ['nullable', 'email', 'max:190'],
            'display_name' => ['nullable', 'string', 'max:120'],
        ]);

        // The plan and price come from the merchant's project, never from the request.
        $project = MerchantProject::current();
        $first = trim(strip_tags((string) ($data['display_name'] ?? ''))) ?: 'John Doe';
        $email = strtolower(trim((string) ($data['email'] ?? ''))) ?: ('john.doe.'.Str::lower(Str::random(4)).'@example.com');
        $product = $project->product();
        $amountMinor = $project->priceMinor;

        $userId = 'demo_user_'.Str::lower(Str::random(6));
        $subId = (string) Str::uuid();
        $txId = (string) Str::uuid();
        $paypalRef = 'PAYPAL-SANDBOX-'.strtoupper(Str::random(10));
        $trust = random_int(72, 96);
        $churn = $trust >= 80 ? 'low' : ($trust >= 50 ? 'medium' : 'high');

        $driver = (string) (config('luvupay.payment.driver') ?: env('LUVUPAY_PAYMENT_DRIVER', 'mock'));
        $clientId = trim((string) config('services.paypal.client_id', env('PAYPAL_CLIENT_ID', '')));
        $secret = trim((string) config('services.paypal.secret', env('PAYPAL_SECRET', '')));
        $hasPayPalCreds = $clientId !== '' && $secret !== '';
        // Real sandbox path: hold local rows as pending until Capture succeeds on return.
        $useLiveSandbox = $hasPayPalCreds && ($driver === 'paypal' || $hasPayPalCreds);
        $provider = $useLiveSandbox ? 'paypal' : ($driver === 'paypal' ? 'paypal' : 'mock');
        $txStatus = $useLiveSandbox ? 'pending' : 'completed';
        $subStatus = $useLiveSandbox ? 'pending_approval' : 'active';
        $lifecycle = $useLiveSandbox ? 'PENDING_PAYMENT' : 'ACTIVE';

        DB::transaction(function () use (
            $merchantId, $currency, $now, $userId, $email, $subId, $txId, $paypalRef,
            $trust, $churn, $first, $product, $amountMinor, $provider, $txStatus, $subStatus, $lifecycle, $useLiveSandbox
        ) {
            DB::table('subscriptions')->insert([
                'id' => $subId,
                'merchant_id' => $merchantId,
                'merchant_user_id' => $userId,
                'email' => $email,
                'provider' => $provider,
                'provider_subscription_id' => 'I-DEMO-'.strtoupper(Str::random(8)),
                'provider_plan_id' => 'premium_monthly',
                'status' => $subStatus,
                'amount_minor' => $amountMinor,
                'currency' => $currency,
                'current_period_start' => $now,
                'current_period_end' => $now->copy()->addDays(30),
                'next_billing_at' => $now->copy()->addDays(30),
                'forgotten_cancel_count' => 0,
                'metadata' => json_encode([
                    'demo' => true,
                    'source' => 'developer_sandbox',
                    'display_name' => $first,
                    'product' => $product,
                    'awaiting_paypal_capture' => $useLiveSandbox,
                ]),
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('transactions')->insert([
                'id' => $txId,
                'merchant_id' => $merchantId,
                'subscription_id' => $subId,
                'type' => 'charge',
                'status' => $txStatus,
                'idempotency_key' => 'sandbox-checkout-'.$txId,
                'merchant_user_id' => $userId,
                'email' => $email,
                'amount_minor' => $amountMinor,
                'currency' => $currency,
                'provider' => $provider,
                'provider_reference' => $paypalRef,
                'processed_at' => $useLiveSandbox ? null : $now,
                'trust_score' => $trust,
                'churn_risk' => $churn,
                'metadata' => json_encode([
                    'demo' => true,
                    'lifecycle' => $lifecycle,
                    'source' => 'developer_sandbox',
                    'plan_id' => 'premium_monthly',
                    'display_name' => $first,
                    'product' => $product,
                    'awaiting_paypal_capture' => $useLiveSandbox,
                ]),
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            // Only emit "captured / activated" webhooks immediately in mock mode.
            // Live sandbox: those fire after real Capture on /dashboard/paypal/return.
            if (! $useLiveSandbox) {
                $this->logWebhookEvent('paypal', 'PAYMENT.CAPTURE.COMPLETED', [
                    'id' => $paypalRef,
                    'status' => 'COMPLETED',
                    'amount' => ['currency_code' => $currency, 'value' => number_format($amountMinor / 100, 2, '.', '')],
                    'custom_id' => $txId,
                ]);
                $this->logWebhookEvent('paypal', 'CHECKOUT.ORDER.APPROVED', [
                    'id' => 'ORDER-'.strtoupper(Str::random(10)),
                    'status' => 'APPROVED',
                    'custom_id' => $txId,
                ]);
                $this->logWebhookEvent('paypal', 'BILLING.SUBSCRIPTION.ACTIVATED', [
                    'id' => 'I-DEMO-'.strtoupper(Str::random(8)),
                    'status' => 'ACTIVE',
                    'custom_id' => $txId,
                ]);
                $this->logWebhookEvent('luvupay', 'subscription.activated', [
                    'transaction_id' => $txId,
                    'email' => $email,
                ]);
            } else {
                $this->logWebhookEvent('paypal', 'CHECKOUT.ORDER.PENDING', [
                    'transaction_id' => $txId,
                    'status' => 'PENDING_BUYER_APPROVAL',
                    'amount' => ['currency_code' => $currency, 'value' => number_format($amountMinor / 100, 2, '.', '')],
                ]);
            }

            $txObj = (object) [
                'id' => $txId,
                'amount_minor' => $amountMinor,
                'currency' => $currency,
                'provider_reference' => $paypalRef,
                'email' => $email,
            ];
            $invoice = app(\App\Services\Billing\InvoiceIssuer::class)->issueForTransaction($merchantId, $txObj, ['customer_name' => $first, 'product' => $product]);

            $this->recordOutboundDelivery($merchantId, 'subscription.activated', [
                'transaction_id' => $txId,
                'subscription_id' => $subId,
                'email' => $email,
                'invoice_number' => $invoice['invoice_number'] ?? null,
            ], success: true);
        });

        // Real PayPal Sandbox OAuth (shows in Developer → API calls)
        $paypalSandbox = ['ok' => false, 'skipped' => true];
        try {
            $paypalSandbox = $this->pingPayPalSandbox($txId, $paypalRef, $currency, $amountMinor);
        } catch (\Throwable $e) {
            $paypalSandbox = ['ok' => false, 'error' => $e->getMessage()];
        }

        // Emails: subscriber + merchant (real SMTP if MAIL_MAILER=smtp)
        $emailResult = ['user_sent' => false, 'merchant_sent' => false, 'error' => null];
        try {
            $emailResult = $this->sendCheckoutEmails($merchantId, $email, $first, $product, $amountMinor, $currency, $paypalRef, $txId);
        } catch (\Throwable $e) {
            $emailResult['error'] = $e->getMessage();
        }

        $row = $this->formatRow($txId);
        try {
            $inv = DB::table('invoices')->where('transaction_id', $txId)->first();
            if ($inv) {
                $row['invoice_id'] = $inv->id;
                $row['invoice_number'] = $inv->invoice_number;
            }
        } catch (\Throwable $e) {
        }

        $needsApproval = ! empty($paypalSandbox['live']) && ! empty($paypalSandbox['approve_url']);
        $message = $needsApproval
            ? 'PayPal Sandbox order created — approve payment to deposit funds into your sandbox business account'
            : ('Checkout captured — subscription ACTIVE · Invoice '.($row['invoice_number'] ?? 'n/a'));

        return response()->json([
            'ok' => true,
            'message' => $message,
            'requires_approval' => $needsApproval,
            'email_sent' => $emailResult['user_sent'] ?? false,
            'merchant_email_sent' => $emailResult['merchant_sent'] ?? false,
            'email_error' => $emailResult['error'] ?? null,
            'email_to' => $email,
            'paypal_sandbox' => $paypalSandbox,
            'row' => $row,
            'recent_events' => $this->recentWebhookEvents(10),
            'invoice' => [
                'id' => $row['invoice_id'] ?? null,
                'invoice_number' => $row['invoice_number'] ?? null,
                'url' => isset($row['invoice_id']) ? url('/dashboard/invoices/'.$row['invoice_id']) : null,
            ],
            'preview' => [
                'phase' => 'checkout',
                'name' => $first,
                'email' => $email,
                'product' => $product,
                'status' => 'ACTIVE',
                'trust_score' => $trust,
                'churn_risk' => $churn,
            ],
        ], 201);
    }

    /** Browser return from PayPal Sandbox approve — Capture and finalize local rows so funds land in sandbox business balance. */
    public function paypalReturn(Request $request)
    {
        $txId = (string) $request->query('tx', '');
        $token = (string) $request->query('token', ''); // PayPal order id on return
        $orderId = $token !== '' ? $token : null;
        $captured = false;
        $captureId = null;
        $error = null;

        try {
            $row = null;
            if ($txId !== '') {
                $row = DB::table('transactions')->where('id', $txId)->first();
                if ($row) {
                    $meta = json_decode($row->metadata ?? '{}', true) ?: [];
                    $orderId = $orderId ?: ($meta['paypal_order_id'] ?? null);
                }
            }
            if ($orderId) {
                $client = \App\Services\PayPal\PayPalClient::fromConfig();
                $cap = $client->capturePayment($orderId, 'demo-capture-'.$orderId);
                $captureId = $cap['purchase_units'][0]['payments']['captures'][0]['id'] ?? null;
                $capStatus = $cap['purchase_units'][0]['payments']['captures'][0]['status']
                    ?? ($cap['status'] ?? null);
                $captured = in_array(strtoupper((string) $capStatus), ['COMPLETED', 'PENDING'], true)
                    || ($capStatus === null && is_array($cap));

                $this->logWebhookEvent('paypal', 'PAYMENT.CAPTURE.COMPLETED', [
                    'order_id' => $orderId,
                    'status' => $capStatus,
                    'transaction_id' => $txId,
                    'capture' => $captureId,
                ]);

                if ($txId !== '' && $row) {
                    $meta = json_decode($row->metadata ?? '{}', true) ?: [];
                    $meta['paypal_captured'] = true;
                    $meta['paypal_capture_status'] = $capStatus;
                    $meta['paypal_capture_id'] = $captureId;
                    $meta['paypal_order_id'] = $orderId;
                    $meta['lifecycle'] = 'ACTIVE';
                    $meta['awaiting_paypal_capture'] = false;

                    DB::table('transactions')->where('id', $txId)->update([
                        'status' => 'completed',
                        'provider' => 'paypal',
                        'provider_reference' => $captureId ?: $orderId,
                        'processed_at' => now(),
                        'metadata' => json_encode($meta),
                        'updated_at' => now(),
                    ]);

                    if (! empty($row->subscription_id)) {
                        DB::table('subscriptions')->where('id', $row->subscription_id)->update([
                            'status' => 'active',
                            'updated_at' => now(),
                        ]);
                    }

                    $this->logWebhookEvent('paypal', 'BILLING.SUBSCRIPTION.ACTIVATED', [
                        'transaction_id' => $txId,
                        'subscription_id' => $row->subscription_id ?? null,
                        'status' => 'ACTIVE',
                        'order_id' => $orderId,
                        'capture_id' => $captureId,
                    ]);
                    $this->logWebhookEvent('luvupay', 'subscription.activated', [
                        'transaction_id' => $txId,
                        'email' => $row->email ?? null,
                        'funds' => 'deposited_to_paypal_sandbox_business',
                    ]);
                }
            } else {
                $error = 'missing_order_id';
            }
        } catch (\Throwable $e) {
            $error = $e->getMessage();
            $this->logWebhookEvent('paypal', 'PAYMENT.CAPTURE.FAILED', [
                'order_id' => $orderId,
                'transaction_id' => $txId,
                'error' => $e->getMessage(),
            ], 'failed');
        }

        $qs = $captured ? 'paypal=captured' : 'paypal=capture_failed';
        if ($error && ! $captured) {
            $qs .= '&err='.urlencode(mb_substr($error, 0, 120));
        }

        return redirect('/dashboard?'.$qs);
    }

    public function paypalCancel(Request $request)
    {
        $txId = (string) $request->query('tx', '');
        $this->logWebhookEvent('paypal', 'CHECKOUT.ORDER.CANCELLED', [
            'transaction_id' => $txId,
        ], 'failed');

        return redirect('/dashboard?paypal=cancelled');
    }

    /**
     * Real HTTP call to PayPal Sandbox so Developer Dashboard API call logs are not empty.
     * Uses OAuth client-credentials only (safe, no capture without buyer).
     */
    /**
     * Real PayPal Sandbox checkout: OAuth + Create Order (CAPTURE).
     * Returns approve_url so the buyer can complete payment on paypal.com sandbox.
     *
     * @return array<string,mixed>
     */
    private function pingPayPalSandbox(string $txId, string $paypalRef, string $currency, int $amountMinor): array
    {
        $driver = (string) (config('luvupay.payment.driver') ?: env('LUVUPAY_PAYMENT_DRIVER', 'mock'));
        $clientId = trim((string) config('services.paypal.client_id', env('PAYPAL_CLIENT_ID', '')));
        $secret = trim((string) config('services.paypal.secret', env('PAYPAL_SECRET', '')));

        if ($clientId === '' || $secret === '') {
            $this->logWebhookEvent('paypal', 'SANDBOX.SKIPPED', [
                'reason' => 'missing_credentials',
                'driver' => $driver,
                'transaction_id' => $txId,
                'hint' => 'Set PAYPAL_CLIENT_ID and PAYPAL_SECRET from developer.paypal.com Sandbox app',
            ], 'failed');

            return [
                'live' => false,
                'error' => 'PAYPAL_CLIENT_ID / PAYPAL_SECRET empty in .env',
            ];
        }

        // Force live PayPal calls when credentials exist (even if driver was left as mock)
        try {
            $client = \App\Services\PayPal\PayPalClient::fromConfig();
            $token = $client->accessToken(true);
            $this->logWebhookEvent('paypal', 'OAUTH2.CLIENT_CREDENTIALS', [
                'transaction_id' => $txId,
                'mode' => config('services.paypal.mode', 'sandbox'),
                'token_preview' => substr($token, 0, 12).'…',
            ]);

            $returnUrl = url('/dashboard/paypal/return?tx='.$txId);
            $cancelUrl = url('/dashboard/paypal/cancel?tx='.$txId);
            $money = \App\Support\Money::of($amountMinor, $currency);
            $order = $client->createOrder(
                $money,
                $txId,
                $returnUrl,
                $cancelUrl,
                'demo-order-'.$txId,
                $txId
            );

            $orderId = (string) ($order['id'] ?? '');
            $approveUrl = null;
            foreach ($order['links'] ?? [] as $link) {
                if (($link['rel'] ?? '') === 'payer-action' || ($link['rel'] ?? '') === 'approve') {
                    $approveUrl = $link['href'] ?? null;
                    break;
                }
            }
            // v2 sometimes uses payer-action under payment_source
            if (! $approveUrl) {
                foreach ($order['links'] ?? [] as $link) {
                    if (isset($link['href']) && str_contains((string) $link['href'], 'paypal.com')) {
                        $approveUrl = $link['href'];
                        break;
                    }
                }
            }

            $this->logWebhookEvent('paypal', 'CHECKOUT.ORDER.CREATED', [
                'transaction_id' => $txId,
                'order_id' => $orderId,
                'status' => $order['status'] ?? null,
                'approve_url' => $approveUrl,
                'amount' => number_format($amountMinor / 100, 2, '.', '').' '.$currency,
            ]);

            // Persist order id on transaction metadata for capture on return
            try {
                $row = DB::table('transactions')->where('id', $txId)->first();
                if ($row) {
                    $meta = json_decode($row->metadata ?? '{}', true) ?: [];
                    $meta['paypal_order_id'] = $orderId;
                    $meta['paypal_approve_url'] = $approveUrl;
                    DB::table('transactions')->where('id', $txId)->update([
                        'provider' => 'paypal',
                        'provider_reference' => $orderId ?: $paypalRef,
                        'metadata' => json_encode($meta),
                        'updated_at' => now(),
                    ]);
                }
            } catch (\Throwable $e) {
            }

            return [
                'live' => true,
                'mode' => config('services.paypal.mode', 'sandbox'),
                'order_id' => $orderId,
                'status' => $order['status'] ?? null,
                'approve_url' => $approveUrl,
                'reference' => $paypalRef,
                'message' => 'PayPal Sandbox order created — complete payment on PayPal',
            ];
        } catch (\Throwable $e) {
            $this->logWebhookEvent('paypal', 'SANDBOX.ORDER_FAILED', [
                'transaction_id' => $txId,
                'error' => $e->getMessage(),
            ], 'failed');

            return [
                'live' => false,
                'error' => $e->getMessage(),
            ];
        }
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
