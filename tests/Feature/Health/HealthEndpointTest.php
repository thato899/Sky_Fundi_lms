<?php

declare(strict_types=1);

namespace Tests\Feature\Health;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class HealthEndpointTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_public_health_endpoint_responds_successfully(): void
    {
        $response = $this->getJson('/api/v1/health');

        $response->assertOk();
        $this->assertContains($response->json('status'), ['healthy', 'degraded']);
    }

    public function test_the_detailed_health_endpoint_requires_authentication(): void
    {
        $this->getJson('/api/v1/health/detailed')->assertUnauthorized();
    }
}
