<?php

declare(strict_types=1);

namespace Modules\Organizations\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateOrganizationSettingsRequest extends FormRequest
{
    public function authorize(): bool { return $this->user()?->can('organizations.settings.manage') ?? false; }
    public function rules(): array { return ['settings' => ['required', 'array'], 'settings.*' => ['array'], 'settings.*.*' => ['nullable']]; }
}
