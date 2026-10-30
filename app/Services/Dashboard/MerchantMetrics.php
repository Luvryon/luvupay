<?php

namespace App\Services\Dashboard;

use App\Support\MerchantProject;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Single source of truth for every number the merchant console shows
 * (Overview KPIs, Wallet balance, Analytics). One pass over the ledger.
 *
 * Money semantics (all minor units):
 *  - charge_volume   : completed `charge` rows
 *  - refunded        : partial refunds the merchant gave to keep the subscriber (meta.smart_convert.refund_minor)
 *  - charity         : completed `charity_payout` rows (ABS: some writers store negatives)
 *  - resale_volume   : completed `resale_sale` rows (buyer payments, flows through the platform)
 *  - resale_fees     : (resale_sale - resale_payout) + listing `resale_fee` rows = what the merchant keeps
 *  - revenue_saved   : partial-refund cash kept + chargeback fee avoided (charity path) + resale fees + smart-convert retained
 *  - wallet          : charge_volume - refunded - charity + resale_fees - withdrawn   (never below 0)
 */
final class MerchantMetrics
{
    /** Typical PayPal dispute/chargeback fee avoided when the user donates instead of disputing. */
    public const CHARGEBACK_FEE_MINOR = 1500;

    /** @return array<string,mixed> */
    public function compute(string $merchantId, ?int $days = null): array
    {
        $merchant = DB::table('merchants')->where('id', $merchantId)->first();
        $currency = trim((string) ($merchant->currency ?? '')) ?: 'USD';
        $from = $days ? now()->subDays($days - 1)->startOfDay() : null;

        $rows = DB::table('transactions')->where('merchant_id', $merchantId)
            ->orderBy('created_at')
            ->get(['id', 'type', 'status', 'amount_minor', 'metadata', 'created_at', 'subscription_id']);

        // all-time (wallet) and in-range (KPIs) accumulators
        $all = $this->bucket();
        $rng = $this->bucket();
        $series = [];
        if ($days) {
            for ($i = $days - 1; $i >= 0; $i--) {
                $series[now()->subDays($i)->toDateString()] = ['revenue' => 0, 'saved' => 0, 'charity' => 0, 'resale' => 0, 'refunded' => 0];
            }
        }

        foreach ($rows as $r) {
            $created = Carbon::parse($r->created_at);
            $inRange = ! $from || $created->gte($from);
            $day = $created->toDateString();
            $meta = json_decode($r->metadata ?? '{}', true) ?: [];
            $amt = abs((int) $r->amount_minor);
            $done = in_array($r->status, ['completed', 'settled', 'held'], true); // held = grace; funds still captured

            $this->apply($all, $r, $meta, $amt, $done);
            if ($inRange) {
                $delta = $this->apply($rng, $r, $meta, $amt, $done);
                if (isset($series[$day])) {
                    foreach ($delta as $key => $v) {
                        $series[$day][$key] += $v;
                    }
                }
            }
        }

        $subs = $this->subscribers($merchantId);
        $wallet = max(0, $all['charge_volume'] - $all['refunded'] - $all['charity'] - $all['withdrawn']);
        $k = $rng;
        $resaleFees = $this->resaleFees($k);
        $saved = $k['saved_partial'] + $k['saved_charity'] + $k['saved_smart'];

        $totalSubs = max(1, $subs['total']);
        $out = [
            'currency' => $currency,
            'range_days' => $days,
            'charge_volume_minor' => $k['charge_volume'],
            'total_revenue_minor' => $k['charge_volume'],
            'refunded_minor' => $k['refunded'],
            'charity_minor' => $k['charity'],
            'charity_impact_minor' => $k['charity'],
            'resale_volume_minor' => $k['resale_volume'],
            'marketplace_volume_minor' => $k['resale_volume'],
            'resale_fees_minor' => $resaleFees,
            'revenue_saved_minor' => $saved,
            'total_revenue_saved_minor' => $saved,
            'saved_breakdown' => [
                'partial_refund_minor' => $k['saved_partial'],
                'charity_minor' => $k['saved_charity'],
                'resale_fees_minor' => $resaleFees,
                'smart_convert_minor' => $k['saved_smart'],
            ],
            'transaction_count' => $k['charges'],
            'chargebacks_prevented' => $k['prevented'],
            'churned_count' => $subs['churned'],
            'churn_rate' => round(($subs['churned'] / $totalSubs) * 100, 1),
            'active_subscribers' => $subs['active'],
            'subscribers' => $subs,
            'outcomes' => $k['outcomes'],
            'at_risk_minor' => $k['at_risk'],
            'withdrawn_minor' => $all['withdrawn'],
            'wallet_balance_minor' => $wallet,
            'available_balance_minor' => $wallet,
            'wallet' => [
                'balance_minor' => $wallet,
                'charges_minor' => $all['charge_volume'],
                'refunded_minor' => $all['refunded'],
                'charity_minor' => $all['charity'],
                'resale_fees_minor' => $this->resaleFees($all),
                'withdrawn_minor' => $all['withdrawn'],
            ],
            'active_listings' => (int) DB::table('resale_listings')->where('merchant_id', $merchantId)
                ->where('status', 'active')->where('access_expires_at', '>', now())->count(),
            'serial_forgetters' => (int) DB::table('subscriptions')->where('merchant_id', $merchantId)
                ->where('forgotten_cancel_count', '>=', (int) ($merchant->serial_forgetter_threshold ?? 2))
                ->distinct()->count('merchant_user_id'),
            'series' => collect($series)->map(fn ($v, $d) => ['date' => $d] + $v)->values()->all(),
        ];
        $out['insights'] = $this->insights($out);

        return $out;
    }

