<?php

declare(strict_types=1);

namespace Modules\Learners\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

final class AcceptGuardianInvitationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['nullable', 'string', 'max:255'],
            'password' => ['required', 'string', Password::defaults()],
            'password_confirmation' => ['nullable', 'same:password'],
        ];
    }
}
