<?php

namespace App\Models;

use App\Enums\SubscriptionStatus;
use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Subscription extends Model
{
    use HasUuids, Auditable;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'status' => SubscriptionStatus::class,
            'amount_minor' => 'integer',
            'current_period_start' => 'datetime', 'current_period_end' => 'datetime', 'next_billing_at' => 'datetime',
            'nudge_sent_at' => 'datetime', 'paused_by_nudge_at' => 'datetime', 'nudge_confirmed_at' => 'datetime', 'cancelled_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function merchant() { return $this->belongsTo(Merchant::class); }
    public function transactions() { return $this->hasMany(Transaction::class); }
}