    /** @return array<string,int|array> */
    private function bucket(): array
    {
        return [
            'charge_volume' => 0, 'charges' => 0, 'refunded' => 0, 'charity' => 0, 'resale_volume' => 0,
            'resale_payouts' => 0, 'resale_listing_fees' => 0, 'withdrawn' => 0, 'prevented' => 0, 'at_risk' => 0,
            'saved_partial' => 0, 'saved_charity' => 0, 'saved_smart' => 0,
            'outcomes' => ['active' => 0, 'renewed' => 0, 'grace' => 0, 'partial_refund' => 0, 'charity' => 0,
                'resale' => 0, 'resale_pending' => 0, 'smart_convert' => 0, 'churned' => 0],
        ];
    }

    private function resaleFees(array $b): int
    {
        return max(0, $b['resale_volume'] - $b['resale_payouts']) + $b['resale_listing_fees'];
    }

    /** Applies one ledger row to a bucket; returns the per-day deltas for the chart. */
    private function apply(array &$b, object $r, array $meta, int $amt, bool $done): array
    {
        $d = ['revenue' => 0, 'saved' => 0, 'charity' => 0, 'resale' => 0, 'refunded' => 0];
        switch ($r->type) {
            case 'charge':
                $b['charges']++;
                $life = strtoupper(str_replace('_', ' ', (string) ($meta['lifecycle'] ?? '')));
                $decision = (string) ($meta['decision'] ?? '');
                if ($r->status === 'held') {
                    $b['at_risk'] += $amt;
                    $b['outcomes'][$life === 'PENDING RESALE' ? 'resale_pending' : 'grace']++;
                    break;
                }
                if (in_array($r->status, ['cancelled', 'failed', 'expired'], true)) {
                    $b['outcomes']['churned']++;
                    break;
                }
                if (! $done) {
                    break;
                }
                $b['charge_volume'] += $amt;
                $d['revenue'] = $amt;
                $sc = $meta['smart_convert'] ?? [];
                if ($decision === 'partial_refund_accepted') {
                    $refund = min($amt, abs((int) ($sc['refund_minor'] ?? 0)));
                    $b['refunded'] += $refund;
                    $b['saved_partial'] += $amt - $refund;
                    $b['prevented']++;
                    $b['outcomes']['partial_refund']++;
                    $d['refunded'] = $refund;
                    $d['saved'] = $amt - $refund;
                } elseif ($decision === 'charity_donate') {
                    $b['saved_charity'] += self::CHARGEBACK_FEE_MINOR;
                    $b['prevented']++;
                    $b['outcomes']['charity']++;
                    $d['saved'] = self::CHARGEBACK_FEE_MINOR;
                } elseif ($life === 'SMART-CONVERTED' || ! empty($sc['chargeback_prevented'])) {
                    $kept = abs((int) ($sc['revenue_retained_minor'] ?? 0));
                    $b['saved_smart'] += $kept;
                    $b['prevented']++;
                    $b['outcomes']['smart_convert']++;
                    $d['saved'] = $kept;
                } elseif ($life === 'RESOLD') {
                    $b['prevented']++;
                    $b['outcomes']['resale']++;
                } elseif ($decision === 'user_renewed') {
                    $b['outcomes']['renewed']++;
                } else {
                    $b['outcomes']['active']++;
                }
                break;
            case 'resale_sale':
                if ($done) {
                    $b['resale_volume'] += $amt;
                    $d['resale'] = $amt;
                }
                break;
            case 'resale_payout':
                if ($done) {
                    $b['resale_payouts'] += $amt;
                }
                break;
            case 'resale_fee':
                if ($done) {
                    $b['resale_listing_fees'] += $amt;
                }
                break;
            case 'charity_payout':
                if ($done) {
                    $b['charity'] += $amt;
                    $d['charity'] = $amt;
                }
                break;
            case 'payout':
            case 'merchant_withdrawal':
                if (in_array($r->status, ['completed', 'settled', 'pending'], true)) {
                    $b['withdrawn'] += $amt;
                }
                break;
        }

        return $d;
    }

