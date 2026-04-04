<?php

use App\Filament\App\Resources\LessonPlanFamilyResource\Pages\ViewLessonPlanFamily;
use App\Models\LessonPlanVersion;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'site_administrator', 'guard_name' => 'web']);
    Filament::setCurrentPanel(Filament::getPanel('app'));
});

test('entering compare mode sets compareVersion and compareMode', function () {
    $sg = makeSubjectGrade();
    [$family, $v1] = makeFamilyWithVersion($sg);
    $v2 = LessonPlanVersion::factory()->create([
        'lesson_plan_family_id' => $family->id,
        'version' => '1.0.1',
    ]);

    $this->actingAs(makeTeacher());

    $component = Livewire::test(ViewLessonPlanFamily::class, ['record' => $family])
        ->call('enterCompareMode', $v2->id);

    expect($component->get('compareMode'))->toBeTrue();
    expect($component->get('compareVersion')->id)->toBe($v2->id);
});

test('compare mode only allows versions from the same family', function () {
    $sg = makeSubjectGrade();
    [$family, $v1] = makeFamilyWithVersion($sg);

    $otherSg = makeSubjectGrade();
    [$otherFamily, $otherVersion] = makeFamilyWithVersion($otherSg);

    $this->actingAs(makeTeacher());

    $component = Livewire::test(ViewLessonPlanFamily::class, ['record' => $family])
        ->call('enterCompareMode', $otherVersion->id);

    // compareMode should remain false since the version is not in this family
    expect($component->get('compareMode'))->toBeFalse();
});

test('compare mode is read-only — save new version is still forbidden in compare mode', function () {
    $sg = makeSubjectGrade();
    [$family, $v1] = makeFamilyWithVersion($sg);
    $v2 = LessonPlanVersion::factory()->create([
        'lesson_plan_family_id' => $family->id,
        'version' => '1.0.1',
    ]);

    $teacher = makeTeacher();
    $this->actingAs($teacher);

    Livewire::test(ViewLessonPlanFamily::class, ['record' => $family])
        ->call('enterCompareMode', $v2->id)
        ->set('editContent', '# Changed')
        ->call('saveNewVersion')
        ->assertForbidden();
});

test('compare to previous version picks the correct previous version', function () {
    $sg = makeSubjectGrade();
    [$family, $v1] = makeFamilyWithVersion($sg);
    $v2 = LessonPlanVersion::factory()->create([
        'lesson_plan_family_id' => $family->id,
        'version' => '1.0.1',
    ]);
    $v3 = LessonPlanVersion::factory()->create([
        'lesson_plan_family_id' => $family->id,
        'version' => '1.1.0',
    ]);

    $this->actingAs(makeTeacher());

    // Select v3 and compare to previous — should be v2 (1.0.1 is closer than 1.0.0)
    $component = Livewire::test(ViewLessonPlanFamily::class, ['record' => $family])
        ->call('selectVersion', $v3->id)
        ->call('compareToPreviousVersion');

    expect($component->get('compareMode'))->toBeTrue();
    expect($component->get('compareVersion')->version)->toBe('1.0.1');
});

test('compare to previous version shows warning when no previous version exists', function () {
    $sg = makeSubjectGrade();
    [$family, $v1] = makeFamilyWithVersion($sg);

    $this->actingAs(makeTeacher());

    // v1 is the only version — no previous exists
    $component = Livewire::test(ViewLessonPlanFamily::class, ['record' => $family])
        ->call('compareToPreviousVersion')
        ->assertNotified();

    expect($component->get('compareMode'))->toBeFalse();
});

test('compare to official version picks the official version when set', function () {
    $sg = makeSubjectGrade();
    [$family, $v1] = makeFamilyWithVersion($sg);
    $v2 = LessonPlanVersion::factory()->create([
        'lesson_plan_family_id' => $family->id,
        'version' => '1.0.1',
    ]);
    $family->official_version_id = $v1->id;
    $family->save();

    $this->actingAs(makeTeacher());

    $component = Livewire::test(ViewLessonPlanFamily::class, ['record' => $family])
        ->call('selectVersion', $v2->id)
        ->call('compareToOfficialVersion');

    expect($component->get('compareMode'))->toBeTrue();
    expect($component->get('compareVersion')->id)->toBe($v1->id);
});

test('compare to official version shows warning when no official is set', function () {
    $sg = makeSubjectGrade();
    [$family] = makeFamilyWithVersion($sg);
    // No official version set

    $this->actingAs(makeTeacher());

    $component = Livewire::test(ViewLessonPlanFamily::class, ['record' => $family])
        ->call('compareToOfficialVersion')
        ->assertNotified();

    expect($component->get('compareMode'))->toBeFalse();
});

test('toggle diff layout switches between side-by-side and stacked', function () {
    $sg = makeSubjectGrade();
    [$family, $v1] = makeFamilyWithVersion($sg);
    $v2 = LessonPlanVersion::factory()->create([
        'lesson_plan_family_id' => $family->id,
        'version' => '1.0.1',
    ]);

    $this->actingAs(makeTeacher());

    $component = Livewire::test(ViewLessonPlanFamily::class, ['record' => $family])
        ->call('enterCompareMode', $v2->id);

    expect($component->get('diffLayout'))->toBe('side-by-side');

    $component->call('toggleDiffLayout');
    expect($component->get('diffLayout'))->toBe('stacked');

    $component->call('toggleDiffLayout');
    expect($component->get('diffLayout'))->toBe('side-by-side');
});
