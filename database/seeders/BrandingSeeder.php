<?php

declare(strict_types=1);

namespace Database\Seeders;

use Core\Branding\Application\BrandingService;
use Illuminate\Database\Seeder;

/**
 * Seeds Sky Fundi's default branding — see core/Branding/README.md
 * ("Default branding must be Sky Fundi.") and config/branding.php.
 * Idempotent; safe to re-run without clobbering values an
 * administrator has already customised, since BrandingService::update
 * only ever sets the keys it's given and this seeder passes the full
 * default set every time (acceptable for first-install seeding; use
 * the API afterwards for changes, not by re-running this seeder).
 */
final class BrandingSeeder extends Seeder
{
    public function run(): void
    {
        app(BrandingService::class)->update(config('branding'));
    }
}
