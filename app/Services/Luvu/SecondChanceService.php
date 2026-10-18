<?php

namespace App\Services\Luvu;

use App\Contracts\Payment\PaymentProviderInterface;
use App\DTOs\Payment\PayoutRequest;
use App\DTOs\Payment\ProviderWebhookEvent;
use App\Enums\SubscriptionStatus;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Exceptions\PaymentProviderException;
use App\Exceptions\SecondChanceException;
use App\Jobs\ExecuteSecondChanceSplitJob;
use App\Jobs\ExpireSecondChanceJob;
use App\Mail\PlainHtmlMail;
use App\Models\CharityWallet;
use App\Models\Merchant;
use App\Models\Subscription;
use App\Models\Transaction;
use App\Support\AuditContext;
use App\Support\Money;
use App\Services\Security\PayoutAllowList;
use App\Services\Fraud\TrustScoreService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

/**
 * THE LUVI ENGINE.
 *
 *   charge captured --registerCharge()--> HELD (48h) --+-- trigger()  --> PROCESSING --execute()--> COMPLETED
 *                                                       |                  (cancel sub, 30% refund, 20% charity, 50% merchant)
 *                                                       +-- timer/sweeper settleIfExpired() --> SETTLED (merchant keeps 100%)
 *
 * Safety properties:
 *  - trigger() and settleIfExpired() lock the charge row, so a click at the deadline can never do both.
 *  - Every money movement is a child ledger row with a deterministic idempotency key ("sc:{charge}:{part}"),
 *    reused as the PayPal request id: retries and double-clicks can never refund or donate twice.
 *  - The split uses largest-remainder allocation: parts always sum to the charge exactly.
 */
class SecondChanceService
{
    private const ORDER = [TransactionType::Refund->value => 1, TransactionType::CharityPayout->value => 2, TransactionType::MerchantRetention->value => 3];

    public function __construct(private readonly PaymentProviderInterface $provider, private readonly LedgerService $ledger, private readonly \App\Services\Ledger\LedgerPoster $poster) {}

    /**
     * ETHICAL CONSTRAINT (do not weaken):
     * - The charity portion MUST equal exactly split_charity_bps of the original charge (default 20%).
     * - The user refund portion MUST equal exactly split_user_bps (default 30%).
     * - Any LuvuPay platform fee MUST be taken ONLY from the merchant retained portion
     *   (or recorded as a separate fee line item). NEVER reduce charity or user refund to fund fees.
     *
     * @return array{user:Money,charity:Money,merchant:Money,luvi_fee:Money}
     */
    public function split(Merchant $m, Money $amount, int $platformFeeBpsFromMerchant = 0): array
    {
        // Exact BPS split first — charity and user are inviolable.
        $parts = $amount->allocate([
            'user' => $m->split_user_bps,
            'charity' => $m->split_charity_bps,
            'merchant' => $m->split_merchant_bps,
        ]);

        $fee = Money::zero($amount->currency);
        if ($platformFeeBpsFromMerchant > 0 && ! $parts['merchant']->isZero()) {
            // Fee carved from merchant share only.
            $feeMinor = intdiv($parts['merchant']->amountMinor * $platformFeeBpsFromMerchant, 10000);
            $fee = Money::of($feeMinor, $amount->currency);
            $parts['merchant'] = Money::of($parts['merchant']->amountMinor - $feeMinor, $amount->currency);
        }
        $parts['luvi_fee'] = $fee;

        return $parts;
    }

    // ------------------------------------------------------------------ 1. a charge arrives (PayPal webhook)

