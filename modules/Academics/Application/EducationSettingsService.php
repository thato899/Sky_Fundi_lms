<?php

declare(strict_types=1);

namespace Modules\Academics\Application;

use Core\Settings\Application\SettingsService;

/**
 * Education settings (current academic year/term, default curriculum,
 * grading/assessment/promotion/timetable rules) are stored as a named
 * group ("academics") of Core\Settings rows rather than a dedicated
 * table — mirrors Core\Branding\Application\BrandingService's exact
 * pattern on top of the same underlying service. See
 * modules/Academics/README.md#education-settings.
 */
final class EducationSettingsService
{
    private const GROUP = 'academics';

    public function __construct(
        private readonly SettingsService $settings,
    ) {}

    public function all(): array
    {
        return $this->settings->all(self::GROUP);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->settings->get($key, $default);
    }

    public function set(string $key, mixed $value): void
    {
        $this->settings->set($key, $value, group: self::GROUP);
    }

    public function setMany(array $values): void
    {
        $this->settings->setMany($values, group: self::GROUP);
    }
}
