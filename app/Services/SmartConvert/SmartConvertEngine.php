<?php

namespace App\Services\SmartConvert;

use App\Contracts\NebiusAIContract;
use App\Exceptions\SmartConvertException;
use App\Services\PayPal\PayPalClient;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * AI Smart-Convert: a Nebius-hosted NVIDIA Nemotron model PROPOSES how unused subscription value is settled
 * (store credit / charity / fee); PayPal Payouts EXECUTES the charity part.
 *
 *   propose()  – model call + policy guardrails. Moves no money. Safe to show to a merchant for approval.
 *   execute()  – PayPal Payouts for the charity amount. Returns only what PayPal really returned.
 *
 * Honesty rules (the evidence page depends on them):
 *   - path = "ai" only when a live model call produced the split. A canned split is path = "fallback" and is
 *     refused outright in strict mode (config luvupay.smart_convert.strict, default on).
 *   - A PayPal batch id is only ever reported when PayPal returned it. With the mock driver the payout is
 *     labelled simulated and carries no batch id.
 *   - Whatever the model returned is stored untouched (raw_split); every guardrail adjustment is listed.
 */
final class SmartConvertEngine
{
    public function __construct(private readonly ?NebiusAIContract $ai = null) {}

    /**
     * Backwards-compatible one-shot: propose, then execute.
     *
     * @throws SmartConvertException
     */
    public function negotiateAndSettle(string $merchantId, array $context = []): array
    {
        $plan = $this->propose($merchantId, $context);
        $plan['paypal'] = $this->execute($merchantId, $plan['charity_minor'], $plan['currency'], $context['charity_email'] ?? null, (string) Str::uuid());

        return $plan;
    }

    /**
     * Ask the model for a split. No side effects except the ai_request_logs row.
     *
     * @return array{
     *   path:string, unused_days:int, unused_value_minor:int, currency:string,
     *   store_credit_minor:int, charity_minor:int, fee_minor:int,
     *   raw_split:?array, guardrails:list<string>, rationale:list<string>, reasoning:?string,
     *   narrative:string, risk_score:int, input:array,
     *   ai:array{live:bool,model:?string,tokens:?int,latency_ms:?int,provider:string,error:?string,log_id:?string}
     * }
     *
     * @throws SmartConvertException stage "ai" in strict mode when no live model answer could be obtained
     */
    public function propose(string $merchantId, array $ctx = []): array
    {
        $days = max(1, (int) ($ctx['unused_days'] ?? 10));
        $value = max(1, (int) ($ctx['unused_value_minor'] ?? 3000));
        $currency = strtoupper((string) ($ctx['currency'] ?? 'USD')) ?: 'USD';
        $email = (string) ($ctx['email'] ?? 'user@example.com');
        $policy = [
            'max_fee_minor' => intdiv($value * (int) config('luvupay.smart_convert.max_fee_bps', 1500), 10000),
            'max_charity_minor' => intdiv($value * (int) config('luvupay.smart_convert.max_charity_bps', 5000), 10000),
        ];
        $input = [
            'unused_days' => $days,
            'unused_value_minor' => $value,
            'currency' => $currency,
            'inactive_days' => (int) ($ctx['inactive_days'] ?? 20),
            'email_hash' => substr(hash('sha256', $email), 0, 16),   // never the address itself
            'policy' => $policy,
        ];

        $model = (string) config('nebius.model', 'nvidia/NVIDIA-Nemotron-3-Nano-30B-A3B');
        $started = microtime(true);

        try {
            $res = $this->callModel($merchantId, $input);
            $raw = $res['data'];
            foreach (['store_credit_minor', 'charity_minor', 'fee_minor'] as $key) {
                if (! isset($raw[$key]) || ! is_numeric($raw[$key]) || (int) $raw[$key] < 0) {
                    throw new \UnexpectedValueException("Model response is missing a valid {$key}");
                }
            }
            $rawSplit = [
                'store_credit_minor' => (int) $raw['store_credit_minor'],
                'charity_minor' => (int) $raw['charity_minor'],
                'fee_minor' => (int) $raw['fee_minor'],
            ];
            [$split, $guardrails] = $this->applyGuardrails($rawSplit, $value, $policy);

            return $split + [
                'path' => 'ai',
                'unused_days' => $days,
                'unused_value_minor' => $value,
                'currency' => $currency,
                'raw_split' => $rawSplit,
                'guardrails' => $guardrails,
                'rationale' => $this->rationale($raw['rationale'] ?? null),
                'reasoning' => $res['reasoning'] ?? null,
                'narrative' => Str::limit(trim((string) ($raw['narrative'] ?? '')) ?: 'Model returned no narrative.', 400, '…'),
                'risk_score' => max(0, min(100, (int) ($raw['risk_score'] ?? 50))),
                'input' => $input,
                'ai' => [
                    'live' => true,
                    'model' => $res['model'] ?? $model,
                    'tokens' => $res['tokens_used'] ?? null,
                    'latency_ms' => $res['latency_ms'] ?? (int) round((microtime(true) - $started) * 1000),
                    'provider' => 'Nebius Token Factory · NVIDIA Nemotron',
                    'error' => null,
                    'log_id' => $res['log_id'] ?? null,
                ],
            ];
        } catch (Throwable $e) {
            Log::warning('smart_convert.ai_unavailable', ['err' => $e->getMessage()]);
            $latency = (int) round((microtime(true) - $started) * 1000);

            if (config('luvupay.smart_convert.strict', true)) {
                throw new SmartConvertException('ai', 'The AI model did not return a usable answer: '.Str::limit($e->getMessage(), 240), ['latency_ms' => $latency, 'model' => $model]);
            }

            return $this->fallbackPlan($days, $value, $currency, $input, $model, $latency, $e->getMessage());
        }
    }

