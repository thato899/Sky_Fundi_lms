<?php

declare(strict_types=1);

namespace Core\Auth\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class DisableTwoFactorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        // Not the `current_password` rule: it validates against the
        // default ('web') guard, which would reject a correct password
        // on API requests authenticated via the 'sanctum' guard instead.
        // The controller checks the hash directly against $request->user().
        return ['password' => ['required', 'string']];
    }
}
