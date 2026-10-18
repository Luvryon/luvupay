<?php

namespace App\Services\Luvu;

use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Models\CharityWallet;
use App\Models\Transaction;
use App\Services\Ledger\LedgerPoster;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Single place where a payout becomes Completed / Failed, shared by jobs and webhook reconciliation. */
class LedgerService
{
    public function __construct(private readonly LedgerPoster $poster) {}

    public function completePayout(Transaction $tx, ?string $providerRef = null): void
    {
        DB::transaction(function () use ($tx, $providerRef) {
            $t = Transaction::query()->lockForUpdate()->findOrFail($tx->getKey());
            if ($t->status === TransactionStatus::Completed) {
                return;                                         // exactly-once crediting
            }
            $t->transitionTo(TransactionStatus::Completed, ['processed_at' => now(), 'failure_reason' => null]
                + ($providerRef ? ['provider_reference' => $providerRef] : []));

            $this->poster->payoutCompleted($t);

            if ($t->type === TransactionType::CharityPayout && $t->charity_wallet_id) {
                $w = CharityWallet::query()->lockForUpdate()->find($t->charity_wallet_id);
                $w?->update(['total_received_minor' => $w->total_received_minor + $t->amount_minor]);
            }
        });
    }

    public function failPayout(Transaction $tx, string $reason): void
    {
        DB::transaction(function () use ($tx, $reason) {
            $t = Transaction::query()->lockForUpdate()->findOrFail($tx->getKey());
            if ($t->status === TransactionStatus::Completed) {
                return;                                         // never downgrade a payout that already succeeded
            }
            $t->transitionTo(TransactionStatus::Failed, ['failure_reason' => Str::limit($reason, 500)]);
        });
    }
}
