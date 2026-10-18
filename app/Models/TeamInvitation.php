<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class TeamInvitation extends Model
{
    use HasUuids;

    protected $guarded = [];
    protected $hidden = ['token_hash'];

    protected function casts(): array
    {
        return ['expires_at' => 'datetime', 'accepted_at' => 'datetime'];
    }

    public function merchant() { return $this->belongsTo(Merchant::class); }

    public function isUsable(): bool
    {
        return $this->accepted_at === null && $this->expires_at->isFuture();
    }

    public static function findByToken(string $token): ?self
    {
        return static::query()->where('token_hash', hash('sha256', $token))->first();
    }
}
