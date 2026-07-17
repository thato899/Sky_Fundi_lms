<?php

declare(strict_types=1);

namespace Tests\Feature\Observability;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class PlatformDiagnoseCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_diagnostic_command_is_read_only_secret_safe_and_returns_success(): void
    {
        config()->set('health.readiness_checks', [HealthyReadinessCheck::class]);
        config()->set('database.connections.sqlite.password', 'diagnostic-secret');

        $this->artisan('platform:diagnose')
            ->expectsOutputToContain('Environment')
            ->expectsOutputToContain('Application key')
            ->doesntExpectOutputToContain('diagnostic-secret')
            ->assertSuccessful();
    }

    public function test_diagnostic_command_fails_for_unsafe_production_configuration(): void
    {
        $this->app->detectEnvironment(static fn (): string => 'production');
        config()->set('app.env', 'production');
        config()->set('app.debug', true);
        config()->set('health.readiness_checks', [HealthyReadinessCheck::class]);

        $this->artisan('platform:diagnose')->assertFailed();
    }
}
