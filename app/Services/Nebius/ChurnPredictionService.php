<?php

namespace App\Services\Nebius;

use App\Contracts\NebiusAIContract;
use App\DTOs\AI\ChurnPredictionResult;
use Throwable;

class ChurnPredictionService
{
    public function __construct(
        private readonly NebiusAIContract $client,
        private readonly AiResponseCache $cache,
    ) {}

    /** @param array<string,mixed> $userData */
    public function predict(array $userData, ?string $merchantId = null): ChurnPredictionResult
    {
        $hash = AiResponseCache::hash(['f' => 'churn', 'd' => $userData]);
        if ($hit = $this->cache->get('churn_prediction', $hash)) {
            return ChurnPredictionResult::fromArray($hit, fromCache: true);
        }

        $system = <<<'PROMPT'
You are a Subscription Behavior Analyst for LuvuPay, an ethical subscription platform.
Analyze anonymized behavioral metrics and predict churn / "forget to cancel" risk.
Respond with ONLY a JSON object (no markdown) with keys:
churn_risk_score (0-100 integer),
risk_level ("low"|"medium"|"high"|"critical"),
primary_reason (short natural language),
recommended_actions (array of actionable strings, e.g. "Send 72h nudge email", "Offer resale option", "Trigger discount"),
confidence (0.0-1.0).
Consider Second Chance usage, login decay, and tenure. Prefer retention actions that respect user agency.
PROMPT;

        $user = "Behavioral data (anonymized):\n".json_encode($userData, JSON_PRETTY_PRINT);

        try {
            $res = $this->client->chatJson('churn_prediction', [
                ['role' => 'system', 'content' => $system],
                ['role' => 'user', 'content' => $user],
            ], $merchantId);
            $data = json_decode($res['content'], true) ?: [];
            $result = ChurnPredictionResult::fromArray($data);
            $this->cache->put('churn_prediction', $hash, $result->toArray());

            return $result;
        } catch (Throwable) {
            return ChurnPredictionResult::fallback();
        }
    }
}
