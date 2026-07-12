<?php

declare(strict_types=1);

namespace Core\Subscriptions\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class StoreSubscriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('core.billing.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'subscriber_type' => ['sometimes', 'nullable', 'string'],
            'subscriber_id' => ['sometimes', 'nullable', 'uuid'],
            'license_id' => ['sometimes', 'nullable', 'uuid', 'exists:licenses,id'],
            'plan' => ['required', 'string', 'max:255'],
            'billing_cycle' => ['required', 'string', 'in:monthly,annual,lifetime,custom'],
            'started_at' => ['sometimes', 'date'],
            'renewal_date' => ['sometimes', 'nullable', 'date'],
            'max_users' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'max_storage_mb' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'module_access' => ['sometimes', 'nullable', 'array'],
        ];
    }
}