    /**
     * Send the charity part through PayPal Payouts.
     *
     * @return array{live:bool,simulated:bool,batch_id:?string,sender_batch_id:?string,status:?string,mode:string,receiver_masked:?string,verified:bool,error:?string}
     *
     * @throws SmartConvertException stage "paypal" when the driver is paypal and PayPal did not accept the payout
     */
    public function execute(string $merchantId, int $charityMinor, string $currency, ?string $receiverEmail, string $runId): array
    {
        $mode = (string) config('services.paypal.mode', 'sandbox');
        $base = ['live' => false, 'simulated' => false, 'batch_id' => null, 'sender_batch_id' => null, 'status' => null, 'mode' => $mode, 'receiver_masked' => null, 'verified' => false, 'error' => null];

        if ($charityMinor <= 0) {
            return ['status' => 'SKIPPED', 'error' => 'Charity share is zero: nothing to pay out'] + $base;
        }

        $creds = ! empty(config('services.paypal.client_id')) && ! empty(config('services.paypal.secret'));
        if ((string) config('luvupay.payment.driver', 'mock') !== 'paypal' || ! $creds) {
            // Explicit mock driver: labelled simulation, and NO batch id is invented.
            return ['simulated' => true, 'status' => 'SIMULATED', 'error' => 'Mock payment driver (LUVUPAY_PAYMENT_DRIVER != paypal): no PayPal call was made'] + $base;
        }

        $receiver = $this->charityReceiver($receiverEmail);
        if ($receiver === null) {
            throw new SmartConvertException('paypal', 'No charity PayPal email configured (PAYPAL_CHARITY_SANDBOX_EMAIL or an active charity wallet).');
        }

        $senderBatchId = 'sc-'.substr(str_replace('-', '', $runId), 0, 24);   // stable per run → a retry cannot double-pay
        try {
            $client = app()->bound(PayPalClient::class) ? app(PayPalClient::class) : PayPalClient::fromConfig();
            $res = $client->createPayout([[
                'receiver' => $receiver,
                'amount' => Money::of($charityMinor, $currency),
                'note' => 'LuvuPay Smart-Convert matched charity donation',
                'sender_item_id' => $senderBatchId.'-1',
            ]], $senderBatchId, $senderBatchId, 'LuvuPay Smart-Convert charity payout');

            $batchId = (string) data_get($res, 'batch_header.payout_batch_id', '');
            if ($batchId === '') {
                throw new \RuntimeException('PayPal accepted the request but returned no payout_batch_id');
            }
            $status = (string) data_get($res, 'batch_header.batch_status', 'PENDING');
            $verified = false;

            if (config('luvupay.smart_convert.verify_batch', true)) {
                try {
                    $check = $client->getPayoutBatch($batchId);
                    $verified = (string) data_get($check, 'batch_header.payout_batch_id') === $batchId;
                    $status = (string) data_get($check, 'batch_header.batch_status', $status);
                } catch (Throwable $e) {
                    Log::notice('smart_convert.batch_verify_failed', ['batch' => $batchId, 'err' => $e->getMessage()]);
                }
            }

            return ['live' => true, 'batch_id' => $batchId, 'sender_batch_id' => $senderBatchId, 'status' => $status, 'receiver_masked' => $this->mask($receiver), 'verified' => $verified] + $base;
        } catch (Throwable $e) {
            Log::warning('smart_convert.paypal_failed', ['err' => $e->getMessage()]);
            throw new SmartConvertException('paypal', 'PayPal Payouts did not accept the payout: '.Str::limit($e->getMessage(), 240), ['sender_batch_id' => $senderBatchId]);
        }
    }

