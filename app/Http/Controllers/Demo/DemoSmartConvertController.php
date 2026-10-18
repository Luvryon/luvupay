<?php

namespace App\Http\Controllers\Demo;

use App\Http\Controllers\Demo\Concerns\DemoSupport;
use App\Services\SmartConvert\SmartConvertEngine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Developer Sandbox: AI Smart-Convert protocol (Nebius negotiation + PayPal payout evidence).
 * Gated by the demo.simulator middleware (off by default in production).
 */
final class DemoSmartConvertController
{
    use DemoSupport;

    /** POST /api/v1/dashboard/demo/smart-convert — AI Smart-Convert Engine (Nebius + PayPal Sandbox) */
    public function smartConvert(Request $request, SmartConvertEngine $engine): JsonResponse
    {
        $merchantId = $this->merchantId($request);
        $data = $request->validate([
            'transaction_id' => ['nullable', 'uuid'],
        ]);

        $tx = null;
        if (! empty($data['transaction_id'])) {
            $tx = DB::table('transactions')
                ->where('merchant_id', $merchantId)
                ->where('id', $data['transaction_id'])
                ->first();
        }
        if (! $tx) {
            $tx = DB::table('transactions')
                ->where('merchant_id', $merchantId)
                ->where('type', 'charge')
                ->orderByDesc('created_at')
                ->first();
        }

        $email = $tx->email ?? 'smartconvert.demo@demo.luvupay.local';
        $currency = $tx->currency ?? $this->merchantCurrency($merchantId);
        $charityEmail = DB::table('charity_wallets')->where('is_active', true)->value('payout_email');

        // AI negotiate (Nebius) + charity payout (PayPal Sandbox) — outside tight lock when possible
        $plan = $engine->negotiateAndSettle($merchantId, [
            'email' => $email,
            'unused_days' => 10,
            'unused_value_minor' => 3000,
            'inactive_days' => 20,
            'charity_email' => $charityEmail,
        ]);

        $result = DB::transaction(function () use ($merchantId, $tx, $email, $currency, $plan) {
            $now = now();
            $store = $plan['store_credit_minor'];
            $charity = $plan['charity_minor'];
            $fee = $plan['fee_minor'];
            $unusedValue = $plan['unused_value_minor'];
            $unusedDays = $plan['unused_days'];

            if ($tx) {
                $meta = json_decode($tx->metadata ?? '{}', true) ?: [];
                $meta['lifecycle'] = 'SMART-CONVERTED';
                $meta['breakdown'] = sprintf(
                    'Store Credit: $%s | Charity: $%s | Fee: $%s',
                    number_format($store / 100, 2),
                    number_format($charity / 100, 2),
                    number_format($fee / 100, 2)
                );
                $meta['smart_convert'] = [
                    'unused_days' => $unusedDays,
                    'unused_value_minor' => $unusedValue,
                    'store_credit_minor' => $store,
                    'charity_minor' => $charity,
                    'fee_minor' => $fee,
                    'narrative' => $plan['narrative'],
                    'risk_score' => $plan['risk_score'],
                    'ai' => $plan['ai'],
                    'paypal' => $plan['paypal'],
                    'chargeback_prevented' => true,
                    'revenue_retained_minor' => $store + $fee,
                ];

                DB::table('transactions')->where('id', $tx->id)->update([
                    'status' => 'completed',
                    'metadata' => json_encode($meta),
                    'churn_risk' => 'low',
                    'trust_score' => min(99, (int) ($tx->trust_score ?? 70) + 15),
                    'updated_at' => $now,
                ]);
                $txId = $tx->id;
            } else {
                $txId = (string) Str::uuid();
                DB::table('transactions')->insert([
                    'id' => $txId,
                    'merchant_id' => $merchantId,
                    'type' => 'charge',
                    'status' => 'completed',
                    'idempotency_key' => 'smart-convert-'.$txId,
                    'email' => $email,
                    'amount_minor' => $unusedValue,
                    'currency' => $currency,
                    'provider' => config('luvupay.payment.driver') === 'paypal' ? 'paypal' : 'mock',
                    'provider_reference' => $plan['paypal']['batch_id'] ?? ('SMART-'.strtoupper(Str::random(8))),
                    'processed_at' => $now,
                    'trust_score' => 91,
                    'churn_risk' => 'low',
                    'metadata' => json_encode([
                        'demo' => true,
                        'lifecycle' => 'SMART-CONVERTED',
                        'display_name' => 'Alex',
                        'breakdown' => sprintf(
                            'Store Credit: $%s | Charity: $%s | Fee: $%s',
                            number_format($store / 100, 2),
                            number_format($charity / 100, 2),
                            number_format($fee / 100, 2)
                        ),
                        'smart_convert' => [
                            'unused_days' => $unusedDays,
                            'unused_value_minor' => $unusedValue,
                            'store_credit_minor' => $store,
                            'charity_minor' => $charity,
                            'fee_minor' => $fee,
                            'narrative' => $plan['narrative'],
                            'ai' => $plan['ai'],
                            'paypal' => $plan['paypal'],
                            'chargeback_prevented' => true,
                            'revenue_retained_minor' => $store + $fee,
                        ],
                    ]),
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            $this->logWebhookEvent('luvupay', 'smart_convert.completed', [
                'transaction_id' => $txId,
                'store_credit_minor' => $store,
                'charity_minor' => $charity,
                'fee_minor' => $fee,
                'ai' => $plan['ai'],
                'paypal' => $plan['paypal'],
            ]);

            try {
                $this->recordOutboundDelivery($merchantId, 'smart_convert.completed', [
                    'transaction_id' => $txId,
                    'store_credit_minor' => $store,
                    'charity_minor' => $charity,
                    'paypal_batch_id' => $plan['paypal']['batch_id'] ?? null,
                ], success: true);
            } catch (\Throwable $e) {
            }

            try {
                DB::table('transactions')->insert([
                    'id' => (string) Str::uuid(),
                    'merchant_id' => $merchantId,
                    'parent_transaction_id' => $txId,
                    'type' => 'charity_payout',
                    'status' => 'completed',
                    'idempotency_key' => 'smart-charity-'.Str::uuid(),
                    'email' => 'Ocean Cleanup Alliance',
                    'amount_minor' => $charity,
                    'currency' => $currency,
                    'provider' => ($plan['paypal']['live'] ?? false) ? 'paypal' : 'mock',
                    'provider_reference' => $plan['paypal']['batch_id'] ?? ('PAYPAL-CHARITY-SC-'.strtoupper(Str::random(6))),
                    'processed_at' => $now,
                    'metadata' => json_encode([
                        'source' => 'smart_convert',
                        'matched_by_merchant' => true,
                        'paypal' => $plan['paypal'],
                    ]),
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            } catch (\Throwable $e) {
            }

            return ['tx_id' => $txId];
        });

        $row = $this->formatRow($result['tx_id']);

        return response()->json([
            'ok' => true,
            'message' => 'Smart-Convert complete — chargeback prevented · merchant revenue retained',
            'row' => $row,
            'conversion' => [
                'unused_days' => $plan['unused_days'],
                'unused_value_minor' => $plan['unused_value_minor'],
                'store_credit_minor' => $plan['store_credit_minor'],
                'charity_minor' => $plan['charity_minor'],
                'fee_minor' => $plan['fee_minor'],
                'narrative' => $plan['narrative'],
                'risk_score' => $plan['risk_score'],
            ],
            'ai' => $plan['ai'],
            'paypal' => $plan['paypal'],
        ]);
    }
}
