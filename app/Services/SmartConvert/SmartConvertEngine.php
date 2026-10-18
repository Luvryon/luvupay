<?php

namespace App\Services\SmartConvert;

use App\Contracts\NebiusAIContract;
use App\Services\PayPal\PayPalClient;
use App\Support\Money;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * AI Smart-Convert: Nebius negotiates non-cash settlement; PayPal Sandbox pays charity.
 * Graceful fallback so demo day never dies if keys are missing.
 */
final class SmartConvertEngine
{
    public function __construct(private readonly ?NebiusAIContract $ai = null) {}

    /**
     * @return array{
     *   unused_days:int, unused_value_minor:int,
     *   store_credit_minor:int, charity_minor:int, fee_minor:int,
     *   narrative:string, risk_score:int,
     *   ai: array{live:bool,model:?string,tokens:?int,latency_ms:?int,provider:string,error:?string},
     *   paypal: array{live:bool,batch_id:?string,mode:string,error:?string}
     * }
     */
    public function negotiateAndSettle(string $merchantId, array $context = []): array
    {
        $unusedDays = (int) ($context['unused_days'] ?? 10);
        $unusedValue = (int) ($context['unused_value_minor'] ?? 3000);
        $email = (string) ($context['email'] ?? 'user@example.com');

        $ai = $this->runNebius($merchantId, $unusedDays, $unusedValue, $email, $context);

        $store = (int) ($ai['split']['store_credit_minor'] ?? 1500);
        $charity = (int) ($ai['split']['charity_minor'] ?? 1000);
        $fee = (int) ($ai['split']['fee_minor'] ?? 500);
        // Normalize to unused value
        $sum = $store + $charity + $fee;
        if ($sum !== $unusedValue && $sum > 0) {
            $scale = $unusedValue / $sum;
            $store = (int) round($store * $scale);
            $charity = (int) round($charity * $scale);
            $fee = $unusedValue - $store - $charity;
        }

        $paypal = $this->runPayPalCharityPayout($merchantId, $charity, $context['charity_email'] ?? null);

        return [
            'unused_days' => $unusedDays,
            'unused_value_minor' => $unusedValue,
            'store_credit_minor' => $store,
            'charity_minor' => $charity,
            'fee_minor' => $fee,
            'narrative' => $ai['narrative'],
            'risk_score' => $ai['risk_score'],
            'ai' => $ai['meta'],
            'paypal' => $paypal,
        ];
    }

    /** @return array{split:array,narrative:string,risk_score:int,meta:array} */
    private function runNebius(string $merchantId, int $days, int $valueMinor, string $email, array $ctx): array
    {
        $model = (string) config('nebius.model', 'meta-llama/Llama-3.1-70b-instruct');
        $fallback = [
            'split' => [
                'store_credit_minor' => 1500,
                'charity_minor' => 1000,
                'fee_minor' => 500,
            ],
            'narrative' => 'User inactive 20 days; cash refund would destroy merchant LTV. Non-cash settlement preserves ecosystem value.',
            'risk_score' => 85,
            'meta' => [
                'live' => false,
                'model' => $model,
                'tokens' => null,
                'latency_ms' => null,
                'provider' => 'Nebius Token Factory · NVIDIA Nemotron (offline fallback)',
                'error' => null,
            ],
        ];

        if (! config('nebius.enabled') || ! config('nebius.api_key')) {
            $fallback['meta']['error'] = 'NEBIUS_ENABLED/API_KEY missing — using deterministic negotiation';

            return $fallback;
        }

        $client = $this->ai ?? app(NebiusAIContract::class);
        $started = microtime(true);

        try {
            $res = $client->chatJson('smart_convert', [
                [
                    'role' => 'system',
                    'content' => 'You are LuvuPay Smart-Convert negotiator powered by NVIDIA Nemotron via Nebius Token Factory. '
                        .'Propose a non-cash settlement for unused subscription time. Respond ONLY JSON keys: '
                        .'store_credit_minor (int cents), charity_minor (int cents), fee_minor (int cents), '
                        .'risk_score (0-100), narrative (one sentence). Sum of the three amounts MUST equal unused_value_minor. '
                        .'Prefer keeping majority as store credit so merchant retains ecosystem value.',
                ],
                [
                    'role' => 'user',
                    'content' => json_encode([
                        'unused_days' => $days,
                        'unused_value_minor' => $valueMinor,
                        'email_hash' => hash('sha256', $email),
                        'inactive_days' => $ctx['inactive_days'] ?? 20,
                        'chargeback_signal' => 'high',
                        'merchant_id' => $merchantId,
                    ], JSON_THROW_ON_ERROR),
                ],
            ], $merchantId);

            $latency = (int) round((microtime(true) - $started) * 1000);
            $raw = is_string($res['content'] ?? null) ? json_decode($res['content'], true) : [];
            if (! is_array($raw)) {
                $raw = [];
            }

            return [
                'split' => [
                    'store_credit_minor' => (int) ($raw['store_credit_minor'] ?? 1500),
                    'charity_minor' => (int) ($raw['charity_minor'] ?? 1000),
                    'fee_minor' => (int) ($raw['fee_minor'] ?? 500),
                ],
                'narrative' => (string) ($raw['narrative'] ?? $fallback['narrative']),
                'risk_score' => (int) ($raw['risk_score'] ?? 85),
                'meta' => [
                    'live' => true,
                    'model' => $model,
                    'tokens' => $res['tokens_used'] ?? null,
                    'latency_ms' => $latency,
                    'provider' => 'Nebius Token Factory · NVIDIA Nemotron',
                    'error' => null,
                ],
            ];
        } catch (Throwable $e) {
            Log::warning('smart_convert.nebius_fallback', ['err' => $e->getMessage()]);
            $fallback['meta']['error'] = $e->getMessage();
            $fallback['meta']['latency_ms'] = (int) round((microtime(true) - $started) * 1000);

            return $fallback;
        }
    }

