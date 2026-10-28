<?php

namespace App\Services\Nebius;

use App\Contracts\NebiusAIContract;
use App\DTOs\AI\PriceRecommendation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Throwable;

class DynamicPricingService
{
    public function __construct(
        private readonly NebiusAIContract $client,
        private readonly AiResponseCache $cache,
    ) {}

    /** @param array<string,mixed> $listingData */
    public function suggestPrice(array $listingData, ?string $merchantId = null): PriceRecommendation
    {
        // Inject market context from DB when merchant known
        if ($merchantId) {
            try {
                $sold = DB::table('resale_listings')->where('merchant_id', $merchantId)->where('status', 'sold')->count();
                $listingData['similar_listings_sold_count'] = $listingData['similar_listings_sold_count'] ?? $sold;
                $hours = DB::connection()->getDriverName() === 'pgsql'
                    ? 'EXTRACT(EPOCH FROM (sold_at - listed_at)) / 3600'
                    : 'TIMESTAMPDIFF(HOUR, listed_at, sold_at)';
                $avgDays = DB::table('resale_listings')
                    ->where('merchant_id', $merchantId)
                    ->where('status', 'sold')
                    ->whereNotNull('sold_at')
                    ->whereNotNull('listed_at')
                    ->selectRaw("AVG({$hours}) as h")
                    ->value('h');
                if ($avgDays) {
                    $listingData['avg_days_to_sell'] = $listingData['avg_days_to_sell'] ?? max(1, (int) round($avgDays / 24));
                }
            } catch (Throwable $e) {
                Log::notice('nebius.pricing_context_unavailable', ['error' => $e->getMessage()]); // the prompt still works without market stats
            }
        }

        $hash = AiResponseCache::hash(['f' => 'pricing', 'd' => $listingData]);
        if ($hit = $this->cache->get('dynamic_pricing', $hash)) {
            return PriceRecommendation::fromArray($hit, fromCache: true);
        }

        $system = <<<'PROMPT'
You are a Market Pricing Strategist for LuvuPay's P2P resale marketplace.
Buyers pay a NEW resale price for remaining access days; the merchant's original cycle charge is NOT refunded.
Suggest a price that balances sell-through speed and seller proceeds (seller receives ~90% after platform fee).
Respond with ONLY JSON:
recommended_price (number, major currency units),
price_range_low (number),
price_range_high (number),
expected_sell_time_hours (integer),
confidence (0.0-1.0),
reasoning (natural language).
PROMPT;

        $user = "Listing context:\n".json_encode($listingData, JSON_PRETTY_PRINT);

        try {
            $res = $this->client->chatJson('dynamic_pricing', [
                ['role' => 'system', 'content' => $system],
                ['role' => 'user', 'content' => $user],
            ], $merchantId);
            $result = PriceRecommendation::fromArray($res['data']);
            if ($result->recommendedPrice <= 0 || $result->priceRangeLow > $result->priceRangeHigh) {
                throw new InvalidArgumentException('Model returned an unusable price');
            }
            $this->cache->put('dynamic_pricing', $hash, $result->toArray());

            return $result;
        } catch (Throwable $e) {
            Log::warning('nebius.pricing_fallback', ['error' => $e->getMessage()]);

            return PriceRecommendation::fallback(
                (float) ($listingData['original_price'] ?? 0),
                (int) ($listingData['days_remaining'] ?? 15),
            );
        }
    }
}
