<?php

use App\Filament\App\Resources\LessonPlanFamilyResource\Pages\ViewLessonPlanFamily;
use App\Models\DeletionRequest;
use App\Models\Favorite;
use App\Models\LessonPlanVersion;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'site_administrator', 'guard_name' => 'web']);
    Filament::setCurrentPanel(Filament::getPanel('app'));
});

test('view page loads for authenticated user', function () {
    $sg = makeSubjectGrade();
    [$family] = makeFamilyWithVersion($sg);

    $this->actingAs(makeTeacher());

    Livewire::test(ViewLessonPlanFamily::class, ['record' => $family])
        ->assertOk();
});

test('view page selects the official version by default', function () {
    $sg = makeSubjectGrade();
    [$family, $v1] = makeFamilyWithVersion($sg);
    $v2 = LessonPlanVersion::factory()->create([
        'lesson_plan_family_id' => $family->id,
        'version' => '1.0.1',
    ]);
    $family->official_version_id = $v1->id;
    $family->save();

    $this->actingAs(makeTeacher());

    $component = Livewire::test(ViewLessonPlanFamily::class, ['record' => $family]);

    expect($component->get('selectedVersion')->id)->toBe($v1->id);
});

test('mark official sets the official version', function () {
    $sg = makeSubjectGrade();
    [$family, $version] = makeFamilyWithVersion($sg);
    $subjectAdmin = makeSubjectAdmin($sg);

    $this->actingAs($subjectAdmin);

    Livewire::test(ViewLessonPlanFamily::class, ['record' => $family])
        ->call('markOfficial')
        ->assertNotified();

    expect($family->fresh()->official_version_id)->toBe($version->id);
});

test('save new version creates a new version and sends notification', function () {
    $sg = makeSubjectGrade();
    [$family, $v1] = makeFamilyWithVersion($sg);
    $subjectAdmin = makeSubjectAdmin($sg);

    $this->actingAs($subjectAdmin);

    Livewire::test(ViewLessonPlanFamily::class, ['record' => $family])
        ->set('editMode', true)
        ->set('editContent', '# Updated content')
        ->set('versionBump', 'patch')
        ->call('saveNewVersion')
        ->assertNotified();

    expect(LessonPlanVersion::where('lesson_plan_family_id', $family->id)->count())->toBe(2);
    expect(LessonPlanVersion::where('lesson_plan_family_id', $family->id)->where('version', '1.0.1')->exists())->toBeTrue();
});

test('teacher cannot save a new version', function () {
    $sg = makeSubjectGrade();
    [$family] = makeFamilyWithVersion($sg);
    $teacher = makeTeacher();

    $this->actingAs($teacher);

    Livewire::test(ViewLessonPlanFamily::class, ['record' => $family])
        ->set('editContent', '# Unauthorized change')
        ->call('saveNewVersion')
        ->assertForbidden();
});

test('favoriting a version records the user favorite', function () {
    $sg = makeSubjectGrade();
    [$family] = makeFamilyWithVersion($sg);
    $user = makeTeacher();

    $this->actingAs($user);

    Livewire::test(ViewLessonPlanFamily::class, ['record' => $family])
        ->call('favorite')
        ->assertNotified();

    expect(Favorite::where('user_id', $user->id)
        ->where('lesson_plan_family_id', $family->id)
        ->exists()
    )->toBeTrue();
});

test('request deletion creates a pending deletion request', function () {
    $sg = makeSubjectGrade();
    [$family, $version] = makeFamilyWithVersion($sg);
    $subjectAdmin = makeSubjectAdmin($sg);

    $this->actingAs($subjectAdmin);

    Livewire::test(ViewLessonPlanFamily::class, ['record' => $family])
        ->set('deletionReason', 'Outdated content')
        ->call('requestDeletion')
        ->assertNotified();

    expect(DeletionRequest::where('lesson_plan_version_id', $version->id)
        ->whereNull('resolved_at')
        ->exists()
    )->toBeTrue();
});

test('duplicate deletion request is rejected with a warning', function () {
    $sg = makeSubjectGrade();
    [$family, $version] = makeFamilyWithVersion($sg);
    $subjectAdmin = makeSubjectAdmin($sg);

    // Create the first deletion request manually
    $dr = new DeletionRequest([
        'lesson_plan_version_id' => $version->id,
        'reason' => 'First request',
    ]);
    $dr->requested_by_user_id = $subjectAdmin->id;
    $dr->save();

    $this->actingAs($subjectAdmin);

    Livewire::test(ViewLessonPlanFamily::class, ['record' => $family])
        ->set('hasPendingDeletion', true)
        ->set('deletionReason', 'Duplicate')
        ->call('requestDeletion')
        ->assertNotified();

    // Should still be only one deletion request
    expect(DeletionRequest::where('lesson_plan_version_id', $version->id)->count())->toBe(1);
});

test('select version switches the displayed version', function () {
    $sg = makeSubjectGrade();
    [$family, $v1] = makeFamilyWithVersion($sg);
    $v2 = LessonPlanVersion::factory()->create([
        'lesson_plan_family_id' => $family->id,
        'version' => '1.0.1',
    ]);

    $this->actingAs(makeTeacher());

    $component = Livewire::test(ViewLessonPlanFamily::class, ['record' => $family])
        ->call('selectVersion', $v2->id);

    expect($component->get('selectedVersion')->id)->toBe($v2->id);
});
