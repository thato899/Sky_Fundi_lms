<?php

declare(strict_types=1);

namespace Core\Health\Console;

use Illuminate\Console\Command;

final class ValidateEnvironmentCommand extends Command
{
    protected $signature = 'platform:validate-environment';

    protected $description = 'Fail fast when required runtime configuration or writable paths are unsafe.';

    public function handle(): int
    {
        $errors = [];
        $this->requireValue($errors, 'APP_KEY', config('app.key'));
        $this->requireValue($errors, 'DB_CONNECTION', config('database.default'));
        $this->requireValue($errors, 'CACHE_STORE', config('cache.default'));
        $this->requireValue($errors, 'SESSION_DRIVER', config('session.driver'));
        $this->requireValue($errors, 'QUEUE_CONNECTION', config('queue.default'));
        $this->requireValue($errors, 'MAIL_MAILER', config('mail.default'));
        $this->requireValue($errors, 'FILESYSTEM_DISK', config('filesystems.default'));

        if (app()->environment('production')) {
            $this->requireValue($errors, 'MAIL_FROM_ADDRESS', config('mail.from.address'));
            if ((bool) config('app.debug')) {
                $errors[] = 'APP_DEBUG must be false in production.';
            }
            if (! (bool) config('session.secure')) {
                $errors[] = 'SESSION_SECURE_COOKIE must be true in production.';
            }
        }

        foreach ((array) config('ai.providers', []) as $name => $provider) {
            if (! (bool) ($provider['enabled'] ?? false)) {
                continue;
            }
            if (empty($provider['base_url'])) {
                $errors[] = "Enabled AI provider {$name} requires a base URL.";
            }
            if ($name !== 'ollama' && empty($provider['api_key'])) {
                $errors[] = "Enabled AI provider {$name} requires an API key.";
            }
        }

        foreach ([storage_path(), storage_path('framework'), storage_path('logs'), base_path('bootstrap/cache')] as $path) {
            if (! is_dir($path) || ! is_writable($path)) {
                $errors[] = "Runtime path is not writable: {$path}";
            }
        }

        $publicLink = public_path('storage');
        if (! is_link($publicLink) && ! is_dir($publicLink)) {
            $errors[] = 'The public storage link is missing; run php artisan storage:link.';
        }

        if ($errors !== []) {
            foreach ($errors as $error) {
                $this->components->error($error);
            }

            return self::FAILURE;
        }

        $this->components->info('Runtime environment configuration is valid.');

        return self::SUCCESS;
    }

    private function requireValue(array &$errors, string $name, mixed $value): void
    {
        if ($value === null || $value === '') {
            $errors[] = "{$name} is required.";
        }
    }
}
