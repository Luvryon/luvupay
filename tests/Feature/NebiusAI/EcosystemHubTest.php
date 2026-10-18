<?php

namespace Tests\Feature\NebiusAI;

use App\Services\Nebius\EcosystemHubService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class EcosystemHubTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('nebius.api_key', 'test-key');
        Config::set('nebius.enabled', true);
        Config::set('nebius.base_url', 'https://api.nebius.com/v1');
    }

    public function test_health_report_with_provided_subscriptions(): void
    {
        Http::fake([
            'api.nebius.com/*' => Http::response([
                'choices' => [[
                    'message' => [
                        'content' => json_encode([
                            'total_monthly_spend' => 45.0,
                            'subscription_health_score' => 62,
                            'insights' => [
                                ['type' => 'suggestion', 'message' => 'Resell unused seat', 'action_url' => '/docs/resale-money'],
                            ],
                            'natural_language_report' => 'You have room to optimize spend via resale.',
                        ]),
                    ],
                ]],
                'usage' => ['total_tokens' => 100],
            ], 200),
        ]);

        $report = app(EcosystemHubService::class)->generateHealthReport('user@example.com', [
            ['merchant_name' => 'Demo', 'plan_name' => 'Pro', 'price' => 20, 'billing_cycle' => 'month', 'days_remaining' => 10],
        ]);
        $this->assertSame(62, $report->subscriptionHealthScore);
        $this->assertNotEmpty($report->insights);
    }
}
