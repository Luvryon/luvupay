<?php

namespace App\Services\Resale;

use App\Contracts\Payment\PaymentProviderInterface;
use App\Services\Fraud\TrustScoreService;
use App\Enums\EntitlementStatus;
use App\Enums\ListingStatus;
use App\Enums\SubscriptionStatus;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Exceptions\PaymentProviderException;
use App\Exceptions\ResaleException;
use App\Jobs\DeliverEntitlementTransferJob;
use App\Jobs\SettleResaleSaleJob;
use App\Models\Merchant;
use App\Models\ResaleListing;
use App\Models\Subscription;
use App\Models\Transaction;
use App\Support\Money;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * RESALE MARKETPLACE — Peer-to-Peer (P2P) sale of REMAINING access days in the CURRENT billing cycle.
 *
 * MONEY FLOW (critical — do not "simplify" into a merchant refund):
 *
 *   1. Merchant already charged User A the full cycle price (e.g. $20). That charge STAYS with the merchant.
 *      LuvuPay MUST NOT refund User A from the merchant's original subscription capture.
 *
 *   2. User A lists remaining days at a Resale Price (e.g. $10). This is a NEW payment from User B
 *      (Buyer) into the platform's resale order (PayPal Orders / Capture) — not a transfer of the $20.
 *
 *   3. On capture of User B's payment:
 *        - 90% (seller_bps, default 9000) → PayPal Payouts API → User A (seller)
 *        - 10% → LuvuPay platform fee (luvi_fee_amount / fee_type = resale_marketplace)
 *
 *   4. Entitlement Bridge (signed webhook) tells the merchant: revoke User A, grant User B for the
 *      remainder of the CURRENT cycle only (access_expires_at = current_period_end).
 *
 *   5. Next billing cycle: merchant charges User B the full standard subscription price (new cycle).
 *      User A's provider subscription is cancelled so A is not double-billed for a seat they sold.
 *
 * UNRELATED to Second Chance: no 30/20/50 split, no charity line, no merchant charge clawback.
 *
 * Flow: list → checkout (reserve + order) → complete (capture B, ledger fee, payout A, bridge, settle).
 */
class ResaleService
{
    public function __construct(private readonly PaymentProviderInterface $provider) {}

    public function list(Merchant $m, Subscription $sub, int $priceMinor): ResaleListing
    {
        // Listing price = what Buyer (User B) will pay. Merchant's original cycle charge is NOT involved.
        // Platform fee 10% is taken from THIS resale payment only (seller receives 90%).
        // NOT a Second Chance split; NOT a refund of the merchant's $20 (or whatever) charge.
        app(TrustScoreService::class)->assertResaleAllowed($m->id, $sub->merchant_user_id);

        abort_unless($sub->merchant_id === $m->id, 404);

        if ($sub->status !== SubscriptionStatus::Active || ! $sub->current_period_end?->isFuture()) {
            throw new ResaleException('not_resellable', 'Only active subscriptions with remaining access can be listed.', 409);
        }
        $days = (int) ceil(($sub->current_period_end->timestamp - now()->timestamp) / 86400);
        $min = (int) config('luvupay.resale.min_price_minor');
        if ($priceMinor < $min || $priceMinor > $sub->amount_minor) {
            throw new ResaleException('invalid_price', "Price must be between {$min} and {$sub->amount_minor} (minor units).", 422);
        }

        try {
            return ResaleListing::create([
                'merchant_id' => $m->id, 'subscription_id' => $sub->id,
                'seller_merchant_user_id' => $sub->merchant_user_id, 'seller_email' => $sub->email,
                'asking_price_minor' => $priceMinor, 'currency' => $sub->currency, 'remaining_days' => $days,
                'access_expires_at' => $sub->current_period_end, 'status' => ListingStatus::Active,
                'entitlement_status' => EntitlementStatus::NotRequired, 'listed_at' => now(),
            ]);
        } catch (UniqueConstraintViolationException) {      // partial unique index: one open listing per subscription
            throw new ResaleException('already_listed', 'This subscription already has an open listing.', 409);
        }
    }

