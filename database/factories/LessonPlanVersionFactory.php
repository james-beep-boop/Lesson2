<?php

namespace Database\Factories;

use App\Models\LessonPlanFamily;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class LessonPlanVersionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'lesson_plan_family_id' => LessonPlanFamily::factory(),
            'contributor_id' => User::factory(),
            'version' => '1.0.0',
            'content' => '# Lesson Plan' . "\n\n" . fake()->paragraphs(3, true),
            'revision_note' => null,
        ];
    }
}
