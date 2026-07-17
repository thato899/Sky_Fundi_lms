<?php

declare(strict_types=1);

namespace Tests\Feature\Observability;

use Core\Health\Application\DTOs\HealthCheckResult;
use Core\Health\Contracts\HealthCheckInterface;
use Illuminate\Database\Migrations\MigrationRepositoryInterface;
use Illuminate\Database\Migrations\Migrator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Mockery;
use RuntimeException;
use Tests\TestCase;

final class PlatformDiagnoseCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_diagnostic_command_is_read_only_secret_safe_and_returns_success(): void
    {
        config()->set('health.readiness_checks', [DiagnoseHealthyReadinessCheck::class]);
        config()->set('database.connections.sqlite.password', 'diagnostic-secret');

        $this->assertSame(0, Artisan::call('platform:diagnose'));

        $output = Artisan::output();
        $this->assertStringContainsString('Environment', $output);
        $this->assertStringContainsString('Application key', $output);
        $this->assertStringContainsString('Migration repository', $output);
        $this->assertStringContainsString('Pending migrations', $output);
        $this->assertStringContainsString('none', $output);
        $this->assertStringNotContainsString('diagnostic-secret', $output);
    }

    public function test_diagnostic_command_fails_and_reports_pending_migration_count_without_executing_it(): void
    {
        config()->set('health.readiness_checks', [DiagnoseHealthyReadinessCheck::class]);
        $this->mockMigrator(
            files: [
                '2026_01_01_000000_ran' => '/migrations/2026_01_01_000000_ran.php',
                '2026_01_02_000000_pending' => '/migrations/2026_01_02_000000_pending.php',
            ],
            ran: ['2026_01_01_000000_ran'],
        );

        $this->assertSame(1, Artisan::call('platform:diagnose'));

        $output = Artisan::output();
        $this->assertStringContainsString('Migration repository', $output);
        $this->assertStringContainsString('available', $output);
        $this->assertStringContainsString('Pending migrations', $output);
        $this->assertStringContainsString('1', $output);
    }

    public function test_diagnostic_command_safely_fails_when_migration_repository_is_unavailable(): void
    {
        config()->set('health.readiness_checks', [DiagnoseHealthyReadinessCheck::class]);
        config()->set('database.connections.sqlite.password', 'database-secret');

        $migrator = Mockery::mock(Migrator::class);
        $migrator->shouldReceive('repositoryExists')
            ->once()
            ->andThrow(new RuntimeException('database-secret at private-host'));
        $migrator->shouldNotReceive('run');
        $this->app->instance(Migrator::class, $migrator);

        $this->assertSame(1, Artisan::call('platform:diagnose'));

        $output = Artisan::output();
        $this->assertStringContainsString('Migration repository', $output);
        $this->assertStringContainsString('unavailable', $output);
        $this->assertStringContainsString('Pending migrations', $output);
        $this->assertStringContainsString('unknown', $output);
        $this->assertStringNotContainsString('database-secret', $output);
        $this->assertStringNotContainsString('private-host', $output);
    }

    public function test_diagnostic_command_fails_for_unsafe_production_configuration(): void
    {
        $this->app->detectEnvironment(static fn (): string => 'production');
        config()->set('app.env', 'production');
        config()->set('app.debug', true);
        config()->set('health.readiness_checks', [DiagnoseHealthyReadinessCheck::class]);

        $this->artisan('platform:diagnose')->assertFailed();
    }

    /**
     * @param  array<string, string>  $files
     * @param  list<string>  $ran
     */
    private function mockMigrator(array $files, array $ran): void
    {
        $repository = Mockery::mock(MigrationRepositoryInterface::class);
        $repository->shouldReceive('getRan')->once()->andReturn($ran);
        $repository->shouldNotReceive('log', 'delete', 'createRepository', 'deleteRepository');

        $migrator = Mockery::mock(Migrator::class);
        $migrator->shouldReceive('repositoryExists')->once()->andReturnTrue();
        $migrator->shouldReceive('paths')->once()->andReturn(['/registered/migrations']);
        $migrator->shouldReceive('getMigrationFiles')->once()->andReturn($files);
        $migrator->shouldReceive('getRepository')->once()->andReturn($repository);
        $migrator->shouldNotReceive('run', 'runPending', 'rollback', 'reset');
        $this->app->instance(Migrator::class, $migrator);
    }
}

final class DiagnoseHealthyReadinessCheck implements HealthCheckInterface
{
    public function name(): string
    {
        return 'database';
    }

    public function check(): HealthCheckResult
    {
        return HealthCheckResult::healthy($this->name());
    }
}
