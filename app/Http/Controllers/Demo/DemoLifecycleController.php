<?php

namespace App\Http\Controllers\Demo;

use App\Contracts\NebiusAIContract;
use App\Http\Controllers\Demo\Concerns\DemoSupport;
use App\Services\Nebius\ChurnPredictionService;
use App\Services\Luvu\GraceDecisionService;
use App\Services\Luvu\GraceStagePlanner;
use App\Services\Luvu\SplitPayService;
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
        $data = $request->validate(['transaction_id' => ['required', 'uuid'], 'hours_before' => ['nullable', 'integer', 'in:168,72,48,6']]);
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
            $windowHours = (int) ($data['hours_before'] ?? config('luvupay.defaults.second_chance_window_hours', 48));
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
            'message' => 'Missed cancellation - GRACE PERIOD opened with '.((int) ($data['hours_before'] ?? 48)).'h left. Default if no response: expire (churn)',
            'grace' => $result,
            'stage' => $this->stagePlan($merchantId, $result['transaction_id']),
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

    public function decision(Request $request, GraceDecisionService $decisions): JsonResponse
    {
        $merchantId = $this->merchantId($request);
        $project = MerchantProject::current();
        $data = $request->validate([
            'transaction_id' => ['required', 'uuid'],
            'choice' => ['required', 'in:renew,split_pay,partial_refund,expire,cancel,resale,donate,retain'],
        ]);

        try {
            $r = $decisions->decide($merchantId, $data['transaction_id'], $data['choice'], $project);
        } catch (\Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $e) {
            return response()->json(['ok' => false, 'error' => 'decision_rejected', 'message' => $e->getMessage() ?: 'Not found'], $e->getStatusCode());
        } catch (\RuntimeException $e) {
            return response()->json(['ok' => false, 'error' => 'decision_rejected', 'message' => $e->getMessage()], 409);
        }

        return response()->json([
            'ok' => true,
            'message' => $r['message'],
            'choice' => $r['choice'],
            'listing' => $r['choice'] === 'resale' ? $this->activeListingPayload($r['subscription_id']) : null,
            'plan' => $r['plan'] ?? null,
            'stage' => $r['choice'] === 'resale' ? $this->stagePlan($merchantId, $data['transaction_id']) : null,
            'row' => $this->formatRow($data['transaction_id']),
            'project_features' => ['marketplace_resale' => $project->marketplaceResale, 'charity_split' => $project->charitySplit],
            'integrations' => ['nebius' => $this->nebiusConfigured(), 'paypal' => $this->paypalConfigured()],
            'recent_events' => $this->recentWebhookEvents(10),
        ]);
    }

    /** @return array<string,mixed>|null */
    private function stagePlan(string $merchantId, string $txId): ?array
    {
        $tx = DB::table('transactions')->where('merchant_id', $merchantId)->where('id', $txId)->first();
        if (! $tx) {
            return null;
        }
        $sub = $tx->subscription_id ? DB::table('subscriptions')->where('id', $tx->subscription_id)->first() : null;
        $meta = json_decode((string) ($tx->metadata ?? '{}'), true) ?: [];

        return app(GraceStagePlanner::class)->plan($tx, $sub, MerchantProject::current(), $meta['display_name'] ?? null);
    }

    /**
     * POST demo/grace/stage: sandbox time travel for ONE held charge. Moves its grace deadline so it has
     * {hours_before} left (168 / 72 / 48 / 6), or closes the window now (0). Everything downstream (sweeper,
     * stage rules, resale price) then behaves exactly as it would in real life at that moment.
     */
    public function setStage(Request $request, GraceDecisionService $decisions): JsonResponse
    {
        $merchantId = $this->merchantId($request);
        $data = $request->validate(['transaction_id' => ['required', 'uuid'], 'hours_before' => ['required', 'integer', 'in:168,72,48,6,0']]);
        $hours = (int) $data['hours_before'];

        try {
            if ($hours === 0) {
                $r = $decisions->decide($merchantId, $data['transaction_id'], 'expire', MerchantProject::current(), 'timer');

                return response()->json(['ok' => true, 'message' => $r['message'], 'closed' => true, 'stage' => null, 'row' => $this->formatRow($data['transaction_id']), 'recent_events' => $this->recentWebhookEvents(10)]);
            }

            DB::transaction(function () use ($merchantId, $data, $hours) {
                $tx = DB::table('transactions')->where('merchant_id', $merchantId)->where('id', $data['transaction_id'])->lockForUpdate()->first();
                abort_unless($tx, 404, 'Transaction not found');
                $meta = json_decode((string) ($tx->metadata ?? '{}'), true) ?: [];
                if ($tx->type !== 'charge' || $tx->status !== 'held' || ($meta['decision'] ?? '') === 'split_pay') {
                    throw new \RuntimeException('Only a charge that is still in its grace period can be moved on the timeline.');
                }
                $now = now();
                $expires = $now->copy()->addHours($hours);
                $meta['grace'] = ($meta['grace'] ?? []) + [];
                $meta['grace']['expires_at'] = $expires->toIso8601String();
                $meta['grace']['window_hours'] = $hours;
                $meta['simulated_stage'] = ['hours_before' => $hours, 'at' => $now->toIso8601String()];
                $stage = GraceStagePlanner::keyForRemaining($hours - 0.0001);
                // A listing that this stage no longer allows is withdrawn and the charge goes back to its grace state.
                if (($meta['decision'] ?? '') === 'marketplace_resale' && ! in_array('resale', GraceStagePlanner::allowedChoices($stage), true)) {
                    if ($tx->subscription_id) {
                        \App\Services\Resale\ResaleSettlement::cancelOpenListings($tx->subscription_id);
                    }
                    $meta['lifecycle'] = 'GRACE PERIOD';
                    unset($meta['decision'], $meta['resale']);
                }
                DB::table('transactions')->where('id', $tx->id)->update(['second_chance_expires_at' => $expires, 'metadata' => json_encode($meta), 'updated_at' => $now]);
                $this->scopedMerchant = $merchantId;
                $this->logWebhookEvent('luvupay', 'subscription.grace_stage_changed', ['transaction_id' => $tx->id, 'hours_left' => $hours, 'stage' => $stage, 'simulated' => true]);
            });
        } catch (\Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage() ?: 'Not found'], $e->getStatusCode());
        } catch (\RuntimeException $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 409);
        }

        $plan = $this->stagePlan($merchantId, $data['transaction_id']);

        return response()->json(['ok' => true, 'message' => 'Timeline moved: '.($plan['label'] ?? ''), 'stage' => $plan, 'row' => $this->formatRow($data['transaction_id']), 'recent_events' => $this->recentWebhookEvents(10)]);
    }

    /** POST demo/split/collect: the subscriber pays the next instalment (simulated "pay now" from the console). */
    public function collectInstallment(Request $request, SplitPayService $split): JsonResponse
    {
        $merchantId = $this->merchantId($request);
        $data = $request->validate(['transaction_id' => ['required', 'uuid']]);
        $plan = $split->forCharge($merchantId, $data['transaction_id']);
        if (! $plan) {
            return response()->json(['ok' => false, 'message' => 'This charge has no instalment plan.'], 404);
        }
        try {
            $r = $split->payInstallment($merchantId, $plan['plan_id'], null, 'merchant_console');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage() ?: 'Not found'], $e->getStatusCode());
        } catch (\RuntimeException $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 409);
        }

        return response()->json([
            'ok' => true,
            'message' => $r['completed'] ? 'Final instalment collected - plan completed, charge settled in full' : 'Instalment collected',
            'plan' => $r,
            'row' => $this->formatRow($data['transaction_id']),
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
                'id' => 'split_pay',
                'label' => 'Split pay (down payment now, rest in 7 days)',
                'description' => 'Subscriber pays part now and the rest later; access stays active while the plan is current.',
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
            "Hi {$name}, your {$product} renewal is pending. Within 48 hours you can retry payment, or cancel with a {$pct}% partial refund - your call.",
            "{$name}, we noticed {$usage} on {$product}. Before the grace window ends, you can keep access or take a {$pct}% refund if you cancel now.",
            "Hey {$name} - {$product} is in a 48h grace period. Stay active, take a {$pct}% refund, or simply let it expire. No pressure.",
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
