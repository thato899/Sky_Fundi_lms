<?php

declare(strict_types=1);

namespace Modules\Staff\Http\Requests;

use Core\Identity\Application\PermissionResolver;
use Core\Identity\Infrastructure\Models\Membership;
use Illuminate\Foundation\Http\FormRequest;

final class BulkStoreTeachingAssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $membership = $this->attributes->get('organization_membership');

        return $membership instanceof Membership && app(PermissionResolver::class)->allows($membership, 'teaching_assignments.manage');
    }

    public function rules(): array
    {
        return [
            'assignments' => ['required', 'array', 'min:1', 'max:200'],
            'assignments.*.staff_profile_id' => ['required', 'uuid'],
            'assignments.*.class_id' => ['required', 'uuid'],
            'assignments.*.subject_id' => ['nullable', 'uuid'],
            'assignments.*.academic_year_id' => ['required', 'uuid'],
            'assignments.*.started_on' => ['nullable', 'date_format:Y-m-d'],
        ];
    }
}
