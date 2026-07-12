<?php

declare(strict_types=1);

namespace Core\FeatureFlags\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class DefineFeatureFlagRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('core.feature-flags.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'key' => ['required', 'string', 'max:255', 'regex:/^[a-z0-9_.-]+$/'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
