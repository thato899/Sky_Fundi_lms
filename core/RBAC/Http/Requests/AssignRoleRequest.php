<?php

declare(strict_types=1);

namespace Core\RBAC\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class AssignRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('core.roles.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'role_id' => ['required', 'uuid', 'exists:roles,id'],
        ];
    }
}
