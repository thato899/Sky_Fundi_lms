<?php

declare(strict_types=1);

namespace Modules\Learners\Http\Resources;

use Core\Identity\Domain\Enums\MembershipStatus;
use Core\Identity\Infrastructure\Models\Membership;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class GuardianInvitationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $membership = $this->resource;
        assert($membership instanceof Membership);
        $status = $membership->status === MembershipStatus::Invited && $membership->invitation_expires_at?->isPast()
            ? MembershipStatus::Expired->value
            : $membership->status->value;

        return [
            'id' => $membership->getKey(),
            'email' => $membership->invited_email,
            'status' => $status,
            'sent_at' => $membership->invitation_sent_at?->toIso8601String(),
            'expires_at' => $membership->invitation_expires_at?->toIso8601String(),
            'accepted_at' => $membership->accepted_at?->toIso8601String(),
            'revoked_at' => $membership->revoked_at?->toIso8601String(),
            'resend_count' => $membership->resend_count,
        ];
    }
}
