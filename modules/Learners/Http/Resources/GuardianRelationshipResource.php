<?php

declare(strict_types=1);

namespace Modules\Learners\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Learners\Infrastructure\Models\LearnerGuardianRelationship;

final class GuardianRelationshipResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $relationship = $this->resource;
        assert($relationship instanceof LearnerGuardianRelationship);
        $guardian = $relationship->relationLoaded('guardian')
            ? $relationship->getRelation('guardian')
            : null;
        $canManage = $guardian !== null
            && ($request->user()?->can('manageRelationships', $guardian) ?? false);

        return [
            'uuid' => $relationship->getAttribute('uuid'),
            'relationship_type' => $relationship->getAttribute('relationship_type'),
            'is_primary' => $this->when($canManage, $relationship->getAttribute('is_primary')),
            'is_emergency_contact' => $this->when($canManage, $relationship->getAttribute('is_emergency_contact')),
            'is_authorized_pickup' => $this->when($canManage, $relationship->getAttribute('is_authorized_pickup')),
            'receives_academic_communication' => $this->when($canManage, $relationship->getAttribute('receives_academic_communication')),
            'receives_financial_communication' => $this->when($canManage, $relationship->getAttribute('receives_financial_communication')),
            'status' => $relationship->getAttribute('status'),
            'effective_from' => $relationship->getAttribute('effective_from')?->toDateString(),
            'effective_until' => $relationship->getAttribute('effective_until')?->toDateString(),
            'guardian' => new GuardianResource($this->whenLoaded('guardian')),
        ];
    }
}
