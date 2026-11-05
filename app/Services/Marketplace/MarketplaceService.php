<?php

namespace App\Services\Marketplace;

use App\Services\Ledger\LedgerPoster;
use App\Services\PayPal\PayPalAmount;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * State machine for B2C marketplace listings.
 *
 *   pending ──verify──▶ active ──reserve──▶ reserved ──claimCapture──▶ capturing ──finalize──▶ sold
 *                          ▲                   │                          │
 *                          └──release/expire───┘◀────────unclaim───────────┘
 *
 * Every transition is a single conditional UPDATE (compare-and-set), so concurrent requests cannot both win,
 * and no provider (PayPal) call is ever made while a row lock is held.
 */
final class MarketplaceService
{
    public function __construct(private readonly LedgerPoster $ledger) {}

    private function listings()
    {
        return DB::table('marketplace_listings');
    }

    /** Short, non-reversible tag of the seller address. Goes into signed links so a link cannot be reused if the address on the row changes. */
    public static function emailFingerprint(string $email): string
    {
        return substr(hash_hmac('sha256', strtolower(trim($email)), (string) config('app.key')), 0, 16);
    }

    /**
     * Record that the owner of the seller address clicked the signed link. This is the ONLY place ownership is granted.
     * Evidence keeps hashes only (no raw IP / user agent). Returns true when this call granted it.
     */
    public function grantOwnership(string $id, ?string $ip, ?string $userAgent): bool
    {
        $key = (string) config('app.key');

        return $this->listings()->where('id', $id)->whereNull('ownership_level')->update([
            'ownership_level' => 'email_signed_link',
            'verified_at' => now(),
            'ownership_evidence' => json_encode([
                'method' => 'signed_link_sent_to_seller_email',
                'verified_at' => now()->toIso8601String(),
                'ip_hash' => $ip ? substr(hash_hmac('sha256', $ip, $key), 0, 16) : null,
                'ua_hash' => $userAgent ? substr(hash_hmac('sha256', $userAgent, $key), 0, 16) : null,
                'proves' => 'control of the payout email inbox at verification time',
                'does_not_prove' => 'ownership of the underlying third-party subscription account',
            ]),
            'updated_at' => now(),
        ]) === 1;
    }

    /** Sold listings whose seller payout never completed (worker down, PayPal outage). Used by resale:maintain to re-dispatch. */
    public function stuckPayoutIds(int $olderThanMinutes = 2): array
    {
        return $this->listings()->where('status', 'sold')
            ->where(fn ($q) => $q->whereNull('payout_status')->orWhere('payout_status', 'failed'))
            ->where('sold_at', '<', now()->subMinutes($olderThanMinutes))
            ->limit(100)->pluck('id')->all();
    }

    public function reservationMinutes(): int
    {
        return max(1, (int) config('luvupay.marketplace.reservation_minutes', 15));
    }

    /** Return abandoned reservations to the pool. Returns how many were released. */
    public function releaseExpired(): int
    {
        return $this->listings()
            ->where('status', 'reserved')
            ->where('reserved_until', '<', now())
            ->update($this->clearedReservation());
    }

    /**
     * Atomically hold a listing for one buyer. Returns the reservation token that must come back on the PayPal URLs.
     * Aborts 404/409/422 when the listing is missing, not available, or the buyer is the seller.
     */
    public function reserve(string $id, string $buyerEmail): string
    {
        $this->releaseExpired();

        $listing = $this->listings()->where('id', $id)->first();
        abort_unless($listing, 404, 'Listing not found');
        abort_unless($listing->status === 'active', 409, 'Listing not available');
        abort_if(strcasecmp((string) $listing->seller_email, $buyerEmail) === 0, 422, 'You cannot buy your own listing');

        $token = Str::random(40);
        $won = $this->listings()->where('id', $id)->where('status', 'active')->update([
            'status' => 'reserved',
            'buyer_email' => $buyerEmail,
            'reserved_until' => now()->addMinutes($this->reservationMinutes()),
            'reservation_token' => $token,
            'paypal_order_id' => null,
            'updated_at' => now(),
        ]);
        abort_unless($won === 1, 409, 'Listing not available');   // lost the race to another buyer

        return $token;
    }

