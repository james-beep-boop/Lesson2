<?php

use App\Models\LessonPlanFamily;
use App\Models\LessonPlanVersion;
use App\Services\VersionService;

test('first normal family version is 1.0.0', function () {
    $sg = makeSubjectGrade();
    $contributor = makeTeacher();
    $service = new VersionService;

    $version = $service->createFamilyWithFirstVersion(
        $sg->id, '1', 'en', '# Content', null, $contributor
    );

    expect($version->version)->toBe('1.0.0');
    expect($version->family)->not->toBeNull();
});

test('patch bump increments patch', function () {
    $service = new VersionService;
    $sg = makeSubjectGrade();
    [$family, $v1] = makeFamilyWithVersion($sg);

    $v2 = $service->addVersion($family, '# Updated', 'patch', null, $v1->contributor);
    expect($v2->version)->toBe('1.0.1');
});

test('minor bump increments minor and resets patch', function () {
    $service = new VersionService;
    $sg = makeSubjectGrade();
    [$family, $v1] = makeFamilyWithVersion($sg);

    $v2 = $service->addVersion($family, '# Updated', 'minor', null, $v1->contributor);
    expect($v2->version)->toBe('1.1.0');
});

test('major bump increments major and resets minor and patch', function () {
    $service = new VersionService;
    $sg = makeSubjectGrade();
    [$family, $v1] = makeFamilyWithVersion($sg);

    $v2 = $service->addVersion($family, '# Updated', 'major', null, $v1->contributor);
    expect($v2->version)->toBe('2.0.0');
});

test('saving a new family creates family and version in one transaction', function () {
    $sg = makeSubjectGrade();
    $contributor = makeTeacher();
    $service = new VersionService;

    $familyCountBefore = LessonPlanFamily::count();
    $versionCountBefore = LessonPlanVersion::count();

    $version = $service->createFamilyWithFirstVersion(
        $sg->id, '2', 'en', '# Content', null, $contributor
    );

    expect(LessonPlanFamily::count())->toBe($familyCountBefore + 1);
    expect(LessonPlanVersion::count())->toBe($versionCountBefore + 1);
    expect($version->lesson_plan_family_id)->toBeTruthy();
});

test('official_version_id logic works atomically', function () {
    $service = new VersionService;
    $sg = makeSubjectGrade();
    [$family, $v1] = makeFamilyWithVersion($sg);
    $contributor = makeTeacher();
    $v2 = $service->addVersion($family, '# v2', 'minor', null, $contributor);

    $service->setOfficialVersion($family, $v1);
    expect($family->fresh()->official_version_id)->toBe($v1->id);

    $service->setOfficialVersion($family, $v2);
    expect($family->fresh()->official_version_id)->toBe($v2->id);

    $service->setOfficialVersion($family, null);
    expect($family->fresh()->official_version_id)->toBeNull();
});

test('version numbers must be unique within a family', function () {
    $service = new VersionService;
    $sg = makeSubjectGrade();
    [$family, $v1] = makeFamilyWithVersion($sg);
    $contributor = makeTeacher();

    // Attempt to create a version with the same number should throw
    expect(fn () => LessonPlanVersion::create([
        'lesson_plan_family_id' => $family->id,
        'contributor_id' => $contributor->id,
        'version' => '1.0.0',
        'content' => '# duplicate',
    ]))->toThrow(\Illuminate\Database\QueryException::class);
});
