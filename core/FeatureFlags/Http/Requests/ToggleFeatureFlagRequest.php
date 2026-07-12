<?php

declare(strict_types=1);

namespace Core\FeatureFlags\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class ToggleFeatureFlagRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('core.feature-flags.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'enabled' => ['required', 'boolean'],
            'scope_type' => ['sometimes', 'string', 'in:organization,user,module'],
            'scope_id' => ['required_with:scope_type', 'string'],
        ];
    }
}