    public function registerCharge(Subscription $sub, ProviderWebhookEvent $w): ?Transaction
    {
        $merchant = $sub->merchant;
        $first = ! Transaction::query()->where('subscription_id', $sub->id)->where('type', TransactionType::Charge)->exists();
        $eligible = ! $first || config('luvupay.second_chance.on_first_charge');
        $expires = now()->addHours($merchant->second_chance_window_hours);

        try {
            $charge = DB::transaction(function () use ($sub, $w, $eligible, $expires) {
                $tx = Transaction::create([
                    'merchant_id' => $sub->merchant_id, 'subscription_id' => $sub->id,
                    'type' => TransactionType::Charge,
                    'status' => $eligible ? TransactionStatus::Held : TransactionStatus::Settled,
                    'provider' => 'paypal', 'provider_reference' => $w->providerCaptureId, 'provider_event_id' => $w->eventId,
                    'idempotency_key' => 'charge:'.$w->eventId,
                    'merchant_user_id' => $sub->merchant_user_id, 'email' => $sub->email,
                    'amount_minor' => $w->amount->amountMinor, 'currency' => $w->amount->currency,
                    'second_chance_expires_at' => $eligible ? $expires : null,
                    'processed_at' => $eligible ? null : now(),
                ]);

                $this->poster->chargeCaptured($tx);

                $next = $this->nextBilling($sub);
                $sub->update([
                    'current_period_start' => now(), 'current_period_end' => $next, 'next_billing_at' => $next,
                    'nudge_sent_at' => null, 'paused_by_nudge_at' => null, 'nudge_confirmed_at' => null,   // new cycle: nudge can fire again
                ]);

                return $tx;
            });
        } catch (UniqueConstraintViolationException) {
            return null;    // PayPal redelivered a charge we already recorded
        }

        if ($eligible) {
            ExpireSecondChanceJob::dispatch($charge->id)->delay($expires);   // Redis delayed job = the 48h timer
            $this->sendOffer($charge, $merchant, $expires);
        }

        return $charge;
    }

    // ------------------------------------------------------------------ 2. user (or merchant UI) clicks "Second Chance"

    public function trigger(Transaction $charge, string $source): Transaction
    {
        // Behavioral gate: low trust scores cannot keep looping Second Chance.
        if ($charge->merchant_user_id) {
            app(TrustScoreService::class)->assertSecondChanceAllowed(
                (string) $charge->merchant_id,
                (string) $charge->merchant_user_id,
            );
        }

        [$tx, $created] = DB::transaction(function () use ($charge, $source) {
            $c = Transaction::query()->whereKey($charge->getKey())->lockForUpdate()->firstOrFail();

            if ($c->type !== TransactionType::Charge) {
                throw new SecondChanceException('not_a_charge', 'Only subscription charges can use a Second Chance.', 422);
            }
            if ($c->second_chance_triggered_at !== null) {
                return [$c, false];                                   // already triggered: idempotent
            }
            if ($c->status !== TransactionStatus::Held) {
                throw new SecondChanceException('not_eligible', 'This charge is not eligible for a Second Chance.', 409);
            }
            if (! $c->second_chance_expires_at || $c->second_chance_expires_at->isPast()) {
                throw new SecondChanceException('window_closed', 'The Second Chance window has closed.', 409);
            }

            $merchant = $c->merchant;
            $parts = $this->split($merchant, $c->money());
            $wallet = $this->charityFor($c->subscription, $merchant);
            if ($parts['charity']->amountMinor > 0 && ! $wallet) {
                throw new SecondChanceException('no_charity', 'No active charity wallet is configured.', 422);
            }

            foreach ([[TransactionType::Refund, 'user', null], [TransactionType::CharityPayout, 'charity', $wallet?->id], [TransactionType::MerchantRetention, 'merchant', null]] as [$type, $key, $walletId]) {
                if ($parts[$key]->isZero()) {
                    continue;                                          // amount_minor > 0 is a DB constraint
                }
                Transaction::create([
                    'merchant_id' => $c->merchant_id, 'subscription_id' => $c->subscription_id, 'parent_transaction_id' => $c->id,
                    'charity_wallet_id' => $walletId, 'type' => $type, 'status' => TransactionStatus::Pending,
                    'idempotency_key' => "sc:{$c->id}:{$type->value}",
                    'merchant_user_id' => $c->merchant_user_id, 'email' => $c->email,
                    'amount_minor' => $parts[$key]->amountMinor, 'currency' => $c->currency,
                ]);
            }

            $this->poster->splitCommitted($c, $parts);

            $c->transitionTo(TransactionStatus::Processing, [
                'second_chance_triggered_at' => now(),
                'split_snapshot' => [
                    'source' => $source,
                    'bps' => ['user' => $merchant->split_user_bps, 'charity' => $merchant->split_charity_bps, 'merchant' => $merchant->split_merchant_bps],
                    'amounts_minor' => array_map(fn (Money $m) => $m->amountMinor, $parts),
                ],
            ]);

            return [$c, true];
        });

        if ($created) {
            ExecuteSecondChanceSplitJob::dispatch($tx->id);
            if ($tx->merchant_user_id) {
                app(TrustScoreService::class)->recordSecondChance(
                    (string) $tx->merchant_id,
                    (string) $tx->merchant_user_id,
                );
            }
        }

        return $tx->refresh();
    }

