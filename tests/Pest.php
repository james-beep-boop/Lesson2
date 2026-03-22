<?php

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature', 'Unit');

// --- Helpers ---

function makeTeacher(): \App\Models\User
{
    return \App\Models\User::factory()->create();
}

function makeSiteAdmin(): \App\Models\User
{
    $user = \App\Models\User::factory()->create();
    $user->assignRole('site_administrator');
    return $user;
}

function makeSubjectGrade(): \App\Models\SubjectGrade
{
    return \App\Models\SubjectGrade::factory()->create();
}

function makeSubjectAdmin(\App\Models\SubjectGrade $sg): \App\Models\User
{
    $user = \App\Models\User::factory()->create();
    $sg->update(['subject_admin_user_id' => $user->id]);
    return $user;
}

function makeEditor(\App\Models\SubjectGrade $sg): \App\Models\User
{
    $user = \App\Models\User::factory()->create();
    \DB::table('subject_grade_user')->insert([
        'subject_grade_id' => $sg->id,
        'user_id' => $user->id,
        'role' => 'editor',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    return $user;
}

function makeFamilyWithVersion(\App\Models\SubjectGrade $sg, string $language = 'en'): array
{
    $family = \App\Models\LessonPlanFamily::factory()->create([
        'subject_grade_id' => $sg->id,
        'language' => $language,
    ]);
    $version = \App\Models\LessonPlanVersion::factory()->create([
        'lesson_plan_family_id' => $family->id,
        'version' => '1.0.0',
    ]);
    return [$family, $version];
}
