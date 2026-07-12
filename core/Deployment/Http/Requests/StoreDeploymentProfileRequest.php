<?php

declare(strict_types=1);

namespace Core\Deployment\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class StoreDeploymentProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('core.deployment.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'subject_type' => ['sometimes', 'nullable', 'string'],
            'subject_id' => ['sometimes', 'nullable', 'uuid'],
            'strategy' => ['required', 'string', 'in:single_server,dedicated_server,cloud,docker,kubernetes'],
            'database_config' => ['sometimes', 'nullable', 'array'],
            'storage_config' => ['sometimes', 'nullable', 'array'],
            'branding_config' => ['sometimes', 'nullable', 'array'],
            'environment_config' => ['sometimes', 'nullable', 'array'],
            'ai_provider' => ['sometimes', 'nullable', 'string'],
            'modules' => ['sometimes', 'nullable', 'array'],
            'administrator_id' => ['sometimes', 'nullable', 'uuid', 'exists:users,id'],
            'metadata' => ['sometimes', 'nullable', 'array'],
        ];
    }
}
