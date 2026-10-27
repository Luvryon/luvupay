<?php

namespace App\Services\Hosted;

use App\Http\Controllers\Demo\Concerns\DemoSupport;
use App\Mail\PlainHtmlMail;
use App\Services\Billing\InvoiceIssuer;
use App\Services\Billing\InvoiceMailer;
use App\Services\PayPal\PayPalClient;
use App\Support\MerchantProject;
use App\Support\Money;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * Hosted checkout (/checkout/{merchant}) and smart-token redemption (/redeem/{token}).
 * Works with the mock driver (instant, no PayPal) and the PayPal sandbox driver (Orders v2).
 * Every purchase writes the same ledger rows as the rest of the console, so KPIs/Wallet/Analytics stay consistent.
 */
final class HostedPurchase
{
    use DemoSupport;

    public static function slug(string $name): string
    {
        return Str::slug($name) ?: 'merchant';
    }

    public function merchant(string $key): ?object
    {
        $q = DB::table('merchants')->where('is_active', true);
        if (preg_match('/^[0-9a-f-]{36}$/i', $key)) {
            return (clone $q)->where('id', $key)->first();
        }
        foreach ((clone $q)->get(['id', 'name']) as $m) {
            if (self::slug($m->name) === Str::lower($key)) {
                return DB::table('merchants')->where('id', $m->id)->first();
            }
        }

        return null;
    }

    public static function token(string $sellerName, int $days): string
    {
        $first = Str::lower(preg_replace('/[^A-Za-z0-9]/', '', explode(' ', trim($sellerName))[0] ?? '') ?: 'user');

        return 'QP_LuV_'.$first.'_'.$days.'days_'.Str::lower(Str::random(4));
    }

    public function listingByToken(string $token): ?object
    {
        if (! preg_match('/^[A-Za-z0-9_\-]{6,80}$/', $token)) {
            return null;
        }

        return DB::table('resale_listings')->where('redeem_token', $token)->first();
    }

    /** @return array<string,mixed> */
    public function listingView(object $l, object $merchant): array
    {
        $project = MerchantProject::forMerchant($merchant->id);
        $meta = json_decode((string) ($l->metadata ?? '{}'), true) ?: [];
        $days = max(1, (int) ceil(Carbon::parse($l->access_expires_at)->floatDiffInDays(now(), false) * -1));
        $seller = (string) ($meta['seller_name'] ?? '');
        if ($seller === '') {
            $seller = ucfirst(explode('@', (string) $l->seller_email)[0]);
        }
        $parts = preg_split('/\s+/', trim($seller)) ?: [$seller];
        $label = $parts[0].(isset($parts[1]) ? ' '.mb_strtoupper(mb_substr($parts[1], 0, 1)).'.' : '');

        return [
            'token' => $l->redeem_token,
            'days' => $days,
            'seller' => $label,
            'product' => $project->product() ?: (string) $merchant->name,
            'price_minor' => (int) $l->asking_price_minor,
            'currency' => trim((string) $l->currency) ?: 'USD',
            'status' => $l->status,
            'available' => $l->status === 'active' && Carbon::parse($l->access_expires_at)->isFuture(),
            'sold' => $l->status === 'sold',
        ];
    }

    /** @return list<array<string,mixed>> */
    public function activeListings(object $merchant): array
    {
        return DB::table('resale_listings')->where('merchant_id', $merchant->id)->where('status', 'active')
            ->whereNotNull('redeem_token')->where('access_expires_at', '>', now())
            ->orderByDesc('listed_at')->limit(24)->get()
            ->map(fn ($l) => $this->listingView($l, $merchant))->all();
    }

    // ------------------------------------------------------------------ payment orchestration

    /**
     * Starts a payment. Mock: completes immediately. PayPal: creates an order and returns the approval URL.
     *
     * @param  array{intent:string,merchant_id:string,token?:string,name?:string,email:string,amount_minor:int,currency:string}  $ctx
     * @return array{done:bool,invoice_url?:string,approve_url?:string,message?:string}
     */
    public function start(array $ctx): array
    {
        $driver = (string) config('luvupay.payment.driver', 'mock');
        $hasCreds = trim((string) config('services.paypal.client_id', '')) !== '' && trim((string) config('services.paypal.secret', '')) !== '';
        $mock = $driver !== 'paypal' || ! $hasCreds;

        if ($mock) {
            return ['done' => true, 'invoice_url' => $this->complete($ctx, 'mock', 'MOCK-PAYPAL-'.strtoupper(Str::random(10)))];
        }

        $key = Str::random(40);
        $ret = url('/pay/return?'.http_build_query(['k' => $key]));
        $cancel = url('/pay/cancel?'.http_build_query(['k' => $key]));
        $order = PayPalClient::fromConfig()->createOrder(Money::of($ctx['amount_minor'], $ctx['currency']), $key, $ret, $cancel, 'hosted-'.$key, $key);
        $orderId = (string) ($order['id'] ?? '');
        $approve = PayPalClient::link($order, 'payer-action', 'approve');
        if ($orderId === '' || ! $approve) {
            throw new \RuntimeException('PayPal order response missing id or approval link.');
        }
        Cache::put('hosted:pay:'.$key, $ctx + ['order_id' => $orderId], now()->addMinutes(30));

        return ['done' => false, 'approve_url' => $approve];
    }

