<?php

namespace App\Http\Controllers\Demo;

use App\Http\Controllers\Demo\Concerns\DemoSupport;
use App\Services\Hosted\HostedPurchase;
use App\Services\Resale\ResaleSettlement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Developer Sandbox: "a buyer purchases the listing". Uses the same settlement routine as the public redeem page,
 * so the ledger, wallet and KPIs behave identically. Gated by the demo.simulator middleware.
 */
final class DemoResaleController
{
    use DemoSupport;

    /** POST /api/v1/dashboard/demo/resale */
    public function simulateResale(Request $request, ResaleSettlement $settlement): JsonResponse
    {
        $merchantId = $this->merchantId($request);
        $data = $request->validate(['transaction_id' => ['required', 'uuid']]);

        try {
            $result = DB::transaction(function () use ($merchantId, $data, $settlement) {
                $tx = DB::table('transactions')->where('merchant_id', $merchantId)->where('id', $data['transaction_id'])->lockForUpdate()->first();
                abort_unless($tx, 404, 'Transaction not found');
                abort_unless($tx->type === 'charge' && $tx->status === 'held', 422, 'Only a held charge in its grace period can be resold');
                abort_unless($tx->subscription_id, 422, 'Charge has no subscription to resell');
                $sub = DB::table('subscriptions')->where('id', $tx->subscription_id)->lockForUpdate()->first();
                abort_unless($sub, 404, 'Subscription missing');

                // Reuse the listing the subscriber created; create it (prorated price) only if none exists yet.
                $listing = DB::table('resale_listings')->where('subscription_id', $sub->id)->where('status', 'active')->orderByDesc('listed_at')->first();
                if (! $listing) {
                    $now = now();
                    $periodEnd = $sub->current_period_end ? Carbon::parse($sub->current_period_end) : null;
                    $asking = ResaleSettlement::askingPrice((int) $tx->amount_minor, $periodEnd, $now);
                    $id = (string) Str::uuid();
                    DB::table('resale_listings')->insert([
                        'id' => $id, 'merchant_id' => $merchantId, 'subscription_id' => $sub->id, 'seller_merchant_user_id' => (string) $sub->merchant_user_id,
                        'seller_email' => (string) $sub->email, 'status' => 'active', 'entitlement_status' => 'pending', 'asking_price_minor' => $asking,
                        'currency' => trim((string) $tx->currency) ?: 'USD', 'access_expires_at' => $periodEnd, 'listed_at' => $now, 'correlation_id' => (string) Str::uuid(),
                        'redeem_token' => HostedPurchase::token((string) (json_decode((string) $tx->metadata, true)['display_name'] ?? 'user'), 15),
                        'metadata' => json_encode(['demo' => true, 'source' => 'developer_sandbox']), 'created_at' => $now, 'updated_at' => $now,
                    ]);
                    $listing = DB::table('resale_listings')->where('id', $id)->first();
                }

                $buyerFirst = collect(['Taylor', 'Quinn', 'Avery', 'Reese'])->random();
                $buyerUserId = 'demo_buyer_'.Str::lower(Str::random(6));
                $buyerEmail = Str::lower($buyerFirst).'.'.$buyerUserId.'@demo.luvupay.local';
                $this->scopedMerchant = $merchantId;
                $r = $settlement->settle($listing, $buyerEmail, $buyerUserId, 'mock', 'PAYPAL-RESALE-'.strtoupper(Str::random(8)), 'developer_sandbox');
                $this->logWebhookEvent('paypal', 'PAYMENT.CAPTURE.COMPLETED', ['id' => 'RESALE-'.$listing->id, 'status' => 'COMPLETED', 'amount' => ['value' => number_format($r['sale_minor'] / 100, 2, '.', '')]]);

                $meta = json_decode((string) $tx->metadata, true) ?: [];

                return $r + ['tx_id' => $tx->id, 'buyer_email' => $buyerEmail, 'buyer_name' => $buyerFirst, 'name' => $meta['display_name'] ?? 'Member'];
            });
        } catch (\RuntimeException $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 409);
        }

        return response()->json([
            'ok' => true,
            'message' => sprintf('Resale complete - seller %s - platform fee %s. The original charge is now released to your wallet.', $this->m($result['seller_minor'], $result['currency']), $this->m($result['fee_minor'], $result['currency'])),
            'row' => $this->formatRow($result['tx_id']),
            'preview' => [
                'phase' => 'resale', 'name' => $result['name'], 'buyer_name' => $result['buyer_name'], 'buyer_email' => $result['buyer_email'],
                'status' => 'RESOLD', 'certificate_code' => null, 'charity_name' => null, 'charity_minor' => 0, 'trust_score' => 92, 'churn_risk' => 'low',
            ],
        ]);
    }

    private function m(int $minor, string $cur): string
    {
        return (strtoupper($cur) === 'USD' ? '$' : '').number_format($minor / 100, 2).(strtoupper($cur) === 'USD' ? '' : ' '.strtoupper($cur));
    }
}
