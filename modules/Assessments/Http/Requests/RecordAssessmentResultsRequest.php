<?php

declare(strict_types=1);

namespace Modules\Assessments\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Assessments\Domain\Enums\AssessmentResultStatus;

final class RecordAssessmentResultsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('mark', $this->route('assessment')) ?? false;
    }

    public function rules(): array
    {
        return ['results' => ['required', 'array'], 'results.*.result_uuid' => ['required', 'uuid', 'distinct'], 'results.*.result_status' => ['required', Rule::enum(AssessmentResultStatus::class)], 'results.*.score' => ['nullable'], 'results.*.feedback' => ['nullable', 'string', 'max:2000'], 'results.*.private_note' => ['nullable', 'string', 'max:2000']];
    }
}
