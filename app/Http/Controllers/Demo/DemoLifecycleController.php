<?php

namespace App\Http\Controllers\Demo;

use App\Contracts\NebiusAIContract;
use App\Http\Controllers\Demo\Concerns\DemoSupport;
use App\Services\Nebius\ChurnPredictionService;
use App\Services\Luvu\GraceDecisionService;
use App\Services\Luvu\GraceStagePlanner;
use App\Services\Luvu\RenewalJourney;
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

    /**
     * POST demo/forgot: start the renewal journey for a subscriber who already paid. The paid period is untouched; the
     * NEXT charge (renewal) is created and placed at {hours_before} on the timeline (168/72/48/6 before expiry, -25 = +1 day after).
     */
    public function simulateForgot(Request $request, RenewalJourney $journey): JsonResponse
    {
        $merchantId = $this->merchantId($request);
        $data = $request->validate(['transaction_id' => ['required', 'uuid'], 'hours_before' => ['nullable', 'integer', 'in:168,72,48,6,-25']]);
        $project = MerchantProject::current();
        $position = (int) ($data['hours_before'] ?? 48);

        try {
            $renewal = $journey->ensureRenewal($merchantId, $data['transaction_id']);
            $moved = $journey->moveTo($merchantId, $renewal->id, $position, $project);
        } catch (\Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage() ?: 'Not found'], $e->getStatusCode());
        } catch (\RuntimeException $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 409);
        }
        $plan = $moved['plan'];

        return response()->json([
            'ok' => true,
            'message' => 'Renewal journey started: '.($plan['label'] ?? ''),
            'grace' => [
                'transaction_id' => $renewal->id, 'paid_transaction_id' => $data['transaction_id'], 'lifecycle' => 'GRACE PERIOD',
                'expires_at' => $plan['deadline_at'] ?? null, 'partial_refund_pct' => (int) round($project->partialRefundBps / 100),
                'ai_nudge' => $plan['message'] ?? '', 'ai_source' => $plan['message_source'] ?? 'template',
            ],
            'stage' => $plan,
            'row' => $this->formatRow($renewal->id),
            'project_features' => ['marketplace_resale' => $project->marketplaceResale, 'charity_split' => $project->charitySplit],
            'integrations' => ['nebius' => ($plan['message_source'] ?? '') === 'nebius' || $this->nebiusConfigured(), 'paypal' => $this->paypalConfigured()],
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
     * POST demo/grace/stage: sandbox time travel for ONE renewal. Moves the expiry T of the current period so the renewal
     * sits at the chosen point (168 / 72 / 48 / 6 hours before, -25 = +1 day after expiry, -49 = window closed).
     * The stage's reminder (email + phone notification) is generated and sent once per stage.
     */
    public function setStage(Request $request, RenewalJourney $journey): JsonResponse
    {
        $merchantId = $this->merchantId($request);
        $data = $request->validate(['transaction_id' => ['required', 'uuid'], 'hours_before' => ['required', 'integer', 'in:168,72,48,6,-25,-49']]);

        try {
            $r = $journey->moveTo($merchantId, $data['transaction_id'], (int) $data['hours_before'], MerchantProject::current());
        } catch (\Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage() ?: 'Not found'], $e->getStatusCode());
        } catch (\RuntimeException $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 409);
        }

        return response()->json([
            'ok' => true, 'message' => $r['closed'] ? $r['message'] : 'Timeline moved: '.($r['plan']['label'] ?? ''),
            'closed' => $r['closed'], 'stage' => $r['plan'], 'row' => $this->formatRow($data['transaction_id']), 'recent_events' => $this->recentWebhookEvents(10),
        ]);
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