    /** PayPal return leg: capture, verify amount, then complete. Returns the signed invoice URL or null. */
    public function finish(string $key): ?string
    {
        $ctx = Cache::get('hosted:pay:'.$key);
        if (! is_array($ctx)) {
            return null;
        }
        $resp = PayPalClient::fromConfig()->capturePayment((string) $ctx['order_id'], 'hosted-cap-'.$key);
        $cap = $resp['purchase_units'][0]['payments']['captures'][0] ?? null;
        $paid = is_array($cap) ? \App\Services\PayPal\PayPalAmount::toMoney($cap['amount'] ?? null) : null;
        if (($resp['status'] ?? null) !== 'COMPLETED' || ! $paid || $paid->amountMinor !== (int) $ctx['amount_minor'] || $paid->currency !== strtoupper((string) $ctx['currency'])) {
            Log::warning('hosted.capture_rejected', ['key' => substr($key, 0, 8), 'status' => $resp['status'] ?? null]);

            return null;
        }
        Cache::forget('hosted:pay:'.$key);

        return $this->complete($ctx, 'paypal', (string) ($cap['id'] ?? 'PAYPAL-'.$key));
    }

    /** @param array<string,mixed> $ctx */
    private function complete(array $ctx, string $provider, string $ref): string
    {
        $this->scopedMerchant = (string) $ctx['merchant_id'];

        return $ctx['intent'] === 'redeem'
            ? $this->completeRedeem((string) $ctx['token'], (string) $ctx['email'], $provider, $ref)
            : $this->completeCheckout((string) $ctx['merchant_id'], (string) ($ctx['name'] ?? ''), (string) $ctx['email'], $provider, $ref);
    }

    // ------------------------------------------------------------------ completion: new subscription

    private function completeCheckout(string $m, string $name, string $email, string $provider, string $ref): string
    {
        $project = MerchantProject::forMerchant($m);
        $cur = $this->merchantCurrency($m);
        $amount = $project->priceMinor;
        $product = $project->product() ?: 'Subscription';
        $name = trim(strip_tags($name)) ?: ucfirst(explode('@', $email)[0]);
        $now = now();
        $subId = (string) Str::uuid();
        $txId = (string) Str::uuid();
        $userId = 'hosted_'.Str::lower(Str::random(8));
        $invoice = null;

        DB::transaction(function () use (&$invoice, $m, $project, $cur, $amount, $product, $name, $email, $provider, $ref, $now, $subId, $txId, $userId) {
            DB::table('subscriptions')->insert([
                'id' => $subId, 'merchant_id' => $m, 'merchant_user_id' => $userId, 'email' => strtolower($email), 'provider' => $provider,
                'provider_subscription_id' => 'I-HOSTED-'.strtoupper(Str::random(8)), 'provider_plan_id' => 'premium_monthly', 'status' => 'active',
                'amount_minor' => $amount, 'currency' => $cur, 'current_period_start' => $now, 'current_period_end' => $now->copy()->addDays(30),
                'next_billing_at' => $now->copy()->addDays(30), 'forgotten_cancel_count' => 0,
                'metadata' => json_encode(['source' => 'hosted_checkout', 'display_name' => $name, 'product' => $product]),
                'created_at' => $now, 'updated_at' => $now,
            ]);
            DB::table('transactions')->insert([
                'id' => $txId, 'merchant_id' => $m, 'subscription_id' => $subId, 'type' => 'charge', 'status' => 'completed',
                'idempotency_key' => 'hosted-'.$txId, 'merchant_user_id' => $userId, 'email' => strtolower($email), 'amount_minor' => $amount,
                'currency' => $cur, 'provider' => $provider, 'provider_reference' => $ref, 'processed_at' => $now, 'trust_score' => 85, 'churn_risk' => 'low',
                'metadata' => json_encode(['source' => 'hosted_checkout', 'display_name' => $name, 'product' => $product, 'lifecycle' => 'ACTIVE']),
                'created_at' => $now, 'updated_at' => $now,
            ]);
            $invoice = app(InvoiceIssuer::class)->issueForTransaction($m, (object) [
                'id' => $txId, 'amount_minor' => $amount, 'currency' => $cur, 'provider_reference' => $ref, 'email' => strtolower($email),
            ], ['customer_name' => $name, 'product' => $product]);
            $this->logWebhookEvent('luvupay', 'subscription.activated', ['transaction_id' => $txId, 'email' => $email, 'source' => 'hosted_checkout']);
            $this->recordOutboundDelivery($m, 'subscription.activated', ['transaction_id' => $txId, 'invoice_number' => $invoice['invoice_number'] ?? null], success: true);
        });

        // Email after response — generating PDF + SMTP was delaying the post-PayPal redirect.
        app()->terminating(function () use ($txId, $email) {
            try {
                $mail = InvoiceMailer::sendForTransaction($txId, 'subscription', $email);
                \Illuminate\Support\Facades\Log::info('hosted.checkout_mail', ['sent' => $mail['sent'], 'err' => $mail['error'], 'to' => $email]);
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('hosted.checkout_mail_failed', ['err' => $e->getMessage()]);
            }
        });

        return \App\Http\Controllers\InvoiceController::publicUrl((string) $invoice['id']);
    }

