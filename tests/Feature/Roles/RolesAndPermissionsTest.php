<?php

use App\Models\LessonPlanFamily;
use App\Models\LessonPlanVersion;
use App\Models\Message;
use App\Policies\LessonPlanVersionPolicy;
use App\Policies\SubjectGradePolicy;
use App\Policies\UserPolicy;
use App\Services\DeletionRequestService;
use App\Services\SubjectAdminService;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'site_administrator', 'guard_name' => 'web']);
});

test('teachers cannot edit lesson plans', function () {
    $sg = makeSubjectGrade();
    [$family] = makeFamilyWithVersion($sg);
    $teacher = makeTeacher();
    $policy = new LessonPlanVersionPolicy;

    expect($policy->create($teacher, $family))->toBeFalse();
});

test('editors can edit only assigned subject_grades', function () {
    $sg1 = makeSubjectGrade();
    $sg2 = makeSubjectGrade();
    [$family1] = makeFamilyWithVersion($sg1);
    [$family2] = makeFamilyWithVersion($sg2);
    $editor = makeEditor($sg1);
    $policy = new LessonPlanVersionPolicy;

    expect($policy->create($editor, $family1))->toBeTrue();
    expect($policy->create($editor, $family2))->toBeFalse();
});

test('editor can view lesson plans from any subject_grade', function () {
    $sg1 = makeSubjectGrade();
    $sg2 = makeSubjectGrade();
    [$family2, $version2] = makeFamilyWithVersion($sg2);
    $editor = makeEditor($sg1); // assigned only to sg1
    $policy = new LessonPlanVersionPolicy;

    // View is universal
    expect($policy->view($editor, $version2))->toBeTrue();
});

test('subject admin can manage only own subject_grades', function () {
    $sg1 = makeSubjectGrade();
    $sg2 = makeSubjectGrade();
    [$family1, $version1] = makeFamilyWithVersion($sg1);
    [$family2, $version2] = makeFamilyWithVersion($sg2);
    $subjectAdmin = makeSubjectAdmin($sg1);
    $policy = new LessonPlanVersionPolicy;

    expect($policy->create($subjectAdmin, $family1))->toBeTrue();
    expect($policy->markOfficial($subjectAdmin, $version1))->toBeTrue();
    expect($policy->create($subjectAdmin, $family2))->toBeFalse();
    expect($policy->markOfficial($subjectAdmin, $version2))->toBeFalse();
});

test('site admin can manage all subject_grades and users', function () {
    $sg = makeSubjectGrade();
    [$family, $version] = makeFamilyWithVersion($sg);
    $siteAdmin = makeSiteAdmin();
    $versionPolicy = new LessonPlanVersionPolicy;
    $userPolicy = new UserPolicy;
    $sgPolicy = new SubjectGradePolicy;

    expect($versionPolicy->create($siteAdmin, $family))->toBeTrue();
    expect($versionPolicy->markOfficial($siteAdmin, $version))->toBeTrue();
    expect($userPolicy->viewAny($siteAdmin))->toBeTrue();
    expect($sgPolicy->create($siteAdmin))->toBeTrue();
});

test('replacing a subject admin only affects the target subject_grade', function () {
    $sg = makeSubjectGrade();
    $otherSg = makeSubjectGrade();
    $admin1 = makeTeacher();
    $admin2 = makeTeacher();
    $service = new SubjectAdminService;

    $service->promote($admin1, $sg);
    $service->promote($admin1, $otherSg);
    $service->promote($admin2, $sg);

    expect($sg->fresh()->subject_admin_user_id)->toBe($admin2->id);
    expect($otherSg->fresh()->subject_admin_user_id)->toBe($admin1->id);
    expect(
        DB::table('subject_grade_user')
            ->where('subject_grade_id', $sg->id)
            ->where('user_id', $admin1->id)
            ->where('role', 'editor')
            ->exists()
    )->toBeTrue();
});

test('view is universal for all roles', function () {
    $sg = makeSubjectGrade();
    [$family, $version] = makeFamilyWithVersion($sg);
    $policy = new LessonPlanVersionPolicy;

    expect($policy->view(makeTeacher(), $version))->toBeTrue();
    expect($policy->view(makeEditor($sg), $version))->toBeTrue();
    expect($policy->view(makeSubjectAdmin($sg), $version))->toBeTrue();
    expect($policy->view(makeSiteAdmin(), $version))->toBeTrue();
});

