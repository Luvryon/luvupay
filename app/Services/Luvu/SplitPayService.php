<?php

namespace App\Services\Luvu;

use App\Http\Controllers\Demo\Concerns\DemoSupport;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Split pay.
 *
 * A subscriber who cannot pay the whole charge pays a down payment NOW and the remainder in instalments.
 * Money model (all integers, minor units; the parts always sum to the charge exactly):
 *
 *   charge (held, lifecycle "SPLIT PAY")     <- the receivable; NOT in the merchant wallet while the plan is open
 *   installment rows (completed)             <- cash actually collected; counted in the wallet as they arrive
 *
 *   plan completed -> charge becomes completed (full amount) and the installment rows become "superseded"
 *                     (so nothing is counted twice) and the subscription stays active.
 *   plan defaulted -> the charge is cancelled, the subscription ends, the installments already collected stay
 *                     with the merchant, and the unpaid remainder is written off.
 *
 * Every state change runs under a row lock and a conditional status check, and every installment carries a
 * unique idempotency key (plan-{id}-{n}), so double clicks, retries and the scheduler can never collect twice.
 */
final class SplitPayService
{
    use DemoSupport;

    /**
     * @return array{parts:int,amounts:list<int>,due:list<string>,total_minor:int}
     */
    public function quote(int $totalMinor, ?Carbon $from = null): array
    {
        $cfg = (array) config('luvupay.split_pay', []);
        $parts = max(2, min(4, (int) ($cfg['parts'] ?? 2)));
        $min = max(2 * $parts, (int) ($cfg['min_total_minor'] ?? 200));
        if ($totalMinor < $min) {
            throw new RuntimeException('This amount is too small to split into instalments.');
        }
        $down = max(1000, min(9000, (int) ($cfg['down_payment_bps'] ?? 5000)));
        $interval = max(1, (int) ($cfg['interval_days'] ?? 7));

        $first = (int) round($totalMinor * $down / 10000);
        $first = max(1, min($totalMinor - ($parts - 1), $first));
        $rest = $totalMinor - $first;
        $n = $parts - 1;
        $base = intdiv($rest, $n);
        $amounts = [$first];
        for ($i = 0; $i < $n; $i++) {
            $amounts[] = $base + ($i === $n - 1 ? $rest - $base * $n : 0);   // remainder goes to the last part
        }
        if (array_sum($amounts) !== $totalMinor || min($amounts) < 1) {
            throw new RuntimeException('Split calculation failed.');   // can never happen; guards the invariant
        }

        $from ??= now();
        $due = [];
        foreach ($amounts as $i => $_) {
            $due[] = $from->copy()->addDays($interval * $i)->toIso8601String();
        }

        return ['parts' => $parts, 'amounts' => $amounts, 'due' => $due, 'total_minor' => $totalMinor];
    }

    /**
     * Opens the plan for a charge and collects the down payment. Call inside the caller's transaction with the
     * charge row already locked. Returns the plan view.
     *
     * @return array<string,mixed>
     */
    public function open(string $merchantId, object $charge, ?Carbon $at = null): array
    {
        $this->scopedMerchant = $merchantId;
        $at ??= now();
        if (DB::table('payment_plans')->where('charge_id', $charge->id)->exists()) {
            throw new RuntimeException('This charge already has a payment plan.');
        }
        $q = $this->quote((int) $charge->amount_minor, $at);

        $installments = [];
        foreach ($q['amounts'] as $i => $amt) {
            $installments[] = ['n' => $i + 1, 'amount_minor' => $amt, 'due_at' => $q['due'][$i], 'status' => 'scheduled', 'paid_at' => null, 'transaction_id' => null];
        }
        $planId = (string) Str::uuid();
        DB::table('payment_plans')->insert([
            'id' => $planId, 'merchant_id' => $merchantId, 'subscription_id' => $charge->subscription_id, 'charge_id' => $charge->id,
            'email' => $charge->email, 'currency' => trim((string) $charge->currency) ?: 'USD', 'total_minor' => $q['total_minor'], 'paid_minor' => 0,
            'installments' => json_encode($installments), 'status' => 'active', 'next_due_at' => $q['due'][0],
            'created_at' => $at, 'updated_at' => $at,
        ]);

        $this->logWebhookEvent('luvupay', 'split.plan_created', ['transaction_id' => $charge->id, 'plan_id' => $planId, 'parts' => $q['parts'], 'amounts_minor' => $q['amounts']]);

        return $this->payInstallment($merchantId, $planId, 1, 'down_payment', $at);
    }

