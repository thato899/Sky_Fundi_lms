<?php

declare(strict_types=1);

namespace Modules\Learners\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateLearnerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('learner')) ?? false;
    }

    public function rules(): array
    {
        $rules = (new StoreLearnerRequest)->rules();
        unset($rules['learner_number'], $rules['current_academic_year_id'], $rules['current_grade_id'], $rules['current_class_id'], $rules['curriculum_id']);
        $rules['first_name'][0] = 'sometimes';
        $rules['last_name'][0] = 'sometimes';
        $rules['learner_number'] = ['prohibited'];

        return $rules;
    }
}
