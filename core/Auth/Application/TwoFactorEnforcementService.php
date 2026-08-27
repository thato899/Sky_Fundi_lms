<?php

declare(strict_types=1);

namespace Core\Auth\Application;

use Core\Users\Infrastructure\Models\User;
use Modules\Organizations\Application\OrganizationService;
use Modules\Organizations\Infrastructure\Models\Organization;

/**
 * Opt-in per-organization two-factor enforcement, mirroring the settings
 * pattern already used by Modules\Staff\Application\TeachingAssignmentService
 * (group/key stored via OrganizationService::updateSettings(), read via
 * settings()). A user is required to have two-factor enabled if ANY
 * organization they hold an active membership in enforces it.
 *
 * Toggling the setting currently goes through the existing platform-wide
 * `PUT /organizations/{organization}/settings` endpoint (organizations.
 * settings.manage) — there is no organization-admin self-service settings
 * surface for this yet, consistent with enforce_teaching_assignments
 * having the same gap today. A dedicated self-service toggle is a
 * reasonable follow-up, not built here.
 */
final class TwoFactorEnforcementService
{
    public const SETTING_GROUP = 'security';

    public const SETTING_KEY = 'enforce_two_factor';

    public function __construct(private readonly OrganizationService $organizations) {}

    public function isRequiredFor(User $user): bool
    {
        return $user->memberships()
            ->where('status', 'active')
            ->with('organization')
            ->get()
            ->contains(function ($membership): bool {
                $organization = $membership->getRelationValue('organization');

                return $organization instanceof Organization && $this->enforced($organization);
            });
    }

    public function enforced(Organization $organization): bool
    {
        $settings = $this->organizations->settings($organization);

        return (bool) ($settings[self::SETTING_GROUP][self::SETTING_KEY] ?? false);
    }
}
