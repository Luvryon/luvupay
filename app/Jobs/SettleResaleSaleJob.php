<?php

namespace App\Jobs;

use App\Contracts\Payment\PaymentProviderInterface;
use App\DTOs\Payment\PayoutRequest;
use App\Enums\SubscriptionStatus;
use App\Enums\TransactionStatus;
use App\Exceptions\PaymentProviderException;
use App\Models\ResaleListing;
use App\Models\Transaction;
use App\Services\Luvu\LedgerService;
use App\Support\AuditContext;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Throwable;

/**
 * Post-resale settlement (idempotent, retried independently):
 *  (1) Cancel seller's provider subscription so User A is not billed next cycle for a seat they sold.
 *  (2) PayPal Payouts → User A for (resale price − platform fee).
 *
 * Does NOT refund the merchant's original cycle charge to User A. That money stays with the merchant.
 * User B paid a separate resale order; only that capture funds the seller payout + LuvuPay fee.
 */
class SettleResaleSaleJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 6;

    public function __construct(public string $listingId) { $this->onQueue('financial'); }

    public function backoff(): array { return [30, 120, 600, 1800, 3600]; }

    public function middleware(): array { return [(new WithoutOverlapping($this->listingId))->releaseAfter(60)->expireAfter(300)]; }

    public function handle(PaymentProviderInterface $provider, LedgerService $ledger, AuditContext $ctx): void
    {
        $ctx->runAs('job', 'SettleResaleSale', function () use ($provider, $ledger) {
            $l = ResaleListing::query()->with('subscription')->findOrFail($this->listingId);

            try {
                // 1) A must not be billed again for the seat B now owns
                $sub = $l->subscription;
                if ($sub->provider_subscription_id && ! $sub->cancelled_at) {
                    try {
                        $provider->cancelSubscription($sub->provider_subscription_id, 'Access resold via LuvuPay');
                    } catch (PaymentProviderException $e) {
                        if (strtoupper($provider->getSubscription($sub->provider_subscription_id)->status) !== 'CANCELLED') {
                            throw $e;
                        }
                    }
                    $sub->update(['cancelled_at' => now()]);      // status stays Resold
                }

                // 2) pay the seller (PayPal request id = ledger idempotency key)
                $tx = $l->seller_payout_transaction_id ? Transaction::find($l->seller_payout_transaction_id) : null;
                if (! $tx || in_array($tx->status, [TransactionStatus::Completed, TransactionStatus::Processing], true)) {
                    return;
                }
                $tx->update(['status' => TransactionStatus::Processing, 'attempts' => $tx->attempts + 1]);
                $r = $provider->sendPayout(new PayoutRequest($tx->email, $tx->money(), 'You sold your unused subscription days on LuvuPay', $tx->id), $tx->idempotency_key);
                if (! $r->succeeded()) {
                    throw new PaymentProviderException("Seller payout ended as {$r->status}", false);
                }
                $r->status === 'SUCCESS' ? $ledger->completePayout($tx, $r->providerBatchId) : $tx->update(['provider_reference' => $r->providerBatchId]);
            } catch (PaymentProviderException $e) {
                if (! $e->retryable) {
                    $this->fail($e);
                    return;
                }
                throw $e;
            }
        });
    }

    public function failed(Throwable $e): void
    {
        $l = ResaleListing::query()->find($this->listingId);
        if ($l?->seller_payout_transaction_id && ($tx = Transaction::find($l->seller_payout_transaction_id))) {
            app(LedgerService::class)->failPayout($tx, $e->getMessage());
        }
        \Log::critical('luvi.resale.settlement_failed', ['listing' => $this->listingId, 'error' => $e->getMessage()]);
    }
}
