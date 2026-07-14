<?php

declare(strict_types=1);

namespace Modules\Learners\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class RestoreLearnerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('restore', $this->route('learner')) ?? false;
    }

    public function rules(): array
    {
        return ['reason' => ['required', 'string', 'max:2000']];
    }
}
