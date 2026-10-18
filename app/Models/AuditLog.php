<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use LogicException;

/** Read-only at the application layer; the DB triggers (Step 1) enforce it at the storage layer. */
class AuditLog extends Model
{
    public $timestamps = false;
    protected $guarded = [];

    protected function casts(): array
    {
        return ['old_values' => 'array', 'new_values' => 'array', 'created_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('audit_logs is append-only.'));
        static::deleting(fn () => throw new LogicException('audit_logs is append-only.'));
    }
}
