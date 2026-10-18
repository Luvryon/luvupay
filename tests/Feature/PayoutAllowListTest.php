<?php

namespace Tests\Feature;

use App\Exceptions\LuvuApiException;
use App\Models\Merchant;
use App\Models\MerchantUser;
use App\Models\PaypalPayoutRecipient;
use App\Services\Security\PayoutAllowList;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PayoutAllowListTest extends TestCase
{
    use RefreshDatabase;

    public function test_inactive_recipient_is_rejected(): void
    {
        $m = Merchant::create([
            'id' => (string) Str::uuid(),
            'name' => 'M',
            'email' => 'm@test.com',
            'api_key_hash' => hash('sha256', 'k'),
            'is_active' => true,
        ]);
        $this->expectException(LuvuApiException::class);
        app(PayoutAllowList::class)->assertAllowed($m, 'thief@evil.test', 'charity');
    }

    public function test_active_recipient_passes(): void
    {
        $m = Merchant::create([
            'id' => (string) Str::uuid(),
            'name' => 'M',
            'email' => 'm@test.com',
            'api_key_hash' => hash('sha256', 'k'),
            'is_active' => true,
        ]);
        PaypalPayoutRecipient::create([
            'id' => (string) Str::uuid(),
            'merchant_id' => $m->id,
            'email' => 'ok@charity.org',
            'purpose' => 'charity',
            'status' => 'active',
            'requested_at' => now(),
            'activated_at' => now(),
        ]);
        app(PayoutAllowList::class)->assertAllowed($m, 'ok@charity.org', 'charity');
        $this->assertTrue(true);
    }
}
