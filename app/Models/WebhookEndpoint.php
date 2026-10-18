<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class WebhookEndpoint extends Model
{
    use HasUuids, Auditable;

    protected $guarded = [];
    protected $hidden = ['secret', 'previous_secret'];
    protected array $auditRedact = ['secret', 'previous_secret'];

    protected function casts(): array
    {
        return [
            'secret' => 'encrypted',
            'previous_secret' => 'encrypted',
            'previous_secret_expires_at' => 'datetime',
            'events' => 'array',
            'is_active' => 'boolean',
            'last_success_at' => 'datetime',
            'last_failure_at' => 'datetime',
        ];
    }

    /** Current secret first, then the previous one while its rotation grace period lasts. */
    public function activeSecrets(): array
    {
        $secrets = [$this->secret];
        if ($this->previous_secret && $this->previous_secret_expires_at?->isFuture()) {
            $secrets[] = $this->previous_secret;
        }

        return $secrets;
    }

    public function merchant() { return $this->belongsTo(Merchant::class); }
}
