<?php

namespace App\Http\Controllers\Demo;

use App\Http\Controllers\Demo\Concerns\DemoSupport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Developer Sandbox: Resale simulation.
 * Gated by the demo.simulator middleware (off by default in production).
 */
final class DemoResaleController
{
    use DemoSupport;

    /** POST /api/v1/dashboard/demo/resale — Trigger Test: Execute Resale Logic */
    public function simulateResale(Request $request): JsonResponse
    {
        $merchantId = $this->merchantId($request);
        $data = $request->validate(['transaction_id' => ['required', 'uuid']]);

        $result = DB::transaction(function () use ($merchantId, $data) {
            $tx = DB::table('transactions')
                ->where('merchant_id', $merchantId)
                ->where('id', $data['transaction_id'])
                ->lockForUpdate()
                ->first();

            abort_unless($tx, 404, 'Transaction not found');
            $meta = json_decode($tx->metadata ?? '{}', true) ?: [];
            abort_unless(
                ($meta['lifecycle'] ?? null) === 'GRACE_PERIOD' || $tx->status === 'held',
                422,
                'Row must be in GRACE_PERIOD before resale test'
            );
            abort_unless($tx->subscription_id, 422, 'Charge has no subscription to resell');

            $sub = DB::table('subscriptions')->where('id', $tx->subscription_id)->lockForUpdate()->first();
            abort_unless($sub, 404, 'Subscription missing');

            $now = now();
            $buyerUserId = 'demo_buyer_'.Str::lower(Str::random(6));
            $buyerFirst = collect(['Taylor', 'Quinn', 'Avery', 'Reese'])->random();
            $buyerEmail = Str::lower($buyerFirst).'.'.$buyerUserId.'@demo.luvupay.local';
            $asking = max(100, (int) round(((int) $tx->amount_minor) * 0.5));
            $sellerBps = (int) config('luvupay.resale.seller_bps', 9000);
            $sellerPayout = (int) round($asking * $sellerBps / 10000);
            $platformFee = $asking - $sellerPayout;
            $listingId = (string) Str::uuid();
            $resaleSaleId = (string) Str::uuid();
            $resalePayoutId = (string) Str::uuid();
            $charityPayoutId = (string) Str::uuid();
            $receiptId = (string) Str::uuid();
            $certCode = 'LUVI-'.strtoupper(Str::random(8));
            $accessExpires = $sub->current_period_end
                ? Carbon::parse($sub->current_period_end)
                : $now->copy()->addDays(15);

            // Charity leg from original Second Chance split (if present)
            $split = json_decode($tx->split_snapshot ?? 'null', true) ?: [];
            $charityMinor = (int) ($split['charity_minor'] ?? round(((int) $tx->amount_minor) * 0.2));
            $charityName = DB::table('charity_wallets')->where('is_active', true)->value('name') ?: 'Ocean Cleanup Alliance';

            DB::table('resale_listings')->insert([
                'id' => $listingId,
                'merchant_id' => $merchantId,
                'subscription_id' => $sub->id,
                'seller_merchant_user_id' => $sub->merchant_user_id,
                'seller_email' => $sub->email,
                'buyer_merchant_user_id' => $buyerUserId,
                'buyer_email' => $buyerEmail,
                'status' => 'sold',
                'entitlement_status' => 'transferred',
                'asking_price_minor' => $asking,
                'currency' => $tx->currency,
                'access_expires_at' => $accessExpires,
                'listed_at' => $now,
                'sold_at' => $now,
                'entitlement_sent_at' => $now,
                'correlation_id' => (string) Str::uuid(),
                'buyer_payment_reference' => 'PAYPAL-RESALE-'.strtoupper(Str::random(8)),
                'metadata' => json_encode(['demo' => true, 'source' => 'developer_sandbox']),
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('transactions')->insert([
                'id' => $resaleSaleId,
                'merchant_id' => $merchantId,
                'subscription_id' => $sub->id,
                'parent_transaction_id' => $tx->id,
                'type' => 'resale_sale',
                'status' => 'completed',
                'idempotency_key' => 'sandbox-resale-sale-'.$resaleSaleId,
                'merchant_user_id' => $buyerUserId,
                'email' => $buyerEmail,
                'amount_minor' => $asking,
                'currency' => $tx->currency,
                'provider' => 'mock',
                'provider_reference' => 'PAYPAL-RESALE-CAP-'.strtoupper(Str::random(8)),
                'processed_at' => $now,
                'trust_score' => 88,
                'churn_risk' => 'low',
                'metadata' => json_encode(['demo' => true, 'lifecycle' => 'RESOLD', 'listing_id' => $listingId]),
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('transactions')->insert([
                'id' => $resalePayoutId,
                'merchant_id' => $merchantId,
                'subscription_id' => $sub->id,
                'parent_transaction_id' => $resaleSaleId,
                'type' => 'resale_payout',
                'status' => 'completed',
                'idempotency_key' => 'sandbox-resale-payout-'.$resalePayoutId,
                'merchant_user_id' => $sub->merchant_user_id,
                'email' => $sub->email,
                'amount_minor' => $sellerPayout,
                'currency' => $tx->currency,
                'provider' => 'mock',
                'provider_reference' => 'PAYPAL-PAYOUT-'.strtoupper(Str::random(8)),
                'processed_at' => $now,
                'metadata' => json_encode(['demo' => true, 'listing_id' => $listingId, 'buyer_email' => $buyerEmail]),
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $paypalCharityRef = 'PAYPAL-CHARITY-'.strtoupper(Str::random(8));
            DB::table('transactions')->insert([
                'id' => $charityPayoutId,
                'merchant_id' => $merchantId,
                'subscription_id' => $sub->id,
                'parent_transaction_id' => $tx->id,
                'type' => 'charity_payout',
                'status' => 'completed',
                'idempotency_key' => 'sandbox-charity-'.$charityPayoutId,
                'email' => $charityName,
                'amount_minor' => $charityMinor,
                'currency' => $tx->currency,
                'provider' => 'mock',
                'provider_reference' => $paypalCharityRef,
                'processed_at' => $now,
                'metadata' => json_encode(['demo' => true, 'charity_name' => $charityName, 'receipt_id' => $receiptId]),
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            if (true) {
                try {
                    DB::table('charity_receipts')->insert([
                        'id' => $receiptId,
                        'merchant_id' => $merchantId,
                        'transaction_id' => $charityPayoutId,
                        'certificate_code' => $certCode,
                        'charity_name' => $charityName,
                        'amount_minor' => $charityMinor,
                        'currency' => $tx->currency,
                        'paypal_payout_id' => $paypalCharityRef,
                        'donor_email' => $tx->email,
                        'metadata' => json_encode(['parent_charge_id' => $tx->id]),
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                } catch (\Throwable $e) {
                    // table may not be migrated yet
                }
            }

            $meta['lifecycle'] = 'RESOLD';
            $meta['buyer_email'] = $buyerEmail;
            $meta['listing_id'] = $listingId;
            $meta['receipt_id'] = $receiptId;
            $meta['certificate_code'] = $certCode;
            $meta['charity_name'] = $charityName;
            $meta['charity_minor'] = $charityMinor;

            DB::table('transactions')->where('id', $tx->id)->update([
                'status' => 'completed',
                'metadata' => json_encode($meta),
                'trust_score' => min(99, (int) ($tx->trust_score ?? 60) + 12),
                'churn_risk' => 'low',
                'updated_at' => $now,
            ]);

            DB::table('subscriptions')->where('id', $sub->id)->update([
                'status' => 'resold',
                'merchant_user_id' => $buyerUserId,
                'email' => $buyerEmail,
                'updated_at' => $now,
            ]);

            $this->logWebhookEvent('luvupay', 'entitlement.transfer', [
                'listing_id' => $listingId,
                'seller' => $sub->email,
                'buyer' => $buyerEmail,
                'access_expires_at' => $accessExpires->toIso8601String(),
            ]);
            $this->logWebhookEvent('paypal', 'PAYMENT.CAPTURE.COMPLETED', [
                'id' => 'RESALE-'.$listingId,
                'status' => 'COMPLETED',
                'amount' => ['value' => number_format($asking / 100, 2, '.', '')],
            ]);

            return [
                'tx_id' => $tx->id,
                'buyer_email' => $buyerEmail,
                'buyer_name' => $buyerFirst,
                'listing_id' => $listingId,
                'seller_payout' => $sellerPayout,
                'platform_fee' => $platformFee,
                'charity_minor' => $charityMinor,
                'charity_name' => $charityName,
                'certificate_code' => $certCode,
                'receipt_id' => $receiptId,
                'name' => $meta['display_name'] ?? 'Member',
            ];
        });

        return response()->json([
            'ok' => true,
            'message' => sprintf(
                'Resale complete — seller $%.2f · platform $%.2f · charity $%.2f to %s',
                $result['seller_payout'] / 100,
                $result['platform_fee'] / 100,
                $result['charity_minor'] / 100,
                $result['charity_name']
            ),
            'row' => $this->formatRow($result['tx_id']),
            'preview' => [
                'phase' => 'resale',
                'name' => $result['name'],
                'buyer_name' => $result['buyer_name'],
                'buyer_email' => $result['buyer_email'],
                'status' => 'RESOLD',
                'certificate_code' => $result['certificate_code'],
                'charity_name' => $result['charity_name'],
                'charity_minor' => $result['charity_minor'],
                'trust_score' => 92,
                'churn_risk' => 'low',
            ],
        ]);
    }
}
