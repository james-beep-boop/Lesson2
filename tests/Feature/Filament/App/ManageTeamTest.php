<?php

use App\Filament\App\Pages\ManageTeam;
use App\Livewire\SubjectGradeTeamManager;
use App\Models\LessonPlanFamily;
use App\Models\LessonPlanVersion;
use App\Models\User;
use Carbon\Carbon;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'site_administrator', 'guard_name' => 'web']);
    Filament::setCurrentPanel(Filament::getPanel('app'));
});

test('manage team page loads for subject admin', function () {
    $sg = makeSubjectGrade();
    $subjectAdmin = makeSubjectAdmin($sg);

    $this->actingAs($subjectAdmin);

    Livewire::test(ManageTeam::class)
        ->assertOk();
});

test('manage team page lists all administered subject grades and repeated editor sections', function () {
    $sg1 = makeSubjectGrade();
    $sg2 = makeSubjectGrade();
    $subjectAdmin = makeSubjectAdmin($sg1);
    $sg2->subject_admin_user_id = $subjectAdmin->id;
    $sg2->save();
    $editorOne = makeEditor($sg1);
    $editorTwo = makeEditor($sg2);

    $this->actingAs($subjectAdmin);

    Livewire::test(ManageTeam::class)
        ->assertSee('Manage Subject Editors')
        ->assertSee($sg1->subject->name)
        ->assertSee('Grade '.$sg1->grade)
        ->assertSee($sg2->subject->name)
        ->assertSee('Grade '.$sg2->grade)
        ->assertSee("Current Editors of {$sg1->subject->name}, Grade {$sg1->grade}")
        ->assertSee("Current Editors of {$sg2->subject->name}, Grade {$sg2->grade}")
        ->assertSee("Add Editor for {$sg1->subject->name}, Grade {$sg1->grade}")
        ->assertSee("Add Editor for {$sg2->subject->name}, Grade {$sg2->grade}")
        ->assertSee($editorOne->name)
        ->assertSee($editorTwo->name);
});

test('subject grade team manager table renders metrics and scoped editor data', function () {
    $sg = makeSubjectGrade();
    $otherSg = makeSubjectGrade();
    $subjectAdmin = makeSubjectAdmin($sg);
    $editor = makeEditor($sg);
    $familyA = LessonPlanFamily::factory()->create(['subject_grade_id' => $sg->id, 'day' => '1']);
    $familyB = LessonPlanFamily::factory()->create(['subject_grade_id' => $sg->id, 'day' => '2']);
    $otherFamily = LessonPlanFamily::factory()->create(['subject_grade_id' => $otherSg->id, 'day' => '1']);

    LessonPlanVersion::factory()->create([
        'lesson_plan_family_id' => $familyA->id,
        'contributor_id' => $editor->id,
        'created_at' => Carbon::create(2026, 4, 17, 9, 0, 0, config('app.timezone')),
        'updated_at' => Carbon::create(2026, 4, 17, 9, 0, 0, config('app.timezone')),
    ]);

    LessonPlanVersion::factory()->create([
        'lesson_plan_family_id' => $familyB->id,
        'contributor_id' => $editor->id,
        'created_at' => Carbon::create(2026, 4, 18, 15, 30, 0, config('app.timezone')),
        'updated_at' => Carbon::create(2026, 4, 18, 15, 30, 0, config('app.timezone')),
    ]);

    LessonPlanVersion::factory()->create([
        'lesson_plan_family_id' => $otherFamily->id,
        'contributor_id' => $editor->id,
        'created_at' => Carbon::create(2026, 4, 19, 10, 0, 0, config('app.timezone')),
        'updated_at' => Carbon::create(2026, 4, 19, 10, 0, 0, config('app.timezone')),
    ]);

    $this->actingAs($subjectAdmin);

    Livewire::test(SubjectGradeTeamManager::class, ['subjectGradeId' => $sg->id])
        ->assertSeeHtml('type="checkbox"')
        ->assertSee('Full name')
        ->assertSee('Username')
        ->assertSee('Email')
        ->assertSee('Edits')
        ->assertSee('Last Edit')
        ->assertSee($editor->name)
        ->assertSee($editor->username)
        ->assertSee($editor->email)
        ->assertSee('2')
        ->assertSee('Apr 18, 2026 3:30 PM')
        ->assertDontSee('Apr 19, 2026 10:00 AM');
});

