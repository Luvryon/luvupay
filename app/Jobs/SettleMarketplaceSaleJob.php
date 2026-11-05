<?php

namespace App\Jobs;

use App\Contracts\Payment\PaymentProviderInterface;
use App\DTOs\Payment\PayoutRequest;
use App\Mail\PlainHtmlMail;
use App\Services\Ledger\LedgerPoster;
use App\Services\Marketplace\MarketplaceService;
use App\Support\Money;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Throwable;

/**
 * Pays the seller of a SOLD marketplace listing through PayPal Payouts (same provider path as resale payouts).
 *
 *  - Exactly once: compare-and-set on payout_status + a stable sender_batch_id (mkt-<listing>) used as the PayPal request id.
 *  - Ownership gate: money only goes to an address whose owner proved control via the signed link
 *    (ownership_level = email_signed_link). Otherwise the payout is HELD and the link is emailed; verifying releases it.
 *  - Honest evidence: payout_batch_id is only what PayPal returned; the mock driver is flagged payout_simulated.
 */
class SettleMarketplaceSaleJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 6;

    public function __construct(public string $listingId) { $this->onQueue('financial'); }

    public function backoff(): array { return [30, 120, 600, 1800, 3600]; }

    public function middleware(): array { return [(new WithoutOverlapping('mkt-payout-'.$this->listingId))->releaseAfter(60)->expireAfter(300)]; }

    public function handle(PaymentProviderInterface $provider, LedgerPoster $ledger, MarketplaceService $market): void
    {
        if (! config('luvupay.marketplace.auto_payout', true)) {
            return;
        }
        $l = DB::table('marketplace_listings')->where('id', $this->listingId)->first();
        if (! $l || $l->status !== 'sold' || in_array($l->payout_status, ['sent', 'paid', 'sending'], true)) {
            return;                                                    // not sold yet, or already settled / in flight
        }

        // Ownership gate
        if (config('luvupay.marketplace.require_verified_for_payout', true) && $l->ownership_level !== 'email_signed_link') {
            $this->hold($l);

            return;
        }

        $sellerMinor = intdiv((int) $l->price_minor * (int) config('luvupay.marketplace.seller_bps', 9000), 10000);
        $senderBatchId = 'mkt-'.str_replace('-', '', $l->id);

        // Claim (null / held / failed → sending). Two workers can never both pay.
        $claimed = DB::table('marketplace_listings')->where('id', $l->id)->where('status', 'sold')
            ->where(fn ($q) => $q->whereNull('payout_status')->orWhereIn('payout_status', ['held_unverified', 'failed']))
            ->update(['payout_status' => 'sending', 'payout_minor' => $sellerMinor, 'payout_sender_batch_id' => $senderBatchId,
                'payout_attempts' => DB::raw('payout_attempts + 1'), 'payout_error' => null, 'updated_at' => now()]);
        if ($claimed !== 1) {
            return;
        }

        $simulated = (string) config('luvupay.payment.driver', 'mock') !== 'paypal';
        try {
            $r = $provider->sendPayout(
                new PayoutRequest($l->seller_email, Money::of($sellerMinor, $l->currency), 'You sold your unused subscription time on LuvuPay', $senderBatchId.'-1'),
                $senderBatchId,
            );
            if (! $r->succeeded()) {
                throw new \RuntimeException("PayPal ended the payout as {$r->status}");
            }
        } catch (Throwable $e) {
            DB::table('marketplace_listings')->where('id', $l->id)->update(['payout_status' => 'failed', 'payout_error' => mb_substr($e->getMessage(), 0, 480), 'updated_at' => now()]);
            Log::error('marketplace.payout_failed', ['listing' => $l->id, 'attempt' => $this->attempts(), 'err' => $e->getMessage()]);
            if ($this->attempts() >= $this->tries) {
                Log::critical('marketplace.payout_gave_up', ['listing' => $l->id]);

                return;                                                // stays "failed": visible, retried by resale:maintain
            }
            throw $e;                                                  // queue retries with backoff; same sender_batch_id → no double pay
        }

        $final = $r->status === 'SUCCESS' ? 'paid' : 'sent';
        DB::transaction(function () use ($l, $r, $final, $simulated, $sellerMinor, $ledger) {
            DB::table('marketplace_listings')->where('id', $l->id)->update([
                'payout_status' => $final, 'payout_batch_id' => $r->providerBatchId, 'payout_batch_status' => $r->status,
                'payout_simulated' => $simulated, 'paid_out_at' => now(), 'updated_at' => now(),
            ]);
            // Seller payable is settled: money leaves PayPal clearing
            $ledger->post("marketplace_payout:{$l->id}", 'marketplace_payout', $l->currency, [
                [LedgerPoster::SELLER, 'debit', $sellerMinor],
                [LedgerPoster::CLEARING, 'credit', $sellerMinor],
            ], null, null, ['listing_id' => $l->id, 'payout_batch_id' => $r->providerBatchId, 'simulated' => $simulated]);
        });

        $this->notify($l, $sellerMinor, $r->providerBatchId, $simulated);
    }

    /** Hold the money and give the seller a one-click way to prove the address is theirs. */
    private function hold(object $l): void
    {
        if ($l->payout_status === 'held_unverified') {
            return;
        }
        DB::table('marketplace_listings')->where('id', $l->id)->update(['payout_status' => 'held_unverified', 'updated_at' => now()]);
        try {
            $link = URL::temporarySignedRoute('marketplace.verify', now()->addDays(7), ['id' => $l->id, 'e' => MarketplaceService::emailFingerprint($l->seller_email)]);
            Mail::to($l->seller_email)->sendNow(new PlainHtmlMail(
                'Confirm your PayPal email to receive your payout',
                '<p>Your listing <strong>'.e($l->title).'</strong> sold. To protect you, we only pay out to an address you have confirmed.</p>'
                .'<p><a href="'.e($link).'">Confirm and release my payout</a></p><p>If this was not you, ignore this email and nothing is paid.</p>'
            ));
        } catch (Throwable $e) {
            Log::warning('marketplace.payout_hold_email_failed', ['listing' => $l->id, 'err' => $e->getMessage()]);
        }
    }

    private function notify(object $l, int $minor, string $batchId, bool $simulated): void
    {
        try {
            Mail::to($l->seller_email)->sendNow(new PlainHtmlMail(
                'Your LuvuPay payout is on its way',
                '<p>We sent <strong>'.e(number_format($minor / 100, 2).' '.$l->currency).'</strong> for <strong>'.e($l->title).'</strong> to your PayPal account.</p>'
                .'<p>Payout reference: <code>'.e($simulated ? 'SIMULATED (mock driver)' : $batchId).'</code></p>'
            ));
        } catch (Throwable $e) {
            Log::warning('marketplace.payout_email_failed', ['listing' => $l->id, 'err' => $e->getMessage()]);
        }
    }

    public function failed(Throwable $e): void
    {
        Log::critical('marketplace.payout_job_failed', ['listing' => $this->listingId, 'err' => $e->getMessage()]);
    }
}
