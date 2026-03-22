<?php

use App\Models\SubjectGrade;

beforeEach(function () {
    \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'site_administrator', 'guard_name' => 'web']);
});

test('teachers cannot edit lesson plans', function () {
    $sg = makeSubjectGrade();
    [$family] = makeFamilyWithVersion($sg);
    $teacher = makeTeacher();
    $policy = new \App\Policies\LessonPlanVersionPolicy;

    expect($policy->create($teacher, $family))->toBeFalse();
});

test('editors can edit only assigned subject_grades', function () {
    $sg1 = makeSubjectGrade();
    $sg2 = makeSubjectGrade();
    [$family1] = makeFamilyWithVersion($sg1);
    [$family2] = makeFamilyWithVersion($sg2);
    $editor = makeEditor($sg1);
    $policy = new \App\Policies\LessonPlanVersionPolicy;

    expect($policy->create($editor, $family1))->toBeTrue();
    expect($policy->create($editor, $family2))->toBeFalse();
});

test('editor can view lesson plans from any subject_grade', function () {
    $sg1 = makeSubjectGrade();
    $sg2 = makeSubjectGrade();
    [$family2, $version2] = makeFamilyWithVersion($sg2);
    $editor = makeEditor($sg1); // assigned only to sg1
    $policy = new \App\Policies\LessonPlanVersionPolicy;

    // View is universal
    expect($policy->view($editor, $version2))->toBeTrue();
});

test('subject admin can manage only own subject_grades', function () {
    $sg1 = makeSubjectGrade();
    $sg2 = makeSubjectGrade();
    [$family1, $version1] = makeFamilyWithVersion($sg1);
    [$family2, $version2] = makeFamilyWithVersion($sg2);
    $subjectAdmin = makeSubjectAdmin($sg1);
    $policy = new \App\Policies\LessonPlanVersionPolicy;

    expect($policy->create($subjectAdmin, $family1))->toBeTrue();
    expect($policy->markOfficial($subjectAdmin, $version1))->toBeTrue();
    expect($policy->create($subjectAdmin, $family2))->toBeFalse();
    expect($policy->markOfficial($subjectAdmin, $version2))->toBeFalse();
});

test('site admin can manage all subject_grades and users', function () {
    $sg = makeSubjectGrade();
    [$family, $version] = makeFamilyWithVersion($sg);
    $siteAdmin = makeSiteAdmin();
    $versionPolicy = new \App\Policies\LessonPlanVersionPolicy;
    $userPolicy = new \App\Policies\UserPolicy;
    $sgPolicy = new \App\Policies\SubjectGradePolicy;

    expect($versionPolicy->create($siteAdmin, $family))->toBeTrue();
    expect($versionPolicy->markOfficial($siteAdmin, $version))->toBeTrue();
    expect($userPolicy->viewAny($siteAdmin))->toBeTrue();
    expect($sgPolicy->create($siteAdmin))->toBeTrue();
});

test('only one subject admin per subject_grade', function () {
    $sg = makeSubjectGrade();
    $admin1 = makeTeacher();
    $admin2 = makeTeacher();
    $service = new \App\Services\SubjectAdminService;

    $service->promote($admin1, $sg);
    $service->promote($admin2, $sg);

    expect($sg->fresh()->subject_admin_user_id)->toBe($admin2->id);
    expect(SubjectGrade::where('subject_admin_user_id', $admin1->id)->count())->toBe(0);
});

test('view is universal for all roles', function () {
    $sg = makeSubjectGrade();
    [$family, $version] = makeFamilyWithVersion($sg);
    $policy = new \App\Policies\LessonPlanVersionPolicy;

    expect($policy->view(makeTeacher(), $version))->toBeTrue();
    expect($policy->view(makeEditor($sg), $version))->toBeTrue();
    expect($policy->view(makeSubjectAdmin($sg), $version))->toBeTrue();
    expect($policy->view(makeSiteAdmin(), $version))->toBeTrue();
});
