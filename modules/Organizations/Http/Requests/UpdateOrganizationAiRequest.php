<?php

declare(strict_types=1);

namespace Modules\Organizations\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateOrganizationAiRequest extends FormRequest
{
    public function authorize(): bool { return $this->user()?->can('organizations.ai.manage') ?? false; }
    public function rules(): array { return ['provider' => ['nullable', 'string', 'max:100'], 'credentials' => ['nullable', 'array'], 'configuration' => ['nullable', 'array']]; }
}
