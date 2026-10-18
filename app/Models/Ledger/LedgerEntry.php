<?php

namespace App\Models\Ledger;

use App\Exceptions\LedgerException;
use Illuminate\Database\Eloquent\Model;

/** Immutable once written. Corrections are made with a new, reversing journal. */
class LedgerEntry extends Model
{
    public $timestamps = false;
    protected $guarded = [];

    protected function casts(): array
    {
        return ['amount_minor' => 'integer', 'created_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LedgerException('Ledger entries are immutable.'));
        static::deleting(fn () => throw new LedgerException('Ledger entries cannot be deleted.'));
    }

    public function journal() { return $this->belongsTo(LedgerJournal::class, 'journal_id'); }
    public function account() { return $this->belongsTo(LedgerAccount::class, 'account_id'); }
}
