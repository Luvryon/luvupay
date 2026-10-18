<?php

namespace App\Jobs;

use App\Services\Nebius\DynamicPricingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Cache;

class CalculateOptimalPriceJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 3;

    /** @param array<string,mixed> $listingData */
    public function __construct(
        public array $listingData,
        public ?string $merchantId = null,
        public ?string $resultCacheKey = null,
    ) {
        $this->onQueue(config('luvupay.queues.default', 'default'));
    }

    public function handle(DynamicPricingService $svc): void
    {
        $result = $svc->suggestPrice($this->listingData, $this->merchantId);
        if ($this->resultCacheKey) {
            Cache::put($this->resultCacheKey, $result->toArray(), 3600);
        }
    }
}
