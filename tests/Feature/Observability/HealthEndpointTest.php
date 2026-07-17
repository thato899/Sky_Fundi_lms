<?php

declare(strict_types=1);

namespace Tests\Feature\Observability;

use Core\Health\Application\DTOs\HealthCheckResult;
use Core\Health\Contracts\HealthCheckInterface;
use Tests\TestCase;

final class HealthEndpointTest extends TestCase
{
    public function test_liveness_is_public_and_does_not_require_tenant_context(): void
    {
        $this->get('/up')
            ->assertOk()
            ->assertHeader('X-Request-ID');
    }

    public function test_readiness_is_public_and_returns_only_safe_component_statuses(): void
    {
        config()->set('health.readiness_checks', [HealthyReadinessCheck::class]);

        $this->getJson('/api/v1/ready')
            ->assertOk()
            ->assertJson([
                'status' => 'ready',
                'checks' => [['name' => 'database', 'status' => 'healthy']],
            ])
            ->assertJsonMissing(['message' => 'connected to mysql.internal'])
            ->assertJsonMissingPath('checks.0.meta');
    }

    public function test_failed_readiness_returns_503_without_internal_details(): void
    {
        config()->set('health.readiness_checks', [FailedReadinessCheck::class]);

        $response = $this->getJson('/api/v1/health')
            ->assertServiceUnavailable()
            ->assertJson([
                'status' => 'not_ready',
                'checks' => [['name' => 'database', 'status' => 'unhealthy']],
            ]);

        $this->assertStringNotContainsString('mysql.internal', $response->getContent());
        $this->assertStringNotContainsString('password', $response->getContent());
    }
}

final class HealthyReadinessCheck implements HealthCheckInterface
{
    public function name(): string
    {
        return 'database';
    }

    public function check(): HealthCheckResult
    {
        return HealthCheckResult::healthy($this->name(), 'connected to mysql.internal', ['host' => 'mysql.internal']);
    }
}

final class FailedReadinessCheck implements HealthCheckInterface
{
    public function name(): string
    {
        return 'database';
    }

    public function check(): HealthCheckResult
    {
        return HealthCheckResult::unhealthy($this->name(), 'password=secret mysql.internal');
    }
}