// ---------------------------------------------------------------------------
// Translation policy — plain teachers (area 1)
// ---------------------------------------------------------------------------

test('translate policy allows plain teacher when AI flag on', function () {
    config(['features.ai_suggestions' => true]);
    $sg = makeSubjectGrade();
    [$family, $version] = makeFamilyWithVersion($sg);
    $policy = new LessonPlanVersionPolicy;

    expect($policy->translate(makeTeacher(), $version))->toBeTrue();
});

test('translate policy denies when AI flag off', function () {
    config(['features.ai_suggestions' => false]);
    $sg = makeSubjectGrade();
    [$family, $version] = makeFamilyWithVersion($sg);
    $policy = new LessonPlanVersionPolicy;

    expect($policy->translate(makeTeacher(), $version))->toBeFalse();
    expect($policy->translate(makeEditor($sg), $version))->toBeFalse();
});

test('ask ai policy denies plain teacher even when AI flag on', function () {
    config(['features.ai_suggestions' => true]);
    $sg = makeSubjectGrade();
    [$family, $version] = makeFamilyWithVersion($sg);
    $policy = new LessonPlanVersionPolicy;

    expect($policy->askAi(makeTeacher(), $version))->toBeFalse();
});

// ---------------------------------------------------------------------------
// directDelete policy (area 3)
// ---------------------------------------------------------------------------

test('editor can directDelete own non-official version', function () {
    $sg = makeSubjectGrade();
    $editor = makeEditor($sg);
    $family = LessonPlanFamily::factory()->create(['subject_grade_id' => $sg->id]);
    $version = LessonPlanVersion::factory()->create([
        'lesson_plan_family_id' => $family->id,
        'version' => '1.0.0',
        'contributor_id' => $editor->id,
    ]);
    $version->setRelation('family', $family->load('subjectGrade'));

    $policy = new LessonPlanVersionPolicy;

    expect($policy->directDelete($editor, $version))->toBeTrue();
});

test('editor cannot directDelete another contributors version', function () {
    $sg = makeSubjectGrade();
    $editor = makeEditor($sg);
    $other = makeEditor($sg);
    $family = LessonPlanFamily::factory()->create(['subject_grade_id' => $sg->id]);
    $version = LessonPlanVersion::factory()->create([
        'lesson_plan_family_id' => $family->id,
        'version' => '1.0.0',
        'contributor_id' => $other->id,
    ]);
    $version->setRelation('family', $family->load('subjectGrade'));

    $policy = new LessonPlanVersionPolicy;

    expect($policy->directDelete($editor, $version))->toBeFalse();
});

test('editor cannot directDelete official version', function () {
    $sg = makeSubjectGrade();
    $editor = makeEditor($sg);
    $family = LessonPlanFamily::factory()->create(['subject_grade_id' => $sg->id]);
    $version = LessonPlanVersion::factory()->create([
        'lesson_plan_family_id' => $family->id,
        'version' => '1.0.0',
        'contributor_id' => $editor->id,
    ]);
    $family->official_version_id = $version->id;
    $family->save();
    $version->setRelation('family', $family->fresh()->load('subjectGrade'));

    $policy = new LessonPlanVersionPolicy;

    expect($policy->directDelete($editor, $version))->toBeFalse();
});

test('subject admin can directDelete any non-official version in own subject grade', function () {
    $sg = makeSubjectGrade();
    $subjectAdmin = makeSubjectAdmin($sg);
    $family = LessonPlanFamily::factory()->create(['subject_grade_id' => $sg->id]);
    $version = LessonPlanVersion::factory()->create([
        'lesson_plan_family_id' => $family->id,
        'version' => '1.0.0',
    ]);
    $version->setRelation('family', $family->load('subjectGrade'));

    $policy = new LessonPlanVersionPolicy;

    expect($policy->directDelete($subjectAdmin, $version))->toBeTrue();
});

test('subject admin cannot directDelete official version', function () {
    $sg = makeSubjectGrade();
    $subjectAdmin = makeSubjectAdmin($sg);
    $family = LessonPlanFamily::factory()->create(['subject_grade_id' => $sg->id]);
    $version = LessonPlanVersion::factory()->create([
        'lesson_plan_family_id' => $family->id,
        'version' => '1.0.0',
    ]);
    $family->official_version_id = $version->id;
    $family->save();
    $version->setRelation('family', $family->fresh()->load('subjectGrade'));

    $policy = new LessonPlanVersionPolicy;

    expect($policy->directDelete($subjectAdmin, $version))->toBeFalse();
});