    // ------------------------------------------------------------------ completion: resale redemption

    private function completeRedeem(string $token, string $email, string $provider, string $ref): string
    {
        $buyerEmail = strtolower($email);
        $out = DB::transaction(function () use ($token, $buyerEmail, $provider, $ref) {
            $l = DB::table('resale_listings')->where('redeem_token', $token)->lockForUpdate()->first();
            if (! $l || $l->status !== 'active' || Carbon::parse($l->access_expires_at)->isPast()) {
                throw new \RuntimeException('This token is no longer available.');
            }
            if (strtolower((string) $l->seller_email) === $buyerEmail) {
                throw new \RuntimeException('You cannot redeem your own listing.');
            }
            $m = (string) $l->merchant_id;
            $cur = trim((string) $l->currency) ?: 'USD';
            $project = MerchantProject::forMerchant($m);
            $product = $project->product() ?: 'Subscription';
            $asking = (int) $l->asking_price_minor;
            $now = now();
            $buyerId = 'redeem_'.Str::lower(Str::random(8));
            $sellerPayout = (int) round($asking * (int) config('luvupay.resale.seller_bps', 9000) / 10000);
            $days = max(1, (int) ceil(Carbon::parse($l->access_expires_at)->floatDiffInDays($now, false) * -1));
            $parent = $l->subscription_id ? DB::table('transactions')->where('subscription_id', $l->subscription_id)->where('type', 'charge')->orderBy('created_at')->first() : null;
            $this->scopedMerchant = $m;

            DB::table('resale_listings')->where('id', $l->id)->update([
                'status' => 'sold', 'buyer_merchant_user_id' => $buyerId, 'buyer_email' => $buyerEmail, 'sold_at' => $now,
                'entitlement_status' => 'transferred', 'entitlement_sent_at' => $now, 'buyer_payment_reference' => $ref, 'updated_at' => $now,
            ]);

            $sale = (string) Str::uuid();
            DB::table('transactions')->insert([
                'id' => $sale, 'merchant_id' => $m, 'subscription_id' => $l->subscription_id, 'parent_transaction_id' => $parent->id ?? null,
                'type' => 'resale_sale', 'status' => 'completed', 'idempotency_key' => 'redeem-sale-'.$sale, 'merchant_user_id' => $buyerId,
                'email' => $buyerEmail, 'amount_minor' => $asking, 'currency' => $cur, 'provider' => $provider, 'provider_reference' => $ref,
                'processed_at' => $now, 'trust_score' => 88, 'churn_risk' => 'low',
                'metadata' => json_encode(['lifecycle' => 'RESOLD', 'listing_id' => $l->id, 'redeem_token' => $token, 'source' => 'hosted_redeem']),
                'created_at' => $now, 'updated_at' => $now,
            ]);
            $payout = (string) Str::uuid();
            DB::table('transactions')->insert([
                'id' => $payout, 'merchant_id' => $m, 'subscription_id' => $l->subscription_id, 'parent_transaction_id' => $sale,
                'type' => 'resale_payout', 'status' => 'completed', 'idempotency_key' => 'redeem-payout-'.$payout, 'merchant_user_id' => $l->seller_merchant_user_id,
                'email' => $l->seller_email, 'amount_minor' => $sellerPayout, 'currency' => $cur, 'provider' => $provider,
                'provider_reference' => 'PAYOUT-'.strtoupper(Str::random(8)), 'processed_at' => $now,
                'metadata' => json_encode(['listing_id' => $l->id, 'buyer_email' => $buyerEmail]), 'created_at' => $now, 'updated_at' => $now,
            ]);

            if ($parent) {
                $pm = json_decode((string) $parent->metadata, true) ?: [];
                $pm['lifecycle'] = 'RESOLD';
                $pm['buyer_email'] = $buyerEmail;
                $pm['listing_id'] = $l->id;
                DB::table('transactions')->where('id', $parent->id)->update(['status' => 'completed', 'metadata' => json_encode($pm), 'updated_at' => $now]);
            }
            // The listing-time fee is superseded: the fee is now recognised as (sale - seller payout).
            DB::table('transactions')->where('subscription_id', $l->subscription_id)->where('type', 'resale_fee')->where('status', 'completed')->update(['status' => 'superseded', 'updated_at' => $now]);
            DB::table('subscriptions')->where('id', $l->subscription_id)->update(['status' => 'resold', 'merchant_user_id' => $buyerId, 'email' => $buyerEmail, 'updated_at' => $now]);

            $accessCode = strtoupper(Str::random(4).'-'.Str::random(4).'-'.Str::random(4));
            $invoice = app(InvoiceIssuer::class)->issueForTransaction($m, (object) [
                'id' => $sale, 'amount_minor' => $asking, 'currency' => $cur, 'provider_reference' => $ref, 'email' => $buyerEmail,
            ], [
                'customer_name' => ucfirst(explode('@', $buyerEmail)[0]),
                'product' => $product,
                'kind' => 'resale',
                'access_code' => $accessCode,
                'access_days' => $days,
                'line_items' => [['description' => $days.' days of '.$product.' (resale access)', 'quantity' => 1, 'unit_amount_minor' => $asking, 'amount_minor' => $asking]],
            ]);
            // Persist code on the listing for merchant support lookups
            DB::table('resale_listings')->where('id', $l->id)->update([
                'updated_at' => $now,
            ]);
            $this->logWebhookEvent('luvupay', 'marketplace.listing_sold', ['listing_id' => $l->id, 'buyer' => $buyerEmail, 'access_code' => $accessCode]);
            $this->logWebhookEvent('luvupay', 'entitlement.transfer', ['listing_id' => $l->id, 'seller' => $l->seller_email, 'buyer' => $buyerEmail, 'access_code' => $accessCode]);
            $this->recordOutboundDelivery($m, 'entitlement.transfer', ['listing_id' => $l->id, 'invoice_number' => $invoice['invoice_number'] ?? null, 'access_code' => $accessCode], success: true);

            return ['sale' => $sale, 'invoice' => $invoice, 'seller_email' => $l->seller_email, 'payout' => $sellerPayout, 'cur' => $cur, 'product' => $product, 'merchant' => $m, 'access_code' => $accessCode, 'days' => $days, 'buyer_email' => $buyerEmail];
        });

        // Defer email so the buyer is redirected to the invoice immediately (PDF+SMTP was blocking PayPal return).
        $saleId = $out['sale'];
        $buyerEmailCopy = $out['buyer_email'];
        $sellerEmail = $out['seller_email'];
        $productName = $out['product'];
        $payoutMinor = $out['payout'];
        $cur = $out['cur'];
        $accessCode = $out['access_code'];
        $days = $out['days'];
        app()->terminating(function () use ($saleId, $buyerEmailCopy, $sellerEmail, $productName, $payoutMinor, $cur, $accessCode, $days) {
            try {
                $mail = InvoiceMailer::sendForTransaction($saleId, 'resale', $buyerEmailCopy);
                Log::info('hosted.buyer_mail', ['sent' => $mail['sent'], 'err' => $mail['error'], 'to' => $buyerEmailCopy]);
            } catch (\Throwable $e) {
                Log::warning('hosted.buyer_mail_failed', ['err' => $e->getMessage()]);
            }
            try {
                $body = '<div style="font-family:Arial,sans-serif;font-size:15px;color:#0f172a">'
                    .'<p>Good news — someone bought your resale listing for <b>'.e($productName).'</b>.</p>'
                    .'<p>Payout: <b>'.e(\App\Services\Billing\InvoiceView::money($payoutMinor, $cur)).'</b></p>'
                    .'<p style="color:#64748b;font-size:13px">Buyer access code (for support): <code>'.e($accessCode).'</code> · '.$days.' days</p>'
                    .'</div>';
                Mail::to($sellerEmail)->sendNow(new PlainHtmlMail('Your '.$productName.' access was sold', $body));
            } catch (\Throwable $e) {
                Log::info('hosted.seller_mail_failed', ['err' => $e->getMessage()]);
            }
        });

        $this->scopedMerchant = $out['merchant'];
        return \App\Http\Controllers\InvoiceController::publicUrl((string) $out['invoice']['id']);
    }
}
