<?php

namespace App\Services\Luvu;

use App\Http\Controllers\Demo\Concerns\DemoSupport;
use App\Services\Resale\ResaleSettlement;
use App\Support\MerchantProject;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * The missed-cancellation journey of the NEXT subscription charge.
 *
 * The subscriber's current period stays paid and stays in the merchant wallet, untouched. Only a separate ledger row,
 * the renewal charge (status `pending` before expiry, `held` once the subscription expired), walks through the
 * stages in config('luvupay.grace_stages'). Time is position relative to the expiry T of the paid period
 * (subscriptions.current_period_end); the sandbox moves T, so the sweeper, stage rules and resale price all follow.
 */
final class RenewalJourney
{
    use DemoSupport;

    public function __construct(private readonly GraceStagePlanner $planner, private readonly RenewalReminder $reminders, private readonly GraceDecisionService $decisions) {}

    /** Creates (or returns) the renewal charge that follows a paid charge. */
    public function ensureRenewal(string $merchantId, string $paidTxId): object
    {
        $this->scopedMerchant = $merchantId;

        return DB::transaction(function () use ($merchantId, $paidTxId) {
            $paid = DB::table('transactions')->where('merchant_id', $merchantId)->where('id', $paidTxId)->lockForUpdate()->first();
            abort_unless($paid, 404, 'Transaction not found');
            if ($paid->type !== 'charge' || ! in_array($paid->status, ['completed', 'settled'], true) || ! $paid->subscription_id) {
                throw new RuntimeException('Pick a paid subscription charge. The renewal journey starts from a subscriber who already paid once.');
            }
            $key = 'renewal-'.$paid->id;
            $existing = DB::table('transactions')->where('merchant_id', $merchantId)->where('idempotency_key', $key)->first();
            if ($existing) {
                return $existing;
            }
            $sub = DB::table('subscriptions')->where('id', $paid->subscription_id)->where('merchant_id', $merchantId)->first();
            abort_unless($sub, 404, 'Subscription not found');
            $pm = json_decode((string) ($paid->metadata ?? '{}'), true) ?: [];
            $now = now();
            $id = (string) Str::uuid();
            DB::table('transactions')->insert([
                'id' => $id, 'merchant_id' => $merchantId, 'subscription_id' => $paid->subscription_id, 'parent_transaction_id' => $paid->id,
                'type' => 'charge', 'status' => 'pending', 'idempotency_key' => $key, 'merchant_user_id' => $paid->merchant_user_id, 'email' => $paid->email,
                'amount_minor' => (int) ($sub->amount_minor ?: $paid->amount_minor), 'currency' => trim((string) ($sub->currency ?: $paid->currency)) ?: 'USD',
                'provider' => $paid->provider, 'provider_reference' => 'RENEWAL-'.strtoupper(Str::random(8)), 'trust_score' => $paid->trust_score, 'churn_risk' => $paid->churn_risk,
                'metadata' => json_encode([
                    'lifecycle' => 'UPCOMING RENEWAL', 'renewal_of' => $paid->id, 'display_name' => $pm['display_name'] ?? Str::before((string) $paid->email, '@'),
                    'product' => $pm['product'] ?? null, 'usage_pattern' => $pm['usage_pattern'] ?? null, 'renews_at' => $sub->current_period_end,
                ]),
                'created_at' => $now, 'updated_at' => $now,
            ]);
            $this->logWebhookEvent('luvupay', 'subscription.renewal_scheduled', ['transaction_id' => $id, 'renewal_of' => $paid->id]);

            return DB::table('transactions')->where('id', $id)->first();
        });
    }