test('teacher cannot directDelete any version', function () {
    $sg = makeSubjectGrade();
    [$family, $version] = makeFamilyWithVersion($sg);
    $version->setRelation('family', $family->load('subjectGrade'));

    $policy = new LessonPlanVersionPolicy;

    expect($policy->directDelete(makeTeacher(), $version))->toBeFalse();
});

// ---------------------------------------------------------------------------
// requestDeletion policy — editors can request (area 3)
// ---------------------------------------------------------------------------

test('editor can request deletion for any version in their subject grade', function () {
    $sg = makeSubjectGrade();
    $editor = makeEditor($sg);
    $family = LessonPlanFamily::factory()->create(['subject_grade_id' => $sg->id]);
    $version = LessonPlanVersion::factory()->create([
        'lesson_plan_family_id' => $family->id,
        'version' => '1.0.0',
    ]);

    $policy = new LessonPlanVersionPolicy;

    expect($policy->requestDeletion($editor, $version))->toBeTrue();
});

test('teacher outside subject grade cannot request deletion', function () {
    $sg = makeSubjectGrade();
    [$family, $version] = makeFamilyWithVersion($sg);

    $policy = new LessonPlanVersionPolicy;

    expect($policy->requestDeletion(makeTeacher(), $version))->toBeFalse();
});

// ---------------------------------------------------------------------------
// Deletion request notification routing (area 5)
// ---------------------------------------------------------------------------

test('deletion request notifies contributor unless they are the requester', function () {
    $sg = makeSubjectGrade();
    $subjectAdmin = makeSubjectAdmin($sg);
    $contributor = makeTeacher();
    $family = LessonPlanFamily::factory()->create(['subject_grade_id' => $sg->id]);
    $version = LessonPlanVersion::factory()->create([
        'lesson_plan_family_id' => $family->id,
        'version' => '1.0.0',
        'contributor_id' => $contributor->id,
    ]);

    app(DeletionRequestService::class)->request($version, $subjectAdmin, 'test reason');

    expect(Message::where('to_user_id', $contributor->id)->exists())->toBeTrue();
});

test('deletion request does not notify contributor when they are the requester', function () {
    $sg = makeSubjectGrade();
    $contributor = makeEditor($sg);
    $family = LessonPlanFamily::factory()->create(['subject_grade_id' => $sg->id]);
    $version = LessonPlanVersion::factory()->create([
        'lesson_plan_family_id' => $family->id,
        'version' => '1.0.0',
        'contributor_id' => $contributor->id,
    ]);

    app(DeletionRequestService::class)->request($version, $contributor);

    expect(Message::where('to_user_id', $contributor->id)->exists())->toBeFalse();
});

test('deletion request notifies subject admin when they exist and are not requester', function () {
    $sg = makeSubjectGrade();
    $subjectAdmin = makeSubjectAdmin($sg);
    $editor = makeEditor($sg);
    $family = LessonPlanFamily::factory()->create(['subject_grade_id' => $sg->id]);
    $version = LessonPlanVersion::factory()->create([
        'lesson_plan_family_id' => $family->id,
        'version' => '1.0.0',
        'contributor_id' => $editor->id,
    ]);

    app(DeletionRequestService::class)->request($version, $editor);

    expect(Message::where('to_user_id', $subjectAdmin->id)->exists())->toBeTrue();
});

test('deletion request notifies site admins even when subject admin exists', function () {
    $sg = makeSubjectGrade();
    makeSubjectAdmin($sg); // subject admin exists
    $siteAdmin = makeSiteAdmin();
    $requester = makeEditor($sg);
    $family = LessonPlanFamily::factory()->create(['subject_grade_id' => $sg->id]);
    $version = LessonPlanVersion::factory()->create([
        'lesson_plan_family_id' => $family->id,
        'version' => '1.0.0',
        'contributor_id' => $requester->id,
    ]);

    app(DeletionRequestService::class)->request($version, $requester);

    expect(Message::where('to_user_id', $siteAdmin->id)->exists())->toBeTrue();
});

test('deletion request does not notify site admin when they are the requester', function () {
    $sg = makeSubjectGrade();
    $siteAdmin = makeSiteAdmin();
    [$family, $version] = makeFamilyWithVersion($sg);

    app(DeletionRequestService::class)->request($version, $siteAdmin);

    expect(Message::where('to_user_id', $siteAdmin->id)->exists())->toBeFalse();
});
