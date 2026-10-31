<?php

namespace App\Services\Luvu;

use App\Http\Controllers\Demo\Concerns\DemoSupport;
use App\Services\Hosted\HostedPurchase;
use App\Services\Resale\ResaleSettlement;
use App\Support\MerchantProject;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Outcome of a held (grace-period) charge. Every path runs under a row lock and only a HELD charge can be decided,
 * so a double click, a replay or two browser tabs can never refund twice, donate twice or resolve a settled charge.
 *
 *  renew          user pays the outstanding charge        -> completed, subscription active
 *  split_pay      down payment now, rest in instalments   -> held as SPLIT PAY (see SplitPayService)
 *  partial_refund user cancels, gets refund_bps back      -> completed (merchant keeps the rest), subscription cancelled
 *  donate         user cancels, charity_bps is donated    -> completed (merchant keeps the rest), subscription cancelled
 *  resale         remaining access listed on marketplace  -> stays held until it sells (then RESOLD) or the window closes
 *  expire         nothing happens / user cancels          -> cancelled (no revenue), subscription expired
 */
final class GraceDecisionService
{
    use DemoSupport;

    public const CHOICES = ['renew', 'split_pay', 'partial_refund', 'donate', 'resale', 'expire'];

    public function __construct(private readonly SplitPayService $split) {}

