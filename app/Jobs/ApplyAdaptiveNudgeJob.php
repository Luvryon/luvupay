<?php

namespace App\Jobs;

use App\Contracts\Payment\PaymentProviderInterface;
use App\Enums\SubscriptionStatus;
use App\Exceptions\PaymentProviderException;
use App\Mail\PlainHtmlMail;
use App\Models\Subscription;
use App\Support\AuditContext;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

/** Pauses the auto-charge BEFORE the billing date and asks the user to confirm. Safe to run twice (re-checks under lock). */
class ApplyAdaptiveNudgeJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 5;
    public int $uniqueFor = 600;

    public function __construct(public string $subscriptionId) { $this->onQueue('financial'); }

    public function uniqueId(): string { return $this->subscriptionId; }

    public function backoff(): array { return [30, 120, 600, 1800]; }

    public function handle(PaymentProviderInterface $provider, AuditContext $ctx): void
    {
        $ctx->runAs('scheduler', 'AdaptiveNudge', function () use ($provider) {
            $sub = DB::transaction(function () {
                $s = Subscription::query()->whereKey($this->subscriptionId)->lockForUpdate()->first();

                return ($s && $s->status === SubscriptionStatus::Active && $s->nudge_sent_at === null && $s->provider_subscription_id && $s->next_billing_at?->isFuture()) ? $s : null;
            });
            if (! $sub) {
                return;                                   // charged, cancelled, resold or already nudged since the scan
            }

            // Pause FIRST: if the email provider is down the user is still protected from the charge.
            try {
                $provider->suspendSubscription($sub->provider_subscription_id, 'LuvuPay Adaptive Nudge: awaiting confirmation');
            } catch (PaymentProviderException $e) {
                if (strtoupper($provider->getSubscription($sub->provider_subscription_id)->status) !== 'SUSPENDED') {
                    throw $e;
                }
            }
            $sub->update(['status' => SubscriptionStatus::PausedByNudge, 'paused_by_nudge_at' => now(), 'nudge_sent_at' => now()]);

            // The link dies at the billing date: confirming later would re-activate into an immediate charge.
            $url = URL::temporarySignedRoute('nudge.show', $sub->next_billing_at, ['subscription' => $sub->id]);
            $html = '<h2>Still using '.e($sub->merchant->name).'?</h2>'
                .'<p>Your subscription renews on <b>'.e($sub->next_billing_at->format('M j')).'</b>. Because you have cancelled renewals before, we <b>paused the automatic charge</b> so you are not billed by accident.</p>'
                .'<p><a href="'.e($url).'" style="background:#4f46e5;color:#fff;padding:12px 20px;border-radius:10px;text-decoration:none">Yes, keep my subscription</a></p>'
                .'<p style="color:#64748b">Do nothing and you will not be charged. Protected by LuvuPay.</p>';
            Mail::to($sub->email)->queue(new PlainHtmlMail('Your renewal is paused: confirm to keep it', $html));
        });
    }
}
