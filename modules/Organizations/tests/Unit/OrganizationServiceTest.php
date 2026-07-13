<?php

declare(strict_types=1);

namespace Modules\Organizations\Tests\Unit;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Organizations\Application\OrganizationService;
use Modules\Organizations\Domain\Enums\OrganizationStatus;
use Tests\TestCase;

final class OrganizationServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_new_organization_is_active_and_uses_configured_defaults(): void
    {
        $service = $this->app->make(OrganizationService::class);
        $organization = $service->create(['name' => 'Example College', 'code' => 'example-college', 'type' => 'college'], null);

        $this->assertSame(OrganizationStatus::Active, $organization->status);
        $this->assertSame('Africa/Johannesburg', $service->settings($organization)['general']['timezone']);
    }
}
