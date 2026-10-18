<?php

namespace Tests\Feature;

use App\Models\Merchant;
use App\Models\MerchantUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class MerchantAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_merchant_can_register_and_is_redirected_to_verification(): void
    {
        $res = $this->post('/register', [
            'company_name' => 'Acme SaaS',
            'name' => 'Owner',
            'email' => 'owner@acme.test',
            'password' => 'SecurePass1',
            'password_confirmation' => 'SecurePass1',
        ]);

        $res->assertRedirect(route('verification.notice'));
        $this->assertDatabaseHas('merchants', ['email' => 'owner@acme.test']);
        $this->assertDatabaseHas('merchant_users', ['email' => 'owner@acme.test']);
    }

    public function test_unverified_user_cannot_open_dashboard(): void
    {
        $merchant = Merchant::create([
            'id' => (string) Str::uuid(),
            'name' => 'X',
            'email' => 'x@test.com',
            'api_key_hash' => hash('sha256', 'x'),
            'is_active' => true,
        ]);
        $user = MerchantUser::registerOwner($merchant, 'X', 'x@test.com', 'SecurePass1');

        $this->actingAs($user, 'merchant_web')
            ->get('/dashboard')
            ->assertRedirect(route('verification.notice'));
    }
}
