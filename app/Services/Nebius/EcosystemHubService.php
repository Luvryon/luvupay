<?php

namespace App\Services\Nebius;

use App\Contracts\NebiusAIContract;
use App\DTOs\AI\SubscriptionHealthReport;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Throwable;

class EcosystemHubService
{
    public function __construct(
        private readonly NebiusAIContract $client,
        private readonly AiResponseCache $cache,
    ) {}

    /**
     * Aggregate active subscriptions across LuvuPay merchants by email, then LLM health report.
     *
     * @param  list<array<string,mixed>>|null  $subscriptions  Optional pre-aggregated rows
     */
    public function generateHealthReport(string $email, ?array $subscriptions = null, ?string $merchantId = null): SubscriptionHealthReport
    {
        $subs = $subscriptions ?? $this->aggregateByEmail($email, $merchantId);
        $payload = ['email_hash' => hash('sha256', strtolower($email)), 'subscriptions' => $subs];
        $hash = AiResponseCache::hash(['f' => 'hub', 'd' => $payload]);
        if ($hit = $this->cache->get('ecosystem_hub', $hash)) {
            return SubscriptionHealthReport::fromArray($hit, fromCache: true);
        }

        $spend = 0.0;
        foreach ($subs as $s) {
            $spend += (float) ($s['price'] ?? 0);
        }

        $system = <<<'PROMPT'
You are a Personal Finance Coach for LuvuPay Ecosystem Hub.
You only see subscriptions from merchants registered on LuvuPay (not external apps).
Produce a friendly health report. Respond with ONLY JSON:
total_monthly_spend (number),
subscription_health_score (0-100 integer),
insights (array of {type: "warning"|"suggestion"|"opportunity", message: string, action_url: string}),
natural_language_report (2-3 short paragraphs on health and resale/save opportunities).
PROMPT;

        $user = "User subscriptions across LuvuPay merchants:\n".json_encode($subs, JSON_PRETTY_PRINT);

        try {
            $res = $this->client->chatJson('ecosystem_hub', [
                ['role' => 'system', 'content' => $system],
                ['role' => 'user', 'content' => $user],
            ], $merchantId);
            $data = $res['data'];
            if (trim((string) ($data['natural_language_report'] ?? '')) === '') {
                throw new InvalidArgumentException('Model response is missing natural_language_report');
            }
            if (! isset($data['total_monthly_spend'])) {
                $data['total_monthly_spend'] = $spend;
            }
            $result = SubscriptionHealthReport::fromArray($data);
            $this->cache->put('ecosystem_hub', $hash, $result->toArray());

            return $result;
        } catch (Throwable $e) {
            Log::warning('nebius.hub_fallback', ['error' => $e->getMessage()]);

            return SubscriptionHealthReport::fallback($spend);
        }
    }

    /** @return list<array<string,mixed>> */
    public function aggregateByEmail(string $email, ?string $merchantId = null): array
    {
        $email = strtolower($email);
        $rows = DB::table('subscriptions as s')
            ->join('merchants as m', 'm.id', '=', 's.merchant_id')
            ->whereRaw('LOWER(s.email) = ?', [$email])
            ->when($merchantId, fn ($q) => $q->where('s.merchant_id', $merchantId)) // an API key only sees its own merchant
            ->whereIn('s.status', ['active', 'paused_by_nudge', 'pending_approval'])
            ->get([
                'm.name as merchant_name',
                's.plan_code',
                's.amount_minor',
                's.currency',
                's.billing_interval',
                's.current_period_end',
                's.status',
            ]);

        $out = [];
        foreach ($rows as $r) {
            $days = $r->current_period_end
                ? max(0, (int) ceil((strtotime((string) $r->current_period_end) - time()) / 86400))
                : 0;
            $out[] = [
                'merchant_name' => $r->merchant_name,
                'plan_name' => $r->plan_code ?? 'plan',
                'price' => round(((int) $r->amount_minor) / 100, 2),
                'billing_cycle' => $r->billing_interval ?? 'month',
                'last_used_days_ago' => null,
                'features_used' => [],
                'days_remaining' => $days,
                'status' => $r->status,
            ];
        }

        return $out;
    }
}
