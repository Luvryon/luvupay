<?php

namespace App\Console\Commands;

use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Jobs\ExpireSecondChanceJob;
use App\Models\Transaction;
use Illuminate\Console\Command;

/** Safety net: if a delayed Redis job was lost (flush, failover), HELD charges past their deadline still get settled. */
class SettleExpiredSecondChances extends Command
{
    protected $signature = 'luvi:settle-expired';
    protected $description = 'Settle Second Chance windows that expired without their timer job running.';

    public function handle(): int
    {
        $n = 0;
        Transaction::query()->where('type', TransactionType::Charge)->where('status', TransactionStatus::Held)
            ->where('second_chance_expires_at', '<', now()->subMinutes(2))
            ->chunkById(200, function ($rows) use (&$n) {
                foreach ($rows as $r) {
                    ExpireSecondChanceJob::dispatch($r->id);
                    $n++;
                }
            });
        $this->info("Re-queued {$n} expired window(s).");

        return self::SUCCESS;
    }
}