    /**
     * Collects the next instalment (they are strictly in order). Idempotent per (plan, n).
     *
     * @return array<string,mixed> plan view (+ 'completed' bool, 'collected_minor')
     */
    public function payInstallment(string $merchantId, string $planId, ?int $n = null, string $source = 'manual', ?Carbon $at = null): array
    {
        $this->scopedMerchant = $merchantId;

        return DB::transaction(function () use ($merchantId, $planId, $n, $source, $at) {
            $now = $at ?? now();
            $plan = DB::table('payment_plans')->where('id', $planId)->where('merchant_id', $merchantId)->lockForUpdate()->first();
            if (! $plan) {
                abort(404, 'Payment plan not found.');
            }
            if ($plan->status !== 'active') {
                throw new RuntimeException('This payment plan is '.$plan->status.'.');
            }
            $inst = json_decode((string) $plan->installments, true) ?: [];
            $idx = null;
            foreach ($inst as $i => $row) {
                if (($row['status'] ?? '') !== 'paid') {
                    $idx = $i;
                    break;
                }
            }
            if ($idx === null) {
                throw new RuntimeException('Nothing left to collect on this plan.');
            }
            if ($n !== null && (int) $inst[$idx]['n'] !== $n) {
                throw new RuntimeException('Instalments must be paid in order (next is #'.$inst[$idx]['n'].').');
            }
            $charge = DB::table('transactions')->where('id', $plan->charge_id)->where('merchant_id', $merchantId)->lockForUpdate()->first();
            if (! $charge) {
                abort(404, 'Charge not found.');
            }

            $k = (int) $inst[$idx]['n'];
            $amount = (int) $inst[$idx]['amount_minor'];
            $txId = (string) Str::uuid();
            $driver = (string) config('luvupay.payment.driver', 'mock');
            DB::table('transactions')->insert([
                'id' => $txId, 'merchant_id' => $merchantId, 'subscription_id' => $plan->subscription_id, 'parent_transaction_id' => $plan->charge_id,
                'type' => 'installment', 'status' => 'completed', 'idempotency_key' => 'plan-'.$planId.'-'.$k,
                'merchant_user_id' => $charge->merchant_user_id, 'email' => $plan->email, 'amount_minor' => $amount, 'currency' => $plan->currency,
                'provider' => $driver === 'paypal' ? 'paypal' : 'mock', 'provider_reference' => 'INST-'.strtoupper(Str::random(10)),
                'processed_at' => $now,
                'metadata' => json_encode(['lifecycle' => 'SPLIT PAY', 'plan_id' => $planId, 'installment' => $k, 'of' => count($inst), 'source' => $source, 'simulated' => true]),
                'created_at' => $now, 'updated_at' => $now,
            ]);

            $inst[$idx]['status'] = 'paid';
            $inst[$idx]['paid_at'] = $now->toIso8601String();
            $inst[$idx]['transaction_id'] = $txId;
            $paid = (int) $plan->paid_minor + $amount;
            $nextDue = null;
            foreach ($inst as $row) {
                if (($row['status'] ?? '') !== 'paid') {
                    $nextDue = $row['due_at'];
                    break;
                }
            }
            $completed = $nextDue === null;
            if ($completed && $paid !== (int) $plan->total_minor) {
                throw new RuntimeException('Plan total mismatch; refusing to complete.');   // invariant
            }

            DB::table('payment_plans')->where('id', $planId)->update([
                'installments' => json_encode($inst), 'paid_minor' => $paid, 'next_due_at' => $nextDue,
                'status' => $completed ? 'completed' : 'active', 'completed_at' => $completed ? $now : null, 'updated_at' => $now,
            ]);

            $meta = json_decode((string) ($charge->metadata ?? '{}'), true) ?: [];
            $meta['split'] = ['plan_id' => $planId, 'parts' => count($inst), 'total_minor' => (int) $plan->total_minor, 'paid_minor' => $paid, 'next_due_at' => $nextDue, 'status' => $completed ? 'completed' : 'active'];
            if ($completed) {
                $meta['lifecycle'] = 'ACTIVE';
                $meta['decision'] = 'split_paid';
                unset($meta['grace']);
                DB::table('transactions')->where('id', $charge->id)->update(['status' => 'completed', 'second_chance_expires_at' => null, 'metadata' => json_encode($meta), 'updated_at' => $now]);
                // The charge now carries the full amount: retire the instalment rows so nothing is counted twice.
                DB::table('transactions')->where('parent_transaction_id', $charge->id)->where('type', 'installment')->where('status', 'completed')->update(['status' => 'superseded', 'updated_at' => $now]);
                if ($plan->subscription_id) {
                    DB::table('subscriptions')->where('id', $plan->subscription_id)->update(['status' => 'active', 'updated_at' => $now]);
                }
                $this->logWebhookEvent('luvupay', 'split.completed', ['plan_id' => $planId, 'transaction_id' => $charge->id, 'total_minor' => (int) $plan->total_minor]);
            } else {
                $meta['lifecycle'] = 'SPLIT PAY';
                $meta['decision'] = 'split_pay';
                DB::table('transactions')->where('id', $charge->id)->update(['status' => 'held', 'second_chance_expires_at' => null, 'metadata' => json_encode($meta), 'updated_at' => $now]);
                if ($plan->subscription_id) {
                    DB::table('subscriptions')->where('id', $plan->subscription_id)->update(['status' => 'active', 'updated_at' => $now]);
                }
            }
            $this->logWebhookEvent('luvupay', 'split.installment_paid', ['plan_id' => $planId, 'installment' => $k, 'amount_minor' => $amount, 'source' => $source]);

            return $this->view((object) array_merge((array) $plan, ['installments' => json_encode($inst), 'paid_minor' => $paid, 'status' => $completed ? 'completed' : 'active', 'next_due_at' => $nextDue])) + ['completed' => $completed, 'collected_minor' => $amount, 'transaction_id' => $charge->id];
        });
    }

