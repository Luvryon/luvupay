<?php

namespace App\Services\Resale;

use App\Http\Controllers\Demo\Concerns\DemoSupport;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Marketplace resale money rules (single implementation, used by the sandbox button AND the public redeem page).
 *
 *  - The merchant already collected the cycle charge from User A and KEEPS it (it is released to the wallet on sale).
 *  - User B pays the asking price into the platform. seller_bps goes to User A, the rest is the LuvuPay platform fee.
 *  - The platform fee is NOT merchant revenue and is never part of the merchant wallet.
 *  - The subscription moves to User B for the rest of the current cycle.
 *
 * Ledger rows written: resale_sale (buyer paid), resale_payout (seller share), resale_fee (platform share, informational).
 * Invariant: sale = payout + fee.
 */
final class ResaleSettlement
{
    use DemoSupport;

    /** Prorated asking price for the unused time, bounded by [min price, original charge]. */
    public static function askingPrice(int $chargeMinor, ?Carbon $periodEnd, ?Carbon $now = null): int
    {
        $now ??= now();
        $period = max(1, (int) config('luvupay.resale.period_days', 30));
        $remaining = $periodEnd && $periodEnd->gt($now) ? max(1, (int) ceil($now->floatDiffInDays($periodEnd, true))) : 0;
        if ($remaining === 0) {
            throw new RuntimeException('There is no remaining access to resell.');
        }
        $remaining = min($remaining, $period);
        $price = (int) round($chargeMinor * ($remaining / $period) * ((int) config('luvupay.resale.price_bps_of_remaining', 8000) / 10000));

        return max((int) config('luvupay.resale.min_price_minor', 100), min($chargeMinor, $price));
    }

    /** @return array{seller_minor:int,fee_minor:int} */
    public static function split(int $saleMinor): array
    {
        $seller = (int) round($saleMinor * (int) config('luvupay.resale.seller_bps', 9000) / 10000);

        return ['seller_minor' => $seller, 'fee_minor' => $saleMinor - $seller];
    }

