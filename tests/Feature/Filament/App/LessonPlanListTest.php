<?php

use App\Filament\App\Resources\LessonPlanFamilyResource\Pages\ListLessonPlanFamilies;
use App\Models\LessonPlanFamily;
use App\Models\LessonPlanVersion;
use App\Services\FavoriteService;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'site_administrator', 'guard_name' => 'web']);
    Filament::setCurrentPanel(Filament::getPanel('app'));
});

test('list page loads for authenticated user', function () {
    $this->actingAs(makeTeacher());

    Livewire::test(ListLessonPlanFamilies::class)
        ->assertOk();
});

test('lesson plan versions appear in the table', function () {
    $sg = makeSubjectGrade();
    [, $version] = makeFamilyWithVersion($sg);

    $this->actingAs(makeTeacher());

    Livewire::test(ListLessonPlanFamilies::class)
        ->assertCanSeeTableRecords([$version]);
});

test('official tab shows only official versions', function () {
    $sg = makeSubjectGrade();
    [$family1, $official] = makeFamilyWithVersion($sg);
    // Use a separate subject grade to avoid the (subject_grade_id, day, language) unique constraint.
    [, $unofficial] = makeFamilyWithVersion(makeSubjectGrade());

    $family1->official_version_id = $official->id;
    $family1->save();

    $this->actingAs(makeTeacher());

    Livewire::test(ListLessonPlanFamilies::class)
        ->set('activeTab', 'official')
        ->assertCanSeeTableRecords([$official])
        ->assertCanNotSeeTableRecords([$unofficial]);
});

test('latest tab shows only the most recent version per family', function () {
    $sg = makeSubjectGrade();
    [$family, $v1] = makeFamilyWithVersion($sg);

    $v2 = LessonPlanVersion::factory()->create([
        'lesson_plan_family_id' => $family->id,
        'version' => '1.0.1',
    ]);

    $this->actingAs(makeTeacher());

    Livewire::test(ListLessonPlanFamilies::class)
        ->set('activeTab', 'latest')
        ->assertCanSeeTableRecords([$v2])
        ->assertCanNotSeeTableRecords([$v1]);
});

test('favorites tab shows only versions the user has favorited', function () {
    $sg = makeSubjectGrade();
    [, $favored] = makeFamilyWithVersion($sg);
    [, $unfavored] = makeFamilyWithVersion($sg);

    $user = makeTeacher();
    (new FavoriteService)->upsert($user, $favored);

    $this->actingAs($user);

    Livewire::test(ListLessonPlanFamilies::class)
        ->set('activeTab', 'favorites')
        ->assertCanSeeTableRecords([$favored])
        ->assertCanNotSeeTableRecords([$unfavored]);
});

test('language filter narrows results to selected language', function () {
    $sg = makeSubjectGrade();
    [, $enVersion] = makeFamilyWithVersion($sg, 'en');

    $swFamily = LessonPlanFamily::factory()->create([
        'subject_grade_id' => $sg->id,
        'language' => 'sw',
    ]);
    $swVersion = LessonPlanVersion::factory()->create([
        'lesson_plan_family_id' => $swFamily->id,
        'version' => '1.0.0',
    ]);

    $this->actingAs(makeTeacher());

    Livewire::test(ListLessonPlanFamilies::class)
        ->filterTable('language', 'sw')
        ->assertCanSeeTableRecords([$swVersion])
        ->assertCanNotSeeTableRecords([$enVersion]);
});

test('teacher does not see create button', function () {
    $this->actingAs(makeTeacher());

    Livewire::test(ListLessonPlanFamilies::class)
        ->assertActionDoesNotExist('create');
});

test('subject admin sees create button', function () {
    $sg = makeSubjectGrade();
    $admin = makeSubjectAdmin($sg);

    $this->actingAs($admin);

    Livewire::test(ListLessonPlanFamilies::class)
        ->assertActionExists('create');
});

test('site admin sees create button', function () {
    $this->actingAs(makeSiteAdmin());

    Livewire::test(ListLessonPlanFamilies::class)
        ->assertActionExists('create');
});
