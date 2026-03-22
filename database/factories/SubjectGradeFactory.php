<?php

namespace Database\Factories;

use App\Models\Subject;
use Illuminate\Database\Eloquent\Factories\Factory;

class SubjectGradeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'subject_id' => Subject::factory(),
            'grade' => fake()->numberBetween(1, 12),
            'subject_admin_user_id' => null,
        ];
    }
}
