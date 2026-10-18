<?php

namespace App\Jobs;

use App\Models\PaypalPayoutRecipient;
use App\Services\Security\AuditLogger;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ActivatePayoutRecipientJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public string $recipientId) {}

    public function handle(AuditLogger $audit): void
    {
        $r = PaypalPayoutRecipient::query()->find($this->recipientId);
        if (! $r || $r->status !== 'pending_verification') {
            return;
        }
        if ($r->activates_at && $r->activates_at->isFuture()) {
            $this->release(max(60, $r->activates_at->diffInSeconds(now())));
            return;
        }
        $r->update(['status' => 'active', 'activated_at' => now()]);
        $audit->custom($r, 'payout_recipient.activated', ['email' => $r->email]);
    }
}
