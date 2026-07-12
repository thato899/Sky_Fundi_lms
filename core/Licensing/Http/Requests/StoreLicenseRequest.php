<?php

declare(strict_types=1);

namespace Core\Licensing\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class StoreLicenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('core.licenses.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'tier' => ['required', 'string', 'in:trial,starter,professional,enterprise,government,custom'],
            'status' => ['sometimes', 'string', 'in:pending_activation,active,suspended,expired,cancelled'],
            'licensee_type' => ['sometimes', 'nullable', 'string'],
            'licensee_id' => ['sometimes', 'nullable', 'uuid'],
            'expiry_date' => ['sometimes', 'nullable', 'date'],
            'renewal_date' => ['sometimes', 'nullable', 'date'],
            'max_users' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'max_storage_mb' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'enabled_modules' => ['sometimes', 'nullable', 'array'],
            'enabled_modules.*' => ['string'],
            'ai_provider' => ['sometimes', 'nullable', 'string'],
            'support_level' => ['sometimes', 'nullable', 'string'],
            'metadata' => ['sometimes', 'nullable', 'array'],
        ];
    }
}
