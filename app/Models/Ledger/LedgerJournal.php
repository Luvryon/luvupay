<?php

namespace App\Models\Ledger;

use App\Exceptions\LedgerException;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/** Immutable once written. */
class LedgerJournal extends Model
{
    use HasUuids;

    public $timestamps = false;
    protected $guarded = [];

    protected function casts(): array
    {
        return ['metadata' => 'array', 'posted_at' => 'datetime', 'created_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LedgerException('Ledger journals are immutable.'));
        static::deleting(fn () => throw new LedgerException('Ledger journals cannot be deleted.'));
    }

    public function entries() { return $this->hasMany(LedgerEntry::class, 'journal_id'); }
}