test('teacher cannot access the manage team page', function () {
    $teacher = makeTeacher();

    $this->actingAs($teacher)
        ->get(ManageTeam::getUrl())
        ->assertForbidden();
});

test('add editor assigns editor role in the pivot', function () {
    $sg = makeSubjectGrade();
    $subjectAdmin = makeSubjectAdmin($sg);
    $newEditor = makeTeacher();

    $this->actingAs($subjectAdmin);

    Livewire::test(SubjectGradeTeamManager::class, ['subjectGradeId' => $sg->id])
        ->set('addUserId', $newEditor->id)
        ->call('addEditor')
        ->assertNotified();

    expect(
        DB::table('subject_grade_user')
            ->where('subject_grade_id', $sg->id)
            ->where('user_id', $newEditor->id)
            ->where('role', 'editor')
            ->exists()
    )->toBeTrue();
});

test('remove editor detaches the user from the pivot', function () {
    $sg = makeSubjectGrade();
    $subjectAdmin = makeSubjectAdmin($sg);
    $editor = makeEditor($sg);

    $this->actingAs($subjectAdmin);

    Livewire::test(SubjectGradeTeamManager::class, ['subjectGradeId' => $sg->id])
        ->call('removeEditor', $editor->id)
        ->assertNotified();

    expect(
        DB::table('subject_grade_user')
            ->where('subject_grade_id', $sg->id)
            ->where('user_id', $editor->id)
            ->exists()
    )->toBeFalse();
});

test('remove selected detaches editors from the pivot', function () {
    $sg = makeSubjectGrade();
    $subjectAdmin = makeSubjectAdmin($sg);
    $editor = makeEditor($sg);

    $this->actingAs($subjectAdmin);

    Livewire::test(SubjectGradeTeamManager::class, ['subjectGradeId' => $sg->id])
        ->set('selectedTableRecords', [$editor->id])
        ->call('removeSelectedEditors')
        ->assertNotified();

    expect(
        DB::table('subject_grade_user')
            ->where('subject_grade_id', $sg->id)
            ->where('user_id', $editor->id)
            ->exists()
    )->toBeFalse();
});

test('add editor validates that user id is required', function () {
    $sg = makeSubjectGrade();
    $subjectAdmin = makeSubjectAdmin($sg);

    $this->actingAs($subjectAdmin);

    Livewire::test(SubjectGradeTeamManager::class, ['subjectGradeId' => $sg->id])
        ->set('addUserId', null)
        ->call('addEditor')
        ->assertHasErrors(['addUserId' => 'required']);
});

test('subject admin adds editors only to the scoped subject grade', function () {
    $sg1 = makeSubjectGrade();
    $sg2 = makeSubjectGrade();
    $subjectAdmin = makeSubjectAdmin($sg1);
    $sg2->subject_admin_user_id = $subjectAdmin->id;
    $sg2->save();
    $userToAdd = makeTeacher();

    $this->actingAs($subjectAdmin);

    Livewire::test(SubjectGradeTeamManager::class, ['subjectGradeId' => $sg1->id])
        ->set('addUserId', $userToAdd->id)
        ->call('addEditor')
        ->assertNotified();

    expect(
        DB::table('subject_grade_user')
            ->where('subject_grade_id', $sg1->id)
            ->where('user_id', $userToAdd->id)
            ->exists()
    )->toBeTrue();

    expect(
        DB::table('subject_grade_user')
            ->where('subject_grade_id', $sg2->id)
            ->where('user_id', $userToAdd->id)
            ->exists()
    )->toBeFalse();
});

test('manage team does not list unverified users as available editors', function () {
    $sg = makeSubjectGrade();
    $subjectAdmin = makeSubjectAdmin($sg);
    $verifiedUser = makeTeacher();
    $unverifiedUser = User::factory()->unverified()->create();

    $this->actingAs($subjectAdmin);

    Livewire::test(SubjectGradeTeamManager::class, ['subjectGradeId' => $sg->id])
        ->assertSee($verifiedUser->name)
        ->assertDontSee($unverifiedUser->name);
});

test('manage team rejects adding an unverified editor', function () {
    $sg = makeSubjectGrade();
    $subjectAdmin = makeSubjectAdmin($sg);
    $unverifiedUser = User::factory()->unverified()->create();

    $this->actingAs($subjectAdmin);

    Livewire::test(SubjectGradeTeamManager::class, ['subjectGradeId' => $sg->id])
        ->set('addUserId', $unverifiedUser->id)
        ->call('addEditor')
        ->assertHasErrors(['addUserId']);
});
