<?php

use App\Models\LessonPlanFamily;
use App\Models\LessonPlanVersion;
use App\Models\Subject;
use App\Models\SubjectGrade;

test('getFilename returns correct format', function () {
    $subject = Subject::factory()->create(['name' => 'English']);
    $sg = SubjectGrade::factory()->create(['subject_id' => $subject->id, 'grade' => 10]);
    $family = LessonPlanFamily::factory()->create(['subject_grade_id' => $sg->id, 'day' => '1']);
    $version = LessonPlanVersion::factory()->create([
        'lesson_plan_family_id' => $family->id,
        'version' => '1.1.1',
    ]);

    expect($version->getFilename())->toBe('ENGL_10_1_REV_1.1.1.md');
});

test('getFilename truncates subject name to 4 characters', function () {
    $subject = Subject::factory()->create(['name' => 'Mathematics']);
    $sg = SubjectGrade::factory()->create(['subject_id' => $subject->id, 'grade' => 4]);
    $family = LessonPlanFamily::factory()->create(['subject_grade_id' => $sg->id, 'day' => '3']);
    $version = LessonPlanVersion::factory()->create([
        'lesson_plan_family_id' => $family->id,
        'version' => '2.0.0',
    ]);

    expect($version->getFilename())->toBe('MATH_4_3_REV_2.0.0.md');
});

test('getFilename auto-loads relations when not loaded', function () {
    $subject = Subject::factory()->create(['name' => 'Science']);
    $sg = SubjectGrade::factory()->create(['subject_id' => $subject->id, 'grade' => 7]);
    $family = LessonPlanFamily::factory()->create(['subject_grade_id' => $sg->id, 'day' => '2']);
    $version = LessonPlanVersion::factory()->create([
        'lesson_plan_family_id' => $family->id,
        'version' => '1.0.0',
    ]);

    // Reload from DB with no eager-loaded relations.
    $fresh = LessonPlanVersion::find($version->id);

    expect($fresh->relationLoaded('family'))->toBeFalse();
    expect($fresh->getFilename())->toBe('SCIE_7_2_REV_1.0.0.md');
});
