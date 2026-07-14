<?php

declare(strict_types=1);

namespace Modules\Organizations\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class AssignOrganizationAdministratorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('organizations.users.manage') ?? false;
    }

    public function rules(): array
    {
        return ['user_id' => ['required', 'uuid', 'exists:users,id']];
    }
}
