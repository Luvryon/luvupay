<?php

namespace App\DTOs\AI;

final class SubscriptionHealthReport
{
    /** @param list<array{type:string,message:string,action_url:string}> $insights */
    public function __construct(
        public readonly float $totalMonthlySpend,
        public readonly int $subscriptionHealthScore,
        public readonly array $insights,
        public readonly string $naturalLanguageReport,
        public readonly bool $fromCache = false,
    ) {}

    public static function fromArray(array $data, bool $fromCache = false): self
    {
        $insights = $data['insights'] ?? [];
        if (! is_array($insights)) {
            $insights = [];
        }
        $normalized = [];
        foreach ($insights as $row) {
            if (! is_array($row)) {
                continue;
            }
            $normalized[] = [
                'type' => (string) ($row['type'] ?? 'suggestion'),
                'message' => (string) ($row['message'] ?? ''),
                'action_url' => (string) ($row['action_url'] ?? ''),
            ];
        }

        return new self(
            (float) ($data['total_monthly_spend'] ?? 0),
            max(0, min(100, (int) ($data['subscription_health_score'] ?? 50))),
            $normalized,
            (string) ($data['natural_language_report'] ?? ''),
            $fromCache,
        );
    }

    public function toArray(): array
    {
        return [
            'total_monthly_spend' => $this->totalMonthlySpend,
            'subscription_health_score' => $this->subscriptionHealthScore,
            'insights' => $this->insights,
            'natural_language_report' => $this->naturalLanguageReport,
            'from_cache' => $this->fromCache,
        ];
    }

    public static function fallback(float $spend): self
    {
        return new self(
            $spend,
            55,
            [['type' => 'suggestion', 'message' => 'Review unused subscriptions for resale.', 'action_url' => '/docs/resale-money']],
            'We could not reach the AI coach right now. As a baseline, review plans you have not used in 14+ days and consider listing remaining days on the LuvuPay resale marketplace.',
        );
    }
}
