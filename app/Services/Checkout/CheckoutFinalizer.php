<?php

namespace App\Services\Checkout;

use App\Http\Controllers\Demo\Concerns\DemoSupport;
use App\Mail\PlainHtmlMail;
use App\Services\Billing\InvoiceIssuer;
use App\Support\MerchantProject;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Everything that must happen exactly once AFTER a checkout is really paid:
 * invoice, outbound webhook delivery record, subscriber receipt email, merchant notification.
 *
 * Several paths can notice the payment first (PayPal return URL, the dashboard status poll, the PayPal webhook).
 * Whoever gets here first does the work; everybody else gets `finalized => false` and does nothing.
 */
final class CheckoutFinalizer
{
    use DemoSupport;

    /**
     * @return array{finalized:bool,user_sent:bool,merchant_sent:bool,error:?string}
     */
    public function finalize(string $merchantId, string $transactionId): array
    {
        $skipped = ['finalized' => false, 'user_sent' => false, 'merchant_sent' => false, 'error' => null];
        $this->scopedMerchant = $merchantId;

        $row = DB::table('transactions')->where('id', $transactionId)->where('merchant_id', $merchantId)->first();
        if (! $row || $row->status !== 'completed') {
            return $skipped;
        }
        $meta = json_decode($row->metadata ?? '{}', true) ?: [];
        if (! empty($meta['checkout_finalized_at'])) {
            return $skipped;
        }
        // Atomic claim: only one concurrent caller gets past this line.
        if (! Cache::add('checkout:finalize:'.$row->id, 1, 600)) {
            return $skipped;
        }

        $meta['checkout_finalized_at'] = now()->toIso8601String();
        DB::table('transactions')->where('id', $row->id)->update(['metadata' => json_encode($meta), 'updated_at' => now()]);

        $name = (string) ($meta['display_name'] ?? 'Customer');
        $product = (string) ($meta['product'] ?? MerchantProject::forMerchant($merchantId)->product());
        $reference = (string) ($row->provider_reference ?? '');

        $invoice = $this->ensureInvoice($merchantId, (string) $row->id, $name, $product);
        $this->recordOutboundDelivery($merchantId, 'subscription.activated', [
            'transaction_id' => $row->id,
            'subscription_id' => $row->subscription_id,
            'email' => $row->email,
            'invoice_number' => $invoice['invoice_number'] ?? null,
        ], success: true);

        try {
            $mail = $this->sendCheckoutEmails(
                $merchantId, (string) $row->email, $name, $product,
                (int) $row->amount_minor, trim((string) $row->currency), $reference, (string) $row->id
            );
        } catch (\Throwable $e) {
            Log::warning('checkout.finalize.email_failed', ['tx' => $row->id, 'err' => $e->getMessage()]);
            $mail = ['user_sent' => false, 'merchant_sent' => false, 'error' => $e->getMessage()];
        }

        return ['finalized' => true] + $mail;
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