    // ------------------------------------------------------------------ internals

    /** @return array<string,mixed> chatJson result */
    private function callModel(string $merchantId, array $input): array
    {
        if (! config('nebius.enabled') || ! config('nebius.api_key')) {
            throw new \RuntimeException('NEBIUS_ENABLED / NEBIUS_API_KEY is not configured');
        }

        $client = $this->ai ?? app(NebiusAIContract::class);
        $wantTrace = (bool) config('luvupay.smart_convert.request_reasoning', false);

        return $client->chatJson('smart_convert', [
            [
                'role' => 'system',
                'content' => 'You are the LuvuPay Smart-Convert negotiator. A subscriber forgot to cancel; propose how the UNUSED value is settled without a cash refund. '
                    .'Reply with ONLY a JSON object with keys: store_credit_minor (int), charity_minor (int), fee_minor (int), risk_score (int 0-100), '
                    .'rationale (array of 2-4 short strings, each under 140 characters, stating the concrete reasons for THIS split), narrative (one sentence). '
                    .'All amounts are integers in minor units and the three amounts MUST sum exactly to unused_value_minor. '
                    .'Respect the policy limits: fee_minor <= policy.max_fee_minor and charity_minor <= policy.max_charity_minor. '
                    .'Prefer store credit for the largest share so the merchant keeps the customer, while giving a meaningful share to charity.',
            ],
            ['role' => 'user', 'content' => json_encode($input, JSON_THROW_ON_ERROR)],
        ], $merchantId, $wantTrace ? ['thinking' => true, 'max_tokens' => max(4096, (int) config('nebius.max_tokens', 2048))] : []);
    }

