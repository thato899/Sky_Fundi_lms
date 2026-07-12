<?php

declare(strict_types=1);

namespace Core\Security\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class AddIpRestrictionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('core.security.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', 'string', 'in:allow,deny'],
            'ip_cidr' => ['required', 'string', 'max:64'],
            'scope_type' => ['sometimes', 'string', 'in:platform,organization'],
            'scope_id' => ['required_if:scope_type,organization', 'nullable', 'string'],
            'description' => ['nullable', 'string', 'max:255'],
        ];
    }
}
