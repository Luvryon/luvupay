<?php

namespace Tests\Feature\NebiusAI;

use App\Services\Nebius\ChurnPredictionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\Support\NebiusAIMockHelper;
use Tests\TestCase;

class ChurnPredictionTest extends TestCase
{
    use RefreshDatabase, NebiusAIMockHelper;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('nebius.api_key', 'test-key');
        Config::set('nebius.enabled', true);
        Config::set('nebius.base_url', 'https://api.nebius.com/v1');
    }

    public function test_successful_prediction_with_mocked_nebius(): void
    {
        $this->fakeNebiusChurnResponse(72);
        $result = app(ChurnPredictionService::class)->predict([
            'login_frequency' => 0.2,
            'days_since_last_login' => 21,
            'previous_second_chance_usage' => 1,
            'subscription_tenure' => 120,
        ]);
        $this->assertSame(72, $result->churnRiskScore);
        $this->assertContains($result->riskLevel, ['high', 'critical', 'medium', 'low']);
        $this->assertNotEmpty($result->recommendedActions);
    }

    public function test_second_call_hits_cache(): void
    {
        $this->fakeNebiusChurnResponse(60);
        $svc = app(ChurnPredictionService::class);
        $input = ['login_frequency' => 1, 'days_since_last_login' => 3];
        $a = $svc->predict($input);
        $b = $svc->predict($input);
        $this->assertFalse($a->fromCache);
        $this->assertTrue($b->fromCache);
        $this->assertSame($a->churnRiskScore, $b->churnRiskScore);
    }

    public function test_timeout_returns_fallback(): void
    {
        $this->fakeNebiusTimeout();
        $result = app(ChurnPredictionService::class)->predict(['login_frequency' => 0]);
        $this->assertLessThanOrEqual(1.0, $result->confidence);
        $this->assertNotEmpty($result->primaryReason);
    }
}