    /**
     * Clamp the model's proposal to policy and make it sum to the unused value, recording every change.
     *
     * @return array{0:array{store_credit_minor:int,charity_minor:int,fee_minor:int},1:list<string>}
     */
    private function applyGuardrails(array $raw, int $value, array $policy): array
    {
        $store = $raw['store_credit_minor'];
        $charity = $raw['charity_minor'];
        $fee = $raw['fee_minor'];
        $notes = [];

        if ($fee > $policy['max_fee_minor']) {
            $notes[] = "Fee capped at {$policy['max_fee_minor']} (model proposed {$fee}); the excess went to store credit.";
            $store += $fee - $policy['max_fee_minor'];
            $fee = $policy['max_fee_minor'];
        }
        if ($charity > $policy['max_charity_minor']) {
            $notes[] = "Charity capped at {$policy['max_charity_minor']} (model proposed {$charity}); the excess went to store credit.";
            $store += $charity - $policy['max_charity_minor'];
            $charity = $policy['max_charity_minor'];
        }

        $sum = $store + $charity + $fee;
        if ($sum !== $value) {
            $diff = $value - $sum;
            if ($diff > 0) {
                $store += $diff;
                $notes[] = "Model amounts summed to {$sum}, not {$value}; the missing {$diff} was added to store credit.";
            } else {
                // Too much: trim store credit first, then charity, never below zero; fee is left alone
                $over = -$diff;
                $cut = min($store, $over);
                $store -= $cut;
                $over -= $cut;
                if ($over > 0) {
                    $c2 = min($charity, $over);
                    $charity -= $c2;
                    $over -= $c2;
                }
                $notes[] = "Model amounts summed to {$sum}, not {$value}; trimmed ".(-$diff).' to match.';
            }
        }

        return [['store_credit_minor' => $store, 'charity_minor' => $charity, 'fee_minor' => $fee], $notes];
    }

    /** @return list<string> */
    private function rationale(mixed $raw): array
    {
        $items = is_array($raw) ? $raw : (is_string($raw) && trim($raw) !== '' ? [$raw] : []);

        return array_slice(array_values(array_filter(array_map(
            fn ($x) => is_scalar($x) ? Str::limit(trim((string) $x), 200, '…') : '',
            $items
        ))), 0, 4);
    }

    /** Offline-development split. Only reachable with SMART_CONVERT_STRICT=false and always labelled path=fallback. */
    private function fallbackPlan(int $days, int $value, string $currency, array $input, string $model, int $latency, string $error): array
    {
        $store = intdiv($value, 2);
        $charity = intdiv($value, 3);
        $fee = $value - $store - $charity;

        return [
            'path' => 'fallback',
            'unused_days' => $days,
            'unused_value_minor' => $value,
            'currency' => $currency,
            'store_credit_minor' => $store,
            'charity_minor' => $charity,
            'fee_minor' => $fee,
            'raw_split' => null,
            'guardrails' => [],
            'rationale' => ['FALLBACK: fixed 50/33/17 policy split. No AI model produced this result.'],
            'reasoning' => null,
            'narrative' => 'Deterministic fallback split because the AI model was unavailable.',
            'risk_score' => 50,
            'input' => $input,
            'ai' => ['live' => false, 'model' => $model, 'tokens' => null, 'latency_ms' => $latency, 'provider' => 'NONE: deterministic fallback, no AI call succeeded', 'error' => Str::limit($error, 300), 'log_id' => null],
        ];
    }

    private function charityReceiver(?string $explicit): ?string
    {
        foreach ([$explicit, config('services.paypal.charity_payout_email'), $this->walletEmail()] as $c) {
            $c = trim((string) $c);
            // Only the exact seeder placeholder is refused. PayPal sandbox accounts legitimately look like sb-xxx@business.example.com.
            if ($c !== '' && filter_var($c, FILTER_VALIDATE_EMAIL) && strcasecmp($c, 'donations@charity.example.org') !== 0) {
                return $c;
            }
        }

        return null;
    }

    private function walletEmail(): ?string
    {
        try {
            return DB::table('charity_wallets')->where('is_active', true)->value('payout_email');
        } catch (Throwable) {
            return null;
        }
    }

    private function mask(string $email): string
    {
        [$l, $d] = array_pad(explode('@', $email, 2), 2, '');

        return $d === '' ? '***' : mb_substr($l, 0, 2).'***@'.$d;
    }
}