    /** @return array{live:bool,batch_id:?string,mode:string,error:?string} */
    private function runPayPalCharityPayout(string $merchantId, int $charityMinor, ?string $receiverEmail): array
    {
        $mode = (string) config('services.paypal.mode', 'sandbox');
        $driver = (string) config('luvupay.payment.driver', 'mock');

        if ($charityMinor <= 0) {
            return ['live' => false, 'batch_id' => null, 'mode' => $mode, 'error' => 'zero charity'];
        }

        if ($driver !== 'paypal' || empty(config('services.paypal.client_id')) || empty(config('services.paypal.secret'))) {
            return [
                'live' => false,
                'batch_id' => 'MOCK-BATCH-'.strtoupper(substr(md5($merchantId.microtime()), 0, 10)),
                'mode' => $mode,
                'error' => 'LUVUPAY_PAYMENT_DRIVER not paypal or credentials missing — simulated sandbox batch id',
            ];
        }

        $receiver = $receiverEmail
            ?: (string) (\Illuminate\Support\Facades\DB::table('charity_wallets')->where('is_active', true)->value('payout_email')
                ?: 'donations@charity.example.org');

        try {
            $client = PayPalClient::fromConfig();
            $batchId = 'SC-'.strtoupper(substr(md5($merchantId.uniqid('', true)), 0, 16));
            $requestId = 'sc-payout-'.uniqid('', true);
            $res = $client->createPayout([
                [
                    'receiver' => $receiver,
                    'amount' => Money::of($charityMinor, 'USD'),
                    'note' => 'LuvuPay Smart-Convert matched charity donation',
                    'sender_item_id' => 'charity-'.$batchId,
                ],
            ], $batchId, $requestId, 'LuvuPay Smart-Convert charity payout');

            $paypalBatch = data_get($res, 'batch_header.payout_batch_id')
                ?: data_get($res, 'payout_batch_id')
                ?: $batchId;

            return [
                'live' => true,
                'batch_id' => (string) $paypalBatch,
                'mode' => $mode,
                'error' => null,
            ];
        } catch (Throwable $e) {
            Log::warning('smart_convert.paypal_fallback', ['err' => $e->getMessage()]);

            return [
                'live' => false,
                'batch_id' => 'SANDBOX-FALLBACK-'.strtoupper(substr(md5($e->getMessage()), 0, 8)),
                'mode' => $mode,
                'error' => $e->getMessage(),
            ];
        }
    }
}
