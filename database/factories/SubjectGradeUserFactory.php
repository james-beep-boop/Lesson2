<?php

namespace Database\Factories;

use App\Models\SubjectGrade;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class SubjectGradeUserFactory extends Factory
{
    public function definition(): array
    {
        return [
            'subject_grade_id' => SubjectGrade::factory(),
            'user_id' => User::factory(),
            'role' => 'editor',
        ];
    }
}
