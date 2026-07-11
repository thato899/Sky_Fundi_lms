<?php

declare(strict_types=1);

namespace Core\Branding\Application;

use Core\AuditLogs\Application\AuditLogService;
use Core\Branding\Events\BrandingChanged;
use Core\Settings\Application\SettingsService;

/**
 * Branding is modeled as a named group of Settings ("branding") rather
 * than its own table — see config/branding.php: "Core\Branding\
 * Application\BrandingService reads live values from Settings at
 * runtime — these are only the seed/fallback defaults." This keeps
 * branding fully database-driven for free, without duplicating
 * SettingsService's caching/audit machinery.
 */
final class BrandingService
{
    private const GROUP = 'branding';

    private const FIELDS = [
        'platform_name',
        'company_name',
        'support_email',
        'logo_path',
        'favicon_path',
        'primary_colour',
        'secondary_colour',
        'login_background_path',
    ];

    public function __construct(
        private readonly SettingsService $settings,
        private readonly AuditLogService $auditLog,
    ) {}

    /**
     * Current branding, falling back to config/branding.php defaults
     * (Sky Fundi) for any field not yet overridden in Settings.
     */
    public function current(): array
    {
        $stored = $this->settings->all(self::GROUP);
        $defaults = config('branding');

        $result = [];

        foreach (self::FIELDS as $field) {
            $result[$field] = $stored[$field] ?? $defaults[$field] ?? null;
        }

        return $result;
    }

    public function update(array $values): array
    {
        $changed = array_intersect_key($values, array_flip(self::FIELDS));

        foreach ($changed as $key => $value) {
            $this->settings->set($key, $value, self::GROUP);
        }

        if ($changed !== []) {
            event(new BrandingChanged(array_keys($changed)));

            $this->auditLog->record(
                action: 'branding.updated',
                after: ['changed_keys' => array_keys($changed)],
            );
        }

        return $this->current();
    }

    /**
     * Resets every branding field back to the Sky Fundi platform
     * defaults from config/branding.php — see core/Branding/README.md
     * ("Default branding must be Sky Fundi.").
     */
    public function resetToDefaults(): array
    {
        return $this->update(config('branding'));
    }
}
