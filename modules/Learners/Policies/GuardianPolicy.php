<?php

declare(strict_types=1);

namespace Modules\Learners\Policies;

use Core\Identity\Application\PermissionResolver;
use Core\Identity\Infrastructure\Models\Membership;
use Core\Users\Infrastructure\Models\User;
use Modules\Learners\Domain\Enums\GuardianStatus;
use Modules\Learners\Infrastructure\Models\GuardianProfile;

final class GuardianPolicy
{
    public function __construct(private readonly PermissionResolver $permissions) {}

    public function viewAny(User $user): bool
    {
        return $this->allows($user, 'guardians.view');
    }

    public function create(User $user): bool
    {
        return $this->allows($user, 'guardians.create');
    }

    public function viewArchived(User $user): bool
    {
        return $this->allows($user, 'guardians.archive');
    }

    public function view(User $user, GuardianProfile $guardian): bool
    {
        return $this->allows($user, 'guardians.view', $guardian) || $guardian->getAttribute('user_id') === $user->getKey();
    }

    public function update(User $user, GuardianProfile $guardian): bool
    {
        return $this->allows($user, 'guardians.update', $guardian) && $guardian->getAttribute('status') !== GuardianStatus::Archived;
    }

    public function archive(User $user, GuardianProfile $guardian): bool
    {
        return $this->allows($user, 'guardians.archive', $guardian) && $guardian->getAttribute('status') !== GuardianStatus::Archived;
    }

    public function manageRelationships(User $user, GuardianProfile $guardian): bool
    {
        return $this->allows($user, 'guardians.manage_relationships', $guardian);
    }

    private function allows(User $user, string $permission, ?GuardianProfile $guardian = null): bool
    {
        $membership = request()->attributes->get('organization_membership');
        if (! $membership instanceof Membership || $membership->getAttribute('user_id') !== $user->getKey()) {
            return false;
        }
        if ($guardian !== null && $guardian->getAttribute('organization_id') !== $membership->getAttribute('organization_id')) {
            return false;
        }

        return $this->permissions->allows($membership, $permission);
    }
}
