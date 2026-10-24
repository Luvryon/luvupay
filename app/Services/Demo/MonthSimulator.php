<?php

namespace App\Services\Demo;

use App\Http\Controllers\Demo\Concerns\DemoSupport;
use App\Services\Billing\InvoiceIssuer;
use App\Support\MerchantProject;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * "Simulate 30 days": writes a realistic month of subscription lifecycles into the REAL ledger tables
 * (subscriptions, transactions, resale_listings, charity_receipts, invoices, webhook events/deliveries),
 * so every KPI, the wallet and Analytics are driven by the same rows a live merchant would have.
 * Outcomes honour the project's Settings (grace period / marketplace resale / charity split / refund %).
 * Works with LUVUPAY_PAYMENT_DRIVER=mock (default) and paypal (rows are tagged with the provider).
 */
final class MonthSimulator
{
    use DemoSupport;

    private const PATTERN = [
        'active', 'partial_refund', 'resale', 'active', 'charity', 'renewed',
        'churned', 'active', 'resale', 'partial_refund', 'grace', 'charity',
    ];

    private const NAMES = [
        ['Sarah Johnson', 'sarah@streamnet.com'], ['Mario Chen', 'mario@fitclub.io'], ['Emma Wilson', 'emma.w@lumen.co'],
        ['David Kim', 'david.kim@orbit.dev'], ['Lisa Anderson', 'lisa.a@pixelhub.com'], ['Noah Garcia', 'noah@cloudnine.app'],
        ['Olivia Brown', 'olivia@mediavault.net'], ['Liam Patel', 'liam.p@learnly.org'], ['Ava Martinez', 'ava@sunrise.fm'],
        ['Ethan Lee', 'ethan.lee@gamerz.gg'], ['Mia Thompson', 'mia.t@readwell.com'], ['Lucas Rossi', 'lucas@coursecloud.io'],
    ];

    /**
     * @param  list<array{name?:string,email?:string}>  $people
     * @return array<string,mixed>
     */
    public function run(string $merchantId, array $people = []): array
    {
        $this->scopedMerchant = $merchantId;
        $project = MerchantProject::current();
        $currency = $this->merchantCurrency($merchantId);
        $driver = (string) (config('luvupay.payment.driver') ?: 'mock');
        $provider = $driver === 'paypal' ? 'paypal' : 'mock';
        $amount = $project->priceMinor;
        $product = $project->product() ?: 'Subscription';

        if (! $people) {
            $people = array_map(fn ($p) => ['name' => $p[0], 'email' => $p[1]], self::NAMES);
        }
        $people = array_slice(array_values($people), 0, 20);
        $n = count($people);

        $counts = [];
        DB::transaction(function () use ($people, $n, $merchantId, $project, $currency, $provider, $amount, $product, &$counts) {
            foreach ($people as $i => $p) {
                $outcome = $this->resolveOutcome(self::PATTERN[$i % count(self::PATTERN)], $project);
                $daysAgo = $outcome === 'grace' ? 0 : ($n > 1 ? (int) round((($n - 1 - $i) * 27) / ($n - 1)) + 1 : 1);
                $at = now()->subDays($daysAgo)->setTime(8 + ($i * 7) % 12, ($i * 13) % 60, 0);
                $this->one($merchantId, $project, $currency, $provider, $amount, $product, $outcome, $at, $p, $i);
                $counts[$outcome] = ($counts[$outcome] ?? 0) + 1;
            }
        });

        return ['created' => $n, 'outcomes' => $counts, 'amount_minor' => $amount, 'currency' => $currency];
    }

    /** Falls back to a path the merchant actually enabled. */
    private function resolveOutcome(string $o, MerchantProject $p): string
    {
        // Without a grace period there is no post-charge retention path: everything settles as a normal charge.
        if (! $p->gracePeriod && in_array($o, ['partial_refund', 'resale', 'charity', 'renewed', 'grace'], true)) {
            return 'active';
        }

        return match (true) {
            $o === 'resale' && ! $p->marketplaceResale => $p->charitySplit ? 'charity' : 'partial_refund',
            $o === 'charity' && ! $p->charitySplit => $p->marketplaceResale ? 'resale' : 'partial_refund',
            default => $o,
        };
;
    }

