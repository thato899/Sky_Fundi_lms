<?php

declare(strict_types=1);

namespace Modules\Organizations\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOrganizationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('organizations.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'], 'code' => ['required', 'string', 'max:80', 'alpha_dash', 'unique:organizations,code'],
            'type' => ['required', 'string', Rule::in(array_keys(config('organizations.types')))], 'status' => ['sometimes', Rule::in(['active', 'suspended', 'inactive'])],
            'email' => ['nullable', 'email'], 'telephone' => ['nullable', 'string', 'max:50'], 'website' => ['nullable', 'url'], 'country' => ['nullable', 'string', 'size:2'],
            'timezone' => ['sometimes', 'timezone'], 'language' => ['sometimes', 'string', 'max:10'], 'currency' => ['nullable', 'string', 'size:3'],
            'registration_number' => ['nullable', 'string', 'max:255'], 'tax_number' => ['nullable', 'string', 'max:255'], 'address' => ['nullable', 'string'], 'province' => ['nullable', 'string', 'max:255'], 'city' => ['nullable', 'string', 'max:255'], 'postal_code' => ['nullable', 'string', 'max:30'],
            'storage_quota' => ['sometimes', 'integer', 'min:0'], 'maximum_users' => ['sometimes', 'integer', 'min:0'], 'license_key' => ['nullable', 'string', 'max:255'], 'license_type' => ['nullable', 'string', 'max:255'], 'license_expires_at' => ['nullable', 'date'], 'license_renews_at' => ['nullable', 'date'], 'support_plan' => ['nullable', 'string', 'max:255'],
        ];
    }
}
