<?php

namespace Tests\Feature\NebiusAI;

use App\Services\Nebius\DynamicPricingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\Support\NebiusAIMockHelper;
use Tests\TestCase;

class DynamicPricingTest extends TestCase
{
    use RefreshDatabase, NebiusAIMockHelper;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('nebius.api_key', 'test-key');
        Config::set('nebius.enabled', true);
        Config::set('nebius.base_url', 'https://api.nebius.com/v1');
    }

    public function test_price_suggestion_mocked(): void
    {
        $this->fakeNebiusPricingResponse();
        $r = app(DynamicPricingService::class)->suggestPrice([
            'days_remaining' => 14,
            'original_price' => 20,
            'merchant_category' => 'saas',
        ]);
        $this->assertGreaterThan(0, $r->recommendedPrice);
        $this->assertNotEmpty($r->reasoning);
    }
}
