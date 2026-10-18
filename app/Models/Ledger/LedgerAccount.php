<?php

namespace App\Models\Ledger;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class LedgerAccount extends Model
{
    use HasUuids;

    protected $guarded = [];

    public function entries() { return $this->hasMany(LedgerEntry::class, 'account_id'); }
}
