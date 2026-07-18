<?php

declare(strict_types=1);

namespace Core\Health\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

final class ValidateEnvironmentCommand extends Command
{
    protected $signature = 'platform:validate-environment {--cpanel : Validate the shared-hosting production contract}';

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

        if ((bool) $this->option('cpanel')) {
            $this->validateCpanelEnvironment($errors);
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

    /**
     * @param  list<string>  $errors
     */
    private function validateCpanelEnvironment(array &$errors): void
    {
        if (PHP_VERSION_ID < 80300) {
            $errors[] = 'cPanel must use PHP 8.3 or newer for this application.';
        }

        foreach (['ctype', 'curl', 'dom', 'fileinfo', 'intl', 'mbstring', 'openssl', 'pdo_mysql', 'tokenizer', 'xml', 'zip'] as $extension) {
            if (! extension_loaded($extension)) {
                $errors[] = "Required PHP extension is missing: {$extension}.";
            }
        }

        if (! app()->environment('production')) {
            $errors[] = 'APP_ENV must be production for cPanel deployment.';
        }

        $appUrl = (string) config('app.url');
        if (filter_var($appUrl, FILTER_VALIDATE_URL) === false || strtolower((string) parse_url($appUrl, PHP_URL_SCHEME)) !== 'https') {
            $errors[] = 'APP_URL must be a valid HTTPS URL for cPanel deployment.';
        }

        foreach (['cache.default' => 'database', 'session.driver' => 'database', 'queue.default' => 'database'] as $configuration => $expected) {
            if (config($configuration) !== $expected) {
                $errors[] = "{$configuration} must use the {$expected} driver for cPanel deployment.";
            }
        }

        if ((int) config('queue.connections.database.retry_after') <= 50) {
            $errors[] = 'DB_QUEUE_RETRY_AFTER must be greater than the 50-second cPanel worker timeout.';
        }

        if (config('mail.default') === 'smtp') {
            $this->requireValue($errors, 'MAIL_HOST', config('mail.mailers.smtp.host'));
            $this->requireValue($errors, 'MAIL_PORT', config('mail.mailers.smtp.port'));
            $this->requireValue($errors, 'MAIL_USERNAME', config('mail.mailers.smtp.username'));
            $this->requireValue($errors, 'MAIL_PASSWORD', config('mail.mailers.smtp.password'));
        }

        if (! function_exists('proc_open')) {
            $errors[] = 'PHP proc_open must be available for bounded cron queue processing.';
        }

        try {
            DB::connection()->getPdo();

            foreach (['cache', 'cache_locks', 'sessions', 'jobs', 'failed_jobs'] as $table) {
                if (! Schema::hasTable($table)) {
                    $errors[] = "Required database table is missing: {$table}.";
                }
            }
        } catch (Throwable) {
            $errors[] = 'Database connectivity could not be verified.';
        }
    }

    private function requireValue(array &$errors, string $name, mixed $value): void
    {
        if ($value === null || $value === '') {
            $errors[] = "{$name} is required.";
        }
    }
}
