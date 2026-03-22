<?php

namespace Database\Factories;

use App\Models\LessonPlanVersion;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class DeletionRequestFactory extends Factory
{
    public function definition(): array
    {
        return [
            'lesson_plan_version_id' => LessonPlanVersion::factory(),
            'requested_by_user_id' => User::factory(),
            'reason' => fake()->optional()->sentence(),
            'resolved_at' => null,
            'resolved_by_user_id' => null,
        ];
    }
}