    // ------------------------------------------------------------------ 3. the 48h timer fires

    /** @return bool false when called too early (caller should re-release the job) */
    public function settleIfExpired(string $chargeId): bool
    {
        return DB::transaction(function () use ($chargeId) {
            $c = Transaction::query()->whereKey($chargeId)->lockForUpdate()->first();
            if (! $c || $c->status !== TransactionStatus::Held) {
                return true;                                           // triggered, already settled, or gone
            }
            if ($c->second_chance_expires_at->isFuture()) {
                return false;
            }
            $c->transitionTo(TransactionStatus::Settled, ['processed_at' => now()]);
            $this->poster->chargeSettled($c);

            return true;
        });
    }

    // ------------------------------------------------------------------ 4. money movement (queued, retryable, idempotent)

    public function execute(string $chargeId): void
    {
        $charge = Transaction::query()->with('subscription')->findOrFail($chargeId);
        if ($charge->status === TransactionStatus::Completed) {
            return;
        }

        $this->cancelProviderSubscription($charge->subscription);       // stop the bleeding first

        $children = $charge->children()->get()->sortBy(fn (Transaction $t) => self::ORDER[$t->type->value] ?? 9);
        foreach ($children as $child) {
            $this->executeChild($charge, $child);
        }

        $this->completeIfDone($charge);
    }

    private function executeChild(Transaction $charge, Transaction $child): void
    {
        if (in_array($child->status, [TransactionStatus::Completed, TransactionStatus::Processing], true)) {
            return;
        }
        $child->update(['status' => TransactionStatus::Processing, 'attempts' => $child->attempts + 1, 'failure_reason' => null]);

        try {
            switch ($child->type) {
                case TransactionType::Refund:
                    $r = $this->provider->refundCapture($charge->provider_reference, $child->money(), $child->idempotency_key, 'Second Chance refund');
                    if (! $r->succeeded()) {
                        throw new PaymentProviderException("Refund ended as {$r->status}", false);
                    }
                    $this->ledger->completePayout($child, $r->providerRefundId);
                    break;

                case TransactionType::CharityPayout:
                    $wallet = CharityWallet::query()->findOrFail($child->charity_wallet_id);
                    app(PayoutAllowList::class)->assertAllowed($charge->merchant, $wallet->payout_email, 'charity');
                    $r = $this->provider->sendPayout(new PayoutRequest($wallet->payout_email, $child->money(), 'Donation from a LuvuPay Second Chance', $child->id), $child->idempotency_key);
                    if (! $r->succeeded()) {
                        throw new PaymentProviderException("Payout ended as {$r->status}", false);
                    }
                    // PENDING stays "processing" until PayPal's PAYOUTS-ITEM webhook (ReconcilePayoutOutcome) confirms.
                    $r->status === 'SUCCESS'
                        ? $this->ledger->completePayout($child, $r->providerBatchId)
                        : $child->update(['provider_reference' => $r->providerBatchId]);
                    break;

                case TransactionType::MerchantRetention:       // pure ledger entry: no external money movement
                    $this->ledger->completePayout($child);
                    break;

                default:
                    throw new \LogicException("Unexpected child type {$child->type->value}");
            }
        } catch (PaymentProviderException $e) {
            $this->ledger->failPayout($child, $e->getMessage());
            throw $e;
        }
    }

