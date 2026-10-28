<?php

namespace App\Http\Controllers\Demo;

use App\Contracts\NebiusAIContract;
use App\Http\Controllers\Demo\Concerns\DemoSupport;
use App\Services\Nebius\ChurnPredictionService;
use App\Support\MerchantProject;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Missed-cancellation flow with real Nebius AI nudges (when NEBIUS_API_KEY is set)
 * and merchant-controlled post-grace options (resale / charity).
 */
final class DemoLifecycleController
{
    use DemoSupport;

    public function simulateForgot(Request $request): JsonResponse
    {
        $merchantId = $this->merchantId($request);
        $data = $request->validate(['transaction_id' => ['required', 'uuid']]);
        $project = MerchantProject::current();

        $aiBundle = ['nudge' => '', 'source' => 'local_template'];

        $result = DB::transaction(function () use ($merchantId, $data, $project, &$aiBundle) {
            $tx = DB::table('transactions')
                ->where('merchant_id', $merchantId)
                ->where('id', $data['transaction_id'])
                ->lockForUpdate()
                ->first();

            abort_unless($tx, 404, 'Transaction not found');
            abort_unless($tx->type === 'charge', 422, 'Only charge rows can enter grace period');

            $meta = json_decode($tx->metadata ?? '{}', true) ?: [];
            $lifecycle = strtoupper((string) ($meta['lifecycle'] ?? ''));
            abort_unless(
                $lifecycle === 'ACTIVE' || $lifecycle === '' || in_array($tx->status, ['completed', 'settled'], true),
                422,
                'Row must be ACTIVE before missed-cancellation test'
            );

            $amount = (int) $tx->amount_minor;
            $windowHours = (int) config('luvupay.defaults.second_chance_window_hours', 48);
            $refundBps = $project->partialRefundBps;
            $refundMinor = (int) round($amount * $refundBps / 10000);

            $name = $meta['display_name'] ?? Str::before($tx->email ?? 'Member', '@');
            $product = $meta['product'] ?? ($project->product() ?: 'Subscription');
            $usage = $meta['usage_pattern'] ?? 'regular viewer';

            $aiBundle = $this->generateAiNudge(
                $name,
                $product,
                $usage,
                $refundBps,
                $merchantId,
                $tx->email ?? null
            );
            $aiNudge = $aiBundle['nudge'];

            $now = now();
            $expires = $now->copy()->addHours($windowHours);

            $meta['lifecycle'] = 'GRACE PERIOD';
            $meta['demo'] = true;
            $meta['display_name'] = $name;
            $meta['product'] = $product;
            $meta['missed_cancellation'] = true;
            $meta['grace'] = [
                'started_at' => $now->toIso8601String(),
                'expires_at' => $expires->toIso8601String(),
                'window_hours' => $windowHours,
                'partial_refund_bps' => $refundBps,
                'partial_refund_minor' => $refundMinor,
                'default_if_no_response' => 'expire',
            ];
            $meta['ai_nudge'] = $aiNudge;
            $meta['ai_source'] = $aiBundle['source'] ?? 'local_template';
            $meta['ai_churn'] = $aiBundle['churn'] ?? null;
            $meta['offers'] = $this->availableOffers($project, $refundMinor, $amount);

            DB::table('transactions')->where('id', $tx->id)->update([
                'status' => 'held',
                'second_chance_expires_at' => $expires,
                'second_chance_triggered_at' => $now,
                'metadata' => json_encode($meta),
                'updated_at' => $now,
            ]);

            if ($tx->subscription_id) {
                DB::table('subscriptions')->where('id', $tx->subscription_id)->update([
                    'status' => 'past_due',
                    'updated_at' => $now,
                ]);
            }

            $this->logWebhookEvent('luvupay', 'subscription.grace_period_started', [
                'transaction_id' => $tx->id,
                'expires_at' => $expires->toIso8601String(),
                'partial_refund_bps' => $refundBps,
                'ai_source' => $meta['ai_source'],
            ]);

            if (($meta['ai_source'] ?? '') === 'nebius') {
                $this->logWebhookEvent('nebius', 'ai.grace_period_nudge', [
                    'transaction_id' => $tx->id,
                    'tokens_used' => $aiBundle['tokens_used'] ?? null,
                    'risk_level' => $aiBundle['churn']['risk_level'] ?? null,
                ]);
            }

            return [
                'transaction_id' => $tx->id,
                'lifecycle' => 'GRACE PERIOD',
                'expires_at' => $expires->toIso8601String(),
                'partial_refund_minor' => $refundMinor,
                'partial_refund_pct' => (int) round($refundBps / 100),
                'offers' => $meta['offers'],
                'ai_nudge' => $aiNudge,
                'ai_source' => $meta['ai_source'],
                'ai_churn' => $meta['ai_churn'],
            ];
        });

        return response()->json([
            'ok' => true,
            'message' => 'Missed cancellation — 48h GRACE PERIOD opened. Partial refund offered. Default if no response: expire (churn)',
            'grace' => $result,
            'row' => $this->formatRow($result['transaction_id']),
            'project_features' => [
                'marketplace_resale' => $project->marketplaceResale,
                'charity_split' => $project->charitySplit,
            ],
            'integrations' => [
                'nebius' => ($result['ai_source'] ?? '') === 'nebius',
                'paypal' => $this->paypalConfigured(),
            ],
            'recent_events' => $this->recentWebhookEvents(10),
        ]);
    }

