<?php

declare(strict_types=1);

namespace Core\Subscriptions\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class RecordUsageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('core.billing.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'current_users' => ['sometimes', 'integer', 'min:0'],
            'current_storage_mb' => ['sometimes', 'integer', 'min:0'],
            'ai_usage' => ['sometimes', 'array'],
        ];
    }
}
