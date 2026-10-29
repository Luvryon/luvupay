<?php

namespace App\Jobs;

use App\Models\WebhookEvent;
use App\Services\PayPal\WebhookHandler;
use App\Support\AuditContext;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Turns one stored webhook into a domain event. Idempotent: a processed/ignored row is skipped, so a
 * redelivery or replay can never apply the same event twice. Exhausted retries -> status `dead`.
 */
class ProcessWebhookEventJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 6;

    public function __construct(public string $webhookEventId)
    {
        $this->onQueue((string) config('luvupay.queues.webhooks', 'webhooks'));
    }

    /** @return int[] */
    public function backoff(): array
    {
        return [10, 30, 120, 600, 1800];
    }

    public function middleware(): array
    {
        return [(new WithoutOverlapping($this->webhookEventId))->releaseAfter(30)->expireAfter(300)];
    }

    public function handle(WebhookHandler $handler, AuditContext $ctx): void
    {
        $row = DB::transaction(function () {
            $r = WebhookEvent::query()->lockForUpdate()->find($this->webhookEventId);
            if (! $r || in_array($r->status, [WebhookEvent::PROCESSED, WebhookEvent::IGNORED], true)) {
                return null;
            }
            $r->update(['status' => WebhookEvent::PROCESSING, 'attempts' => $r->attempts + 1]);

            return $r;
        });
        if (! $row) {
            return;
        }

        try {
            $ctx->actor('provider_webhook', 'paypal');
            $event = $handler->parse($row->payload);
            $class = WebhookHandler::EVENT_MAP[$event->type] ?? null;
            if ($class === null) {
                $row->update(['status' => WebhookEvent::IGNORED, 'processed_at' => now()]);

                return;
            }
            $class::dispatch($event);
            $row->update(['status' => WebhookEvent::PROCESSED, 'processed_at' => now(), 'last_error' => null, 'next_retry_at' => null]);
        } catch (Throwable $e) {
            $delay = $this->backoff()[min($row->attempts - 1, count($this->backoff()) - 1)];
            $row->update(['status' => WebhookEvent::FAILED, 'last_error' => Str::limit($e->getMessage(), 1000), 'next_retry_at' => now()->addSeconds($delay)]);
            throw $e;
        }
    }

    public function failed(Throwable $e): void
    {
        WebhookEvent::query()->whereKey($this->webhookEventId)->update([
            'status' => WebhookEvent::DEAD, 'last_error' => Str::limit($e->getMessage(), 1000), 'next_retry_at' => null,
        ]);
        Log::critical('luvi.webhook.dead_letter', ['webhook_event_id' => $this->webhookEventId, 'error' => $e->getMessage()]);
        // Do not hide failures: operators must replay via `php artisan webhooks:replay`.
    }
}
