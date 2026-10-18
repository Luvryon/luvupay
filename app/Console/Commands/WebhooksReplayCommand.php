<?php

namespace App\Console\Commands;

use App\Jobs\ProcessWebhookEventJob;
use App\Models\WebhookEvent;
use Illuminate\Console\Command;

/**
 * Operator + scheduler tool for the webhook inbox.
 *   luvi:webhooks:replay --id=<uuid>                 replay one event (also works on processed ones, with --force)
 *   luvi:webhooks:replay --status=dead               re-queue the dead letters
 *   luvi:webhooks:replay --stuck-minutes=5           scheduler sweeper for failed/received rows nobody picked up
 *   luvi:webhooks:replay --prune-days=90             delete processed/ignored rows older than N days
 */
class WebhooksReplayCommand extends Command
{
    protected $signature = 'luvi:webhooks:replay {--id=} {--status=failed,dead} {--limit=100} {--stuck-minutes=} {--force} {--prune-days=}';
    protected $description = 'Replay failed / dead / stuck webhook events, or prune old ones';

    public function handle(): int
    {
        if ($days = $this->option('prune-days')) {
            $n = WebhookEvent::query()->whereIn('status', [WebhookEvent::PROCESSED, WebhookEvent::IGNORED])
                ->where('received_at', '<', now()->subDays((int) $days))->delete();
            $this->info("Pruned {$n} events older than {$days} days.");

            return self::SUCCESS;
        }

        $q = WebhookEvent::query()->orderBy('received_at')->limit((int) $this->option('limit'));
        if ($id = $this->option('id')) {
            $q->whereKey($id);
            if (! $this->option('force')) {
                $q->whereNotIn('status', [WebhookEvent::PROCESSED, WebhookEvent::IGNORED]);
            }
        } else {
            $q->whereIn('status', array_filter(explode(',', (string) $this->option('status'))));
            if ($m = $this->option('stuck-minutes')) {
                $q->where(fn ($w) => $w->whereNull('next_retry_at')->orWhere('next_retry_at', '<', now()))
                    ->where('updated_at', '<', now()->subMinutes((int) $m));
            }
        }

        $count = 0;
        foreach ($q->get() as $row) {
            if ($row->status === WebhookEvent::DEAD || $this->option('force')) {
                $row->update(['status' => WebhookEvent::FAILED, 'attempts' => 0]);
            }
            ProcessWebhookEventJob::dispatch($row->id);
            $count++;
        }
        $this->info("Re-queued {$count} event(s).");

        return self::SUCCESS;
    }
}
