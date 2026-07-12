<?php

declare(strict_types=1);

namespace Core\Licensing\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class RenewLicenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('core.licenses.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'expiry_date' => ['required', 'date', 'after:today'],
        ];
    }
}
