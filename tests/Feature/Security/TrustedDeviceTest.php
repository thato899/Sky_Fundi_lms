<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use Core\Security\Application\TrustedDeviceService;
use Core\Users\Infrastructure\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class TrustedDeviceTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_device_is_untrusted_until_explicitly_trusted(): void
    {
        $user = User::factory()->create();
        $service = $this->app->make(TrustedDeviceService::class);

        $this->assertFalse($service->isTrusted($user, '203.0.113.5', 'TestAgent/1.0'));

        $service->trust($user, '203.0.113.5', 'TestAgent/1.0', 'My Laptop');

        $this->assertTrue($service->isTrusted($user, '203.0.113.5', 'TestAgent/1.0'));
    }

    public function test_a_different_ip_or_agent_is_a_different_device(): void
    {
        $user = User::factory()->create();
        $service = $this->app->make(TrustedDeviceService::class);

        $service->trust($user, '203.0.113.5', 'TestAgent/1.0');

        $this->assertFalse($service->isTrusted($user, '198.51.100.9', 'TestAgent/1.0'));
        $this->assertFalse($service->isTrusted($user, '203.0.113.5', 'OtherAgent/2.0'));
    }

    public function test_revoking_a_device_removes_trust(): void
    {
        $user = User::factory()->create();
        $service = $this->app->make(TrustedDeviceService::class);

        $device = $service->trust($user, '203.0.113.5', 'TestAgent/1.0');
        $service->revoke($device);

        $this->assertFalse($service->isTrusted($user, '203.0.113.5', 'TestAgent/1.0'));
    }
}
