<?php

namespace App\Jobs;

use App\Exceptions\PaymentProviderException;
use App\Services\Luvu\SecondChanceService;
use App\Support\AuditContext;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Throwable;

class ExecuteSecondChanceSplitJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 6;
    public int $timeout = 120;

    public function __construct(public string $chargeId) { $this->onQueue('financial'); }

    public function backoff(): array { return [30, 120, 600, 1800, 3600]; }

    /** One worker per charge at a time. */
    public function middleware(): array
    {
        return [(new WithoutOverlapping($this->chargeId))->releaseAfter(60)->expireAfter(300)];
    }

    public function handle(SecondChanceService $svc, AuditContext $ctx): void
    {
        try {
            $ctx->runAs('job', 'ExecuteSecondChanceSplit', fn () => $svc->execute($this->chargeId));
        } catch (PaymentProviderException $e) {
            if (! $e->retryable) {
                $this->fail($e);        // PayPal said "no": retrying will not help
                return;
            }
            throw $e;                   // network / 5xx / 429: retry with backoff (idempotent by request id)
        }
    }

    public function failed(Throwable $e): void
    {
        app(SecondChanceService::class)->markFailed($this->chargeId, $e->getMessage());
    }
}
