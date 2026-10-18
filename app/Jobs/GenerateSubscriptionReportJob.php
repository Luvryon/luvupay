<?php

namespace App\Jobs;

use App\Services\Nebius\EcosystemHubService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Cache;

class GenerateSubscriptionReportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 3;

    public function __construct(
        public string $email,
        public ?string $merchantId = null,
        public ?string $resultCacheKey = null,
    ) {
        $this->onQueue(config('luvupay.queues.default', 'default'));
    }

    public function handle(EcosystemHubService $svc): void
    {
        $result = $svc->generateHealthReport($this->email, null, $this->merchantId);
        if ($this->resultCacheKey) {
            Cache::put($this->resultCacheKey, $result->toArray(), 3600);
        }
    }
}
