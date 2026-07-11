<?php

declare(strict_types=1);

namespace Database\Seeders;

use Core\Settings\Application\SettingsService;
use Illuminate\Database\Seeder;

/**
 * Seeds the global platform settings described in
 * core/Settings/README.md: System Name, Timezone, Maintenance Mode,
 * Storage, Security Settings, AI Settings. Email settings are
 * seeded from config/mail.php's existing env-driven values rather
 * than duplicated here in plaintext. Idempotent.
 */
final class SettingsSeeder extends Seeder
{
    public function run(): void
    {
        /** @var SettingsService $settings */
        $settings = app(SettingsService::class);

        $settings->setMany([
            'system_name' => config('app.name'),
            'default_timezone' => config('app.timezone'),
            'default_locale' => config('app.locale'),
        ], group: 'general');

        $settings->setMany([
            'maintenance_mode' => false,
        ], group: 'system');

        $settings->setMany([
            'default_disk' => config('filesystems.default'),
        ], group: 'storage');

        $settings->setMany([
            'max_login_attempts' => (int) config('services.auth.max_login_attempts'),
            'lockout_minutes' => (int) config('services.auth.lockout_minutes'),
            'password_expiry_days' => (int) config('services.auth.password_expiry_days'),
            'two_factor_enforced' => false,
        ], group: 'security');

        $settings->setMany([
            'default_provider' => config('ai.default_provider'),
            'fallback_provider' => config('ai.fallback_provider'),
        ], group: 'ai');
    }
}
