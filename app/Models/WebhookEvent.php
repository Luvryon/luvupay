<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class WebhookEvent extends Model
{
    use HasUuids;

    public const RECEIVED = 'received';
    public const PROCESSING = 'processing';
    public const PROCESSED = 'processed';
    public const FAILED = 'failed';
    public const DEAD = 'dead';
    public const IGNORED = 'ignored';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['received_at' => 'datetime', 'processed_at' => 'datetime', 'next_retry_at' => 'datetime', 'attempts' => 'integer'];
    }
}
