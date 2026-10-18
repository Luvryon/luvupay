<?php

namespace App\Services\Security;

use App\Exceptions\LuvuApiException;
use App\Jobs\ActivatePayoutRecipientJob;
use App\Models\Merchant;
use App\Models\MerchantUser;
use App\Models\PaypalPayoutRecipient;
use Illuminate\Support\Str;

/**
 * Payouts may ONLY go to active allow-listed emails.
 * New recipients: pending_verification + 24h cooling-off before active.
 */
class PayoutAllowList
{
    public function assertAllowed(Merchant $merchant, string $email, ?string $purpose = null): void
    {
        $q = PaypalPayoutRecipient::query()
            ->where('merchant_id', $merchant->id)
            ->where('email', strtolower($email))
            ->where('status', 'active');
        if ($purpose) {
            $q->where(function ($q) use ($purpose) {
                $q->where('purpose', $purpose)->orWhere('purpose', 'general');
            });
        }
        if (! $q->exists()) {
            throw new LuvuApiException('payout_not_allowlisted', 'Payout recipient is not on the active allow-list.', 422);
        }
    }

    public function requestAdd(Merchant $merchant, MerchantUser $actor, string $email, string $purpose = 'general', ?string $label = null): PaypalPayoutRecipient
    {
        $email = strtolower($email);
        $r = PaypalPayoutRecipient::create([
            'id' => (string) Str::uuid(),
            'merchant_id' => $merchant->id,
            'email' => $email,
            'label' => $label,
            'purpose' => $purpose,
            'status' => 'pending_verification',
            'requested_at' => now(),
            'activates_at' => now()->addHours(24),
            'requested_by' => $actor->id,
            'verification_token' => Str::random(48),
        ]);

        ActivatePayoutRecipientJob::dispatch($r->id)->delay($r->activates_at);

        return $r;
    }
}
