<?php

declare(strict_types=1);

namespace Core\Identity\Application;

use Core\Identity\Domain\Enums\MembershipStatus;
use Core\Identity\Infrastructure\Models\Membership;
use Illuminate\Support\Str;

final class MembershipService
{
    /** Creates an invitation; access is unavailable until accepted. */
    public function invite(array $attributes, ?string $actorId): Membership
    {
        return Membership::query()->updateOrCreate(['user_id' => $attributes['user_id'], 'organization_id' => $attributes['organization_id']], [...$attributes, 'status' => MembershipStatus::Invited, 'invited_by' => $actorId, 'invitation_token' => Str::random(64), 'invitation_expires_at' => now()->addDays(7)]);
    }

    public function accept(Membership $membership): Membership
    {
        $membership->update(['status' => MembershipStatus::Active, 'accepted_at' => now(), 'joined_at' => now(), 'invitation_token' => null]);

        return $membership->fresh();
    }

    public function reject(Membership $membership): Membership
    {
        $membership->update(['status' => MembershipStatus::Rejected]);

        return $membership->fresh();
    }

    public function suspend(Membership $membership): Membership
    {
        $membership->update(['status' => MembershipStatus::Suspended]);

        return $membership->fresh();
    }

    public function remove(Membership $membership): void
    {
        $membership->delete();
    }

    public function makeDefault(Membership $membership): Membership
    {
        Membership::query()->where('user_id', $membership->user_id)->update(['is_default' => false]);
        $membership->update(['is_default' => true, 'last_active_at' => now()]);

        return $membership->fresh();
    }
}
