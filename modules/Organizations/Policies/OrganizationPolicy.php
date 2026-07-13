<?php

declare(strict_types=1);

namespace Modules\Organizations\Policies;

use Core\Users\Infrastructure\Models\User;
use Modules\Organizations\Infrastructure\Models\Organization;

/** Administrators are explicitly scoped to their own organisation; platform permissions remain the super-admin path. */
final class OrganizationPolicy
{
    public function view(User $user, Organization $organization): bool { return $user->can('organizations.view') || $organization->administrators()->whereKey($user->id)->exists(); }
    public function update(User $user, Organization $organization): bool { return $user->can('organizations.manage') || $organization->administrators()->whereKey($user->id)->exists(); }
    public function delete(User $user, Organization $organization): bool { return $user->can('organizations.manage'); }
}
