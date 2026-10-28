<?php

namespace App\Console\Commands;

use App\Contracts\NebiusAIContract;
use App\Services\Nebius\ChurnPredictionService;
use App\Services\Nebius\DynamicPricingService;
use Illuminate\Console\Command;
use Throwable;

/**
 * Proves the Nebius Token Factory integration works end to end with real calls.
 * Every check bypasses the response cache with a random nonce and fails when the answer came from a fallback.
 */
class NebiusCheckCommand extends Command
{
    protected $signature = 'nebius:check';

    protected $description = 'Make real Nebius Token Factory calls (text, JSON, churn, pricing) and fail on any fallback';

    public function handle(NebiusAIContract $ai, ChurnPredictionService $churn, DynamicPricingService $pricing): int
    {
        $key = (string) config('nebius.api_key');
        $this->line('Base URL : '.config('nebius.base_url'));
        $this->line('Model    : '.config('nebius.model'));
        $this->line('API key  : '.($key === '' ? 'MISSING' : substr($key, 0, 4).'…'.substr($key, -4)));

        if ($key === '' || ! config('nebius.enabled')) {
            $this->error('Set NEBIUS_API_KEY and NEBIUS_ENABLED=true first');

            return self::FAILURE;
        }

        $nonce = bin2hex(random_bytes(4));
        $failed = 0;

        $failed += $this->step('Plain text completion', function () use ($ai) {
            $r = $ai->chatText('nebius_check', [['role' => 'user', 'content' => 'Reply with exactly one word: pong']]);

            return ['ok' => $r['content'] !== '', 'detail' => sprintf('"%s" · model %s · %d ms · %s tokens', mb_substr($r['content'], 0, 40), $r['model'] ?? '?', $r['latency_ms'], $r['tokens_used'] ?? '?')];
        });

        $failed += $this->step('JSON completion', function () use ($ai) {
            $r = $ai->chatJson('nebius_check', [
                ['role' => 'system', 'content' => 'Respond with ONLY a JSON object.'],
                ['role' => 'user', 'content' => 'Return {"status":"ok","sum":'.'4}'.' and nothing else'],
            ]);

            return ['ok' => ($r['data']['status'] ?? null) === 'ok', 'detail' => json_encode($r['data']).' · '.$r['latency_ms'].' ms'];
        });

        $failed += $this->step('Churn prediction (live, cache bypassed)', function () use ($churn, $nonce) {
            $r = $churn->predict(['tenure_months' => 14, 'logins_last_30d' => 1, 'second_chance_used' => 2, 'check' => $nonce]);

            return ['ok' => $r->source === 'nebius', 'detail' => "source={$r->source} score={$r->churnRiskScore} level={$r->riskLevel} · ".$r->primaryReason];
        });

        $failed += $this->step('Resale pricing (live, cache bypassed)', function () use ($pricing, $nonce) {
            $r = $pricing->suggestPrice(['product' => 'StreamNetFly Premium', 'original_price' => 15.99, 'days_remaining' => 12, 'check' => $nonce]);

            return ['ok' => $r->source === 'nebius', 'detail' => "source={$r->source} price={$r->recommendedPrice} range={$r->priceRangeLow}-{$r->priceRangeHigh} · ".$r->reasoning];
        });

        $this->newLine();
        $failed === 0 ? $this->info('All Nebius checks passed') : $this->error("{$failed} check(s) failed, see storage/logs/laravel.log and ai_request_logs");

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }

    /** @param callable():array{ok:bool,detail:string} $check */
    private function step(string $label, callable $check): int
    {
        try {
            $r = $check();
            $r['ok'] ? $this->line("[OK]   {$label}: {$r['detail']}") : $this->error("[FAIL] {$label}: {$r['detail']}");

            return $r['ok'] ? 0 : 1;
        } catch (Throwable $e) {
            $this->error("[FAIL] {$label}: ".$e->getMessage());

            return 1;
        }
    }
}
