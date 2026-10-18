<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class WebhookDelivery extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'next_retry_at' => 'datetime',
            'delivered_at' => 'datetime',
            'response_code' => 'integer',
            'attempts' => 'integer',
        ];
    }

    public function merchant()
    {
        return $this->belongsTo(Merchant::class);
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed' || ($this->response_code !== null && $this->response_code >= 400);
    }
}
