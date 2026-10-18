<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/** Platform-wide (no merchant_id), so its audit rows land on the 'system' chain. */
class CharityWallet extends Model
{
    use HasUuids, Auditable;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'total_received_minor' => 'integer'];
    }
}