    /** @return array{order_id:string,approval_url:?string,reserved_until:string,listing:ResaleListing} */
    public function checkout(ResaleListing $listing, string $buyerUserId, string $buyerEmail, string $returnUrl, string $cancelUrl): array
    {
        // 1) reserve (short transaction, no network inside it)
        $sale = DB::transaction(function () use ($listing, $buyerUserId, $buyerEmail) {
            $l = ResaleListing::query()->whereKey($listing->id)->lockForUpdate()->firstOrFail();

            if ($l->status !== ListingStatus::Active || $l->access_expires_at->isPast()) {
                throw new ResaleException($l->status === ListingStatus::Reserved ? 'listing_reserved' : 'listing_unavailable', 'This listing is not available.', 409);
            }
            if ($l->seller_merchant_user_id === $buyerUserId) {
                throw new ResaleException('own_listing', 'You cannot buy your own listing.', 422);
            }

            $l->update([
                'status' => ListingStatus::Reserved, 'buyer_merchant_user_id' => $buyerUserId, 'buyer_email' => $buyerEmail,
                'reserved_until' => now()->addMinutes((int) config('luvupay.resale.reservation_minutes')),
            ]);

            return Transaction::create([
                'merchant_id' => $l->merchant_id, 'subscription_id' => $l->subscription_id, 'resale_listing_id' => $l->id,
                'type' => TransactionType::ResaleSale, 'status' => TransactionStatus::Pending, 'provider' => 'paypal',
                'merchant_user_id' => $buyerUserId, 'email' => $buyerEmail,
                'amount_minor' => $l->asking_price_minor, 'currency' => $l->currency,
            ]);
        });

        // 2) PayPal order. On failure: release the reservation so the listing is not stuck.
        try {
            $order = $this->provider->createOrder(Money::of($sale->amount_minor, $sale->currency), $listing->id, $returnUrl, $cancelUrl, "resale-order:{$sale->id}", $listing->id);
        } catch (\Throwable $e) {
            $this->release($listing->id, 'order_creation_failed');
            throw $e;
        }

        $sale->update(['metadata' => ['order_id' => $order->orderId]]);
        $listing->refresh();

        return ['order_id' => $order->orderId, 'approval_url' => $order->approvalUrl, 'reserved_until' => $listing->reserved_until->toIso8601String(), 'listing' => $listing];
    }