    /** The subscriber stopped paying: end access, keep what was collected, write off the rest. */
    public function defaultPlan(string $merchantId, string $planId, string $reason = 'missed_installment'): bool
    {
        $this->scopedMerchant = $merchantId;

        return DB::transaction(function () use ($merchantId, $planId, $reason) {
            $now = now();
            $plan = DB::table('payment_plans')->where('id', $planId)->where('merchant_id', $merchantId)->lockForUpdate()->first();
            if (! $plan || $plan->status !== 'active') {
                return false;
            }
            DB::table('payment_plans')->where('id', $planId)->update(['status' => 'defaulted', 'defaulted_at' => $now, 'next_due_at' => null, 'updated_at' => $now]);
            $charge = DB::table('transactions')->where('id', $plan->charge_id)->lockForUpdate()->first();
            if ($charge) {
                $meta = json_decode((string) ($charge->metadata ?? '{}'), true) ?: [];
                $meta['lifecycle'] = 'CHURNED';
                $meta['decision'] = 'split_defaulted';
                $meta['split'] = ['plan_id' => $planId, 'parts' => count(json_decode((string) $plan->installments, true) ?: []), 'total_minor' => (int) $plan->total_minor, 'paid_minor' => (int) $plan->paid_minor, 'next_due_at' => null, 'status' => 'defaulted', 'written_off_minor' => (int) $plan->total_minor - (int) $plan->paid_minor, 'reason' => $reason];
                DB::table('transactions')->where('id', $charge->id)->update(['status' => 'cancelled', 'metadata' => json_encode($meta), 'updated_at' => $now]);
            }
            if ($plan->subscription_id) {
                DB::table('subscriptions')->where('id', $plan->subscription_id)->update(['status' => 'expired', 'updated_at' => $now]);
            }
            $this->logWebhookEvent('luvupay', 'split.defaulted', ['plan_id' => $planId, 'paid_minor' => (int) $plan->paid_minor, 'written_off_minor' => (int) $plan->total_minor - (int) $plan->paid_minor, 'reason' => $reason]);

            return true;
        });
    }

    /**
     * Scheduler entry point. Mock driver: auto-debit every instalment that is due. PayPal driver: money cannot be
     * pulled without the buyer, so overdue plans are flagged and default after `default_after_days`.
     *
     * @return array{collected:int,defaulted:int,overdue:int}
     */
    public function sweep(): array
    {
        $out = ['collected' => 0, 'defaulted' => 0, 'overdue' => 0];
        $autoDebit = (string) config('luvupay.payment.driver', 'mock') !== 'paypal';
        $grace = max(0, (int) config('luvupay.split_pay.default_after_days', 3));

        DB::table('payment_plans')->where('status', 'active')->where('next_due_at', '<=', now())->orderBy('next_due_at')->limit(200)
            ->get(['id', 'merchant_id', 'next_due_at'])->each(function ($p) use (&$out, $autoDebit, $grace) {
                try {
                    if ($autoDebit) {
                        $this->payInstallment((string) $p->merchant_id, (string) $p->id, null, 'auto_debit');
                        $out['collected']++;

                        return;
                    }
                    if (Carbon::parse($p->next_due_at)->addDays($grace)->isPast()) {
                        $out['defaulted'] += $this->defaultPlan((string) $p->merchant_id, (string) $p->id) ? 1 : 0;
                    } else {
                        $out['overdue']++;
                    }
                } catch (\Throwable $e) {
                    report($e);
                }
            });

        return $out;
    }

    /** @return array<string,mixed> */
    public function view(object $plan): array
    {
        $inst = json_decode((string) $plan->installments, true) ?: [];
        $now = now();
        foreach ($inst as &$row) {
            if (($row['status'] ?? '') !== 'paid') {
                $row['status'] = Carbon::parse($row['due_at'])->lt($now) ? 'overdue' : 'scheduled';
            }
        }
        unset($row);

        return [
            'plan_id' => $plan->id, 'status' => $plan->status, 'currency' => trim((string) $plan->currency),
            'total_minor' => (int) $plan->total_minor, 'paid_minor' => (int) $plan->paid_minor,
            'remaining_minor' => max(0, (int) $plan->total_minor - (int) $plan->paid_minor),
            'next_due_at' => $plan->next_due_at ? Carbon::parse($plan->next_due_at)->toIso8601String() : null,
            'installments' => $inst,
        ];
    }

    /** @return array<string,mixed>|null */
    public function forCharge(string $merchantId, string $chargeId): ?array
    {
        $p = DB::table('payment_plans')->where('merchant_id', $merchantId)->where('charge_id', $chargeId)->first();

        return $p ? $this->view($p) : null;
    }
}
