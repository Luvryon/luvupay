<?php

namespace App\DTOs\AI;

final class ChurnPredictionResult
{
    /** @param list<string> $recommendedActions */
    public function __construct(
        public readonly int $churnRiskScore,
        public readonly string $riskLevel,
        public readonly string $primaryReason,
        public readonly array $recommendedActions,
        public readonly float $confidence,
        public readonly bool $fromCache = false,
        public readonly string $source = 'nebius',
    ) {}

    public static function fromArray(array $data, bool $fromCache = false): self
    {
        $score = (int) ($data['churn_risk_score'] ?? 0);
        $score = max(0, min(100, $score));
        $level = strtolower((string) ($data['risk_level'] ?? 'low'));
        if (! in_array($level, ['low', 'medium', 'high', 'critical'], true)) {
            $level = $score >= 80 ? 'critical' : ($score >= 60 ? 'high' : ($score >= 35 ? 'medium' : 'low'));
        }
        $actions = $data['recommended_actions'] ?? [];
        if (! is_array($actions)) {
            $actions = [];
        }

        return new self(
            $score,
            $level,
            (string) ($data['primary_reason'] ?? 'Insufficient signal'),
            array_values(array_map('strval', $actions)),
            max(0.0, min(1.0, (float) ($data['confidence'] ?? 0.5))),
            $fromCache,
            $fromCache ? 'cache' : 'nebius',
        );
    }

    public function toArray(): array
    {
        return [
            'churn_risk_score' => $this->churnRiskScore,
            'risk_level' => $this->riskLevel,
            'primary_reason' => $this->primaryReason,
            'recommended_actions' => $this->recommendedActions,
            'confidence' => $this->confidence,
            'from_cache' => $this->fromCache,
            'source' => $this->source,
        ];
    }

    public static function fallback(string $reason = 'AI unavailable; heuristic fallback'): self
    {
        return new self(50, 'medium', $reason, ['Send 72h Adaptive Nudge', 'Offer resale of remaining days'], 0.35, false, 'fallback');
    }
}