    /** @param array{name?:string,email?:string} $p */
    private function one(string $m, MerchantProject $project, string $cur, string $provider, int $amount, string $product,
        string $outcome, Carbon $at, array $p, int $i): void
    {
        $name = trim(strip_tags((string) ($p['name'] ?? ''))) ?: 'Member '.($i + 1);
        $email = strtolower(trim((string) ($p['email'] ?? ''))) ?: 'member'.($i + 1).'.'.Str::lower(Str::random(4)).'@example.com';
        $userId = 'sim_user_'.Str::lower(Str::random(6));
        $subId = (string) Str::uuid();
        $txId = (string) Str::uuid();
        $trust = random_int(60, 96);
        $churn = $trust >= 80 ? 'low' : ($trust >= 60 ? 'medium' : 'high');
        $ref = 'PAYPAL-SANDBOX-'.strtoupper(Str::random(10));

        $refundBps = $project->partialRefundBps;
        $refund = (int) round($amount * $refundBps / 10000);
        $meta = ['demo' => true, 'source' => 'simulate_30_days', 'display_name' => $name, 'product' => $product, 'lifecycle' => 'ACTIVE', 'plan_id' => 'premium_monthly'];
        $status = 'completed';
        $subStatus = 'active';
        $second = null;
        $triggered = null;

        switch ($outcome) {
            case 'renewed':
                $meta['decision'] = 'user_renewed';
                break;
            case 'partial_refund':
                $meta['lifecycle'] = 'AI-RETAINED';
                $meta['decision'] = 'partial_refund_accepted';
                $meta['smart_convert'] = ['type' => 'partial_refund', 'refund_bps' => $refundBps, 'refund_minor' => $refund, 'chargeback_prevented' => true];
                break;
            case 'charity':
                $meta['lifecycle'] = 'AI-RETAINED';
                $meta['decision'] = 'charity_donate';
                $meta['charity'] = ['donated_minor' => $amount, 'at' => $at->toIso8601String()];
                break;
            case 'resale':
                $meta['lifecycle'] = 'RESOLD';
                $meta['decision'] = 'marketplace_resale';
                $subStatus = 'resold';
                break;
            case 'churned':
                $meta['lifecycle'] = 'CHURNED';
                $meta['decision'] = 'expired_no_response';
                $status = 'cancelled';
                $subStatus = 'expired';
                $trust = random_int(20, 45);
                $churn = 'high';
                break;
            case 'grace':
                $windowH = (int) config('luvupay.defaults.second_chance_window_hours', 48);
                $meta['lifecycle'] = 'GRACE PERIOD';
                $meta['missed_cancellation'] = true;
                $meta['grace'] = ['started_at' => $at->toIso8601String(), 'expires_at' => $at->copy()->addHours($windowH)->toIso8601String(),
                    'window_hours' => $windowH, 'partial_refund_bps' => $refundBps, 'partial_refund_minor' => $refund, 'default_if_no_response' => 'expire'];
                $meta['ai_nudge'] = "Hi {$name}, we noticed you haven't used {$product} lately. Keep it, take a ".(int) round($refundBps / 100).'% refund, or let it lapse — no pressure.';
                $meta['ai_source'] = 'local_template';
                $status = 'held';
                $subStatus = 'past_due';
                $second = $at->copy()->addHours($windowH);
                $triggered = $at;
                $trust = random_int(40, 58);
                $churn = 'high';
                break;
        }

        DB::table('subscriptions')->insert([
            'id' => $subId, 'merchant_id' => $m, 'merchant_user_id' => $userId, 'email' => $email, 'provider' => $provider,
            'provider_subscription_id' => 'I-SIM-'.strtoupper(Str::random(8)), 'provider_plan_id' => 'premium_monthly',
            'status' => $subStatus, 'amount_minor' => $amount, 'currency' => $cur,
            'current_period_start' => $at, 'current_period_end' => $at->copy()->addDays(30), 'next_billing_at' => $at->copy()->addDays(30),
            'forgotten_cancel_count' => $outcome === 'grace' || $outcome === 'churned' ? 1 : 0,
            'metadata' => json_encode(['demo' => true, 'source' => 'simulate_30_days', 'display_name' => $name, 'product' => $product]),
            'created_at' => $at, 'updated_at' => $at,
        ]);

        DB::table('transactions')->insert([
            'id' => $txId, 'merchant_id' => $m, 'subscription_id' => $subId, 'type' => 'charge', 'status' => $status,
            'idempotency_key' => 'sim30-'.$txId, 'merchant_user_id' => $userId, 'email' => $email, 'amount_minor' => $amount,
            'currency' => $cur, 'provider' => $provider, 'provider_reference' => $ref, 'processed_at' => $at,
            'second_chance_expires_at' => $second, 'second_chance_triggered_at' => $triggered,
            'trust_score' => $trust, 'churn_risk' => $churn, 'metadata' => json_encode($meta),
            'created_at' => $at, 'updated_at' => $at,
        ]);

        $inv = app(InvoiceIssuer::class)->issueForTransaction($m, (object) [
            'id' => $txId, 'amount_minor' => $amount, 'currency' => $cur, 'provider_reference' => $ref, 'email' => $email,
        ]);

        $this->logWebhookEvent('paypal', 'PAYMENT.CAPTURE.COMPLETED', ['id' => $ref, 'status' => 'COMPLETED', 'custom_id' => $txId]);
        $this->logWebhookEvent('luvupay', 'subscription.activated', ['transaction_id' => $txId, 'email' => $email]);
        $this->recordOutboundDelivery($m, 'subscription.activated', ['transaction_id' => $txId, 'invoice_number' => $inv['invoice_number'] ?? null], success: $outcome !== 'churned');

        // ---- outcome side effects (rows written exactly like the manual Demo Simulator flows)
        if ($outcome === 'charity') {
            $this->charityRow($m, $subId, $txId, $userId, $email, $amount, $cur, $at, $provider, 'charity_donate');
            $this->logWebhookEvent('luvupay', 'charity.donation', ['transaction_id' => $txId, 'amount_minor' => $amount]);
        } elseif ($outcome === 'partial_refund') {
            $this->logWebhookEvent('luvupay', 'subscription.partial_refund', ['transaction_id' => $txId, 'refund_minor' => $refund]);
        } elseif ($outcome === 'resale') {
            $this->resaleRows($m, $project, $subId, $txId, $userId, $email, $amount, $cur, $at, $provider);
        } elseif ($outcome === 'churned') {
            $this->logWebhookEvent('paypal', 'BILLING.SUBSCRIPTION.EXPIRED', ['transaction_id' => $txId]);
        } elseif ($outcome === 'grace') {
            $this->logWebhookEvent('luvupay', 'subscription.grace_period_started', ['transaction_id' => $txId]);
        }
    }

