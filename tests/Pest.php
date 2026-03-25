<?php

use App\Models\LessonPlanFamily;
use App\Models\LessonPlanVersion;
use App\Models\SubjectGrade;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature', 'Unit');

// --- Helpers ---

function makeTeacher(): User
{
    return User::factory()->create();
}

function makeSiteAdmin(): User
{
    $user = User::factory()->create();
    $user->assignRole('site_administrator');

    return $user;
}

function makeSubjectGrade(): SubjectGrade
{
    return SubjectGrade::factory()->create();
}

function makeSubjectAdmin(SubjectGrade $sg): User
{
    $user = User::factory()->create();
    $sg->subject_admin_user_id = $user->id;
    $sg->save();

    return $user;
}

function makeEditor(SubjectGrade $sg): User
{
    $user = User::factory()->create();
    DB::table('subject_grade_user')->insert([
        'subject_grade_id' => $sg->id,
        'user_id' => $user->id,
        'role' => 'editor',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return $user;
}

function makeFamilyWithVersion(SubjectGrade $sg, string $language = 'en'): array
{
    $family = LessonPlanFamily::factory()->create([
        'subject_grade_id' => $sg->id,
        'language' => $language,
    ]);
    $version = LessonPlanVersion::factory()->create([
        'lesson_plan_family_id' => $family->id,
        'version' => '1.0.0',
    ]);

    return [$family, $version];
}