    /**
     * Settles an ACTIVE listing as sold. Must run inside a DB transaction.
     *
     * @return array<string,mixed>
     */
    public function settle(object $listing, string $buyerEmail, string $buyerUserId, string $provider, string $reference, string $source): array
    {
        $this->scopedMerchant = (string) $listing->merchant_id;
        $locked = DB::table('resale_listings')->where('id', $listing->id)->lockForUpdate()->first();
        if (! $locked || $locked->status !== 'active' || Carbon::parse($locked->access_expires_at)->isPast()) {
            throw new RuntimeException('This listing is no longer available.');
        }
        if (strtolower((string) $locked->seller_email) === strtolower($buyerEmail)) {
            throw new RuntimeException('You cannot buy your own listing.');
        }
        $now = now();
        $m = (string) $locked->merchant_id;
        $cur = trim((string) $locked->currency) ?: 'USD';
        $asking = (int) $locked->asking_price_minor;
        ['seller_minor' => $sellerMinor, 'fee_minor' => $feeMinor] = self::split($asking);

        // Compare-and-set: only one buyer can win the listing.
        $won = DB::table('resale_listings')->where('id', $locked->id)->where('status', 'active')->update([
            'status' => 'sold', 'buyer_merchant_user_id' => $buyerUserId, 'buyer_email' => strtolower($buyerEmail), 'sold_at' => $now,
            'entitlement_status' => 'transferred', 'entitlement_sent_at' => $now, 'buyer_payment_reference' => $reference, 'updated_at' => $now,
        ]);
        if ($won !== 1) {
            throw new RuntimeException('This listing is no longer available.');
        }

        $parent = $locked->subscription_id
            ? DB::table('transactions')->where('merchant_id', $m)->where('subscription_id', $locked->subscription_id)->where('type', 'charge')->orderByDesc('created_at')->lockForUpdate()->first()
            : null;
        $saleId = (string) Str::uuid();
        $payoutId = (string) Str::uuid();
        $row = fn (string $id, string $type, int $amt, ?string $parentId, string $who, ?string $email, string $ref, array $meta, array $extra = []) => DB::table('transactions')->insert([
            'id' => $id, 'merchant_id' => $m, 'subscription_id' => $locked->subscription_id, 'parent_transaction_id' => $parentId, 'type' => $type,
            'status' => 'completed', 'idempotency_key' => 'resale-'.$type.'-'.$locked->id, 'merchant_user_id' => $who, 'email' => $email,
            'amount_minor' => $amt, 'currency' => $cur, 'provider' => $provider, 'provider_reference' => $ref, 'processed_at' => $now,
            'metadata' => json_encode($meta + ['listing_id' => $locked->id, 'source' => $source]), 'created_at' => $now, 'updated_at' => $now,
        ] + $extra);

        $row($saleId, 'resale_sale', $asking, $parent->id ?? null, $buyerUserId, strtolower($buyerEmail), $reference, ['lifecycle' => 'RESOLD'], ['trust_score' => 88, 'churn_risk' => 'low']);
        $row($payoutId, 'resale_payout', $sellerMinor, $saleId, (string) $locked->seller_merchant_user_id, (string) $locked->seller_email, 'PAYOUT-'.strtoupper(Str::random(8)), ['buyer_email' => strtolower($buyerEmail)]);
        if ($feeMinor > 0) {
            $row((string) Str::uuid(), 'resale_fee', $feeMinor, $saleId, (string) $locked->seller_merchant_user_id, (string) $locked->seller_email, 'RESALE-FEE-'.$locked->id, ['funded_by' => 'buyer_resale_payment', 'platform_fee' => true], ['luvi_fee_amount' => $feeMinor, 'fee_type' => 'resale_marketplace']);
        }

        if ($parent) {
            $pm = json_decode((string) ($parent->metadata ?? '{}'), true) ?: [];
            $pm['lifecycle'] = 'RESOLD';
            $pm['decision'] = 'marketplace_resale';
            $pm['buyer_email'] = strtolower($buyerEmail);
            $pm['listing_id'] = $locked->id;
            unset($pm['grace']);
            // The merchant keeps the original charge: it is released to the wallet now.
            DB::table('transactions')->where('id', $parent->id)->update(['status' => 'completed', 'second_chance_expires_at' => null, 'metadata' => json_encode($pm), 'updated_at' => $now]);
        }
        if ($locked->subscription_id) {
            DB::table('subscriptions')->where('id', $locked->subscription_id)->update(['status' => 'resold', 'merchant_user_id' => $buyerUserId, 'email' => strtolower($buyerEmail), 'updated_at' => $now]);
        }
        // Any other open listing for the same subscription is now void.
        DB::table('resale_listings')->where('subscription_id', $locked->subscription_id)->where('id', '!=', $locked->id)->where('status', 'active')->update(['status' => 'cancelled', 'updated_at' => $now]);

        $this->logWebhookEvent('luvupay', 'marketplace.listing_sold', ['listing_id' => $locked->id, 'sale_minor' => $asking, 'seller_minor' => $sellerMinor, 'platform_fee_minor' => $feeMinor]);
        $this->logWebhookEvent('luvupay', 'entitlement.transfer', ['listing_id' => $locked->id, 'seller' => $locked->seller_email, 'buyer' => strtolower($buyerEmail)]);

        return ['listing_id' => $locked->id, 'sale_id' => $saleId, 'payout_id' => $payoutId, 'parent_id' => $parent->id ?? null, 'merchant_id' => $m,
            'currency' => $cur, 'sale_minor' => $asking, 'seller_minor' => $sellerMinor, 'fee_minor' => $feeMinor, 'seller_email' => $locked->seller_email,
            'access_expires_at' => $locked->access_expires_at, 'seller_user_id' => $locked->seller_merchant_user_id];
    }

    /** Voids open listings of a subscription (user chose something else, or the window closed unsold). */
    public static function cancelOpenListings(string $subscriptionId, string $status = 'cancelled'): int
    {
        return DB::table('resale_listings')->where('subscription_id', $subscriptionId)->where('status', 'active')->update(['status' => $status, 'updated_at' => now()]);
    }
}
