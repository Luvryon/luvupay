<?php

namespace App\Console\Commands;

use App\Enums\ListingStatus;
use App\Models\ResaleListing;
use App\Services\Marketplace\MarketplaceService;
use App\Services\Resale\ResaleService;
use Illuminate\Console\Command;

class ResaleMaintenanceCommand extends Command
{
    protected $signature = 'resale:maintain';
    protected $description = 'Release abandoned checkouts (resale + marketplace) and expire listings whose access period ended.';

    public function handle(ResaleService $svc, MarketplaceService $market): int
    {
        $released = 0;
        ResaleListing::query()->where('status', ListingStatus::Reserved)->where('reserved_until', '<', now())
            ->chunkById(100, function ($rows) use ($svc, &$released) {
                foreach ($rows as $l) { $svc->release($l->id, 'reservation_expired'); $released++; }
            });

        $expired = 0;
        ResaleListing::query()->where('status', ListingStatus::Active)->where('access_expires_at', '<', now())
            ->chunkById(100, function ($rows) use (&$expired) {
                foreach ($rows as $l) { $l->update(['status' => ListingStatus::Expired]); $expired++; }
            });

        $marketReleased = $market->releaseExpired();

        $this->info("Released {$released} reservation(s), expired {$expired} listing(s), released {$marketReleased} marketplace reservation(s).");

        return self::SUCCESS;
    }
}
