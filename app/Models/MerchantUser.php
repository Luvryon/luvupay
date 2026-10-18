<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use App\Services\Security\Totp;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class MerchantUser extends Authenticatable
{
    use HasUuids, Notifiable, Auditable;

    protected $guarded = [];

    protected $hidden = ['password', 'remember_token', 'email_verification_code', 'mfa_secret', 'mfa_recovery_codes'];

    protected array $auditRedact = ['password', 'remember_token', 'email_verification_code', 'mfa_secret', 'mfa_recovery_codes', 'mfa_last_step'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'email_verification_expires_at' => 'datetime',
            'last_login_at' => 'datetime',
            'mfa_enabled_at' => 'datetime',
            'is_owner' => 'boolean',
            'is_active' => 'boolean',
            'password' => 'hashed', // uses config hashing.driver = argon2id
        ];
    }

    public function merchant()
    {
        return $this->belongsTo(Merchant::class);
    }

    public function auditMerchantId(): ?string
    {
        return $this->merchant_id;
    }

    public function hasVerifiedEmail(): bool
    {
        return $this->email_verified_at !== null;
    }

    public function issueEmailVerificationCode(): string
    {
        $code = (string) random_int(100000, 999999);
        $this->forceFill([
            'email_verification_code' => Hash::make($code),
            'email_verification_expires_at' => now()->addMinutes(30),
        ])->save();

        return $code;
    }

    public function verifyEmailCode(string $code): bool
    {
        if (! $this->email_verification_code || ! $this->email_verification_expires_at?->isFuture()) {
            return false;
        }
        if (! Hash::check($code, $this->email_verification_code)) {
            return false;
        }
        $this->forceFill([
            'email_verified_at' => now(),
            'email_verification_code' => null,
            'email_verification_expires_at' => null,
        ])->save();

        return true;
    }

    public static function registerOwner(Merchant $merchant, string $name, string $email, string $password): self
    {
        return static::create([
            'id' => (string) Str::uuid(),
            'merchant_id' => $merchant->id,
            'name' => $name,
            'email' => strtolower($email),
            'password' => $password,
            'is_owner' => true,
            'role' => 'owner',
            'is_active' => true,
        ]);
    }

    // ------------------------------------------------------------------ RBAC

    public const ROLES = ['owner', 'admin', 'finance', 'developer', 'viewer'];

    public function allows(string $permission): bool
    {
        $perms = config('luvupay.rbac.'.($this->role ?: 'viewer'), []);

        return in_array('*', $perms, true) || in_array($permission, $perms, true);
    }

    // ------------------------------------------------------------------ MFA (TOTP + recovery codes)

    public function hasMfa(): bool
    {
        return $this->mfa_enabled_at !== null && $this->mfa_secret !== null;
    }

    /** Roles that can move money or change credentials must use MFA when the platform enforces it. */
    public function mfaRequired(): bool
    {
        return in_array($this->role, ['owner', 'admin', 'finance'], true);
    }

    public function mfaSecret(): ?string
    {
        return $this->mfa_secret ? Crypt::decryptString($this->mfa_secret) : null;
    }

    /** Store a secret that is not active until confirmMfa() succeeds. */
    public function beginMfa(): string
    {
        $secret = Totp::generateSecret();
        $this->forceFill(['mfa_secret' => Crypt::encryptString($secret), 'mfa_enabled_at' => null, 'mfa_recovery_codes' => null, 'mfa_last_step' => null])->save();

        return $secret;
    }

    /** @return list<string>|null plaintext recovery codes (show once) or null when the code was wrong */
    public function confirmMfa(string $code): ?array
    {
        $secret = $this->mfaSecret();
        if (! $secret || $this->mfa_enabled_at !== null || ($step = Totp::verify($secret, $code)) === null) {
            return null;
        }
        $plain = [];
        $hashed = [];
        for ($i = 0; $i < 8; $i++) {
            $c = strtolower(Str::random(5).'-'.Str::random(5));
            $plain[] = $c;
            $hashed[] = hash('sha256', $c);
        }
        $this->forceFill([
            'mfa_enabled_at' => now(), 'mfa_last_step' => $step,
            'mfa_recovery_codes' => Crypt::encryptString(json_encode($hashed)),
        ])->save();

        return $plain;
    }

    /** TOTP (replay-protected) or a one-time recovery code. */
    public function verifyMfa(string $code): bool
    {
        if (! $this->hasMfa()) {
            return false;
        }
        $code = trim($code);
        if (preg_match('/^\d{3}\s?\d{3}$/', $code)) {
            $step = Totp::verify((string) $this->mfaSecret(), $code, $this->mfa_last_step);
            if ($step === null) {
                return false;
            }
            $this->forceFill(['mfa_last_step' => $step])->save();

            return true;
        }

        $codes = json_decode(Crypt::decryptString((string) $this->mfa_recovery_codes), true) ?: [];
        $h = hash('sha256', strtolower($code));
        foreach ($codes as $i => $stored) {
            if (hash_equals($stored, $h)) {
                unset($codes[$i]);
                $this->forceFill(['mfa_recovery_codes' => Crypt::encryptString(json_encode(array_values($codes)))])->save();

                return true;
            }
        }

        return false;
    }

    public function disableMfa(): void
    {
        $this->forceFill(['mfa_secret' => null, 'mfa_enabled_at' => null, 'mfa_recovery_codes' => null, 'mfa_last_step' => null])->save();
    }
}
