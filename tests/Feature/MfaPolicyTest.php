<?php

namespace Tests\Feature;

use App\Models\MerchantUser;
use PHPUnit\Framework\TestCase;

class MfaPolicyTest extends TestCase
{
    public function test_money_roles_require_mfa(): void
    {
        $user = new MerchantUser(['role' => 'owner']);
        $this->assertTrue($user->mfaRequired());
        $user->role = 'finance';
        $this->assertTrue($user->mfaRequired());
        $user->role = 'viewer';
        $this->assertFalse($user->mfaRequired());
    }

    public function test_has_mfa_false_without_secret(): void
    {
        $user = new MerchantUser(['role' => 'owner', 'mfa_enabled_at' => null, 'mfa_secret' => null]);
        $this->assertFalse($user->hasMfa());
    }
}