    /** @return array<string,int> */
    private function subscribers(string $merchantId): array
    {
        $by = DB::table('subscriptions')->where('merchant_id', $merchantId)
            ->selectRaw('status, count(*) as c')->groupBy('status')->pluck('c', 'status');
        $n = fn (array $s) => (int) collect($s)->sum(fn ($k) => (int) ($by[$k] ?? 0));
        $active = $n(['active', 'trialing', 'resold']);
        $atRisk = $n(['past_due', 'grace']);
        $churned = $n(['cancelled', 'churned', 'expired']);

        return ['total' => (int) $by->sum(), 'active' => $active, 'at_risk' => $atRisk, 'churned' => $churned,
            'resold' => $n(['resold'])];
    }

    /** @return list<array{icon:string,text:string}> */
    private function insights(array $m): array
    {
        $fmt = fn (int $minor) => ($m['currency'] === 'USD' ? '$' : '').number_format($minor / 100, 2).($m['currency'] === 'USD' ? '' : ' '.$m['currency']);
        $out = [];
        if ($m['transaction_count'] === 0) {
            return $out;
        }
        if ($m['revenue_saved_minor'] > 0) {
            $out[] = ['icon' => 'shield', 'text' => 'LuvuPay retained '.$fmt($m['revenue_saved_minor']).' that would otherwise have been lost to churn or chargebacks.'];
        }
        if ($m['subscribers']['at_risk'] > 0) {
            $out[] = ['icon' => 'clock', 'text' => $m['subscribers']['at_risk'].' subscriber(s) are in the grace period ('.$fmt($m['at_risk_minor']).' at stake). Pick a retention path before the window closes.'];
        }
        if ($m['churn_rate'] >= 25) {
            $out[] = ['icon' => 'alert', 'text' => 'Churn is '.$m['churn_rate'].'%. Consider a higher partial-refund offer or enabling Marketplace Resale.'];
        } elseif ($m['subscribers']['total'] > 0) {
            $out[] = ['icon' => 'check', 'text' => 'Churn is healthy at '.$m['churn_rate'].'% across '.$m['subscribers']['total'].' subscribers.'];
        }
        if ($m['charity_minor'] > 0) {
            $out[] = ['icon' => 'heart', 'text' => 'Subscribers donated '.$fmt($m['charity_minor']).' to charity through your project.'];
        }
        if ($m['resale_volume_minor'] > 0) {
            $out[] = ['icon' => 'refresh', 'text' => 'Marketplace resale moved '.$fmt($m['resale_volume_minor']).'. Original cycle charges stay with you; platform fee was '.$fmt($m['resale_fees_minor']).'.'];
        }
        $p = MerchantProject::current();
        if ($p->isSet()) {
            if (! $p->marketplaceResale) {
                $out[] = ['icon' => 'lightbulb', 'text' => 'Marketplace Resale is off for '.$p->name.'. Turn it on in Settings to recover value from forgotten subscriptions.'];
            }
            if (! $p->charitySplit) {
                $out[] = ['icon' => 'lightbulb', 'text' => 'Charity Split is off for '.$p->name.'. Turn it on in Settings to let users donate unused value.'];
            }
        }

        return array_slice($out, 0, 6);
    }
}