    /**
     * @return array{message:string,choice:string,listing?:array<string,mixed>,plan?:array<string,mixed>,transaction_id:string,subscription_id:?string}
     */
    public function decide(string $merchantId, string $txId, string $choice, MerchantProject $project, string $source = 'user'): array
    {
        $this->scopedMerchant = $merchantId;
        $choice = ['cancel' => 'expire', 'retain' => 'partial_refund'][$choice] ?? $choice;
        if (! in_array($choice, self::CHOICES, true)) {
            throw new RuntimeException('Unknown option.');
        }
        if ($choice === 'resale' && ! $project->marketplaceResale) {
            throw new RuntimeException('Marketplace Resale is not enabled for this project. Enable it in project settings.');
        }
        if ($choice === 'donate' && ! $project->charitySplit) {
            throw new RuntimeException('Charity Split is not enabled for this project. Enable it in project settings.');
        }

        return DB::transaction(function () use ($merchantId, $txId, $choice, $project, $source) {
            $tx = DB::table('transactions')->where('merchant_id', $merchantId)->where('id', $txId)->lockForUpdate()->first();
            abort_unless($tx, 404, 'Transaction not found');
            abort_unless($tx->type === 'charge', 422, 'Only charge rows can be decided');

            $meta = json_decode((string) ($tx->metadata ?? '{}'), true) ?: [];
            if ($tx->status !== 'held') {
                throw new RuntimeException('This charge is already resolved ('.($meta['lifecycle'] ?? $tx->status).'). Nothing was changed.');
            }
            if (($meta['decision'] ?? '') === 'split_pay') {
                throw new RuntimeException('This charge is on an instalment plan. Collect the next instalment instead.');
            }
            if ($choice !== 'expire' && $tx->second_chance_expires_at && Carbon::parse($tx->second_chance_expires_at)->isPast()) {
                throw new RuntimeException('The grace window has closed for this charge.');
            }
            // Stage rules (7 days / 3 days / 48h / 6h): an option that is closed at this moment cannot be forced through the API either.
            if ($source !== 'timer' && $choice !== 'expire' && $tx->second_chance_expires_at) {
                $stage = GraceStagePlanner::keyForRemaining((float) GraceStagePlanner::remainingHours((string) $tx->second_chance_expires_at));
                if (! in_array($choice, GraceStagePlanner::allowedChoices($stage), true)) {
                    throw new RuntimeException('That option is closed at this stage ('.config('luvupay.grace_stages.'.$stage.'.label').'). Available: '.implode(', ', GraceStagePlanner::allowedChoices($stage)).'.');
                }
            }

            $now = now();
            $amount = (int) $tx->amount_minor;
            $subId = $tx->subscription_id;
            $sub = $subId ? DB::table('subscriptions')->where('id', $subId)->lockForUpdate()->first() : null;
            $out = ['choice' => $choice, 'transaction_id' => $tx->id, 'subscription_id' => $subId, 'message' => ''];

            $closeListings = fn () => $subId ? ResaleSettlement::cancelOpenListings($subId) : 0;
            $setTx = fn (string $status, array $m) => DB::table('transactions')->where('id', $tx->id)->update([
                'status' => $status, 'second_chance_expires_at' => $status === 'held' ? $tx->second_chance_expires_at : null,
                'metadata' => json_encode($m), 'updated_at' => $now,
            ]);
            $setSub = fn (string $status) => $subId ? DB::table('subscriptions')->where('id', $subId)->update(['status' => $status, 'updated_at' => $now]) : null;
            unset($meta['offers']);

            switch ($choice) {
                case 'renew':
                    $closeListings();
                    $meta['lifecycle'] = 'ACTIVE';
                    $meta['decision'] = 'user_renewed';
                    unset($meta['grace']);
                    $setTx('completed', $meta);
                    $setSub('active');
                    $this->logWebhookEvent('paypal', 'BILLING.SUBSCRIPTION.PAYMENT.COMPLETED', ['transaction_id' => $tx->id, 'event' => 'payment_retry_success']);
                    $out['message'] = 'Payment retry successful - ACTIVE';
                    break;

                case 'split_pay':
                    $closeListings();
                    $plan = $this->split->open($merchantId, $tx, $now);
                    $out['plan'] = $plan;
                    $out['message'] = sprintf('Split pay started: %s collected now, %s due %s', self::fmt($plan['collected_minor'], $plan['currency']), self::fmt($plan['remaining_minor'], $plan['currency']), Carbon::parse($plan['next_due_at'])->format('d M'));
                    break;

                case 'partial_refund':
                    $closeListings();
                    $bps = (int) ($meta['grace']['partial_refund_bps'] ?? $project->partialRefundBps);
                    $refund = min($amount, (int) round($amount * $bps / 10000));
                    $meta['lifecycle'] = 'AI-RETAINED';
                    $meta['decision'] = 'partial_refund_accepted';
                    $meta['smart_convert'] = ['type' => 'partial_refund', 'refund_bps' => $bps, 'refund_minor' => $refund, 'chargeback_prevented' => true];
                    $this->tryPayPalRefund($tx, $refund, $meta);
                    unset($meta['grace']);
                    $setTx('completed', $meta);
                    $setSub('cancelled');   // the subscriber cancelled in exchange for the refund
                    $this->logWebhookEvent('luvupay', 'subscription.partial_refund', ['transaction_id' => $tx->id, 'refund_minor' => $refund]);
                    $out['message'] = 'Partial refund accepted - AI-RETAINED (chargeback avoided)';
                    break;

                case 'donate':
                    $closeListings();
                    $donate = max(1, min($amount, (int) round($amount * $project->charityBps / 10000)));
                    $charityName = DB::table('charity_wallets')->where('is_active', true)->value('name') ?: 'Ocean Cleanup Alliance';
                    $receiptId = (string) Str::uuid();
                    $meta['lifecycle'] = 'AI-RETAINED';
                    $meta['decision'] = 'charity_donate';
                    $meta['charity'] = ['donated_minor' => $donate, 'bps' => $project->charityBps, 'charity_name' => $charityName, 'at' => $now->toIso8601String()];
                    $meta['receipt_id'] = $receiptId;
                    unset($meta['grace']);
                    $setTx('completed', $meta);
                    $setSub('cancelled');
                    DB::table('transactions')->insert([
                        'id' => (string) Str::uuid(), 'merchant_id' => $merchantId, 'subscription_id' => $subId, 'parent_transaction_id' => $tx->id,
                        'type' => 'charity_payout', 'status' => 'completed', 'idempotency_key' => 'charity-'.$tx->id,   // unique: one donation per charge
                        'merchant_user_id' => $tx->merchant_user_id, 'email' => $tx->email, 'amount_minor' => $donate, 'currency' => $tx->currency,
                        'provider' => 'luvupay', 'provider_reference' => 'CHARITY-'.$tx->id, 'processed_at' => $now,
                        'metadata' => json_encode(['lifecycle' => 'AI-RETAINED', 'parent_transaction_id' => $tx->id, 'path' => 'charity_donate', 'charity_name' => $charityName, 'receipt_id' => $receiptId]),
                        'created_at' => $now, 'updated_at' => $now,
                    ]);
                    try {
                        DB::table('charity_receipts')->insert([
                            'id' => $receiptId, 'merchant_id' => $merchantId, 'transaction_id' => $tx->id, 'certificate_code' => 'LUVI-'.strtoupper(Str::random(8)),
                            'charity_name' => $charityName, 'amount_minor' => $donate, 'currency' => $tx->currency, 'paypal_payout_id' => 'CHARITY-'.$tx->id,
                            'donor_email' => $tx->email, 'metadata' => json_encode(['parent_charge_id' => $tx->id]), 'created_at' => $now, 'updated_at' => $now,
                        ]);
                    } catch (\Throwable $e) {
                        // receipts table is optional
                    }
                    $this->logWebhookEvent('luvupay', 'charity.donation', ['transaction_id' => $tx->id, 'amount_minor' => $donate]);
                    $out['message'] = 'Donated '.self::fmt($donate, (string) $tx->currency).' to '.$charityName.' - AI-RETAINED (charity split)';
                    break;

                case 'resale':
                    abort_unless($sub, 422, 'Charge has no subscription to resell');
                    $periodEnd = $sub->current_period_end ? Carbon::parse($sub->current_period_end) : null;
                    $asking = ResaleSettlement::askingPrice($amount, $periodEnd, $now);   // throws if nothing is left to resell
                    ResaleSettlement::cancelOpenListings($subId);
                    $meta['lifecycle'] = 'PENDING RESALE';
                    $meta['decision'] = 'marketplace_resale';
                    $meta['resale'] = ['listed_at' => $now->toIso8601String(), 'asking_price_minor' => $asking, 'path' => 'marketplace_resale',
                        'note' => 'Original charge is released to the merchant when the listing sells; seller payout is funded by the buyer'];
                    $setTx('held', $meta);
                    $name = (string) ($meta['display_name'] ?? $tx->email ?? 'user');
                    DB::table('resale_listings')->insert([
                        'id' => (string) Str::uuid(), 'merchant_id' => $merchantId, 'subscription_id' => $subId,
                        'seller_merchant_user_id' => (string) ($sub->merchant_user_id ?? $tx->merchant_user_id ?? 'seller'), 'seller_email' => (string) ($sub->email ?? $tx->email ?? ''),
                        'status' => 'active', 'entitlement_status' => 'pending', 'asking_price_minor' => $asking, 'currency' => trim((string) $tx->currency) ?: 'USD',
                        'access_expires_at' => $periodEnd, 'listed_at' => $now, 'correlation_id' => (string) Str::uuid(),
                        'redeem_token' => HostedPurchase::token($name, max(1, (int) ceil($now->floatDiffInDays($periodEnd, true)))),
                        'metadata' => json_encode(['seller_name' => $meta['display_name'] ?? null, 'source' => 'decision', 'parent_charge_id' => $tx->id]),
                        'created_at' => $now, 'updated_at' => $now,
                    ]);
                    $this->logWebhookEvent('luvupay', 'marketplace.listing_created', ['transaction_id' => $tx->id, 'asking_price_minor' => $asking]);
                    $out['message'] = 'Remaining access listed at '.self::fmt($asking, (string) $tx->currency).'. The original charge is released to you when it sells.';
                    break;

                case 'expire':
                    $closeListings();
                    $meta['lifecycle'] = 'CHURNED';
                    $meta['decision'] = $source === 'timer' ? 'expired_no_response' : 'expired_by_user';
                    $meta['grace'] = ($meta['grace'] ?? []) + ['closed_at' => $now->toIso8601String(), 'outcome' => 'expire'];
                    $setTx('cancelled', $meta);
                    $setSub('expired');
                    $this->logWebhookEvent('paypal', 'BILLING.SUBSCRIPTION.EXPIRED', ['transaction_id' => $tx->id, 'source' => $source]);
                    $out['message'] = 'Grace window closed - subscription EXPIRED (churn). No force.';
                    break;
            }

            return $out;
        });
    }

