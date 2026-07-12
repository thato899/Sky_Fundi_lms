<?php

declare(strict_types=1);

namespace Core\Security\Listeners;

use Core\Auth\Events\UserLoggedIn;
use Core\Security\Application\TrustedDeviceService;
use Core\Security\Events\SecurityAlertRaised;

/**
 * Purely additive to Core\Auth — subscribes to the existing
 * UserLoggedIn event rather than modifying AuthService, per "do not
 * recreate existing work." Raises a non-blocking SecurityAlertRaised
 * when a login comes from an IP/user-agent combination that isn't in
 * the user's trusted-devices list yet. Never blocks the login itself
 * — see core/Security/README.md and the "Future MFA" note there for
 * where a real challenge would hook in.
 */
final class DetectNewDeviceLogin
{
    public function __construct(
        private readonly TrustedDeviceService $trustedDevices,
    ) {}

    public function handle(UserLoggedIn $event): void
    {
        $userAgent = request()?->userAgent() ?? 'unknown';

        if ($this->trustedDevices->isTrusted($event->user, $event->ipAddress, $userAgent)) {
            return;
        }

        event(new SecurityAlertRaised(
            user: $event->user,
            reason: 'login_from_unrecognised_device',
            context: ['ip_address' => $event->ipAddress, 'user_agent' => $userAgent],
        ));
    }
}
