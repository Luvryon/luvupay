<?php

namespace Tests\Feature;

use App\Enums\SubscriptionStatus;
use App\Models\Merchant;
use App\Models\Subscription;
use App\Services\Resale\ResaleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ResaleMoneyFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_list_creates_active_listing_without_refunding_merchant_charge(): void
    {
        $m = Merchant::create([
            'id' => (string) Str::uuid(),
            'name' => 'M',
            'email' => 'm@test.com',
            'api_key_hash' => hash('sha256', 'k'),
            'is_active' => true,
        ]);
        $sub = Subscription::create([
            'id' => (string) Str::uuid(),
            'merchant_id' => $m->id,
            'merchant_user_id' => 'seller1',
            'email' => 's@test.com',
            'status' => SubscriptionStatus::Active,
            'amount_minor' => 2000,
            'currency' => 'USD',
            'current_period_end' => now()->addDays(20),
            'next_billing_at' => now()->addDays(20),
        ]);

        $listing = app(ResaleService::class)->list($m, $sub, 1000);
        $this->assertSame(1000, $listing->asking_price_minor);
        // Merchant original charge is not represented as a refund transaction here
        $this->assertDatabaseMissing('transactions', [
            'merchant_id' => $m->id,
            'type' => 'refund',
        ]);
    }
}
