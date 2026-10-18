<?php

namespace App\Http\Controllers;

use App\Contracts\Payment\PaymentProviderInterface;
use App\Enums\SubscriptionStatus;
use App\Models\Subscription;
use App\Support\AuditContext;
use App\Support\HtmlPage;
use Illuminate\Http\Response;

/** Public, signed-link screens for the Adaptive Nudge (end-users have no account). */
final class NudgeController
{
    public function show(Subscription $subscription): Response
    {
        if ($subscription->status !== SubscriptionStatus::PausedByNudge) {
            return HtmlPage::render('Subscription', '<h1>Nothing to confirm</h1><p>This subscription is not waiting for confirmation.</p>');
        }
        $body = '<h1>Keep your subscription?</h1><p>Your renewal for <b>'.e($subscription->merchant->name).'</b> is paused so you are not charged by accident.</p>'
            .'<form method="POST" action="'.e(request()->fullUrl()).'">'.csrf_field().'<button type="submit">Yes, resume my subscription</button></form>'
            .'<p><small>If you do nothing, you will not be charged. Protected by LuvuPay.</small></p>';

        return HtmlPage::render('Subscription', $body);
    }

    public function confirm(Subscription $subscription, PaymentProviderInterface $provider, AuditContext $ctx): Response
    {
        $ctx->actor('end_user_link', substr($subscription->id, 0, 8));

        if ($subscription->status === SubscriptionStatus::PausedByNudge) {
            $provider->activateSubscription($subscription->provider_subscription_id, 'Confirmed by user via LuvuPay nudge');
            $subscription->update(['status' => SubscriptionStatus::Active, 'nudge_confirmed_at' => now()]);
        }

        return HtmlPage::render('Subscription', '<h1>All set ✓</h1><p>Your subscription is active again. See you next cycle!</p>');
    }
}
