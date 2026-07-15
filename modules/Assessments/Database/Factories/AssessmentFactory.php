<?php

declare(strict_types=1);

namespace Modules\Assessments\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Assessments\Infrastructure\Models\Assessment;

final class AssessmentFactory extends Factory
{
    protected $model = Assessment::class;

    public function definition(): array
    {
        return ['title' => 'Assessment '.$this->faker->unique()->bothify('####'), 'maximum_mark' => 100, 'status' => 'draft', 'result_release_status' => 'withheld'];
    }
}
