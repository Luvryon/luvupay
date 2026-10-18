<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Merchant extends Model
{
    use HasUuids, Auditable;

    protected $guarded = [];
    protected $hidden = ['api_key_hash'];
    protected array $auditRedact = ['api_key_hash'];

    protected function casts(): array
    {
        return ['allowed_origins' => 'array', 'api_scopes' => 'array'];
    }

    public function auditMerchantId(): ?string
    {
        return $this->getKey();
    }

    /**
     * Mint an API key. Show `key` to the merchant ONCE; persist only prefix + hash.
     * (A 40-char random key has ~238 bits of entropy, so plain SHA-256 is sufficient: no slow KDF needed.)
     *
     * @return array{key:string,prefix:string,hash:string,publishable:string}
     */
    public static function issueApiKey(string $env = 'live'): array
    {
        $key = "lp_{$env}_".Str::random(40);

        return ['key' => $key, 'prefix' => substr($key, 0, 12), 'hash' => hash('sha256', $key), 'publishable' => 'lp_pub_'.Str::random(32)];
    }

    public static function findByApiKey(string $key): ?self
    {
        return static::query()->where('api_key_hash', hash('sha256', $key))->first();
    }

    public const SCOPES = [
        'subscriptions:write', 'second-chance:write', 'resale:read', 'resale:write', 'ai:read', 'export:read', 'snippet:read',
    ];

    /** Null scopes = unrestricted (legacy / default keys). Otherwise ANY of the asked scopes (or "*") must be present. */
    public function hasScope(string ...$required): bool
    {
        $scopes = $this->api_scopes;
        if ($scopes === null) {
            return true;
        }

        return in_array('*', $scopes, true) || array_intersect($required, $scopes) !== [];
    }

    public function webhookEndpoints() { return $this->hasMany(WebhookEndpoint::class); }
    public function subscriptions() { return $this->hasMany(Subscription::class); }
}
