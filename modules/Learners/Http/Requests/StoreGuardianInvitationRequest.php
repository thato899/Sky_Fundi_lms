<?php

declare(strict_types=1);

namespace Modules\Learners\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class StoreGuardianInvitationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return ['email' => ['required', 'email:rfc', 'max:255']];
    }
}
