<?php

declare(strict_types=1);

namespace Modules\Staff\Http\Requests;

use Core\Identity\Application\PermissionResolver;
use Core\Identity\Infrastructure\Models\Membership;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreStaffRequest extends FormRequest
{
    public function authorize(): bool
    {
        $membership = $this->attributes->get('organization_membership');

        return $membership instanceof Membership && app(PermissionResolver::class)->allows($membership, 'staff.create');
    }

    public function rules(): array
    {
        $organization = $this->attributes->get('organization');

        return [
            'employee_number' => ['required', 'string', 'max:100', Rule::unique('staff_profiles')->where('organization_id', $organization?->getKey())],
            'title' => ['nullable', 'string', 'max:50'],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email:rfc', 'max:255', Rule::unique('users', 'email')],
            'phone' => ['nullable', 'string', 'max:50'],
            'staff_type' => ['required', Rule::in(['teacher', 'tutor', 'administrator', 'support', 'other'])],
            'department_id' => ['nullable', 'uuid', Rule::exists('academics_departments', 'id')->where('organization_id', $organization?->getKey())],
            'employment_status' => ['required', Rule::in(['invited', 'active', 'suspended'])],
            'portal_access_enabled' => ['sometimes', 'boolean'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
