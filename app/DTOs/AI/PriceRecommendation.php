<?php

namespace App\DTOs\AI;

final class PriceRecommendation
{
    public function __construct(
        public readonly float $recommendedPrice,
        public readonly float $priceRangeLow,
        public readonly float $priceRangeHigh,
        public readonly int $expectedSellTimeHours,
        public readonly float $confidence,
        public readonly string $reasoning,
        public readonly bool $fromCache = false,
        public readonly string $source = 'nebius',
    ) {}

    public static function fromArray(array $data, bool $fromCache = false): self
    {
        return new self(
            (float) ($data['recommended_price'] ?? 0),
            (float) ($data['price_range_low'] ?? 0),
            (float) ($data['price_range_high'] ?? 0),
            max(1, (int) ($data['expected_sell_time_hours'] ?? 72)),
            max(0.0, min(1.0, (float) ($data['confidence'] ?? 0.5))),
            (string) ($data['reasoning'] ?? ''),
            $fromCache,
            $fromCache ? 'cache' : 'nebius',
        );
    }

    public function toArray(): array
    {
        return [
            'recommended_price' => $this->recommendedPrice,
            'price_range_low' => $this->priceRangeLow,
            'price_range_high' => $this->priceRangeHigh,
            'expected_sell_time_hours' => $this->expectedSellTimeHours,
            'confidence' => $this->confidence,
            'reasoning' => $this->reasoning,
            'from_cache' => $this->fromCache,
            'source' => $this->source,
        ];
    }

    public static function fallback(float $original, int $daysRemaining): self
    {
        $ratio = max(0.15, min(0.85, $daysRemaining / 30));
        $mid = round($original * $ratio, 2);

        return new self($mid, round($mid * 0.85, 2), round($mid * 1.1, 2), 48, 0.4, 'Heuristic: pro-rate remaining days; AI unavailable', false, 'fallback');
    }
}
