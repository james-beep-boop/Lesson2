<?php

namespace Database\Factories;

use App\Models\LessonPlanFamily;
use App\Models\LessonPlanVersion;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class FavoriteFactory extends Factory
{
    public function definition(): array
    {
        $family = LessonPlanFamily::factory()->create();
        $version = LessonPlanVersion::factory()->create(['lesson_plan_family_id' => $family->id]);

        return [
            'user_id' => User::factory(),
            'lesson_plan_family_id' => $family->id,
            'lesson_plan_version_id' => $version->id,
        ];
    }
}
