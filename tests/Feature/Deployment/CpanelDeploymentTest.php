<?php

declare(strict_types=1);

namespace Tests\Feature\Deployment;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class CpanelDeploymentTest extends TestCase
{
    use RefreshDatabase;

    public function test_cpanel_environment_contract_passes_with_supported_database_configuration(): void
    {
        $this->configureCpanelEnvironment();

        $this->artisan('platform:validate-environment', ['--cpanel' => true])
            ->assertSuccessful();
    }

    public function test_cpanel_environment_rejects_debug_non_https_and_missing_queue_tables(): void
    {
        $this->configureCpanelEnvironment();
        config()->set('app.debug', true);
        config()->set('app.url', 'http://school.invalid');
        Schema::drop('jobs');

        $this->artisan('platform:validate-environment', ['--cpanel' => true])
            ->expectsOutputToContain('APP_DEBUG must be false in production.')
            ->expectsOutputToContain('APP_URL must be a valid HTTPS URL for cPanel deployment.')
            ->expectsOutputToContain('Required database table is missing: jobs.')
            ->assertFailed();
    }

    public function test_cpanel_environment_rejects_a_missing_public_storage_link(): void
    {
        $this->configureCpanelEnvironment();
        $originalPublicPath = public_path();
        $temporaryPublicPath = storage_path('framework/testing-cpanel-public');
        mkdir($temporaryPublicPath, 0775, true);
        $this->app->usePublicPath($temporaryPublicPath);

        try {
            $this->artisan('platform:validate-environment', ['--cpanel' => true])
                ->expectsOutputToContain('The public storage link is missing')
                ->assertFailed();
        } finally {
            $this->app->usePublicPath($originalPublicPath);
            rmdir($temporaryPublicPath);
        }
    }

    public function test_bounded_queue_command_uses_conservative_worker_arguments(): void
    {
        Process::fake();

        $this->artisan('platform:process-queue')->assertSuccessful();

        Process::assertRan(function (PendingProcess $process): bool {
            $command = $process->command;

            return is_array($command)
                && in_array('queue:work', $command, true)
                && in_array('--stop-when-empty', $command, true)
                && in_array('--tries=3', $command, true)
                && in_array('--timeout=50', $command, true)
                && in_array('--memory=192', $command, true)
                && in_array('--max-time=50', $command, true)
                && $process->timeout === 60;
        });
    }

    public function test_bounded_queue_command_does_not_overlap_an_existing_worker(): void
    {
        Process::fake();
        $lock = Cache::lock('platform:process-queue', 70);
        $this->assertTrue($lock->get());

        try {
            $this->artisan('platform:process-queue')
                ->expectsOutputToContain('already active')
                ->assertSuccessful();
            Process::assertNothingRan();
        } finally {
            $lock->release();
        }
    }

    public function test_sensitive_repository_paths_are_not_publicly_accessible(): void
    {
        foreach (['/.env', '/composer.json', '/vendor/', '/storage/logs/', '/config/app.php'] as $path) {
            $response = $this->get($path);

            $this->assertContains($response->getStatusCode(), [403, 404]);
            $this->assertStringNotContainsString('APP_KEY=', $response->getContent());
            $this->assertStringNotContainsString('"require"', $response->getContent());
        }
    }

    public function test_cpanel_template_uses_placeholders_and_deployment_script_has_valid_shell_syntax(): void
    {
        $template = file_get_contents(base_path('.env.cpanel.example'));

        $this->assertIsString($template);
        $this->assertStringContainsString('APP_KEY=CHANGE_ME_', $template);
        $this->assertStringContainsString('DB_PASSWORD=CHANGE_ME_', $template);
        $this->assertStringContainsString('AI_DEEPSEEK_API_KEY=CHANGE_ME_', $template);
        $this->assertStringNotContainsString('sk-', $template);
        $this->assertStringNotContainsString('APP_DEBUG=true', $template);

        $result = Process::run(['bash', '-n', base_path('scripts/deploy-cpanel.sh')]);
        $this->assertTrue($result->successful(), $result->errorOutput());
    }

    private function configureCpanelEnvironment(): void
    {
        $this->app->detectEnvironment(static fn (): string => 'production');
        config()->set([
            'app.env' => 'production',
            'app.debug' => false,
            'app.url' => 'https://school.invalid',
            'cache.default' => 'database',
            'session.driver' => 'database',
            'session.secure' => true,
            'queue.default' => 'database',
            'queue.connections.database.retry_after' => 120,
            'mail.default' => 'smtp',
            'mail.from.address' => 'no-reply@school.invalid',
            'mail.mailers.smtp.host' => 'smtp.school.invalid',
            'mail.mailers.smtp.port' => 587,
            'mail.mailers.smtp.username' => 'mailer',
            'mail.mailers.smtp.password' => 'test-password',
            'filesystems.default' => 'local',
        ]);
    }
}
