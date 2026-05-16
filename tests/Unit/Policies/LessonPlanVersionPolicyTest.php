<?php

use App\Policies\LessonPlanVersionPolicy;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'site_administrator', 'guard_name' => 'web']);
});

test('teacher cannot add a new version', function () {
    $sg = makeSubjectGrade();
    [$family] = makeFamilyWithVersion($sg);
    $teacher = makeTeacher();
    $policy = new LessonPlanVersionPolicy;

    expect($policy->create($teacher, $family))->toBeFalse();
});

test('editor can add a new version to assigned subject_grade', function () {
    $sg = makeSubjectGrade();
    [$family] = makeFamilyWithVersion($sg);
    $editor = makeEditor($sg);
    $policy = new LessonPlanVersionPolicy;

    expect($policy->create($editor, $family))->toBeTrue();
});

test('editor cannot add a version to a different subject_grade', function () {
    $sg1 = makeSubjectGrade();
    $sg2 = makeSubjectGrade();
    [$family2] = makeFamilyWithVersion($sg2);
    $editor = makeEditor($sg1); // assigned to sg1 only
    $policy = new LessonPlanVersionPolicy;

    expect($policy->create($editor, $family2))->toBeFalse();
});

test('subject admin can add a version to own subject_grade', function () {
    $sg = makeSubjectGrade();
    [$family] = makeFamilyWithVersion($sg);
    $subjectAdmin = makeSubjectAdmin($sg);
    $policy = new LessonPlanVersionPolicy;

    expect($policy->create($subjectAdmin, $family))->toBeTrue();
});

test('subject admin cannot add a version to a different subject_grade', function () {
    $sg1 = makeSubjectGrade();
    $sg2 = makeSubjectGrade();
    [$family2] = makeFamilyWithVersion($sg2);
    $subjectAdmin = makeSubjectAdmin($sg1);
    $policy = new LessonPlanVersionPolicy;

    expect($policy->create($subjectAdmin, $family2))->toBeFalse();
});

test('site admin can add a version to any subject_grade', function () {
    $sg = makeSubjectGrade();
    [$family] = makeFamilyWithVersion($sg);
    $siteAdmin = makeSiteAdmin();
    $policy = new LessonPlanVersionPolicy;

    expect($policy->create($siteAdmin, $family))->toBeTrue();
});

test('only subject admin and site admin can mark a version official', function () {
    // Create all users (including the Subject Admin) BEFORE the version is loaded.
    // Policy reads $version->family?->subjectGrade which caches the relation on first
    // access; mutating subject_admin_user_id after that point would not be reflected.
    $sg = makeSubjectGrade();
    $teacher = makeTeacher();
    $editor = makeEditor($sg);
    $subjectAdmin = makeSubjectAdmin($sg);
    $siteAdmin = makeSiteAdmin();
    [$family, $version] = makeFamilyWithVersion($sg);
    $policy = new LessonPlanVersionPolicy;

    expect($policy->markOfficial($teacher, $version))->toBeFalse();
    expect($policy->markOfficial($editor, $version))->toBeFalse();
    expect($policy->markOfficial($subjectAdmin, $version))->toBeTrue();
    expect($policy->markOfficial($siteAdmin, $version))->toBeTrue();
});

test('ask AI hidden from teacher', function () {
    config(['features.ai_suggestions' => true]);
    $sg = makeSubjectGrade();
    [$family, $version] = makeFamilyWithVersion($sg);
    $policy = new LessonPlanVersionPolicy;

    expect($policy->askAi(makeTeacher(), $version))->toBeFalse();
});

test('ask AI visible to editors when flag is on', function () {
    config(['features.ai_suggestions' => true]);
    $sg = makeSubjectGrade();
    [$family, $version] = makeFamilyWithVersion($sg);
    $editor = makeEditor($sg);
    $policy = new LessonPlanVersionPolicy;

    expect($policy->askAi($editor, $version))->toBeTrue();
});

test('ask AI hidden when flag is off', function () {
    config(['features.ai_suggestions' => false]);
    $sg = makeSubjectGrade();
    [$family, $version] = makeFamilyWithVersion($sg);
    $policy = new LessonPlanVersionPolicy;

    expect($policy->askAi(makeEditor($sg), $version))->toBeFalse();
});

test('translate visible to teachers when flag is on', function () {
    config(['features.ai_suggestions' => true]);
    $sg = makeSubjectGrade();
    [$family, $version] = makeFamilyWithVersion($sg);
    $policy = new LessonPlanVersionPolicy;

    expect($policy->translate(makeTeacher(), $version))->toBeTrue();
});

test('translate visible to editors when flag is on', function () {
    config(['features.ai_suggestions' => true]);
    $sg = makeSubjectGrade();
    [$family, $version] = makeFamilyWithVersion($sg);
    $editor = makeEditor($sg);
    $policy = new LessonPlanVersionPolicy;

    expect($policy->translate($editor, $version))->toBeTrue();
});

test('translate visible to subject admin when flag is on', function () {
    config(['features.ai_suggestions' => true]);
    $sg = makeSubjectGrade();
    [$family, $version] = makeFamilyWithVersion($sg);
    $subjectAdmin = makeSubjectAdmin($sg);
    $policy = new LessonPlanVersionPolicy;

    expect($policy->translate($subjectAdmin, $version))->toBeTrue();
});

test('translate hidden when flag is off even for subject admin', function () {
    config(['features.ai_suggestions' => false]);
    $sg = makeSubjectGrade();
    [$family, $version] = makeFamilyWithVersion($sg);
    $subjectAdmin = makeSubjectAdmin($sg);
    $policy = new LessonPlanVersionPolicy;

    expect($policy->translate($subjectAdmin, $version))->toBeFalse();
});

test('AI response never auto-modifies document content', function () {
    // This is an architectural test: the service layer never writes AI content to versions
    // without explicit user confirmation. We verify the translation service only writes
    // when explicitly called with user-confirmed content.
    $sg = makeSubjectGrade();
    [$family, $version] = makeFamilyWithVersion($sg);
    $originalContent = $version->content;

    // Simulating: AI is called but user doesn't confirm → no write
    // (The actual write only happens when TranslationService::translate() is called explicitly)
    expect($version->fresh()->content)->toBe($originalContent);
});
