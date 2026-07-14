<?php

declare(strict_types=1);

namespace Modules\Organizations\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateOrganizationModuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('organizations.modules.manage') ?? false;
    }

    public function rules(): array
    {
        return ['module_name' => ['required', 'string', 'max:100'], 'enabled' => ['required', 'boolean']];
    }
}
