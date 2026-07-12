<?php

declare(strict_types=1);

namespace Core\Installer\Console;

use Core\Installer\Application\InstallerService;
use Illuminate\Console\Command;

/**
 * `php artisan platform:install` — interactive first-run setup. See
 * core/Installer/README.md for the full workflow and why this is
 * console-driven rather than (also) a public HTTP API today: the
 * administrator-creation step is the mechanism that produces the
 * first authenticated user, so an HTTP installer would need its own
 * unauthenticated-until-installed route guard — deferred as future
 * work once a web-based installer UI is actually needed.
 */
final class InstallCommand extends Command
{
    protected $signature = 'platform:install';

    protected $description = 'Run the interactive first-time platform installation workflow.';

    public function handle(InstallerService $installer): int
    {
        if ($installer->isInstalled()) {
            $this->warn('The platform is already installed. Re-run individual steps are not exposed via this command; use the Settings API to change configuration.');

            return self::SUCCESS;
        }

        $this->info('Sky Fundi Platform installation');
        $this->line('--------------------------------');

        foreach ($installer->steps() as $step) {
            if ($step->isComplete()) {
                $this->line("✔ {$step->label()} (already configured)");

                continue;
            }

            $this->line("→ {$step->label()}");
            $input = $this->collectInputFor($step->key());
            $installer->runStep($step->key(), $input);
            $this->info("  done.");
        }

        $this->info('Installation complete.');

        return self::SUCCESS;
    }

    private function collectInputFor(string $stepKey): array
    {
        return match ($stepKey) {
            'application' => ['name' => $this->ask('Application name', config('app.name'))],
            'localization' => [
                'timezone' => $this->ask('Default timezone', config('app.timezone')),
                'locale' => $this->ask('Default language', config('app.locale')),
            ],
            'mail' => ['provider' => $this->choice('Mail provider', ['smtp', 'ses', 'mailgun', 'microsoft365', 'google_workspace'], 0)],
            'storage' => ['disk' => $this->choice('Storage disk', ['local', 's3', 'azure', 'gcs'], 0)],
            'ai_provider' => ['provider' => $this->choice('Default AI provider', ['ollama', 'deepseek', 'openai', 'claude', 'gemini'], 0)],
            'branding' => [
                'platform_name' => $this->ask('Platform name', 'Sky Fundi'),
                'support_email' => $this->ask('Support email', 'support@skyfundi.app'),
            ],
            'administrator' => [
                'name' => $this->ask('Administrator name'),
                'email' => $this->ask('Administrator email'),
                'password' => $this->secret('Administrator password'),
            ],
            'license' => ['license_key' => $this->secret('Platform license key (leave blank for trial)') ?: 'TRIAL'],
            default => [],
        };
    }
}
