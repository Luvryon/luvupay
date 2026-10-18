<?php

namespace Tests\Feature;

use Tests\TestCase;

class HealthEndpointTest extends TestCase
{
    public function test_public_health_endpoint_is_not_exposed(): void
    {
        $this->getJson('/api/v1/health')->assertStatus(404);
    }

    public function test_internal_health_requires_token(): void
    {
        config(['luvupay.observability.metrics_token' => 'test-metrics-token']);

        $this->getJson('/internal/health')->assertStatus(401);
        $this->withToken('wrong')->getJson('/internal/health')->assertStatus(401);

        $this->withToken('test-metrics-token')
            ->getJson('/internal/health')
            ->assertOk()
            ->assertJsonStructure(['service', 'status', 'checks', 'timestamp']);
    }

    public function test_internal_health_hidden_when_token_unset(): void
    {
        config(['luvupay.observability.metrics_token' => '']);
        $this->getJson('/internal/health')->assertStatus(404);
    }
}
