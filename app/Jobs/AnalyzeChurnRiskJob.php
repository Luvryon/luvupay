<?php

namespace App\Jobs;

use App\Services\Nebius\ChurnPredictionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Cache;

class AnalyzeChurnRiskJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 3;

    /** @param array<string,mixed> $userData */
    public function __construct(
        public array $userData,
        public ?string $merchantId = null,
        public ?string $resultCacheKey = null,
    ) {
        $this->onQueue(config('luvupay.queues.default', 'default'));
    }

    public function handle(ChurnPredictionService $svc): void
    {
        $result = $svc->predict($this->userData, $this->merchantId);
        if ($this->resultCacheKey) {
            Cache::put($this->resultCacheKey, $result->toArray(), 3600);
        }
    }
}
