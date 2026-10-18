<?php

namespace App\Console\Commands;

use App\Contracts\Payment\PaymentProviderInterface;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Models\Ledger\LedgerJournal;
use App\Models\Transaction;
use App\Services\Ledger\LedgerPoster;
use App\Services\Luvu\LedgerService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Daily reconciliation. Every finding becomes a row in reconciliation_exceptions (open / auto_resolved).
 *
 *  1. Internal integrity   negative amounts, split children that do not add up to the charge
 *  2. Ledger integrity     trial balance (debit = credit per currency), money rows that have no journal
 *  3. Provider sync        payouts stuck in `processing` are checked against PayPal and settled/failed
 *                          (webhooks in sandbox are often late or missing: this is the safety net)
 *
 * Exit code is non-zero when anything needs a human, so schedulers / CI alert on it.
 */
class ReconciliationCommand extends Command
{
    protected $signature = 'luvi:reconcile {--stuck-minutes=15}';
    protected $description = 'Reconcile internal ledger and provider state; record exceptions';

    private string $runId;
    private int $checked = 0;
    private int $open = 0;
    private int $autoResolved = 0;

    public function handle(LedgerPoster $poster, LedgerService $ledger, PaymentProviderInterface $provider): int
    {
        $this->runId = (string) Str::uuid();
        DB::table('reconciliation_runs')->insert([
            'id' => $this->runId, 'provider' => config('luvupay.payment.driver', 'mock'),
            'started_at' => now(), 'status' => 'running', 'created_at' => now(), 'updated_at' => now(),
        ]);

        try {
            $this->internalIntegrity();
            $this->ledgerIntegrity($poster);
            $this->providerSync($ledger, $provider);
        } catch (Throwable $e) {
            DB::table('reconciliation_runs')->where('id', $this->runId)->update(['status' => 'failed', 'finished_at' => now(), 'report' => json_encode(['error' => $e->getMessage()]), 'updated_at' => now()]);
            Log::error('luvi.reconciliation.failed', ['error' => $e->getMessage()]);
            throw $e;
        }

        DB::table('reconciliation_runs')->where('id', $this->runId)->update([
            'finished_at' => now(), 'status' => $this->open ? 'discrepancy' : 'ok', 'checked' => $this->checked,
            'mismatches' => $this->open,
            'report' => json_encode(['auto_resolved' => $this->autoResolved, 'driver' => config('luvupay.payment.driver')]),
            'updated_at' => now(),
        ]);

        if ($this->open) {
            Log::warning('luvi.reconciliation.discrepancy', ['checked' => $this->checked, 'open' => $this->open, 'run' => $this->runId]);
            $this->warn("Open exceptions: {$this->open} (auto-resolved {$this->autoResolved}) of {$this->checked} checked. Run {$this->runId}");

            return self::FAILURE;
        }
        $this->info("OK checked={$this->checked} auto_resolved={$this->autoResolved}");

        return self::SUCCESS;
    }

    private function internalIntegrity(): void
    {
        Transaction::query()
            ->whereIn('type', [TransactionType::Charge->value, TransactionType::Refund->value, TransactionType::CharityPayout->value, TransactionType::ResaleSale->value, TransactionType::ResalePayout->value])
            ->orderByDesc('created_at')->limit(8000)->get()
            ->each(function (Transaction $tx) {
                $this->checked++;
                if ($tx->amount_minor < 0) {
                    $this->raise($tx->id, 'negative_amount');
                }
                if ($tx->status === TransactionStatus::Completed
                    && in_array($tx->type, [TransactionType::Refund, TransactionType::CharityPayout, TransactionType::ResalePayout], true)
                    && empty($tx->provider_reference) && config('luvupay.payment.driver') === 'paypal') {
                    $this->raise($tx->id, 'missing_provider_reference');
                }
                if ($tx->type === TransactionType::Charge && $tx->status === TransactionStatus::Completed && $tx->second_chance_triggered_at) {
                    $sum = (int) Transaction::query()->where('parent_transaction_id', $tx->id)
                        ->whereIn('type', [TransactionType::Refund->value, TransactionType::CharityPayout->value, TransactionType::MerchantRetention->value])->sum('amount_minor');
                    $fee = (int) data_get($tx->split_snapshot, 'amounts_minor.luvi_fee', 0);
                    if ($sum + $fee !== $tx->amount_minor) {
                        $this->raise($tx->id, 'split_sum_mismatch', ['children' => $sum, 'fee' => $fee, 'charge' => $tx->amount_minor]);
                    }
                }
            });
    }

    private function ledgerIntegrity(LedgerPoster $poster): void
    {
        foreach ($poster->trialBalance() as $currency => $t) {
            $this->checked++;
            if (! $t['balanced']) {
                $this->raise(null, 'ledger_unbalanced', ['currency' => $currency] + $t);
            }
        }

        // Money that moved after the ledger went live must have a journal. (Older rows predate it: skipped.)
        $since = LedgerJournal::query()->min('posted_at');
        if (! $since) {
            return;
        }
        Transaction::query()->where('created_at', '>=', $since)->where('status', TransactionStatus::Completed->value)
            ->whereIn('type', [TransactionType::Refund->value, TransactionType::CharityPayout->value, TransactionType::ResalePayout->value])
            ->limit(5000)->get()->each(function (Transaction $tx) {
                $this->checked++;
                if (! LedgerJournal::query()->where('reference', "payout:{$tx->id}")->exists()) {
                    $this->raise($tx->id, 'missing_journal', ['expected' => "payout:{$tx->id}"]);
                }
            });
    }

    private function providerSync(LedgerService $ledger, PaymentProviderInterface $provider): void
    {
        $cutoff = now()->subMinutes((int) $this->option('stuck-minutes'));
        Transaction::query()
            ->whereIn('type', [TransactionType::CharityPayout->value, TransactionType::ResalePayout->value])
            ->where('status', TransactionStatus::Processing->value)->whereNotNull('provider_reference')
            ->where('updated_at', '<', $cutoff)->limit(200)->get()
            ->each(function (Transaction $tx) use ($ledger, $provider) {
                $this->checked++;
                try {
                    $r = $provider->getPayoutStatus($tx->provider_reference);
                } catch (Throwable $e) {
                    $this->raise($tx->id, 'provider_lookup_failed', ['error' => Str::limit($e->getMessage(), 300)]);

                    return;
                }
                if (in_array($r->status, ['SUCCESS', 'COMPLETED'], true)) {
                    $ledger->completePayout($tx, $tx->provider_reference);
                    $this->raise($tx->id, 'payout_settled_by_reconciliation', ['provider_status' => $r->status], autoResolved: true);
                } elseif (in_array($r->status, ['DENIED', 'CANCELED', 'CANCELLED', 'FAILED'], true)) {
                    $ledger->failPayout($tx, "provider reports {$r->status}");
                    $this->raise($tx->id, 'payout_failed_by_reconciliation', ['provider_status' => $r->status], autoResolved: true);
                }
                // PENDING / PROCESSING: still in flight, leave it for the next run.
            });
    }

    private function raise(?string $transactionId, string $issue, array $detail = [], bool $autoResolved = false): void
    {
        $autoResolved ? $this->autoResolved++ : $this->open++;
        DB::table('reconciliation_exceptions')->insert([
            'id' => (string) Str::uuid(), 'run_id' => $this->runId, 'transaction_id' => $transactionId, 'issue' => $issue,
            'detail' => $detail ? json_encode($detail) : null, 'status' => $autoResolved ? 'auto_resolved' : 'open',
            'resolved_at' => $autoResolved ? now() : null, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }
}
