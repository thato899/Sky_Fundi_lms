<?php

declare(strict_types=1);

namespace Modules\Assessments\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Assessments\Infrastructure\Models\AssessmentResult;

final class AssessmentResultFactory extends Factory
{
    protected $model = AssessmentResult::class;

    public function definition(): array
    {
        return ['result_status' => 'pending'];
    }
}
