<?php

declare(strict_types=1);

namespace Core\Branding\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateBrandingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('core.branding.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'platform_name' => ['sometimes', 'string', 'max:255'],
            'company_name' => ['sometimes', 'string', 'max:255'],
            'support_email' => ['sometimes', 'email', 'max:255'],
            'logo_path' => ['sometimes', 'nullable', 'string', 'max:2048'],
            'favicon_path' => ['sometimes', 'nullable', 'string', 'max:2048'],
            'primary_colour' => ['sometimes', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'secondary_colour' => ['sometimes', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'login_background_path' => ['sometimes', 'nullable', 'string', 'max:2048'],
        ];
    }
}
