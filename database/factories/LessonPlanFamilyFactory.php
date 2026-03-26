<?php

namespace Database\Factories;

use App\Models\SubjectGrade;
use Illuminate\Database\Eloquent\Factories\Factory;

class LessonPlanFamilyFactory extends Factory
{
    public function definition(): array
    {
        return [
            'subject_grade_id' => SubjectGrade::factory(),
            'day' => (string) fake()->numberBetween(1, 5),
            'strand_number' => fake()->numberBetween(1, 10),
            'strand_name' => fake()->words(3, true),
            'substrand_number' => fake()->numberBetween(1, 5),
            'substrand_name' => fake()->words(3, true),
            'official_version_id' => null,
        ];
    }
}
