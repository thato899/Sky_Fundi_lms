<?php

declare(strict_types=1);

namespace Modules\Learners\Http\Resources;

use Core\Identity\Infrastructure\Models\Membership;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Learners\Domain\Enums\GuardianStatus;
use Modules\Learners\Infrastructure\Models\GuardianProfile;

final class GuardianResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $guardian = $this->resource;
        assert($guardian instanceof GuardianProfile);
        $status = $guardian->getAttribute('status');
        assert($status instanceof GuardianStatus);
        /** @var Membership|null $membership */
        $membership = $guardian->relationLoaded('organizationMembership')
            ? $guardian->getRelation('organizationMembership')
            : null;
        $canManage = ($request->user()?->can('update', $guardian) ?? false)
            || ($request->user()?->can('manageRelationships', $guardian) ?? false);

        return [
            'uuid' => $guardian->getAttribute('uuid'),
            'first_name' => $guardian->getAttribute('first_name'),
            'last_name' => $guardian->getAttribute('last_name'),
            'email' => $guardian->getAttribute('email'),
            'phone' => $guardian->getAttribute('phone'),
            'preferred_communication_channel' => $guardian->getAttribute('preferred_communication_channel'),
            'address' => $this->when($request->user()?->can('update', $guardian) ?? false, $guardian->getAttribute('address')),
            'status' => $status->value,
            'identity_linked' => $this->when($canManage, $guardian->getAttribute('user_id') !== null),
            'invitation_state' => $this->when($canManage, $membership?->getAttribute('status')?->value),
            'archived_at' => $guardian->getAttribute('archived_at')?->toAtomString(),
            'created_at' => $guardian->getAttribute('created_at')?->toAtomString(),
            'updated_at' => $guardian->getAttribute('updated_at')?->toAtomString(),
        ];
    }
}
