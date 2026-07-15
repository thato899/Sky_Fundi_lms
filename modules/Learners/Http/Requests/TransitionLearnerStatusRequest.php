<?php

declare(strict_types=1);

namespace Modules\Learners\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Learners\Domain\Enums\LearnerStatus;

final class TransitionLearnerStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manageStatus', $this->route('learner')) ?? false;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(LearnerStatus::class), Rule::notIn([LearnerStatus::Archived->value])],
            'reason' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
