<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class PaypalPayoutRecipient extends Model
{
    use HasUuids, Auditable;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'requested_at' => 'datetime',
            'activates_at' => 'datetime',
            'activated_at' => 'datetime',
        ];
    }

    public function merchant()
    {
        return $this->belongsTo(Merchant::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}