    /** Timer: every held charge whose grace window has closed expires (open listings are voided too). */
    public function sweepExpired(): int
    {
        $n = 0;
        DB::table('transactions')->where('type', 'charge')->where('status', 'held')->whereNotNull('second_chance_expires_at')->where('second_chance_expires_at', '<=', now())
            ->orderBy('second_chance_expires_at')->limit(200)->get(['id', 'merchant_id', 'metadata'])->each(function ($t) use (&$n) {
                $meta = json_decode((string) $t->metadata, true) ?: [];
                if (($meta['decision'] ?? '') === 'split_pay') {
                    return;
                }
                try {
                    $this->decide((string) $t->merchant_id, (string) $t->id, 'expire', MerchantProject::forMerchant((string) $t->merchant_id), 'timer');
                    $n++;
                } catch (\Throwable $e) {
                    report($e);
                }
            });

        return $n;
    }

    private static function fmt(int $minor, string $cur): string
    {
        $cur = strtoupper(trim($cur) ?: 'USD');

        return ($cur === 'USD' ? '$' : '').number_format($minor / 100, 2).($cur === 'USD' ? '' : ' '.$cur);
    }

    /** Best-effort PayPal refund of the partial amount when the charge carries a real capture id. */
    private function tryPayPalRefund(object $tx, int $refundMinor, array &$meta): void
    {
        $captureId = $meta['paypal_capture_id'] ?? null;
        if ((string) config('luvupay.payment.driver', 'mock') !== 'paypal' || $refundMinor <= 0 || ! is_string($captureId) || $captureId === '') {
            return;
        }
        try {
            $ref = \App\Services\PayPal\PayPalClient::fromConfig()->refundPayment($captureId, \App\Support\Money::of($refundMinor, trim((string) $tx->currency) ?: 'USD'), 'partial-refund-'.$tx->id, 'LuvuPay partial refund (grace period)');
            $meta['paypal_refund_id'] = $ref['id'] ?? null;
            $meta['paypal_refund_status'] = $ref['status'] ?? null;
            $this->logWebhookEvent('paypal', 'PAYMENT.CAPTURE.REFUNDED', ['transaction_id' => $tx->id, 'refund' => $meta['paypal_refund_id']]);
        } catch (\Throwable $e) {
            $meta['paypal_refund_error'] = mb_substr($e->getMessage(), 0, 160);
            $this->logWebhookEvent('paypal', 'PAYMENT.REFUND.FAILED', ['transaction_id' => $tx->id, 'error' => $meta['paypal_refund_error']], 'failed');
        }
    }
}
