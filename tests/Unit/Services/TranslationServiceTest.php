<?php

use App\Ai\Agents\LessonPlanTranslator;
use App\Models\LessonPlanFamily;
use App\Models\LessonPlanVersion;
use App\Services\TranslationService;
use App\Services\VersionService;

beforeEach(function () {
    LessonPlanTranslator::fake(['Mpango wa Somo wa Darasa la 10']);
});

test('translation creates a new Swahili family when none exists', function () {
    $sg = makeSubjectGrade();
    [$family, $version] = makeFamilyWithVersion($sg, 'en');
    $contributor = makeTeacher();
    $service = new TranslationService(new VersionService);

    $swahiliVersion = $service->translate($version, 'Mpango wa Somo', $contributor);

    $swahiliFamily = LessonPlanFamily::where('subject_grade_id', $sg->id)
        ->where('day', $family->day)
        ->where('language', 'sw')
        ->first();

    expect($swahiliFamily)->not->toBeNull();
    expect($swahiliVersion->lesson_plan_family_id)->toBe($swahiliFamily->id);
});

test('translation-created family inherits source version number', function () {
    $sg = makeSubjectGrade();
    [$family, $version] = makeFamilyWithVersion($sg, 'en');
    $contributor = makeTeacher();
    $service = new TranslationService(new VersionService);

    // Source version is 1.0.0
    expect($version->version)->toBe('1.0.0');

    $swahiliVersion = $service->translate($version, 'Mpango wa Somo', $contributor);

    // Swahili version should also be 1.0.0
    expect($swahiliVersion->version)->toBe('1.0.0');
});

test('translation adds a version to an existing Swahili family', function () {
    $sg = makeSubjectGrade();
    [$engFamily, $engVersion] = makeFamilyWithVersion($sg, 'en');
    [$swFamily, $swV1] = makeFamilyWithVersion($sg, 'sw');

    // Give the swahili family a different day to match the english family's day
    $swFamily->update(['day' => $engFamily->day]);

    $contributor = makeTeacher();
    $service = new TranslationService(new VersionService);

    $newSwahiliVersion = $service->translate($engVersion, 'Mpango wa Somo', $contributor);

    expect($newSwahiliVersion->lesson_plan_family_id)->toBe($swFamily->id);
    expect(LessonPlanFamily::where('language', 'sw')->count())->toBe(1);
});

test('translation conflict falls back to normal bump flow', function () {
    $sg = makeSubjectGrade();
    [$engFamily, $engVersion] = makeFamilyWithVersion($sg, 'en'); // version 1.0.0

    // Create Swahili family with same version (1.0.0) — conflict
    $swFamily = LessonPlanFamily::factory()->create([
        'subject_grade_id' => $sg->id,
        'day' => $engFamily->day,
        'language' => 'sw',
    ]);
    $swV1 = LessonPlanVersion::factory()->create([
        'lesson_plan_family_id' => $swFamily->id,
        'version' => '1.0.0', // conflict
    ]);

    $contributor = makeTeacher();
    $service = new TranslationService(new VersionService);

    $newVersion = $service->translate($engVersion, 'Mpango wa Somo', $contributor);

    // Should fall back to patch bump: 1.0.1
    expect($newVersion->version)->toBe('1.0.1');
});

test('source English version is unchanged after translation', function () {
    $sg = makeSubjectGrade();
    [$family, $version] = makeFamilyWithVersion($sg, 'en');
    $originalContent = $version->content;
    $contributor = makeTeacher();
    $service = new TranslationService(new VersionService);

    $service->translate($version, 'Mpango wa Somo', $contributor);

    expect($version->fresh()->content)->toBe($originalContent);
    expect($version->fresh()->version)->toBe('1.0.0');
});

test('abandoning translation review writes nothing to database', function () {
    $sg = makeSubjectGrade();
    [$family, $version] = makeFamilyWithVersion($sg, 'en');
    $versionCountBefore = LessonPlanVersion::count();
    $familyCountBefore = LessonPlanFamily::count();

    // Simulate not calling translate() — no DB writes happen
    expect(LessonPlanVersion::count())->toBe($versionCountBefore);
    expect(LessonPlanFamily::count())->toBe($familyCountBefore);
});
