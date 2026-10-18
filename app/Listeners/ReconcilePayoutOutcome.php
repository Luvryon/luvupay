<?php

namespace App\Listeners;

use App\Enums\TransactionStatus;
use App\Events\PayPal\PayoutFailed;
use App\Events\PayPal\PayoutSucceeded;
use App\Models\Transaction;
use App\Services\Luvu\LedgerService;
use App\Support\AuditContext;
use Illuminate\Contracts\Queue\ShouldQueue;

/** PayPal payouts are async (PENDING first). The PAYOUTS-ITEM webhook settles our ledger row (sender_item_id = tx id). */
class ReconcilePayoutOutcome implements ShouldQueue
{
    public string $queue = 'financial';

    public function whenSucceeded(PayoutSucceeded $e): void
    {
        if ($tx = $this->find($e->webhook->payload)) {
            app(LedgerService::class)->completePayout($tx, data_get($e->webhook->payload, 'resource.payout_batch_id'));
        }
    }

    public function whenFailed(PayoutFailed $e): void
    {
        if ($tx = $this->find($e->webhook->payload)) {
            app(LedgerService::class)->failPayout($tx, $e->webhook->type.': '.(data_get($e->webhook->payload, 'resource.errors.message') ?? 'payout not delivered'));
        }
    }

    private function find(array $payload): ?Transaction
    {
        app(AuditContext::class)->actor('provider_webhook', 'paypal');
        $id = data_get($payload, 'resource.payout_item.sender_item_id');

        return $id ? Transaction::query()->whereKey($id)->whereIn('status', [TransactionStatus::Processing->value, TransactionStatus::Pending->value, TransactionStatus::Failed->value])->first() : null;
    }
}
