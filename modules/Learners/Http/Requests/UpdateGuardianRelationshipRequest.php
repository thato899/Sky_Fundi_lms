<?php

declare(strict_types=1);

namespace Modules\Learners\Http\Requests;

final class UpdateGuardianRelationshipRequest extends StoreGuardianRelationshipRequest
{
    public function rules(): array
    {
        $rules = parent::rules();
        unset($rules['guardian_uuid']);
        $rules['relationship_type'][0] = 'sometimes';

        return $rules;
    }
}
