<?php

namespace App\Jobs;

use App\Services\Luvu\SecondChanceService;
use App\Support\AuditContext;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/** Dispatched with ->delay(expiresAt) = the 48h timer. Also re-dispatched by the sweeper (luvi:settle-expired). */
class ExpireSecondChanceJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 5;

    public function __construct(public string $chargeId) { $this->onQueue('financial'); }

    public function handle(SecondChanceService $svc, AuditContext $ctx): void
    {
        $done = $ctx->runAs('job', 'ExpireSecondChance', fn () => $svc->settleIfExpired($this->chargeId));
        if (! $done) {
            $this->release(30);     // woke up early (clock skew): try again shortly
        }
    }
}