    public function attachOrder(string $id, string $token, string $orderId): void
    {
        $this->listings()->where('id', $id)->where('status', 'reserved')->where('reservation_token', $token)
            ->update(['paypal_order_id' => $orderId, 'updated_at' => now()]);
    }

    /** Release one specific reservation (buyer cancelled, or order creation failed). Needs the matching token. */
    public function release(string $id, string $token): bool
    {
        return $this->listings()->where('id', $id)->where('status', 'reserved')->where('reservation_token', $token)
            ->update($this->clearedReservation()) === 1;
    }

    /** First step of settlement: only the holder of the right token + order id may move reserved → capturing, once. */
    public function claimCapture(string $id, string $token, string $orderId): bool
    {
        if ($token === '' || $orderId === '') {
            return false;
        }

        return $this->listings()->where('id', $id)->where('status', 'reserved')
            ->where('reservation_token', $token)->where('paypal_order_id', $orderId)
            ->update(['status' => 'capturing', 'updated_at' => now()]) === 1;
    }

    /** Capture could not be completed (network error): hand the listing back to the reservation so it can be retried. */
    public function unclaim(string $id): void
    {
        $this->listings()->where('id', $id)->where('status', 'capturing')
            ->update(['status' => 'reserved', 'updated_at' => now()]);
    }

    /** Capture was declined / not COMPLETED: return the listing to the pool. */
    public function failCapture(string $id): void
    {
        $this->listings()->where('id', $id)->where('status', 'capturing')->update($this->clearedReservation());
    }

    /**
     * Verify the provider's capture response against what we asked for.
     *
     * @return array{id:string,money:Money}|null  null when the capture is not COMPLETED or does not match the price.
     */
    public function verifiedCapture(object $listing, array $response): ?array
    {
        $capture = $response['purchase_units'][0]['payments']['captures'][0] ?? null;
        if (($response['status'] ?? null) !== 'COMPLETED' || ! is_array($capture) || ($capture['status'] ?? null) !== 'COMPLETED') {
            return null;
        }
        $paid = PayPalAmount::toMoney($capture['amount'] ?? null);
        if (! $paid || $paid->amountMinor !== (int) $listing->price_minor || $paid->currency !== strtoupper((string) $listing->currency)) {
            return null;
        }

        return ['id' => (string) ($capture['id'] ?? ''), 'money' => $paid];
    }

    /** Mark sold and record the money in the double-entry ledger, atomically. Safe to call twice. */
    public function finalizeSale(string $id, string $captureId, Money $paid): void
    {
        DB::transaction(function () use ($id, $captureId, $paid) {
            $row = $this->listings()->where('id', $id)->lockForUpdate()->first();
            if (! $row || $row->status === 'sold') {
                return;
            }

            $meta = json_decode((string) ($row->metadata ?? '{}'), true) ?: [];
            $this->listings()->where('id', $id)->update([
                'status' => 'sold',
                'capture_id' => $captureId !== '' ? $captureId : null,
                'sold_at' => now(),
                'reserved_until' => null,
                'reservation_token' => null,
                'metadata' => json_encode($meta + ['captured' => true]),
                'updated_at' => now(),
            ]);

            $seller = intdiv($paid->amountMinor * (int) config('luvupay.marketplace.seller_bps', 9000), 10000);
            $this->ledger->post("marketplace:{$id}", 'marketplace_sale', $paid->currency, [
                [LedgerPoster::CLEARING, 'debit', $paid->amountMinor],
                [LedgerPoster::SELLER, 'credit', $seller],
                [LedgerPoster::FEES, 'credit', $paid->amountMinor - $seller],
            ], null, null, ['listing_id' => $id, 'capture_id' => $captureId]);
        });
    }

    /** @return array<string,mixed> */
    private function clearedReservation(): array
    {
        return [
            'status' => 'active',
            'buyer_email' => null,
            'paypal_order_id' => null,
            'reserved_until' => null,
            'reservation_token' => null,
            'updated_at' => now(),
        ];
    }
}
