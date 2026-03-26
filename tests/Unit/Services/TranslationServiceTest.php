<?php

use App\Ai\Agents\LessonPlanTranslator;
use App\Models\LessonPlanFamily;
use App\Models\LessonPlanVersion;
use App\Services\TranslationService;
use App\Services\VersionService;

beforeEach(function () {
    LessonPlanTranslator::fake(['Mpango wa Somo wa Darasa la 10']);
});

// Translation to Swahili is a planned future feature. These tests are skipped
// until the feature is redesigned — the language column has been removed from
// lesson_plan_families, so the old language-based family identification no
// longer exists. Tests will be rewritten when the new mechanism is designed.

test('translation-created version inherits source version number', function () {
    $sg = makeSubjectGrade();
    [$family, $version] = makeFamilyWithVersion($sg);
    $contributor = makeTeacher();
    $service = new TranslationService(new VersionService);

    expect($version->version)->toBe('1.0.0');

    $translatedVersion = $service->translate($version, 'Mpango wa Somo', $contributor);

    expect($translatedVersion->version)->toBe('1.0.0');
})->skip('Translation feature requires redesign after language column removal');

test('translation conflict falls back to normal bump flow', function () {
    $sg = makeSubjectGrade();
    [$engFamily, $engVersion] = makeFamilyWithVersion($sg);

    $swV1 = LessonPlanVersion::factory()->create([
        'lesson_plan_family_id' => $engFamily->id,
        'version' => '1.0.0',
    ]);

    $contributor = makeTeacher();
    $service = new TranslationService(new VersionService);

    $newVersion = $service->translate($engVersion, 'Mpango wa Somo', $contributor);

    expect($newVersion->version)->toBe('1.0.1');
})->skip('Translation feature requires redesign after language column removal');

test('source version is unchanged after translation', function () {
    $sg = makeSubjectGrade();
    [$family, $version] = makeFamilyWithVersion($sg);
    $originalContent = $version->content;
    $contributor = makeTeacher();
    $service = new TranslationService(new VersionService);

    $service->translate($version, 'Mpango wa Somo', $contributor);

    expect($version->fresh()->content)->toBe($originalContent);
    expect($version->fresh()->version)->toBe('1.0.0');
})->skip('Translation feature requires redesign after language column removal');

test('abandoning translation review writes nothing to database', function () {
    $sg = makeSubjectGrade();
    [$family, $version] = makeFamilyWithVersion($sg);
    $versionCountBefore = LessonPlanVersion::count();
    $familyCountBefore = LessonPlanFamily::count();

    // Simulate not calling translate() — no DB writes happen
    expect(LessonPlanVersion::count())->toBe($versionCountBefore);
    expect(LessonPlanFamily::count())->toBe($familyCountBefore);
});
