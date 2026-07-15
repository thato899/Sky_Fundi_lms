<?php

declare(strict_types=1);

namespace Modules\Learners\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class ArchiveLearnerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('archive', $this->route('learner')) ?? false;
    }

    public function rules(): array
    {
        return ['reason' => ['nullable', 'string', 'max:2000']];
    }
}
