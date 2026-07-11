<?php

declare(strict_types=1);

namespace Core\Modules\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class EnableModuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('core.modules.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'tenant_id' => ['sometimes', 'nullable', 'uuid'],
        ];
    }
}
