<?php

namespace App\Console\Commands;

use App\Enums\SubscriptionStatus;
use App\Jobs\ApplyAdaptiveNudgeJob;
use App\Models\Subscription;
use Illuminate\Console\Command;

/**
 * Hourly. Finds "serial forgetters": users whose Second Chances (summed across ALL their subscriptions with the
 * merchant) reach the merchant's threshold, whose next billing falls inside the merchant's lead window (72h default).
 * nudge_sent_at is reset on every new charge, so each billing cycle can nudge at most once.
 */
class NudgeScanCommand extends Command
{
    protected $signature = 'luvi:nudge-scan {--dry-run : List candidates without dispatching}';
    protected $description = 'Pause upcoming charges for serial forgetters and ask them to confirm.';

    public function handle(): int
    {
        $n = 0;
        Subscription::query()
            ->join('merchants as m', 'm.id', '=', 'subscriptions.merchant_id')
            ->where('subscriptions.status', SubscriptionStatus::Active->value)
            ->whereNotNull('subscriptions.provider_subscription_id')
            ->whereNull('subscriptions.nudge_sent_at')
            ->whereRaw('subscriptions.next_billing_at > now()')
            ->whereRaw("subscriptions.next_billing_at <= now() + (m.nudge_lead_hours * interval '1 hour')")
            ->whereRaw('(select coalesce(sum(s2.forgotten_cancel_count), 0) from subscriptions s2
                         where s2.merchant_id = subscriptions.merchant_id and s2.merchant_user_id = subscriptions.merchant_user_id) >= m.serial_forgetter_threshold')
            ->select('subscriptions.id')
            ->chunkById(200, function ($rows) use (&$n) {
                foreach ($rows as $r) {
                    $this->option('dry-run') ? $this->line($r->id) : ApplyAdaptiveNudgeJob::dispatch($r->id);
                    $n++;
                }
            }, 'subscriptions.id', 'id');

        $this->info(($this->option('dry-run') ? 'Would nudge ' : 'Nudged ').$n.' subscription(s).');

        return self::SUCCESS;
    }
}
