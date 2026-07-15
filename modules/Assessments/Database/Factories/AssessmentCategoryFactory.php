<?php

declare(strict_types=1);

namespace Modules\Assessments\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Assessments\Infrastructure\Models\AssessmentCategory;

final class AssessmentCategoryFactory extends Factory
{
    protected $model = AssessmentCategory::class;

    public function definition(): array
    {
        return ['name' => 'Category '.$this->faker->unique()->bothify('####'), 'code' => $this->faker->unique()->bothify('CAT-####'), 'is_active' => true, 'display_order' => 0];
    }
}