    private function completeIfDone(Transaction $charge): void
    {
        $open = $charge->children()->whereNotIn('status', [TransactionStatus::Completed->value, TransactionStatus::Processing->value])->exists();
        if ($open) {
            return;
        }
        DB::transaction(function () use ($charge) {
            $c = Transaction::query()->lockForUpdate()->findOrFail($charge->id);
            if ($c->status === TransactionStatus::Completed) {
                return;
            }
            $c->update(['status' => TransactionStatus::Completed, 'processed_at' => now()]);
            $sub = $c->subscription;
            $sub->update(['forgotten_cancel_count' => $sub->forgotten_cancel_count + 1]);   // feeds the Adaptive Nudge
        });
    }

    /** Called by the job when all retries are exhausted: surface it loudly for operations. */
    public function markFailed(string $chargeId, string $reason): void
    {
        $c = Transaction::query()->find($chargeId);
        if (! $c || $c->status === TransactionStatus::Completed) {
            return;
        }
        $c->update(['status' => TransactionStatus::Failed, 'failure_reason' => Str::limit($reason, 500)]);
        Log::critical('luvi.second_chance.failed', ['charge' => $chargeId, 'reason' => $reason]);
    }

    private function cancelProviderSubscription(?Subscription $sub): void
    {
        if (! $sub || ! $sub->provider_subscription_id || $sub->status === SubscriptionStatus::Cancelled) {
            return;
        }
        try {
            $this->provider->cancelSubscription($sub->provider_subscription_id, 'LuvuPay Second Chance');
        } catch (PaymentProviderException $e) {
            // already cancelled at PayPal (HTTP 422) is fine; anything else is real
            if (strtoupper($this->provider->getSubscription($sub->provider_subscription_id)->status) !== 'CANCELLED') {
                throw $e;
            }
        }
        $sub->update(['status' => SubscriptionStatus::Cancelled, 'cancelled_at' => now()]);
    }

    public function charityFor(?Subscription $sub, Merchant $merchant): ?CharityWallet
    {
        $id = $sub?->charity_wallet_id ?? $merchant->default_charity_wallet_id;

        return CharityWallet::query()->where('is_active', true)->when($id, fn ($q) => $q->whereKey($id))->orderBy('created_at')->first();
    }

    // ------------------------------------------------------------------ helpers

    private function nextBilling(Subscription $sub): \DateTimeInterface
    {
        try {
            if ($sub->provider_subscription_id && ($n = $this->provider->getSubscription($sub->provider_subscription_id)->nextBillingAt)) {
                return $n;
            }
        } catch (\Throwable $e) {
            Log::notice('luvi.next_billing_fallback', ['sub' => $sub->id, 'error' => $e->getMessage()]);
        }

        return match ($sub->billing_interval) {
            'day' => now()->addDay(), 'week' => now()->addWeek(), 'year' => now()->addYear(), default => now()->addMonthNoOverflow(),
        };
    }

    private function sendOffer(Transaction $charge, Merchant $merchant, \DateTimeInterface $expires): void
    {
        $p = $this->split($merchant, $charge->money());
        $url = URL::temporarySignedRoute('second-chance.show', $expires, ['transaction' => $charge->id]);
        $fmt = fn (Money $m) => e(number_format($m->amountMinor / 100, 2).' '.$m->currency);

        $html = '<h2>Forgot to cancel '.e($merchant->name).'?</h2>'
            .'<p>You were just charged <b>'.$fmt($charge->money()).'</b>. If that was a mistake, you have a <b>Second Chance</b> for '.e((string) $merchant->second_chance_window_hours).' hours:</p>'
            .'<ul><li><b>'.$fmt($p['user']).'</b> back to you</li><li><b>'.$fmt($p['charity']).'</b> donated to charity</li></ul>'
            .'<p>Your subscription is cancelled automatically.</p>'
            .'<p><a href="'.e($url).'" style="background:#4f46e5;color:#fff;padding:12px 20px;border-radius:10px;text-decoration:none">Use my Second Chance</a></p>'
            .'<p style="color:#94a3b8;font-size:12px">Protected by LuvuPay. Link expires '.e($expires->format('M j, H:i T')).'.</p>';

        Mail::to($charge->email)->queue(new PlainHtmlMail('Charged by mistake? You have a Second Chance', $html));
    }
}
