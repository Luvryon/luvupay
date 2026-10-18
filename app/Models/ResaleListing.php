<?php

namespace App\Models;

use App\Enums\EntitlementStatus;
use App\Enums\ListingStatus;
use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class ResaleListing extends Model
{
    use HasUuids, Auditable;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'status' => ListingStatus::class,
            'entitlement_status' => EntitlementStatus::class,
            'asking_price_minor' => 'integer',
            'access_expires_at' => 'datetime', 'listed_at' => 'datetime', 'reserved_until' => 'datetime', 'sold_at' => 'datetime',
            'entitlement_sent_at' => 'datetime', 'entitlement_acknowledged_at' => 'datetime',
        ];
    }

    public function merchant() { return $this->belongsTo(Merchant::class); }
    public function subscription() { return $this->belongsTo(Subscription::class); }
}
