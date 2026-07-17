<?php

declare(strict_types=1);

namespace Modules\Organizations\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Organizations\Domain\Enums\OrganizationStatus;

final class IndexOrganizationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', Rule::enum(OrganizationStatus::class)],
            'type' => ['nullable', Rule::in(array_keys(config('organizations.types')))],
            'sort' => ['nullable', Rule::in(['name', 'code', 'type', 'status', 'created_at'])],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
