<?php

declare(strict_types=1);

namespace Modules\Materials\Policies;

use Core\Identity\Application\PermissionResolver;
use Core\Identity\Infrastructure\Models\Membership;
use Core\Users\Infrastructure\Models\User;
use Modules\Materials\Infrastructure\Models\Material;

final class MaterialPolicy
{
    public function __construct(private readonly PermissionResolver $permissions) {}

    public function viewAny(User $user): bool
    {
        return $this->allows($user, 'materials.view');
    }

    public function view(User $user, Material $material): bool
    {
        return $this->allows($user, 'materials.view', $material);
    }

    public function create(User $user): bool
    {
        return $this->allows($user, 'materials.upload');
    }

    public function delete(User $user, Material $material): bool
    {
        return $this->allows($user, 'materials.delete', $material);
    }

    private function allows(User $user, string $permission, ?Material $material = null): bool
    {
        $membership = request()->attributes->get('organization_membership');
        if (! $membership instanceof Membership || $membership->getAttribute('user_id') !== $user->getKey()) {
            return false;
        }
        if ($material !== null && $material->getAttribute('organization_id') !== $membership->getAttribute('organization_id')) {
            return false;
        }

        return $this->permissions->allows($membership, $permission);
    }
}
