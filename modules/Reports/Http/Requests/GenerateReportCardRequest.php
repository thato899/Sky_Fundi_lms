<?php

declare(strict_types=1);

namespace Modules\Reports\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Reports\Infrastructure\Models\ReportCard;

final class GenerateReportCardRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('generate', ReportCard::class) === true;
    }

    public function rules(): array
    {
        return ['organization_id' => ['prohibited'], 'learner_id' => ['required', 'uuid'], 'reporting_period_id' => ['required', 'uuid'], 'grading_scale_id' => ['required', 'uuid'], 'report_card_template_id' => ['required', 'uuid']];
    }
}