    public function complete(ResaleListing $listing, string $orderId): ResaleListing
    {
        $sale = Transaction::query()->where('resale_listing_id', $listing->id)->where('type', TransactionType::ResaleSale)
            ->where('metadata->order_id', $orderId)->firstOrFail();

        if ($sale->status === TransactionStatus::Completed) {
            return $listing->refresh();                                 // idempotent
        }
        if ($listing->status !== ListingStatus::Reserved) {
            throw new ResaleException('not_reserved', 'This listing is no longer reserved for this checkout.', 409);
        }

        try {
            $cap = $this->provider->capturePayment($orderId, "resale-capture:{$sale->id}");
        } catch (PaymentProviderException $e) {
            if (! $e->retryable) {
                throw new ResaleException('payment_not_approved', 'The PayPal payment was not approved or could not be captured.', 402);
            }
            throw $e;
        }

        if (! $cap->completed() || $cap->amount->amountMinor !== $sale->amount_minor || $cap->amount->currency !== $sale->currency) {
            $sale->update(['status' => TransactionStatus::Failed, 'failure_reason' => "capture {$cap->status}"]);
            $this->release($listing->id, 'capture_not_completed');
            throw new ResaleException('payment_failed', 'The payment could not be completed.', 402);
        }

        DB::transaction(function () use ($listing, $sale, $cap) {
            $l = ResaleListing::query()->whereKey($listing->id)->lockForUpdate()->firstOrFail();
            if ($l->status === ListingStatus::Sold) {
                return;
            }
            $sale->update(['status' => TransactionStatus::Completed, 'provider_reference' => $cap->providerCaptureId, 'processed_at' => now()]);

            // --- P2P proceeds from Buyer (User B) only ---
            // Gross = resale price paid by B. Merchant's original subscription revenue is untouched.
            // Split: seller_bps (9000) → User A payout; remainder → LuvuPay platform fee.
            // NEVER allocate resale proceeds as a "refund" against the merchant's cycle charge.
            $sellerBps = (int) config('luvupay.resale.seller_bps', 9000);
            $feeBps = 10000 - $sellerBps; // platform fee BPS (default 10%)
            $gross = Money::of($sale->amount_minor, $sale->currency);
            $parts = $gross->allocate(['seller' => $sellerBps, 'platform' => $feeBps]);
            app(\App\Services\Ledger\LedgerPoster::class)->resaleCaptured($sale, $parts['seller'], $parts['platform']);
            $payout = null;
            // Seller payout line
            if (! $parts['seller']->isZero()) {
                $payout = Transaction::create([
                    'merchant_id' => $l->merchant_id, 'subscription_id' => $l->subscription_id, 'resale_listing_id' => $l->id,
                    'parent_transaction_id' => $sale->id, 'type' => TransactionType::ResalePayout,
                    'status' => TransactionStatus::Pending,
                    'idempotency_key' => "resale-seller:{$l->id}",
                    'merchant_user_id' => $l->seller_merchant_user_id, 'email' => $l->seller_email,
                    'amount_minor' => $parts['seller']->amountMinor, 'currency' => $sale->currency,
                ]);
            }
            // Platform fee line (never taken from Second Chance charity/user refunds)
            if (! $parts['platform']->isZero()) {
                Transaction::create([
                    'merchant_id' => $l->merchant_id, 'subscription_id' => $l->subscription_id, 'resale_listing_id' => $l->id,
                    'parent_transaction_id' => $sale->id, 'type' => TransactionType::MerchantRetention,
                    'status' => TransactionStatus::Completed, 'processed_at' => now(),
                    'idempotency_key' => "resale-fee:{$l->id}",
                    'merchant_user_id' => $l->seller_merchant_user_id, 'email' => $l->seller_email,
                    'amount_minor' => $parts['platform']->amountMinor, 'currency' => $sale->currency,
                    'luvi_fee_amount' => $parts['platform']->amountMinor,
                    'fee_type' => 'resale_marketplace',
                ]);
            }
            $sale->update([
                'luvi_fee_amount' => $parts['platform']->amountMinor,
                'fee_type' => 'resale_marketplace',
            ]);
            app(\App\Services\Fraud\TrustScoreService::class)->recordResale($l->merchant_id, $l->seller_merchant_user_id);

            $l->update([
                'status' => ListingStatus::Sold, 'sold_at' => now(), 'reserved_until' => null,
                'buyer_transaction_id' => $sale->id, 'seller_payout_transaction_id' => $payout?->id,
                'entitlement_status' => EntitlementStatus::Pending, 'entitlement_correlation_id' => (string) Str::uuid(),
            ]);
            $l->subscription->update(['status' => SubscriptionStatus::Resold]);
        });

        // After commit:
        //  - Entitlement Bridge: merchant swaps access A→B for remainder of CURRENT cycle only.
        //  - SettleResaleSaleJob: cancel A's provider billing (so A is not charged next cycle) +
        //    PayPal Payout to A of (resale price − 10% fee). Still NO refund of merchant's original charge.
        DeliverEntitlementTransferJob::dispatch($listing->id);
        SettleResaleSaleJob::dispatch($listing->id);

        return $listing->refresh();
    }

    public function cancel(ResaleListing $listing): ResaleListing
    {
        if ($listing->status !== ListingStatus::Active) {
            throw new ResaleException('not_cancellable', 'Only active listings can be cancelled.', 409);
        }
        $listing->update(['status' => ListingStatus::Cancelled]);

        return $listing;
    }

    /** Give a reserved listing back to the marketplace. */
    public function release(string $listingId, string $reason): void
    {
        DB::transaction(function () use ($listingId, $reason) {
            $l = ResaleListing::query()->whereKey($listingId)->lockForUpdate()->first();
            if (! $l || $l->status !== ListingStatus::Reserved) {
                return;
            }
            $l->update(['status' => ListingStatus::Active, 'buyer_merchant_user_id' => null, 'buyer_email' => null, 'reserved_until' => null]);
            Transaction::query()->where('resale_listing_id', $l->id)->where('type', TransactionType::ResaleSale)->where('status', TransactionStatus::Pending)
                ->get()->each->update(['status' => TransactionStatus::Failed, 'failure_reason' => $reason]);
        });
    }

    /** Buyer-facing view: never exposes the seller's email. */
    public static function present(ResaleListing $l): array
    {
        return [
            'id' => $l->id, 'status' => $l->status->value, 'plan_code' => $l->subscription?->plan_code,
            'asking_price_minor' => $l->asking_price_minor, 'original_price_minor' => $l->subscription?->amount_minor, 'currency' => $l->currency,
            'remaining_days' => max(0, (int) ceil(($l->access_expires_at->timestamp - now()->timestamp) / 86400)),
            'access_expires_at' => $l->access_expires_at->toIso8601String(), 'entitlement_status' => $l->entitlement_status->value,
        ];
    }
}
