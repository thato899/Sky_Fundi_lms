<?php

declare(strict_types=1);

namespace Modules\Materials\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Materials\Infrastructure\Models\Material;

final class StoreMaterialRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Material::class) ?? false;
    }

    public function rules(): array
    {
        $organization = $this->attributes->get('organization');

        return [
            'title' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string', 'min:50', 'max:200000'],
            'subject_id' => ['nullable', 'uuid', Rule::exists('academics_subjects', 'id')->where('organization_id', $organization?->getKey())],
        ];
    }
}
