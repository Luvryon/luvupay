<?php

namespace App\Console\Commands;

use App\Models\Merchant;
use Illuminate\Console\Command;

class PurgeDeactivatedMerchantsCommand extends Command
{
    protected $signature = 'luvi:purge-deactivated';
    protected $description = 'Hard-delete merchants past purge_after (GDPR 90-day retention)';

    public function handle(): int
    {
        $n = 0;
        Merchant::query()
            ->where('subscription_status', 'deactivated')
            ->whereNotNull('purge_after')
            ->where('purge_after', '<=', now())
            ->each(function (Merchant $m) use (&$n) {
                $m->delete(); // cascades via FKs where configured
                $n++;
            });
        $this->info("Purged {$n} merchants");

        return self::SUCCESS;
    }
}
