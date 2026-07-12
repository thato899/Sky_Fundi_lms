<?php

declare(strict_types=1);

namespace Tests\Feature\FeatureFlags;

use Core\FeatureFlags\Application\FeatureFlagService;
use Core\FeatureFlags\Domain\Enums\FeatureFlagScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class FeatureFlagTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_undefined_flag_resolves_to_false(): void
    {
        $service = $this->app->make(FeatureFlagService::class);

        $this->assertFalse($service->isEnabled('does-not-exist'));
    }

    public function test_a_flag_follows_its_global_setting_by_default(): void
    {
        $service = $this->app->make(FeatureFlagService::class);
        $flag = $service->define('new-dashboard', 'New Dashboard');

        $this->assertFalse($service->isEnabled('new-dashboard'));

        $service->setGlobal($flag, true);

        $this->assertTrue($service->isEnabled('new-dashboard'));
    }

    public function test_a_scoped_override_wins_over_the_global_setting(): void
    {
        $service = $this->app->make(FeatureFlagService::class);
        $flag = $service->define('beta-feature', 'Beta Feature');
        $service->setGlobal($flag, false);

        $service->setForScope($flag, FeatureFlagScope::Organization, 'org-123', true);

        $this->assertFalse($service->isEnabled('beta-feature'));
        $this->assertTrue($service->isEnabled('beta-feature', FeatureFlagScope::Organization, 'org-123'));
        $this->assertFalse($service->isEnabled('beta-feature', FeatureFlagScope::Organization, 'org-456'));
    }
}
