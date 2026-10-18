<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'line_items' => 'array',
            'metadata' => 'array',
            'issued_at' => 'datetime',
            'paid_at' => 'datetime',
            'subtotal_minor' => 'integer',
            'tax_amount_minor' => 'integer',
            'total_minor' => 'integer',
        ];
    }

    public function merchant()
    {
        return $this->belongsTo(Merchant::class);
    }

    public function transaction()
    {
        return $this->belongsTo(Transaction::class);
    }
}
