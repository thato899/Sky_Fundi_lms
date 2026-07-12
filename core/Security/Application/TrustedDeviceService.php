<?php

declare(strict_types=1);

namespace Core\Security\Application;

use Core\Security\Events\TrustedDeviceAdded;
use Core\Security\Events\TrustedDeviceRevoked;
use Core\Security\Infrastructure\Models\TrustedDevice;
use Core\Users\Infrastructure\Models\User;

/**
 * Device trust is opt-in per user (see core/Security/README.md) —
 * logging in from a new IP/user-agent combination is never blocked by
 * itself, only reported (see Listeners\DetectNewDeviceLogin). Trusting
 * a device is a deliberate action a user takes (e.g. "remember this
 * device"), tracked here.
 */
final class TrustedDeviceService
{
    public function fingerprint(string $ipAddress, string $userAgent): string
    {
        return hash('sha256', $ipAddress.'|'.$userAgent);
    }

    public function isTrusted(User $user, string $ipAddress, string $userAgent): bool
    {
        $device = TrustedDevice::query()
            ->where('user_id', $user->id)
            ->where('fingerprint', $this->fingerprint($ipAddress, $userAgent))
            ->first();

        return $device !== null && ! $device->isExpired();
    }

    public function trust(User $user, string $ipAddress, string $userAgent, ?string $deviceName = null, ?int $ttlDays = 90): TrustedDevice
    {
        $device = TrustedDevice::query()->updateOrCreate(
            ['user_id' => $user->id, 'fingerprint' => $this->fingerprint($ipAddress, $userAgent)],
            [
                'device_name' => $deviceName,
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent,
                'trusted_at' => now(),
                'last_seen_at' => now(),
                'expires_at' => $ttlDays !== null ? now()->addDays($ttlDays) : null,
            ],
        );

        event(new TrustedDeviceAdded($device));

        return $device;
    }

    public function touch(TrustedDevice $device): void
    {
        $device->update(['last_seen_at' => now()]);
    }

    public function revoke(TrustedDevice $device): void
    {
        event(new TrustedDeviceRevoked($device));

        $device->delete();
    }

    public function listFor(User $user): \Illuminate\Support\Collection
    {
        return TrustedDevice::query()->where('user_id', $user->id)->latest('last_seen_at')->get();
    }
}