    private function charityRow(string $m, string $subId, string $parent, string $userId, string $email, int $amt, string $cur, Carbon $at, string $provider, string $path): string
    {
        $id = (string) Str::uuid();
        $receiptId = (string) Str::uuid();
        $charityName = DB::table('charity_wallets')->where('is_active', true)->value('name') ?: 'Ocean Cleanup Alliance';
        $ref = 'PAYPAL-CHARITY-'.strtoupper(Str::random(8));
        DB::table('transactions')->insert([
            'id' => $id, 'merchant_id' => $m, 'subscription_id' => $subId, 'parent_transaction_id' => $parent, 'type' => 'charity_payout',
            'status' => 'completed', 'idempotency_key' => 'sim30-charity-'.$id, 'merchant_user_id' => $userId, 'email' => $email,
            'amount_minor' => $amt, 'currency' => $cur, 'provider' => $provider, 'provider_reference' => $ref, 'processed_at' => $at,
            'metadata' => json_encode(['lifecycle' => 'AI-RETAINED', 'parent_transaction_id' => $parent, 'path' => $path, 'charity_name' => $charityName, 'receipt_id' => $receiptId]),
            'created_at' => $at, 'updated_at' => $at,
        ]);
        try {
            DB::table('charity_receipts')->insert([
                'id' => $receiptId, 'merchant_id' => $m, 'transaction_id' => $id, 'certificate_code' => 'LUVI-'.strtoupper(Str::random(8)),
                'charity_name' => $charityName, 'amount_minor' => $amt, 'currency' => $cur, 'paypal_payout_id' => $ref,
                'donor_email' => $email, 'metadata' => json_encode(['parent_charge_id' => $parent]), 'created_at' => $at, 'updated_at' => $at,
            ]);
        } catch (\Throwable $e) {
            // receipts table is optional
        }

        return $id;
    }

