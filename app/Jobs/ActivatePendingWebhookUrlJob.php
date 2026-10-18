<?php

namespace App\Jobs;

use App\Models\WebhookEndpoint;
use App\Services\Security\AuditLogger;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/** Applies confirmed pending_url after cooling-off (no silent URL hijack). */
class ActivatePendingWebhookUrlJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public string $endpointId) {}

    public function handle(AuditLogger $audit): void
    {
        $ep = WebhookEndpoint::query()->find($this->endpointId);
        if (! $ep || ! $ep->pending_url) {
            return;
        }
        // Require email confirmation cleared first
        if ($ep->change_confirmation_code) {
            return;
        }
        if ($ep->pending_url_activates_at && $ep->pending_url_activates_at->isFuture()) {
            $this->release(max(60, $ep->pending_url_activates_at->diffInSeconds(now())));

            return;
        }

        $old = $ep->url;
        $ep->update([
            'url' => $ep->pending_url,
            'pending_url' => null,
            'pending_url_activates_at' => null,
            'is_active' => true,
        ]);
        $audit->custom($ep, 'webhook_url.activated', ['old' => $old, 'new' => $ep->url]);
    }
}
