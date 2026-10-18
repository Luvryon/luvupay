<?php

namespace App\Console\Commands;

use App\Jobs\ActivatePendingWebhookUrlJob;
use App\Models\WebhookEndpoint;
use Illuminate\Console\Command;

class ApplyPendingWebhookUrlsCommand extends Command
{
    protected $signature = 'luvi:apply-pending-webhooks';
    protected $description = 'Activate confirmed webhook URL changes whose cooling-off has elapsed';

    public function handle(): int
    {
        $q = WebhookEndpoint::query()
            ->whereNotNull('pending_url')
            ->whereNull('change_confirmation_code')
            ->where(function ($q) {
                $q->whereNull('pending_url_activates_at')
                    ->orWhere('pending_url_activates_at', '<=', now());
            });

        $n = 0;
        $q->each(function (WebhookEndpoint $ep) use (&$n) {
            ActivatePendingWebhookUrlJob::dispatch($ep->id);
            $n++;
        });
        $this->info("Dispatched {$n} webhook activations");

        return self::SUCCESS;
    }
}
