<?php

declare(strict_types=1);

namespace Tests\Feature\Licensing;

use Core\Licensing\Application\LicenseService;
use Core\Licensing\Domain\Enums\LicenseStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class LicenseLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_issuing_a_license_generates_a_unique_key_and_pending_status(): void
    {
        $service = $this->app->make(LicenseService::class);

        $license = $service->issue(['tier' => 'starter', 'max_users' => 25]);

        $this->assertNotEmpty($license->license_key);
        $this->assertSame(LicenseStatus::PendingActivation, $license->status);
    }

    public function test_activating_a_license_sets_its_activation_date(): void
    {
        $service = $this->app->make(LicenseService::class);
        $license = $service->issue(['tier' => 'professional']);

        $activated = $service->activate($license);

        $this->assertSame(LicenseStatus::Active, $activated->status);
        $this->assertNotNull($activated->activation_date);
    }

    public function test_expiring_overdue_licenses_only_affects_active_licenses_past_their_expiry_date(): void
    {
        $service = $this->app->make(LicenseService::class);

        $overdue = $service->issue(['tier' => 'trial', 'expiry_date' => now()->subDay()->toDateString()]);
        $service->activate($overdue);

        $current = $service->issue(['tier' => 'enterprise', 'expiry_date' => now()->addYear()->toDateString()]);
        $service->activate($current);

        $expiredCount = $service->expireOverdueLicenses();

        $this->assertSame(1, $expiredCount);
        $this->assertSame(LicenseStatus::Expired, $overdue->fresh()->status);
        $this->assertSame(LicenseStatus::Active, $current->fresh()->status);
    }
}
