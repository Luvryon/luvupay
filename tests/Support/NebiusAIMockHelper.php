<?php

namespace Tests\Support;

use Illuminate\Support\Facades\Http;

trait NebiusAIMockHelper
{
    protected function fakeNebiusChurnResponse(int $score = 72): void
    {
        Http::fake([
            'api.nebius.com/*' => Http::response([
                'choices' => [[
                    'message' => [
                        'content' => json_encode([
                            'churn_risk_score' => $score,
                            'risk_level' => $score >= 80 ? 'critical' : 'high',
                            'primary_reason' => 'Login frequency dropped and prior Second Chance usage.',
                            'recommended_actions' => ['Send 72h nudge email', 'Offer resale option'],
                            'confidence' => 0.81,
                        ]),
                    ],
                ]],
                'usage' => ['total_tokens' => 120],
            ], 200),
        ]);
    }

    protected function fakeNebiusPricingResponse(): void
    {
        Http::fake([
            'api.nebius.com/*' => Http::response([
                'choices' => [[
                    'message' => [
                        'content' => json_encode([
                            'recommended_price' => 9.5,
                            'price_range_low' => 8.0,
                            'price_range_high' => 11.0,
                            'expected_sell_time_hours' => 36,
                            'confidence' => 0.77,
                            'reasoning' => 'Strong demand for mid-cycle seats in this category.',
                        ]),
                    ],
                ]],
                'usage' => ['total_tokens' => 90],
            ], 200),
        ]);
    }

    protected function fakeNebiusTimeout(): void
    {
        Http::fake([
            'api.nebius.com/*' => function () {
                throw new \Illuminate\Http\Client\ConnectionException('timeout');
            },
        ]);
    }
}
