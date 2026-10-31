<?php

namespace App\Console\Commands;

use App\Services\Luvu\GraceDecisionService;
use App\Services\Luvu\SplitPayService;
use Illuminate\Console\Command;

/** Closes expired grace windows (voiding unsold resale listings) and runs the split-pay instalment schedule. */
final class SweepLifecycleCommand extends Command
{
    protected $signature = 'luvupay:sweep';
    protected $description = 'Expire grace windows, void stale listings and collect/default due split-pay instalments';

    public function handle(GraceDecisionService $grace, SplitPayService $split): int
    {
        $expired = $grace->sweepExpired();
        $plans = $split->sweep();
        $voided = \Illuminate\Support\Facades\DB::table('resale_listings')->where('status', 'active')->where('access_expires_at', '<=', now())->update(['status' => 'expired', 'updated_at' => now()]);
        $this->info("expired={$expired} installments_collected={$plans['collected']} plans_defaulted={$plans['defaulted']} plans_overdue={$plans['overdue']} listings_voided={$voided}");

        return self::SUCCESS;
    }
}
