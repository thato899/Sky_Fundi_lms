<?php

declare(strict_types=1);

namespace Core\Settings\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('core.settings.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'group' => ['required', 'string', 'max:255'],
            'values' => ['required', 'array'],
            'encrypted_keys' => ['sometimes', 'array'],
            'encrypted_keys.*' => ['string'],
        ];
    }
}
