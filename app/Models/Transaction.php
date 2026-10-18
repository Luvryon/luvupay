<?php

namespace App\Models;

use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Exceptions\IllegalTransitionException;
use App\Support\Money;
use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    use HasUuids, Auditable;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'type' => TransactionType::class,
            'status' => TransactionStatus::class,
            'amount_minor' => 'integer',
            'second_chance_expires_at' => 'datetime', 'second_chance_triggered_at' => 'datetime', 'processed_at' => 'datetime',
            'split_snapshot' => 'array', 'metadata' => 'array',
        ];
    }

    /**
     * Guarded status change. Same-status is a no-op (returns false); an illegal move throws.
     * Call inside a transaction on a row loaded with lockForUpdate() when concurrency matters.
     */
    public function transitionTo(TransactionStatus $to, array $attributes = []): bool
    {
        $from = $this->status;
        if ($from === $to) {
            return false;
        }
        if (! $from->canTransitionTo($to)) {
            throw new IllegalTransitionException("Transaction {$this->getKey()}: {$from->value} -> {$to->value} is not allowed.");
        }
        $this->update(['status' => $to] + $attributes);

        return true;
    }

    public function money(): Money { return Money::of($this->amount_minor, $this->currency); }
    public function merchant() { return $this->belongsTo(Merchant::class); }
    public function subscription() { return $this->belongsTo(Subscription::class); }
    public function parent() { return $this->belongsTo(self::class, 'parent_transaction_id'); }
    public function children() { return $this->hasMany(self::class, 'parent_transaction_id'); }
}
