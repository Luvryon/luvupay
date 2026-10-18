<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class UserTrustScore extends Model
{
    use HasUuids;
    protected $guarded = [];
    protected function casts(): array
    {
        return [
            'resale_eligible' => 'boolean',
            'second_chance_eligible' => 'boolean',
            'flags' => 'array',
        ];
    }
}