    public function decision(Request $request): JsonResponse
    {
        $merchantId = $this->merchantId($request);
        $project = MerchantProject::current();
        $data = $request->validate([
            'transaction_id' => ['required', 'uuid'],
            'choice' => ['required', 'in:renew,partial_refund,expire,cancel,resale,donate,retain'],
        ]);

        if ($data['choice'] === 'cancel') {
            $data['choice'] = 'expire';
        }
        if ($data['choice'] === 'retain') {
            $data['choice'] = 'partial_refund';
        }

        if ($data['choice'] === 'resale' && ! $project->marketplaceResale) {
            abort(422, 'Marketplace Resale is not enabled for this project. Enable it in project settings.');
        }
        if ($data['choice'] === 'donate' && ! $project->charitySplit) {
            abort(422, 'Charity Split is not enabled for this project. Enable it in project settings.');
        }

        $tx = DB::table('transactions')
            ->where('merchant_id', $merchantId)
            ->where('id', $data['transaction_id'])
            ->first();
        abort_unless($tx, 404);

        $meta = json_decode($tx->metadata ?? '{}', true) ?: [];
        $amount = (int) $tx->amount_minor;
        $refundBps = (int) ($meta['grace']['partial_refund_bps'] ?? $project->partialRefundBps);
        $refundMinor = (int) ($meta['grace']['partial_refund_minor'] ?? round($amount * $refundBps / 10000));
        $now = now();
        $choice = $data['choice'];
        $msg = '';

        DB::transaction(function () use ($tx, &$meta, $choice, $amount, $refundMinor, $refundBps, $now, $merchantId, &$msg) {
            if ($choice === 'renew') {
                $meta['lifecycle'] = 'ACTIVE';
                $meta['decision'] = 'user_renewed';
                unset($meta['grace']);
                DB::table('transactions')->where('id', $tx->id)->update([
                    'status' => 'completed',
                    'second_chance_expires_at' => null,
                    'metadata' => json_encode($meta),
                    'updated_at' => $now,
                ]);
                if ($tx->subscription_id) {
                    DB::table('subscriptions')->where('id', $tx->subscription_id)->update([
                        'status' => 'active',
                        'updated_at' => $now,
                    ]);
                }
                $this->logWebhookEvent('paypal', 'BILLING.SUBSCRIPTION.PAYMENT.COMPLETED', [
                    'transaction_id' => $tx->id,
                    'event' => 'payment_retry_success',
                ]);
                $msg = 'Payment retry successful — ACTIVE';
            } elseif ($choice === 'partial_refund') {
                $meta['lifecycle'] = 'AI-RETAINED';
                $meta['decision'] = 'partial_refund_accepted';
                $meta['smart_convert'] = [
                    'type' => 'partial_refund',
                    'refund_bps' => $refundBps,
                    'refund_minor' => $refundMinor,
                    'chargeback_prevented' => true,
                ];
                DB::table('transactions')->where('id', $tx->id)->update([
                    'status' => 'completed',
                    'second_chance_expires_at' => null,
                    'metadata' => json_encode($meta),
                    'updated_at' => $now,
                ]);
                if ($tx->subscription_id) {
                    DB::table('subscriptions')->where('id', $tx->subscription_id)->update([
                        'status' => 'active',
                        'updated_at' => $now,
                    ]);
                }
                // Real PayPal refund when capture id exists + driver is paypal
                $this->tryPayPalRefund($tx, $refundMinor, $meta);
                $this->logWebhookEvent('luvupay', 'subscription.partial_refund', [
                    'transaction_id' => $tx->id,
                    'refund_minor' => $refundMinor,
                ]);
                $msg = 'Partial refund accepted — AI-RETAINED (chargeback avoided)';
            } elseif ($choice === 'resale') {
                $meta['lifecycle'] = 'PENDING RESALE';
                $meta['decision'] = 'marketplace_resale';
                $meta['resale'] = [
                    'listed_at' => $now->toIso8601String(),
                    'remaining_value_minor' => $amount,
                ];
                DB::table('transactions')->where('id', $tx->id)->update([
                    'status' => 'held',
                    'metadata' => json_encode($meta),
                    'updated_at' => $now,
                ]);
                // Platform/merchant fee on residual (vision: ~10% of remaining value)
                $feeMinor = max(100, (int) round($amount * 0.10));
                $meta['resale']['fee_minor'] = $feeMinor;
                $meta['resale']['path'] = 'marketplace_resale';
                DB::table('transactions')->where('id', $tx->id)->update([
                    'metadata' => json_encode($meta),
                    'updated_at' => $now,
                ]);
                try {
                    if (\Illuminate\Support\Facades\Schema::hasTable('resale_listings') && $tx->subscription_id) {
                        DB::table('resale_listings')->insert([
                            'id' => (string) Str::uuid(),
                            'merchant_id' => $merchantId,
                            'subscription_id' => $tx->subscription_id,
                            'seller_merchant_user_id' => $tx->merchant_user_id ?? 'demo',
                            'seller_email' => $tx->email ?? 'seller@example.com',
                            'status' => 'active',
                            'entitlement_status' => 'pending',
                            'asking_price_minor' => max(100, (int) round($amount * 0.6)),
                            'currency' => $tx->currency ?? 'USD',
                            'access_expires_at' => $now->copy()->addDays(15),
                            'listed_at' => $now,
                            'redeem_token' => \App\Services\Hosted\HostedPurchase::token((string) ($meta['display_name'] ?? $tx->email ?? 'user'), 15),
                            'metadata' => json_encode(['seller_name' => $meta['display_name'] ?? null, 'source' => 'decision']),
                            'created_at' => $now,
                            'updated_at' => $now,
                        ]);
                    }
                } catch (\Throwable $e) {
                    \Illuminate\Support\Facades\Log::warning('demo.resale_listing_failed', ['err' => $e->getMessage()]);
                }
                DB::table('transactions')->insert([
                    'id' => (string) Str::uuid(),
                    'merchant_id' => $merchantId,
                    'subscription_id' => $tx->subscription_id,
                    'type' => 'resale_fee',
                    'status' => 'completed',
                    'idempotency_key' => 'resale-fee-'.$tx->id,
                    'merchant_user_id' => $tx->merchant_user_id,
                    'email' => $tx->email,
                    'amount_minor' => $feeMinor,
                    'currency' => $tx->currency,
                    'provider' => 'luvupay',
                    'provider_reference' => 'RESALE-FEE-'.$tx->id,
                    'processed_at' => $now,
                    'metadata' => json_encode([
                        'lifecycle' => 'PENDING RESALE',
                        'parent_transaction_id' => $tx->id,
                        'path' => 'resale_fee',
                    ]),
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $this->logWebhookEvent('luvupay', 'marketplace.listing_created', [
                    'transaction_id' => $tx->id,
                    'fee_minor' => $feeMinor,
                ]);
                $msg = 'Remaining access listed on Marketplace — PENDING RESALE';
            } elseif ($choice === 'donate') {
                $meta['lifecycle'] = 'AI-RETAINED';
                $meta['decision'] = 'charity_donate';
                $meta['charity'] = [
                    'donated_minor' => $amount,
                    'at' => $now->toIso8601String(),
                ];
                DB::table('transactions')->where('id', $tx->id)->update([
                    'status' => 'completed',
                    'second_chance_expires_at' => null,
                    'metadata' => json_encode($meta),
                    'updated_at' => $now,
                ]);
                DB::table('transactions')->insert([
                    'id' => (string) Str::uuid(),
                    'merchant_id' => $merchantId,
                    'subscription_id' => $tx->subscription_id,
                    'type' => 'charity_payout',
                    'status' => 'completed',
                    'idempotency_key' => 'charity-'.$tx->id,
                    'merchant_user_id' => $tx->merchant_user_id,
                    'email' => $tx->email,
                    'amount_minor' => $amount,
                    'currency' => $tx->currency,
                    'provider' => 'luvupay',
                    'provider_reference' => 'CHARITY-'.$tx->id,
                    'processed_at' => $now,
                    'metadata' => json_encode([
                        'lifecycle' => 'AI-RETAINED',
                        'parent_transaction_id' => $tx->id,
                        'path' => 'charity_donate',
                    ]),
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $this->logWebhookEvent('luvupay', 'charity.donation', [
                    'transaction_id' => $tx->id,
                    'amount_minor' => $amount,
                ]);
                $msg = 'Remaining value donated — AI-RETAINED (charity split)';
            } else {
                $meta['lifecycle'] = 'CHURNED';
                $meta['decision'] = 'expired_no_response';
                $meta['grace']['closed_at'] = $now->toIso8601String();
                $meta['grace']['outcome'] = 'expire';
                DB::table('transactions')->where('id', $tx->id)->update([
                    'status' => 'cancelled',
                    'second_chance_expires_at' => null,
                    'metadata' => json_encode($meta),
                    'updated_at' => $now,
                ]);
                if ($tx->subscription_id) {
                    DB::table('subscriptions')->where('id', $tx->subscription_id)->update([
                        'status' => 'expired',
                        'updated_at' => $now,
                    ]);
                }
                $this->logWebhookEvent('paypal', 'BILLING.SUBSCRIPTION.EXPIRED', [
                    'transaction_id' => $tx->id,
                ]);
                $msg = 'No response in grace window — subscription EXPIRED (churn). No force.';
            }
        });

        return response()->json([
            'ok' => true,
            'message' => $msg,
            'choice' => $choice,
            'listing' => $choice === 'resale' ? $this->activeListingPayload($tx->subscription_id ?? null) : null,
            'row' => $this->formatRow($tx->id),
            'project_features' => [
                'marketplace_resale' => $project->marketplaceResale,
                'charity_split' => $project->charitySplit,
            ],
            'integrations' => [
                'nebius' => $this->nebiusConfigured(),
                'paypal' => $this->paypalConfigured(),
            ],
            'recent_events' => $this->recentWebhookEvents(10),
        ]);
    }

    /** @return list<array<string, mixed>> */
    private function availableOffers(MerchantProject $project, int $refundMinor, int $amount): array
    {
        $offers = [
            [
                'id' => 'renew',
                'label' => 'Retry payment & stay Active',
                'description' => 'User pays the outstanding charge and continues the subscription.',
            ],
            [
                'id' => 'partial_refund',
                'label' => 'Accept partial refund ('.((int) round($project->partialRefundBps / 100)).'%)',
                'description' => 'AI offer: cancel within 48h and recover part of the charge. Prevents chargebacks.',
                'refund_minor' => $refundMinor,
            ],
            [
                'id' => 'expire',
                'label' => 'Let it expire (default)',
                'description' => 'If the user does nothing in 48h, the subscription ends. No force.',
            ],
        ];

        if ($project->marketplaceResale) {
            $offers[] = [
                'id' => 'resale',
                'label' => 'Sell remaining days (Marketplace)',
                'description' => 'Enabled by merchant: list unused access on the marketplace.',
                'amount_minor' => $amount,
            ];
        }

        if ($project->charitySplit) {
            $offers[] = [
                'id' => 'donate',
                'label' => 'Donate remaining value',
                'description' => 'Enabled by merchant: route residual value to charity.',
                'amount_minor' => $amount,
            ];
        }

        return $offers;
    }

    /**
     * @return array{nudge: string, source: string, churn?: mixed, tokens_used?: mixed, error?: string}
     */
    private function generateAiNudge(
        string $name,
        string $product,
        string $usage,
        int $refundBps,
        ?string $merchantId = null,
        ?string $email = null
    ): array {
        $pct = (int) round($refundBps / 100);
        $templates = [
            "Hi {$name}, your {$product} renewal is pending. Within 48 hours you can retry payment, or cancel with a {$pct}% partial refund — your call.",
            "{$name}, we noticed {$usage} on {$product}. Before the grace window ends, you can keep access or take a {$pct}% refund if you cancel now.",
            "Hey {$name} — {$product} is in a 48h grace period. Stay active, take a {$pct}% refund, or simply let it expire. No pressure.",
        ];
        $fallback = $templates[array_rand($templates)];

        if (! $this->nebiusConfigured()) {
            return ['nudge' => $fallback, 'source' => 'local_template'];
        }

        try {
            /** @var ChurnPredictionService $churnSvc */
            $churnSvc = app(ChurnPredictionService::class);
            $prediction = $churnSvc->predict([
                'display_name' => $name,
                'product' => $product,
                'usage_pattern' => $usage,
                'email_domain' => $email ? (explode('@', $email)[1] ?? '') : '',
                'lifecycle' => 'grace_period',
                'partial_refund_pct' => $pct,
                'window_hours' => 48,
            ], $merchantId);

            /** @var NebiusAIContract $ai */
            $ai = app(NebiusAIContract::class);
            $res = $ai->chatText('grace_period_nudge', [
                [
                    'role' => 'system',
                    'content' => 'You write short, ethical subscription recovery messages for LuvuPay. Respect user agency. Never pressure. Max 2 sentences. Plain text only.',
                ],
                [
                    'role' => 'user',
                    'content' => json_encode([
                        'name' => $name,
                        'product' => $product,
                        'usage' => $usage,
                        'partial_refund_pct' => $pct,
                        'churn_risk_score' => $prediction->churnRiskScore ?? null,
                        'risk_level' => $prediction->riskLevel ?? null,
                        'primary_reason' => $prediction->primaryReason ?? null,
                        'task' => "Write a grace-period notification offering {$pct}% partial refund within 48h, or let the subscription expire.",
                    ], JSON_UNESCAPED_UNICODE),
                ],
            ], $merchantId);

            $text = trim((string) ($res['content'] ?? ''), " \t\n\r\0\x0B\"");
            if ($text === '') {
                throw new \UnexpectedValueException('Model returned an empty nudge');
            }

            return [
                'nudge' => $text,
                'source' => 'nebius',
                'model' => $res['model'] ?? config('nebius.model'),
                'churn_source' => $prediction->source ?? null,
                'churn' => method_exists($prediction, 'toArray') ? $prediction->toArray() : [
                    'risk_level' => $prediction->riskLevel ?? null,
                    'score' => $prediction->churnRiskScore ?? null,
                ],
                'tokens_used' => $res['tokens_used'] ?? null,
            ];
        } catch (Throwable $e) {
            Log::warning('nebius.grace_nudge_failed', ['error' => $e->getMessage()]);

            return ['nudge' => $fallback, 'source' => 'local_template', 'error' => $e->getMessage()];
        }
    }

    private function nebiusConfigured(): bool
    {
        return (bool) config('nebius.enabled', true)
            && trim((string) config('nebius.api_key', '')) !== '';
    }

    private function paypalConfigured(): bool
    {
        return trim((string) config('services.paypal.client_id', '')) !== ''
            && trim((string) config('services.paypal.secret', '')) !== '';
    }

    /** Best-effort PayPal capture refund when we have a capture id. */
    private function tryPayPalRefund(object $tx, int $refundMinor, array &$meta): void
    {
        if (! $this->paypalConfigured() || $refundMinor <= 0) {
            return;
        }
        $captureId = $meta['paypal_capture_id'] ?? null;
        if (! is_string($captureId) || $captureId === '') {
            return;
        }
        try {
            $client = \App\Services\PayPal\PayPalClient::fromConfig();
            $money = \App\Support\Money::of($refundMinor, $tx->currency ?? 'USD');
            $ref = $client->refundPayment($captureId, $money, 'partial-refund-'.$tx->id, 'LuvuPay partial refund (grace period)');
            $meta['paypal_refund_id'] = $ref['id'] ?? null;
            $meta['paypal_refund_status'] = $ref['status'] ?? null;
            $this->logWebhookEvent('paypal', 'PAYMENT.CAPTURE.REFUNDED', [
                'transaction_id' => $tx->id,
                'capture_id' => $captureId,
                'refund' => $meta['paypal_refund_id'],
            ]);
        } catch (Throwable $e) {
            Log::warning('paypal.partial_refund_failed', ['tx' => $tx->id, 'error' => $e->getMessage()]);
            $this->logWebhookEvent('paypal', 'PAYMENT.REFUND.FAILED', [
                'transaction_id' => $tx->id,
                'error' => $e->getMessage(),
            ], 'failed');
        }
    }

    /** @return array{token:string,url:string,price_minor:int}|null */
    private function activeListingPayload(?string $subscriptionId): ?array
    {
        if (! $subscriptionId) {
            return null;
        }
        $l = DB::table('resale_listings')->where('subscription_id', $subscriptionId)->where('status', 'active')
            ->whereNotNull('redeem_token')->orderByDesc('listed_at')->first();

        return $l ? ['token' => $l->redeem_token, 'url' => url('/marketplace'), 'price_minor' => (int) $l->asking_price_minor] : null;
    }
}
