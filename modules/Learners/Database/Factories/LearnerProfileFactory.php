<?php

declare(strict_types=1);

namespace Modules\Learners\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Learners\Domain\Enums\LearnerStatus;
use Modules\Learners\Infrastructure\Models\LearnerProfile;

/**
 * @extends Factory<LearnerProfile>
 */
final class LearnerProfileFactory extends Factory
{
    protected $model = LearnerProfile::class;

    public function definition(): array
    {
        return [
            'learner_number' => 'LRN-'.fake()->unique()->numerify('########'),
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'date_of_birth' => fake()->optional()->dateTimeBetween('-20 years', '-5 years'),
            'learner_status' => LearnerStatus::Pending,
            'onboarding_status' => 'pending',
            'portal_access_enabled' => false,
        ];
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'learner_status' => LearnerStatus::Active,
        ]);
    }

    public function suspended(): static
    {
        return $this->state(fn (array $attributes) => [
            'learner_status' => LearnerStatus::Suspended,
        ]);
    }

    public function archived(): static
    {
        return $this->state(fn (array $attributes) => [
            'learner_status' => LearnerStatus::Archived,
            'archived_at' => now(),
        ]);
    }
}
