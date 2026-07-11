<?php

declare(strict_types=1);

namespace Core\Notifications\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class UpdatePreferenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', 'string', 'max:255'],
            'channel' => ['required', 'string', 'in:mail,database,push,sms'],
            'enabled' => ['required', 'boolean'],
        ];
    }
}
