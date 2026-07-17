<?php

declare(strict_types=1);

namespace Modules\Learners\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Learners\Infrastructure\Models\GuardianProfile;

final class StoreGuardianRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', GuardianProfile::class) ?? false;
    }

    public function rules(): array
    {
        return self::profileRules();
    }

    public static function profileRules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:120'],
            'last_name' => ['required', 'string', 'max:120'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'preferred_communication_channel' => ['required', Rule::in(['email', 'sms', 'phone', 'none'])],
            'address' => ['nullable', 'string', 'max:2000'],
            'status' => ['sometimes', Rule::in(['active', 'inactive'])],
            'organization_membership_id' => ['nullable', 'uuid'],
        ];
    }
}
