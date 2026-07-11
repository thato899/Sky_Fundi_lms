<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Default Platform Branding
|--------------------------------------------------------------------------
|
| Seeded into Core\Settings (group: "branding") by
| database/seeders/BrandingSeeder.php on first install, per
| docs (core/Branding/README.md): "Default branding must be Sky Fundi."
| Core\Branding\Application\BrandingService reads live values from
| Settings at runtime — these are only the seed/fallback defaults.
|
*/

return [
    'platform_name' => 'Sky Fundi',
    'company_name' => 'Sky Fundi',
    'support_email' => 'support@skyfundi.app',
    'logo_path' => null,
    'favicon_path' => null,
    'primary_colour' => '#0B5FFF',
    'secondary_colour' => '#0A2540',
    'login_background_path' => null,
];