    private function resaleRows(string $m, MerchantProject $project, string $subId, string $parent, string $sellerId, string $sellerEmail,
        int $amount, string $cur, Carbon $at, string $provider): void
    {
        $buyerId = 'sim_buyer_'.Str::lower(Str::random(6));
        $buyerEmail = 'buyer.'.$buyerId.'@demo.luvupay.local';
        $asking = max(100, (int) round($amount * 0.5));
        $sellerPayout = (int) round($asking * (int) config('luvupay.resale.seller_bps', 9000) / 10000);
        $listingId = (string) Str::uuid();
        $sale = (string) Str::uuid();
        $payout = (string) Str::uuid();
        $sold = $at->copy()->addDays(2);
        $sold = $sold->isFuture() ? now() : $sold;

        DB::table('resale_listings')->insert([
            'id' => $listingId, 'merchant_id' => $m, 'subscription_id' => $subId, 'seller_merchant_user_id' => $sellerId, 'seller_email' => $sellerEmail,
            'buyer_merchant_user_id' => $buyerId, 'buyer_email' => $buyerEmail, 'status' => 'sold', 'entitlement_status' => 'transferred',
            'asking_price_minor' => $asking, 'currency' => $cur, 'access_expires_at' => $at->copy()->addDays(30), 'listed_at' => $at,
            'sold_at' => $sold, 'entitlement_sent_at' => $sold, 'correlation_id' => (string) Str::uuid(),
            'buyer_payment_reference' => 'PAYPAL-RESALE-'.strtoupper(Str::random(8)),
            'metadata' => json_encode(['demo' => true, 'source' => 'simulate_30_days']), 'created_at' => $at, 'updated_at' => $sold,
        ]);
        DB::table('transactions')->insert([
            'id' => $sale, 'merchant_id' => $m, 'subscription_id' => $subId, 'parent_transaction_id' => $parent, 'type' => 'resale_sale',
            'status' => 'completed', 'idempotency_key' => 'sim30-sale-'.$sale, 'merchant_user_id' => $buyerId, 'email' => $buyerEmail,
            'amount_minor' => $asking, 'currency' => $cur, 'provider' => $provider, 'provider_reference' => 'PAYPAL-RESALE-CAP-'.strtoupper(Str::random(8)),
            'processed_at' => $sold, 'trust_score' => 88, 'churn_risk' => 'low',
            'metadata' => json_encode(['demo' => true, 'lifecycle' => 'RESOLD', 'listing_id' => $listingId]), 'created_at' => $sold, 'updated_at' => $sold,
        ]);
        DB::table('transactions')->insert([
            'id' => $payout, 'merchant_id' => $m, 'subscription_id' => $subId, 'parent_transaction_id' => $sale, 'type' => 'resale_payout',
            'status' => 'completed', 'idempotency_key' => 'sim30-payout-'.$payout, 'merchant_user_id' => $sellerId, 'email' => $sellerEmail,
            'amount_minor' => $sellerPayout, 'currency' => $cur, 'provider' => $provider, 'provider_reference' => 'PAYPAL-PAYOUT-'.strtoupper(Str::random(8)),
            'processed_at' => $sold, 'metadata' => json_encode(['demo' => true, 'listing_id' => $listingId, 'buyer_email' => $buyerEmail]),
            'created_at' => $sold, 'updated_at' => $sold,
        ]);
        // Keep the buyer visible on the original charge row (same as the manual resale flow)
        $meta = json_decode(DB::table('transactions')->where('id', $parent)->value('metadata') ?: '{}', true) ?: [];
        $meta['buyer_email'] = $buyerEmail;
        $meta['listing_id'] = $listingId;
        DB::table('transactions')->where('id', $parent)->update(['metadata' => json_encode($meta)]);
        DB::table('subscriptions')->where('id', $subId)->update(['merchant_user_id' => $buyerId, 'email' => $buyerEmail]);

        if ($project->charitySplit) {
            $this->charityRow($m, $subId, $parent, $sellerId, 'Ocean Cleanup Alliance', (int) round($amount * 0.2), $cur, $sold, $provider, 'resale_charity_leg');
        }
        $this->logWebhookEvent('luvupay', 'entitlement.transfer', ['listing_id' => $listingId, 'seller' => $sellerEmail, 'buyer' => $buyerEmail]);
        $this->logWebhookEvent('paypal', 'PAYMENT.CAPTURE.COMPLETED', ['id' => 'RESALE-'.$listingId, 'status' => 'COMPLETED']);
    }
}
