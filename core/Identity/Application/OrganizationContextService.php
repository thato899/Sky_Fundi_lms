<?php

declare(strict_types=1);

namespace Core\Identity\Application;

use Core\Identity\Infrastructure\Models\Membership;
use Core\Users\Infrastructure\Models\User;
use Illuminate\Http\Request;

/** Resolves the active organisation from an explicit request header or a member default. */
final class OrganizationContextService
{
    public function membership(User $user, ?string $organizationId = null): ?Membership
    {
        $query = Membership::query()->with(['role.permissions', 'organization.modules', 'organization.aiConfiguration'])
            ->where('user_id', $user->getKey())->where('status', 'active')
            ->when($organizationId, fn ($query) => $query->where('organization_id', $organizationId));

        if ($organizationId !== null) {
            return $query->first();
        }

        return (clone $query)->where('is_default', true)->first() ?? $query->first();
    }

    public function fromRequest(Request $request): ?Membership
    {
        return $request->user() ? $this->membership($request->user(), $request->header('X-Organization-Id')) : null;
    }
}
