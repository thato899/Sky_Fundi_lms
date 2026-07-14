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
        return Membership::query()->with(['role.permissions', 'organization.modules', 'organization.aiConfiguration'])
            ->where('user_id', $user->id)->where('status', 'active')
            ->when($organizationId, fn ($query) => $query->where('organization_id', $organizationId), fn ($query) => $query->where('is_default', true))
            ->first() ?? Membership::query()->with(['role.permissions', 'organization.modules', 'organization.aiConfiguration'])->where('user_id', $user->id)->where('status', 'active')->first();
    }

    public function fromRequest(Request $request): ?Membership
    {
        return $request->user() ? $this->membership($request->user(), $request->header('X-Organization-Id')) : null;
    }
}
