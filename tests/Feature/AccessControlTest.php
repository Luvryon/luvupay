<?php

namespace Tests\Feature;

use App\Models\Merchant;
use App\Models\MerchantUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AccessControlTest extends TestCase
{
    use RefreshDatabase;

    private function merchant(?array $scopes = null): array
    {
        $k = Merchant::issueApiKey('live');
        $m = Merchant::create([
            'id' => (string) Str::uuid(), 'name' => 'Acme', 'email' => 'o@acme.test', 'api_key_prefix' => $k['prefix'],
            'api_key_hash' => $k['hash'], 'publishable_key' => $k['publishable'], 'allowed_origins' => [], 'is_active' => true, 'api_scopes' => $scopes,
        ]);

        return [$m, $k['key']];
    }

    private function user(Merchant $m, string $role): MerchantUser
    {
        $u = MerchantUser::registerOwner($m, ucfirst($role), "{$role}@acme.test", 'SecurePass1');
        $u->forceFill(['role' => $role, 'is_owner' => $role === 'owner', 'email_verified_at' => now()])->save();

        return $u;
    }

    public function test_api_key_without_scope_gets_403(): void
    {
        [, $key] = $this->merchant(['ai:read']);

        $this->withToken($key)->postJson('/api/v1/subscriptions', [], ['Idempotency-Key' => 'abcdefgh-1'])
            ->assertStatus(403)->assertJson(['error' => 'insufficient_scope']);
    }

    public function test_api_key_with_null_scopes_is_unrestricted(): void
    {
        [$m] = $this->merchant(null);
        $this->assertTrue($m->hasScope('anything:at-all'));
    }

    public function test_viewer_cannot_rotate_keys_but_can_open_analytics(): void
    {
        [$m] = $this->merchant();
        $viewer = $this->user($m, 'viewer');

        $this->actingAs($viewer, 'merchant_web')->post('/settings/rotate-key')->assertForbidden();
        $this->actingAs($viewer, 'merchant_web')->get('/analytics')->assertOk();
    }

    public function test_only_owner_can_deactivate(): void
    {
        [$m] = $this->merchant();
        $admin = $this->user($m, 'admin');

        $this->actingAs($admin, 'merchant_web')->post('/settings/deactivate')->assertForbidden();
    }

    public function test_totp_enrolment_login_challenge_and_recovery_code(): void
    {
        [$m] = $this->merchant();
        $u = $this->user($m, 'owner');
        $secret = $u->beginMfa();
        $codes = $u->fresh()->confirmMfa(\App\Services\Security\Totp::code($secret, \App\Services\Security\Totp::currentStep()));
        $this->assertCount(8, $codes);

        $this->post('/login', ['email' => $u->email, 'password' => 'SecurePass1'])->assertRedirect(route('mfa.challenge'));
        $this->assertGuest('merchant_web');                                   // password alone is not a session

        $this->post('/mfa/challenge', ['code' => '000000'])->assertSessionHasErrors('code');
        $this->post('/mfa/challenge', ['code' => $codes[0]])->assertRedirect();
        $this->assertAuthenticated('merchant_web');
        $this->assertFalse($u->fresh()->verifyMfa($codes[0]), 'recovery codes are single use');
    }
}