    /**
     * Sandbox time travel for one renewal. $position is hours relative to expiry T (positive = before, negative = after).
     *
     * @return array{closed:bool,message:string,plan:?array<string,mixed>}
     */
    public function moveTo(string $merchantId, string $renewalId, int $position, MerchantProject $project): array
    {
        $this->scopedMerchant = $merchantId;
        if (! in_array($position, GraceStagePlanner::POSITIONS, true)) {
            throw new RuntimeException('Unknown position on the timeline.');
        }

        DB::transaction(function () use ($merchantId, $renewalId, $position) {
            $tx = DB::table('transactions')->where('merchant_id', $merchantId)->where('id', $renewalId)->lockForUpdate()->first();
            abort_unless($tx, 404, 'Renewal not found');
            $meta = json_decode((string) ($tx->metadata ?? '{}'), true) ?: [];
            if (empty($meta['renewal_of'])) {
                throw new RuntimeException('This is not a renewal charge.');
            }
            if (! in_array($tx->status, ['pending', 'held'], true) || ($meta['decision'] ?? '') === 'split_pay') {
                throw new RuntimeException('This renewal is already resolved ('.($meta['lifecycle'] ?? $tx->status).') and cannot be moved on the timeline.');
            }
            $sub = DB::table('subscriptions')->where('id', $tx->subscription_id)->lockForUpdate()->first();
            abort_unless($sub, 404, 'Subscription not found');

            $now = now();
            $expiry = $now->copy()->addHours($position);   // T
            $window = max(1, (int) config('luvupay.renewal.window_hours', 48));
            $deadline = $expiry->copy()->addHours($window);
            $before = $position > 0;

            if ($before && $tx->subscription_id) {
                ResaleSettlement::cancelOpenListings($tx->subscription_id);   // a listing from a later stage is withdrawn
            }
            unset($meta['resale']);
            if ($before) {
                unset($meta['decision'], $meta['grace']);
            } else {
                $meta['grace'] = ['expires_at' => $deadline->toIso8601String(), 'window_hours' => $window, 'opened_at' => $now->toIso8601String()];
                if (($meta['decision'] ?? '') === 'marketplace_resale') {
                    unset($meta['decision']);
                }
            }
            $meta['lifecycle'] = $before ? 'UPCOMING RENEWAL' : 'GRACE PERIOD';
            $meta['renews_at'] = $expiry->toIso8601String();
            $meta['simulated_position'] = ['hours' => $position, 'at' => $now->toIso8601String()];

            DB::table('subscriptions')->where('id', $sub->id)->update(['current_period_end' => $expiry, 'status' => $before ? 'active' : 'past_due', 'updated_at' => $now]);
            DB::table('transactions')->where('id', $tx->id)->update([
                'status' => $before ? 'pending' : 'held', 'second_chance_expires_at' => $deadline, 'metadata' => json_encode($meta), 'updated_at' => $now,
            ]);
            $this->logWebhookEvent('luvupay', 'subscription.timeline_moved', ['transaction_id' => $tx->id, 'position_hours' => $position, 'stage' => GraceStagePlanner::keyForHoursToExpiry((float) $position - 0.0001), 'simulated' => true]);
        });

        if ($position === -49) {
            $r = $this->decisions->decide($merchantId, $renewalId, 'expire', $project, 'timer');

            return ['closed' => true, 'message' => $r['message'], 'plan' => null];
        }

        return ['closed' => false, 'message' => 'Timeline moved', 'plan' => $this->enterStage($merchantId, $renewalId, $project)];
    }

    /** Plans the current stage and sends its reminder (once). Returns the plan including the stored reminder. */
    public function enterStage(string $merchantId, string $renewalId, MerchantProject $project): ?array
    {
        $this->scopedMerchant = $merchantId;
        $tx = DB::table('transactions')->where('merchant_id', $merchantId)->where('id', $renewalId)->first();
        if (! $tx) {
            return null;
        }
        $sub = $tx->subscription_id ? DB::table('subscriptions')->where('id', $tx->subscription_id)->first() : null;
        $name = (json_decode((string) $tx->metadata, true) ?: [])['display_name'] ?? null;
        $plan = $this->planner->plan($tx, $sub, $project, $name);
        if ($plan['is_reminder']) {
            $this->reminders->deliver($merchantId, $tx, $sub, $plan);
            $tx = DB::table('transactions')->where('id', $renewalId)->first();
            $plan = $this->planner->plan($tx, $sub, $project, $name);
        }

        return $plan;
    }

    /** Scheduler: real time passing. Pending renewals whose period ended become held; due reminders go out. */
    public function tick(): int
    {
        $n = 0;
        DB::table('transactions')->where('type', 'charge')->whereIn('status', ['pending', 'held'])->where('idempotency_key', 'like', 'renewal-%')
            ->orderBy('created_at')->limit(200)->get()->each(function ($r) use (&$n) {
                try {
                    $m = (string) $r->merchant_id;
                    $sub = $r->subscription_id ? DB::table('subscriptions')->where('id', $r->subscription_id)->first() : null;
                    if (! $sub || ! $sub->current_period_end) {
                        return;
                    }
                    if ($r->status === 'pending' && Carbon::parse($sub->current_period_end)->lte(now())) {
                        $meta = json_decode((string) $r->metadata, true) ?: [];
                        $window = max(1, (int) config('luvupay.renewal.window_hours', 48));
                        $meta['lifecycle'] = 'GRACE PERIOD';
                        $meta['grace'] = ['expires_at' => Carbon::parse($sub->current_period_end)->addHours($window)->toIso8601String(), 'window_hours' => $window, 'opened_at' => now()->toIso8601String()];
                        DB::table('transactions')->where('id', $r->id)->where('status', 'pending')->update(['status' => 'held', 'second_chance_expires_at' => Carbon::parse($sub->current_period_end)->addHours($window), 'metadata' => json_encode($meta), 'updated_at' => now()]);
                        DB::table('subscriptions')->where('id', $sub->id)->update(['status' => 'past_due', 'updated_at' => now()]);
                    }
                    $this->enterStage($m, (string) $r->id, MerchantProject::forMerchant($m));
                    $n++;
                } catch (\Throwable $e) {
                    report($e);
                }
            });

        return $n;
    }
}
