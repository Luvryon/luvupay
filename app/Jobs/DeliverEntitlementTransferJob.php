<?php

namespace App\Jobs;

use App\Enums\EntitlementStatus;
use App\Exceptions\EntitlementDeliveryException;
use App\Models\ResaleListing;
use App\Services\Resale\EntitlementBridge;
use App\Support\AuditContext;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Throwable;

class DeliverEntitlementTransferJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 8;
    public int $timeout = 60;
    public int $uniqueFor = 3600;

    public function __construct(public string $listingId) { $this->onQueue('webhooks'); }

    public function uniqueId(): string { return $this->listingId; }

    /** 10s, 30s, 2m, 10m, 30m, 1h, 2h: roughly 4h of retrying before we give up and alert. */
    public function backoff(): array { return [10, 30, 120, 600, 1800, 3600, 7200]; }

    public function handle(EntitlementBridge $bridge, AuditContext $ctx): void
    {
        $l = ResaleListing::query()->findOrFail($this->listingId);
        if (in_array($l->entitlement_status, [EntitlementStatus::Sent, EntitlementStatus::Acknowledged], true)) {
            return;
        }

        try {
            $ctx->runAs('job', 'DeliverEntitlementTransfer', function () use ($bridge, $l) {
                $bridge->deliver($l);
                $l->update(['entitlement_status' => EntitlementStatus::Sent, 'entitlement_sent_at' => now()]);
            });
        } catch (EntitlementDeliveryException $e) {
            if ($e->permanent) {
                $this->fail($e);
                return;
            }
            throw $e;
        }
    }

    public function failed(Throwable $e): void
    {
        $l = ResaleListing::query()->find($this->listingId);
        $l?->update(['entitlement_status' => EntitlementStatus::Failed]);
        \Log::critical('luvi.entitlement.delivery_failed', ['listing' => $this->listingId, 'error' => $e->getMessage()]);
    }
}
