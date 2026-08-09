<?php

declare(strict_types=1);

namespace Modules\Staff\Http\Requests;

use Core\Identity\Application\PermissionResolver;
use Core\Identity\Infrastructure\Models\Membership;
use Illuminate\Foundation\Http\FormRequest;

final class StoreTeachingAssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $membership = $this->attributes->get('organization_membership');

        return $membership instanceof Membership && app(PermissionResolver::class)->allows($membership, 'teaching_assignments.manage');
    }

    public function rules(): array
    {
        return [
            'class_id' => ['required', 'uuid'],
            'subject_id' => ['nullable', 'uuid'],
            'academic_year_id' => ['required', 'uuid'],
            'started_on' => ['nullable', 'date_format:Y-m-d'],
        ];
    }
}
